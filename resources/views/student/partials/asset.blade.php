{{-- يصل ملفًا ثابتًا من public/css/ui أو public/js/ui مع رقم نسخة (وقت التعديل) لتجديد الكاش تلقائيًا.
     الاستعمال: @include('student.partials.asset', ['file' => 'header']) أو مع 'type' => 'js' --}}
@php
    $assetType = ($type ?? 'css') === 'js' ? 'js' : 'css';
    $assetPath = $assetType.'/ui/'.preg_replace('/[^a-z0-9_-]/i', '', $file).'.'.$assetType;
    $assetVersion = @filemtime(public_path($assetPath)) ?: 1;
@endphp
@if ($assetType === 'js')
<script src="{{ asset($assetPath) }}?v={{ $assetVersion }}" defer></script>
@else
<link rel="stylesheet" href="{{ asset($assetPath) }}?v={{ $assetVersion }}">
@endif
