@php
    $dark = $dark ?? false;
    $size = $size ?? 'lg';
    $box  = $size === 'lg' ? 'h-14 w-14' : 'h-10 w-10';
    $text = $size === 'lg' ? 'text-3xl' : 'text-xl';
@endphp
<span class="inline-flex items-center gap-3">
    <svg class="{{ $box }} shrink-0 drop-shadow-md" viewBox="0 0 48 48" role="img" aria-label="{{ config('app.name') }}">
        <defs>
            <linearGradient id="logo-g-{{ $dark ? 'd' : 'l' }}" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0" stop-color="#4f46e5"/><stop offset="1" stop-color="#7c3aed"/>
            </linearGradient>
        </defs>
        <rect width="48" height="48" rx="12" fill="url(#logo-g-{{ $dark ? 'd' : 'l' }})"/>
        <polygon points="24,10 42,19 24,28 6,19" fill="#fff"/>
        <path d="M14 24.5v6.5c0 2.8 4.5 5.5 10 5.5s10-2.7 10-5.5v-6.5l-10 5z" fill="#fff" fill-opacity=".92"/>
        <path d="M38 20.5V31" stroke="#fbbf24" stroke-width="2.2" stroke-linecap="round"/>
        <circle cx="38" cy="33" r="2.4" fill="#fbbf24"/>
    </svg>
    <span class="{{ $text }} font-extrabold leading-none tracking-tight {{ $dark ? 'text-white' : 'text-indigo-950' }}">{{ config('app.name', 'Laravel') }}</span>
</span>
