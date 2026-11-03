{{-- تبديل اللغة والوضع الداكن/الفاتح --}}
@php
    $toLocale  = app()->isLocale('ar') ? 'en' : 'ar';
    $localeTxt = $toLocale === 'ar' ? 'العربية' : 'English';
    $tone = $tone ?? 'light';
    $btn  = $tone === 'dark'
        ? 'rounded-full px-2.5 py-1 text-slate-300 hover:bg-white/10 hover:text-white'
        : 'rounded-full bg-slate-100 px-3 py-2 text-slate-700 hover:bg-indigo-100';
@endphp
<form method="POST" action="{{ route('locale.update', $toLocale) }}" class="inline">
    @csrf
    <button type="submit" class="{{ $btn }} text-xs font-bold" lang="{{ $toLocale }}" title="{{ __('student.nav.language') }}" aria-label="{{ __('student.nav.language') }}">
        <i class="fa-solid fa-globe me-1"></i>{{ $localeTxt }}
    </button>
</form>
<button type="button" data-theme-toggle class="{{ $btn }}" title="{{ __('student.nav.theme') }}" aria-label="{{ __('student.nav.theme') }}">
    <i class="fa-solid fa-moon" data-theme-icon="moon"></i>
    <i class="fa-solid fa-sun" data-theme-icon="sun"></i>
</button>
