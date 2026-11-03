@extends('student.layout')

@section('title', __('student.profile.title'))

@section('content')
    <h1 class="mb-6 text-2xl font-extrabold text-slate-900"><i class="fa-solid fa-user me-2 text-indigo-600"></i>{{ __('student.profile.title') }}</h1>

    <div class="grid gap-6 lg:grid-cols-2">
        <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold text-slate-900">{{ __('student.profile.info') }}</h2>
            <div>
                <label for="name" class="mb-1 block text-sm font-medium">{{ __('student.auth.name') }}</label>
                <input id="name" name="name" value="{{ old('name', $user->name) }}" required minlength="2" maxlength="255" autocomplete="name"
                       class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('name') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">{{ __('student.auth.email') }}</label>
                <input value="{{ $user->email }}" disabled dir="ltr" class="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-100 px-3 py-2.5 text-sm text-slate-500">
                <p class="mt-1 text-xs text-slate-500">{{ __('student.profile.email_locked') }}</p>
            </div>
            <button class="rounded-xl bg-indigo-700 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-800">{{ __('student.profile.save') }}</button>
        </form>

        <form method="POST" action="{{ route('student.password.update') }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <h2 class="text-lg font-bold text-slate-900">{{ __('student.profile.change_password') }}</h2>
            <div>
                <label for="current_password" class="mb-1 block text-sm font-medium">{{ __('student.profile.current_password') }}</label>
                <input id="current_password" type="password" name="current_password" required autocomplete="current-password" dir="ltr" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm">
                @error('current_password') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
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
            <button class="rounded-xl bg-slate-900 px-6 py-2.5 text-sm font-bold text-white hover:bg-slate-700">{{ __('student.profile.update_password') }}</button>
        </form>
    </div>
@endsection
