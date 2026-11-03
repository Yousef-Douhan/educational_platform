@extends('cms.parent')

@section('title', __('receipts.title'))

@section('main-content')
@php $order = $receipt->order; @endphp
<div class="space-y-6 p-1">
    <div>
        <a href="{{ route('cms.admin.receipts.index') }}" class="text-sm font-semibold text-indigo-700 hover:underline">← {{ __('receipts.title') }}</a>
        <h1 class="mt-1 text-2xl font-extrabold text-slate-900">{{ $order->order_number }}</h1>
    </div>

    @if (session('status')) <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div> @endif
    @if ($errors->any())
        <div class="rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">@foreach ($errors->all() as $m) <p>{{ $m }}</p> @endforeach</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
            <p class="mb-3 text-sm font-bold text-slate-700">{{ __('receipts.fields.image') }}</p>
            <a href="{{ route('cms.admin.receipts.image', $receipt->id) }}" target="_blank" rel="noopener">
                <img src="{{ route('cms.admin.receipts.image', $receipt->id) }}" alt="" class="max-h-[34rem] w-full rounded-xl border border-slate-200 object-contain">
            </a>
        </div>

        <div class="space-y-4">
            <dl class="grid grid-cols-2 gap-3 rounded-2xl bg-white p-5 text-sm shadow-sm ring-1 ring-slate-200">
                <div><dt class="text-slate-500">{{ __('receipts.fields.student') }}</dt><dd class="font-semibold">{{ $receipt->user->name }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.status') }}</dt><dd class="font-semibold">{{ __('receipts.statuses.' . $receipt->status) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.method') }}</dt><dd class="font-semibold">{{ __('receipts.methods.' . $receipt->method) }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.amount') }}</dt><dd class="text-lg font-extrabold text-indigo-700">{{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.sender_name') }}</dt><dd class="font-semibold">{{ $receipt->sender_name }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.reference') }}</dt><dd class="font-mono font-semibold" dir="ltr">{{ $receipt->reference_number }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.date') }}</dt><dd>{{ $receipt->created_at->format('Y-m-d H:i') }}</dd></div>
                <div><dt class="text-slate-500">{{ __('receipts.fields.order_status') }}</dt><dd>{{ $order->status }}</dd></div>
                @if ($method)
                    <div class="col-span-2 rounded-xl bg-slate-50 p-3"><dt class="text-slate-500">{{ __('receipts.our_account') }}</dt>
                        <dd class="font-mono" dir="ltr">{{ $method['account'] ?? '' }} {{ !empty($method['iban']) ? ' · ' . $method['iban'] : '' }}</dd></dd></div>
                @endif
            </dl>

            <ul class="rounded-2xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200">
                @foreach ($order->items as $item)
                    <li class="flex justify-between py-1"><span>{{ \App\Support\Loc::pick($item->title_ar, $item->title_en) }}</span><span>{{ number_format((float) $item->price, 2) }}</span></li>
                @endforeach
            </ul>

            @if ($receipt->status === 'pending')
                @can('receipts.review')
                    <div class="grid gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                        <p class="text-xs text-amber-700"><i class="fa-solid fa-triangle-exclamation me-1"></i>{{ __('receipts.check_hint') }}</p>
                        <form method="POST" action="{{ route('cms.admin.receipts.approve', $receipt->id) }}" class="space-y-2">
                            @csrf
                            <input name="note" maxlength="250" placeholder="{{ __('receipts.note_optional') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <button class="w-full rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white hover:bg-emerald-700"><i class="fa-solid fa-check me-2"></i>{{ __('receipts.approve') }}</button>
                        </form>
                        <form method="POST" action="{{ route('cms.admin.receipts.reject', $receipt->id) }}" class="space-y-2 border-t border-slate-100 pt-4">
                            @csrf
                            <input name="reason" required minlength="3" maxlength="250" placeholder="{{ __('receipts.reason_required') }}" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <button class="w-full rounded-xl bg-rose-600 px-4 py-3 text-sm font-bold text-white hover:bg-rose-700"><i class="fa-solid fa-xmark me-2"></i>{{ __('receipts.reject') }}</button>
                        </form>
                    </div>
                @endcan
            @else
                <div class="rounded-2xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200">
                    <p>{{ __('receipts.reviewed_by') }}: <strong>{{ $receipt->reviewer?->name ?? '—' }}</strong> · {{ $receipt->reviewed_at?->format('Y-m-d H:i') }}</p>
                    @if ($receipt->review_note) <p class="mt-1 text-slate-600">{{ $receipt->review_note }}</p> @endif
                </div>
            @endif

            @if ($others->isNotEmpty())
                <div class="rounded-2xl bg-white p-4 text-sm shadow-sm ring-1 ring-slate-200">
                    <p class="mb-2 font-bold">{{ __('receipts.other_attempts') }}</p>
                    @foreach ($others as $o)
                        <a class="block py-1 text-indigo-700 hover:underline" href="{{ route('cms.admin.receipts.show', $o->id) }}">#{{ $o->id }} · {{ __('receipts.statuses.' . $o->status) }} · {{ $o->created_at->format('Y-m-d') }}</a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
