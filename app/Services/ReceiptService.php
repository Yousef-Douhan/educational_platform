<?php

namespace App\Services;

use App\Exceptions\OrderException;
use App\Exceptions\ReceiptException;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * الدفع بالتحويل: الطالب يرفع صورة الإشعار، والإدارة تعتمد فيُؤكَّد الدفع عبر OrderService::markPaid.
 * المبلغ المطلوب هو مبلغ الطلب المخزّن دائمًا؛ ما يكتبه الطالب (الاسم/رقم العملية) معلومات للمراجِع فقط.
 */
class ReceiptService
{
    private const EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private readonly OrderService $orders) {}

    /** الطرق المفعّلة فقط: ما له رقم حساب في الإعدادات */
    public function methods(): array
    {
        return collect((array) config('payments.manual.methods'))
            ->filter(fn ($m) => filled($m['account'] ?? null) || filled($m['iban'] ?? null))
            ->all();
    }

    public function submit(User $user, Order $order, string $method, string $senderName, string $reference, UploadedFile $file): PaymentReceipt
    {
        if (! array_key_exists($method, $this->methods())) {
            throw ReceiptException::because('method_unavailable');
        }

        $mime = $file->getMimeType();

        if (! isset(self::EXTENSIONS[$mime])) {
            throw ReceiptException::because('bad_file');
        }

        $reference = trim($reference);
        $path      = null;

        try {
            return DB::transaction(function () use ($user, $order, $method, $senderName, $reference, $file, $mime, &$path) {
                $locked = Order::whereKey($order->getKey())->lockForUpdate()->firstOrFail();

                if ($locked->user_id !== $user->id) {
                    throw ReceiptException::because('not_yours');
                }

                if ($locked->status !== 'pending' || (float) $locked->total_amount <= 0) {
                    throw OrderException::because('order_not_payable');
                }

                if (PaymentReceipt::where(['order_id' => $locked->id, 'is_open' => true])->exists()) {
                    throw ReceiptException::because('already_pending');
                }

                // قرص خاص واسم عشوائي بامتداد من النوع المكتشف، لا من اسم الملف الأصلي
                $path = $file->storeAs("payment-receipts/{$locked->id}", Str::random(40) . '.' . self::EXTENSIONS[$mime], 'local');

                $receipt = new PaymentReceipt;
                $receipt->forceFill([
                    'order_id'         => $locked->id,
                    'user_id'          => $user->id,
                    'method'           => $method,
                    'sender_name'      => trim($senderName),
                    'reference_number' => $reference,
                    'receipt_path'     => $path,
                    'status'           => 'pending',
                    'is_open'          => true,
                    'ref_key'          => $this->refKey($method, $reference),
                ])->save();

                return $receipt;
            });
        } catch (UniqueConstraintViolationException) {
            $this->discard($path);

            // إما تسابق على إشعار مفتوح للطلب، أو رقم عملية مستخدم
            throw PaymentReceipt::where('ref_key', $this->refKey($method, $reference))->exists()
                ? ReceiptException::because('reference_used')
                : ReceiptException::because('already_pending');
        } catch (\Throwable $e) {
            $this->discard($path);

            throw $e;
        }
    }

    /** اعتماد: يؤكد الدفع ويفعّل الدورات. يفشل إن لم يعد الطلب معلّقًا أو تكرر رقم العملية */
    public function approve(PaymentReceipt $receipt, User $by, ?string $note = null): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt, $by, $note) {
            $locked = PaymentReceipt::whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ReceiptException::because('not_pending');
            }

            $order = Order::whereKey($locked->order_id)->firstOrFail();

            if ($order->status !== 'pending') {
                throw ReceiptException::because('order_not_pending');
            }

            // markPaid يفعّل التسجيلات وينظّف السلة؛ gateway = طريقة التحويل، ومرجعه رقم العملية
            $this->orders->markPaid($order, $locked->method, $locked->reference_number, $note ?: null);

            $locked->forceFill([
                'status' => 'approved', 'is_open' => null, 'reviewed_by' => $by->id,
                'reviewed_at' => now(), 'review_note' => $note ? Str::limit($note, 250, '') : null,
            ])->save();

            return $locked;
        });
    }

    /** رفض بسبب إلزامي. يُحرَّر رقم العملية ليتمكن الطالب من إعادة الرفع بعد التصحيح */
    public function reject(PaymentReceipt $receipt, User $by, string $reason): PaymentReceipt
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ReceiptException::because('reason_required');
        }

        $rejected = DB::transaction(function () use ($receipt, $by, $reason) {
            $locked = PaymentReceipt::whereKey($receipt->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ReceiptException::because('not_pending');
            }

            $locked->forceFill([
                'status' => 'rejected', 'is_open' => null, 'ref_key' => null, 'reviewed_by' => $by->id,
                'reviewed_at' => now(), 'review_note' => Str::limit($reason, 250, ''),
            ])->save();

            return $locked;
        });

        // خارج المعاملة: فشل الإشعار لا يُفشل الرفض (Notifier يلتقط الأخطاء)
        app(Notifier::class)->toUser($rejected->user_id, PlatformNotification::receiptRejected($rejected->order, $reason));

        return $rejected;
    }

    private function refKey(string $method, string $reference): string
    {
        return Str::limit($method . ':' . mb_strtolower(preg_replace('/\s+/', '', $reference)), 100, '');
    }

    private function discard(?string $path): void
    {
        if ($path) {
            Storage::disk('local')->delete($path);
        }
    }
}
