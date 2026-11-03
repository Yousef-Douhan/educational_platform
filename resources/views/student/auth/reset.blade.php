@extends('student.layout')

@section('title', __('student.pw.reset_title'))

@section('content')
    <div class="mx-auto my-6 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="mb-5 text-2xl font-extrabold text-slate-900">{{ __('student.pw.reset_title') }}</h1>
        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="mb-1 block text-sm font-medium">{{ __('student.auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required maxlength="255" dir="ltr"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium">{{ __('student.profile.new_password') }}</label>
                <input id="password" type="password" name="password" required autocomplete="new-password" dir="ltr" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                <p class="mt-1 text-xs text-slate-500">{{ __('student.auth.password_hint') }}</p>
                @error('password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium">{{ __('student.auth.password_confirmation') }}</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" dir="ltr" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
            </div>
            <button class="w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.pw.reset_submit') }}</button>
        </form>
    </div>
@endsection
