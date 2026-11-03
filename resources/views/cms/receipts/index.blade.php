@extends('cms.parent')

@section('title', __('receipts.title'))

@section('main-content')
<div class="space-y-6 p-1">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-900"><i class="fa-solid fa-receipt me-2 text-indigo-600"></i>{{ __('receipts.title') }}</h1>
        <p class="text-sm text-slate-500">{{ __('receipts.subtitle') }}</p>
    </div>

    @if (session('status')) <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div> @endif

    <form method="GET" class="flex flex-wrap items-center gap-3 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div class="flex gap-1 text-sm font-semibold">
            @foreach (['pending', 'approved', 'rejected', ''] as $st)
                <a href="{{ route('cms.admin.receipts.index', array_filter(['status' => $st === '' ? 'all' : $st, 'search' => request('search')])) }}"
                   class="rounded-lg px-3 py-2 {{ ($status === $st || ($st === '' && $status === 'all')) ? 'bg-indigo-700 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' }}">
                    {{ $st === '' ? __('receipts.all') : __('receipts.statuses.' . $st) }}
                    @if ($st !== '') <span class="ms-1 opacity-70">({{ $counts[$st] ?? 0 }})</span> @endif
                </a>
            @endforeach
        </div>
        <input type="hidden" name="status" value="{{ $status }}">
        <input type="search" name="search" value="{{ request('search') }}" maxlength="100" placeholder="{{ __('receipts.search') }}" class="ms-auto w-64 rounded-xl border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ __('receipts.apply') }}</button>
    </form>

    <div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.order') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.student') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.method') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.amount') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.reference') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.status') }}</th>
                    <th class="px-4 py-3 text-start">{{ __('receipts.fields.date') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($receipts as $r)
                    <tr>
                        <td class="px-4 py-3 font-mono">{{ $r->order->order_number }}</td>
                        <td class="px-4 py-3">{{ $r->user->name }}</td>
                        <td class="px-4 py-3">{{ __('receipts.methods.' . $r->method) }}</td>
                        <td class="px-4 py-3">{{ number_format((float) $r->order->total_amount, 2) }} {{ $r->order->currency }}</td>
                        <td class="px-4 py-3 font-mono" dir="ltr">{{ $r->reference_number }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-rose-100 text-rose-800'][$r->status] }}">{{ __('receipts.statuses.' . $r->status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $r->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3"><a class="font-semibold text-indigo-700 hover:underline" href="{{ route('cms.admin.receipts.show', $r->id) }}">{{ __('receipts.review') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">{{ __('receipts.empty') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $receipts->links() }}
</div>
@endsection
