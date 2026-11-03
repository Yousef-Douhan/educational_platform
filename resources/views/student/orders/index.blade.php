@extends('student.layout')

@section('title', __('student.nav.orders'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">{{ __('student.nav.orders') }}</h1>

    @if ($orders->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('student.orders.empty') }}</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-start text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-start">{{ __('student.orders.number') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('student.orders.date') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('student.orders.items') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('student.orders.total') }}</th>
                        <th class="px-4 py-3 text-start">{{ __('student.orders.status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($orders as $order)
                        <tr>
                            <td class="px-4 py-3"><a class="font-medium text-sky-700 hover:underline" href="{{ route('student.orders.show', $order->order_number) }}">{{ $order->order_number }}</a></td>
                            <td class="px-4 py-3">{{ $order->created_at->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">{{ $order->items_count }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $order->total_amount, 2) }} {{ $order->currency }}</td>
                            <td class="px-4 py-3">{{ __('student.order_status.' . $order->status) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
@endsection
