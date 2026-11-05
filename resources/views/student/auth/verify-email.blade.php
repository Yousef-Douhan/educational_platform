@extends('student.layout')

@section('title', __('student.verify.title'))

@section('content')
    <div class="mx-auto my-6 max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-center shadow-sm sm:p-8">
        <span class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100 text-2xl text-indigo-700"><i class="fa-solid fa-envelope-circle-check"></i></span>
        <h1 class="text-2xl font-extrabold text-slate-900">{{ __('student.verify.title') }}</h1>
        <p class="mt-2 text-sm leading-7 text-slate-500">{{ __('student.verify.text', ['email' => auth()->user()->email]) }}</p>

        <form method="POST" action="{{ route('verification.send') }}" class="mt-6">
            @csrf
            <button class="w-full rounded-xl bg-indigo-700 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.verify.resend') }}</button>
        </form>
        <a href="{{ route('student.courses.index') }}" class="mt-4 inline-block text-sm font-semibold text-indigo-700 hover:underline">{{ __('student.verify.browse') }}</a>
    </div>
@endsection
