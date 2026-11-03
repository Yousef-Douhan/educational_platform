@extends('student.layout')

@section('title', __('student.pw.forgot_title'))

@section('content')
    <div class="mx-auto my-6 max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
        <h1 class="text-2xl font-extrabold text-slate-900">{{ __('student.pw.forgot_title') }}</h1>
        <p class="mb-5 mt-1 text-sm text-slate-500">{{ __('student.pw.forgot_hint') }}</p>
        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium">{{ __('student.auth.email') }}</label>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus maxlength="255" dir="ltr"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('email') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <button class="w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.pw.send_link') }}</button>
        </form>
        <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:underline">{{ __('student.auth.sign_in') }}</a></p>
    </div>
@endsection
