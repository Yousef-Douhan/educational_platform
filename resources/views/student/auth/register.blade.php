@extends('student.layout')

@section('title', __('student.auth.create_account'))

@section('content')
    <div class="mx-auto max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <h1 class="mb-5 text-xl font-bold text-slate-900">{{ __('student.auth.create_account') }}</h1>
        <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="name">{{ __('student.auth.name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="255" autocomplete="name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="email">{{ __('student.auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password">{{ __('student.auth.password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ __('student.auth.password_hint') }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="password_confirmation">{{ __('student.auth.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
            </div>
            <button class="w-full rounded-xl bg-sky-600 px-4 py-3 text-sm font-semibold text-white hover:bg-sky-700">{{ __('student.auth.create_account') }}</button>
        </form>
        <p class="mt-4 text-center text-sm text-slate-500">{{ __('student.auth.have_account') }} <a class="font-medium text-sky-700 hover:underline" href="{{ route('login') }}">{{ __('student.nav.login') }}</a></p>
    </div>
@endsection
