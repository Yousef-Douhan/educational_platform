@extends('student.layout')

@section('title', $order->order_number)

@section('content')
    <a href="{{ route('student.orders.index') }}" class="text-sm text-sky-700 hover:underline">← {{ __('student.nav.orders') }}</a>
    <h1 class="mb-1 mt-2 text-2xl font-bold text-slate-900">{{ $order->order_number }}</h1>
    <p class="mb-6 text-sm text-slate-500">{{ __('student.order_status.' . $order->status) }} · {{ $order->created_at->format('Y-m-d H:i') }}</p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <ul class="divide-y divide-slate-100">
            @foreach ($order->items as $item)
                <li class="flex justify-between gap-4 px-4 py-3 text-sm">
                    <span>{{ \App\Support\Loc::pick($item->title_ar, $item->title_en) }}</span>
                    <span class="whitespace-nowrap font-medium">{{ number_format((float) $item->price, 2) }} {{ $order->currency }}</span>
                </li>
            @endforeach
        </ul>
        <dl class="space-y-1 border-t border-slate-200 bg-slate-50 px-4 py-3 text-sm">
            <div class="flex justify-between"><dt>{{ __('student.cart.subtotal') }}</dt><dd>{{ number_format((float) $order->subtotal, 2) }} {{ $order->currency }}</dd></div>
            @if ((float) $order->discount_amount > 0)
                <div class="flex justify-between text-emerald-700"><dt>{{ __('student.orders.discount') }}</dt><dd>- {{ number_format((float) $order->discount_amount, 2) }} {{ $order->currency }}</dd></div>
            @endif
            <div class="flex justify-between text-base font-bold"><dt>{{ __('student.orders.total') }}</dt><dd>{{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</dd></div>
        </dl>
    </div>

    @if ($order->status === 'pending')
        @if (request()->boolean('paid'))
            <p class="mt-4 rounded-xl bg-sky-50 px-4 py-3 text-sm text-sky-800">{{ __('student.pay.processing') }}</p>
        @elseif (request()->boolean('canceled'))
            <p class="mt-4 rounded-xl bg-slate-100 px-4 py-3 text-sm text-slate-700">{{ __('student.pay.canceled') }}</p>
        @else
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __('student.orders.pending_note') }}</p>
        @endif

        @if ($canPay)
            <form method="POST" action="{{ route('student.orders.pay', $order->order_number) }}" class="mt-4">
                @csrf
                <button class="rounded-xl bg-indigo-700 px-8 py-3 text-sm font-bold text-white shadow hover:bg-indigo-800"><i class="fa-solid fa-lock me-2"></i>{{ __('student.pay.pay_now') }}</button>
                <p class="mt-2 text-xs text-slate-500">{{ __('student.pay.secure_note') }}</p>
            </form>
        @endif

        {{-- الدفع بالتحويل مع رفع الإشعار --}}
        @if (count($methods) > 0)
            @php
                $open     = $receipts->firstWhere('status', 'pending');
                $rejected = $receipts->firstWhere('status', 'rejected');
                $label    = fn ($m) => \App\Support\Loc::pick($m['label_ar'] ?? '', $m['label_en'] ?? '');
            @endphp
            <section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-1 text-lg font-extrabold text-slate-900"><i class="fa-solid fa-money-bill-transfer me-2 text-indigo-600"></i>{{ __('receipts.student.title') }}</h2>
                <p class="mb-4 text-sm text-slate-500">{{ __('receipts.student.hint', ['amount' => number_format((float) $order->total_amount, 2) . ' ' . $order->currency]) }}</p>

                @if ($open)
                    <div class="rounded-xl bg-sky-50 p-4 text-sm text-sky-900">
                        <p class="font-bold"><i class="fa-solid fa-hourglass-half me-2"></i>{{ __('receipts.student.under_review') }}</p>
                        <p class="mt-1">{{ $label($methods[$open->method] ?? ['label_ar' => $open->method]) }} · {{ $open->reference_number }}</p>
                        <a class="mt-2 inline-block font-semibold underline" target="_blank" rel="noopener" href="{{ route('student.receipts.image', $open->id) }}">{{ __('receipts.student.view_image') }}</a>
                    </div>
                @else
                    @if ($rejected)
                        <div class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
                            <p class="font-bold">{{ __('receipts.student.rejected') }}</p>
                            @if ($rejected->review_note) <p class="mt-1">{{ $rejected->review_note }}</p> @endif
                        </div>
                    @endif

                    <div class="mb-5 grid gap-3 sm:grid-cols-3">
                        @foreach ($methods as $key => $m)
                            <div class="rounded-xl border border-slate-200 p-4 text-sm">
                                <p class="mb-2 font-bold text-slate-900"><i class="fa-solid {{ $m['icon'] ?? 'fa-wallet' }} me-2 text-indigo-600"></i>{{ $label($m) }}</p>
                                @if (!empty($m['holder'])) <p class="text-slate-500">{{ __('receipts.student.holder') }}: <span class="font-semibold text-slate-800">{{ $m['holder'] }}</span></p> @endif
                                @if (!empty($m['account'])) <p class="text-slate-500">{{ __('receipts.student.account') }}: <span class="font-mono font-semibold text-slate-800" dir="ltr">{{ $m['account'] }}</span></p> @endif
                                @if (!empty($m['iban'])) <p class="text-slate-500">IBAN: <span class="font-mono font-semibold text-slate-800" dir="ltr">{{ $m['iban'] }}</span></p> @endif
                            </div>
                        @endforeach
                    </div>

                    <form method="POST" action="{{ route('student.orders.receipt', $order->order_number) }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="method">{{ __('receipts.fields.method') }}</label>
                            <select id="method" name="method" required class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($methods as $key => $m)
                                    <option value="{{ $key }}" @selected(old('method') === $key)>{{ $label($m) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="sender_name">{{ __('receipts.fields.sender_name') }}</label>
                            <input id="sender_name" name="sender_name" value="{{ old('sender_name') }}" required minlength="3" maxlength="120" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="reference_number">{{ __('receipts.fields.reference') }}</label>
                            <input id="reference_number" name="reference_number" value="{{ old('reference_number') }}" required minlength="4" maxlength="60" dir="ltr" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium" for="receipt">{{ __('receipts.fields.image') }}</label>
                            <input id="receipt" type="file" name="receipt" required accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border border-slate-300 px-3 py-1.5 text-sm file:me-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-1.5 file:text-indigo-700">
                            <p class="mt-1 text-xs text-slate-400">{{ __('receipts.student.file_hint') }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <button class="rounded-xl bg-indigo-700 px-8 py-3 text-sm font-bold text-white shadow hover:bg-indigo-800"><i class="fa-solid fa-upload me-2"></i>{{ __('receipts.student.submit') }}</button>
                        </div>
                    </form>
                @endif
            </section>
        @endif
    @elseif ($order->status === 'paid')
        <a href="{{ route('student.courses.mine') }}" class="mt-4 inline-block rounded-xl bg-emerald-600 px-6 py-3 text-sm font-bold text-white hover:bg-emerald-700">{{ __('student.pay.go_learn') }}</a>
    @endif
@endsection
