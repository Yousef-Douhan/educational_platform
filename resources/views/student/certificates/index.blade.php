@extends('student.layout')

@section('title', __('student.nav.certificates'))

@section('content')
    <h1 class="mb-6 text-2xl font-extrabold text-slate-900">{{ __('student.nav.certificates') }}</h1>

    @if ($claimable->isNotEmpty())
        <section class="mb-8 rounded-2xl border border-amber-200 bg-amber-50 p-5">
            <h2 class="mb-3 font-bold text-amber-900"><i class="fa-solid fa-award me-2"></i>{{ __('student.certs.ready') }}</h2>
            <ul class="space-y-2">
                @foreach ($claimable as $enrollment)
                    <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-white px-4 py-3 text-sm">
                        <span class="font-semibold">{{ \App\Support\Loc::pick($enrollment->course->title_ar, $enrollment->course->title_en) }}</span>
                        <form method="POST" action="{{ route('student.certificates.claim', $enrollment->course->slug) }}">
                            @csrf
                            <button class="rounded-lg bg-amber-500 px-4 py-2 text-xs font-bold text-slate-900 hover:bg-amber-400">{{ __('student.certs.get') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($certificates->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-10 text-center text-slate-500">{{ __('student.certs.empty') }}</p>
    @else
        <div class="grid gap-5 md:grid-cols-2">
            @foreach ($certificates as $cert)
                <article class="rounded-2xl border bg-white p-5 shadow-sm {{ $cert->revoked_at ? 'border-rose-200 opacity-80' : 'border-slate-200' }}">
                    <div class="mb-3 flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-bold text-slate-900">{{ \App\Support\Loc::pick($cert->course_title_ar, $cert->course_title_en) }}</h3>
                            <p class="mt-1 text-xs text-slate-500">{{ $cert->student_name }} · {{ $cert->issued_at->format('Y-m-d') }}</p>
                        </div>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-xl text-amber-600"><i class="fa-solid fa-award"></i></span>
                    </div>
                    <p class="mb-4 rounded-lg bg-slate-50 px-3 py-2 text-center font-mono text-sm tracking-wider text-slate-700" dir="ltr">{{ $cert->certificate_code }}</p>

                    @if ($cert->revoked_at)
                        <p class="text-sm font-semibold text-rose-700">{{ __('student.certs.revoked') }}</p>
                    @else
                        <div class="flex flex-wrap gap-2 text-xs font-bold">
                            <a href="{{ route('student.certificates.download', $cert->id) }}" class="rounded-lg bg-indigo-700 px-4 py-2 text-white hover:bg-indigo-800"><i class="fa-solid fa-download me-1"></i>{{ __('student.certs.download') }}</a>
                            <a href="{{ route('student.certificates.download', [$cert->id, 'lang' => 'ar']) }}" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">AR</a>
                            <a href="{{ route('student.certificates.download', [$cert->id, 'lang' => 'en']) }}" class="rounded-lg border border-slate-300 px-3 py-2 hover:bg-slate-50">EN</a>
                            <a href="{{ route('certificates.verify', $cert->certificate_code) }}" target="_blank" rel="noopener" class="rounded-lg border border-emerald-300 px-3 py-2 text-emerald-700 hover:bg-emerald-50">{{ __('student.certs.verify') }}</a>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif
@endsection
