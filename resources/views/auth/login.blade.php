@extends('student.layout')

@section('title', __('student.auth.sign_in'))

@section('content')
    <div class="mx-auto my-6 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-6 text-center">
            <h1 class="text-2xl font-extrabold text-slate-900">{{ __('student.auth.sign_in') }}</h1>
            <p class="mt-1 text-sm text-slate-500">{{ __('student.auth.sign_in_hint') }}</p>
        </div>

        <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium">{{ __('student.auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus maxlength="255"
                       autocomplete="username" dir="ltr"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium">{{ __('student.auth.password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" dir="ltr"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex items-center justify-between text-sm">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="remember" class="rounded border-slate-300"> {{ __('student.auth.remember') }}
                </label>
                <a href="{{ route('password.request') }}" class="font-semibold text-indigo-700 hover:underline">{{ __('student.pw.forgot_link') }}</a>
            </div>
            <button type="submit" class="w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.auth.sign_in') }}</button>
        </form>

        @include('student.partials.google-button')

        <p class="mt-5 text-center text-sm text-slate-500">
            {{ __('student.auth.no_account') }}
            <a href="{{ route('register') }}" class="font-semibold text-indigo-700 hover:underline">{{ __('student.auth.create_account') }}</a>
        </p>
    </div>
@endsection
