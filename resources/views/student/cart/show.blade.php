@extends('student.layout')

@section('title', __('student.nav.cart'))

@section('content')
@php $currency = config('app.currency', 'USD'); $valid = $rows->where('issue', null); @endphp
    <h1 class="mb-6 text-2xl font-bold text-slate-900">{{ __('student.nav.cart') }}</h1>

    @if ($rows->isEmpty())
        <div class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <p class="text-slate-500">{{ __('student.cart.empty') }}</p>
            <a href="{{ route('student.courses.index') }}" class="mt-4 inline-block rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.mine.browse') }}</a>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                @foreach ($rows as $row)
                    @php $course = $row['course']; @endphp
                    <div class="flex items-center justify-between gap-4 rounded-xl border bg-white p-4 {{ $row['issue'] ? 'border-amber-300' : 'border-slate-200' }}">
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-slate-900">{{ $course ? \App\Support\Loc::pick($course->title_ar, $course->title_en) : '—' }}</p>
                            @if ($row['issue'])
                                <p class="mt-1 text-xs text-amber-700">{{ __('student.cart.issues.' . $row['issue']) }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-4">
                            <span class="whitespace-nowrap text-sm font-bold">{{ $row['price'] !== null ? number_format((float) $row['price'], 2) . ' ' . $currency : '' }}</span>
                            <form method="POST" action="{{ route('student.cart.remove', $row['item']->course_id) }}">
                                @csrf @method('DELETE')
                                <button class="text-sm text-rose-600 hover:underline">{{ __('student.cart.remove') }}</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <aside class="h-fit space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex justify-between text-sm"><span>{{ __('student.cart.subtotal') }}</span><span class="font-bold">{{ number_format((float) $subtotal, 2) }} {{ $currency }}</span></div>
                <form method="POST" action="{{ route('student.checkout') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="coupon" value="{{ old('coupon') }}" maxlength="50" placeholder="{{ __('student.cart.coupon') }}"
                           class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" autocomplete="off">
                    <button @disabled($valid->isEmpty()) class="w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50">{{ __('student.cart.checkout') }}</button>
                </form>
            </aside>
        </div>
    @endif
@endsection
