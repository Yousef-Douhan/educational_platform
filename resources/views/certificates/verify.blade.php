<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('ar') ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('certificates.verify.title', [], 'ar') }} | {{ __('brand.name', [], 'ar') }}</title>
    @vite('resources/css/verify.css')
</head>
<body>
<main class="verify" data-state="{{ $state }}">
    <h1>{{ __('certificates.verify.title', [], 'ar') }}<small>{{ __('certificates.verify.title', [], 'en') }}</small></h1>

    <form method="GET" action="{{ route('certificates.verify.form') }}" class="verify__form">
        <input type="text" name="code" value="{{ $code ?? '' }}" maxlength="100" autocomplete="off" dir="ltr"
               placeholder="CERT-XXXX-XXXX-XXXX" aria-label="{{ __('certificates.verify.code_label', [], 'en') }}" required>
        <button type="submit">{{ __('certificates.verify.check', [], 'ar') }} / {{ __('certificates.verify.check', [], 'en') }}</button>
    </form>

    @if ($state === 'valid' || $state === 'revoked')
        <section class="verify__card verify__card--{{ $state }}">
            <p class="verify__status">
                {{ __("certificates.verify.{$state}", [], 'ar') }}<small>{{ __("certificates.verify.{$state}", [], 'en') }}</small>
            </p>
            <dl>
                <div><dt>{{ __('certificates.verify.student', [], 'ar') }} / {{ __('certificates.verify.student', [], 'en') }}</dt>
                    <dd>{{ $certificate->student_name }}</dd></div>
                <div><dt>{{ __('certificates.verify.course', [], 'ar') }} / {{ __('certificates.verify.course', [], 'en') }}</dt>
                    <dd>{{ $certificate->course_title_ar }}<br><span dir="ltr">{{ $certificate->course_title_en }}</span></dd></div>
                <div><dt>{{ __('certificates.verify.issued', [], 'ar') }} / {{ __('certificates.verify.issued', [], 'en') }}</dt>
                    <dd>{{ $certificate->issued_at->format('Y-m-d') }}</dd></div>
                @if ($state === 'revoked')
                    <div><dt>{{ __('certificates.verify.revoked_on', [], 'ar') }} / {{ __('certificates.verify.revoked_on', [], 'en') }}</dt>
                        <dd>{{ $certificate->revoked_at->format('Y-m-d') }}</dd></div>
                @endif
                <div><dt>{{ __('certificates.verify.code_label', [], 'ar') }} / {{ __('certificates.verify.code_label', [], 'en') }}</dt>
                    <dd dir="ltr">{{ $certificate->certificate_code }}</dd></div>
            </dl>
        </section>
    @elseif ($state === 'notfound')
        <section class="verify__card verify__card--notfound">
            <p class="verify__status">{{ __('certificates.verify.not_found', [], 'ar') }}<small>{{ __('certificates.verify.not_found', [], 'en') }}</small></p>
        </section>
    @else
        <p class="verify__hint">{{ __('certificates.verify.hint', [], 'ar') }}<small>{{ __('certificates.verify.hint', [], 'en') }}</small></p>
    @endif
</main>
</body>
</html>