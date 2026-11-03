@extends('student.layout')

@section('title', __('student.nav.courses'))

@section('content')
    <h1 class="mb-6 text-2xl font-bold text-slate-900">{{ __('student.catalog.title') }}</h1>

    <form method="GET" action="{{ route('student.courses.index') }}"
          class="mb-8 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-6">
        <input type="search" name="q" value="{{ $filters['q'] }}" maxlength="100" placeholder="{{ __('student.catalog.search_placeholder') }}"
               class="rounded-xl border border-slate-300 px-3 py-2 text-sm lg:col-span-2">

        <select name="category" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ __('student.catalog.all_categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->slug }}" @selected($filters['category'] === $category->slug)>{{ \App\Support\Loc::pick($category->name_ar, $category->name_en) }}</option>
            @endforeach
        </select>

        <select name="level" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ __('student.catalog.all_levels') }}</option>
            @foreach (\App\Models\Course::LEVELS as $level)
                <option value="{{ $level }}" @selected($filters['level'] === $level)>{{ __('student.levels.' . $level) }}</option>
            @endforeach
        </select>

        <select name="price" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            <option value="">{{ __('student.catalog.any_price') }}</option>
            <option value="free" @selected($filters['price'] === 'free')>{{ __('student.free') }}</option>
            <option value="paid" @selected($filters['price'] === 'paid')>{{ __('student.catalog.paid') }}</option>
        </select>

        <select name="sort" class="rounded-xl border border-slate-300 px-3 py-2 text-sm">
            @foreach (['newest', 'price_asc', 'price_desc'] as $sort)
                <option value="{{ $sort }}" @selected($filters['sort'] === $sort)>{{ __('student.catalog.sort.' . $sort) }}</option>
            @endforeach
        </select>

        <div class="flex gap-2 sm:col-span-2 lg:col-span-6">
            <button class="rounded-xl bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.catalog.apply') }}</button>
            <a href="{{ route('student.courses.index') }}" class="rounded-xl border border-slate-300 px-5 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">{{ __('student.catalog.reset') }}</a>
        </div>
    </form>

    @if ($courses->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('student.catalog.empty') }}</p>
    @else
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($courses as $course)
                @include('student.partials.course-card', ['course' => $course])
            @endforeach
        </div>
        <div class="mt-8">{{ $courses->links() }}</div>
    @endif
@endsection
