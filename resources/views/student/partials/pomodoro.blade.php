{{-- مؤقت التركيز (بومودورو): زر بجانب أزرار السمة، نافذة منبثقة، وشارة عائمة تبقى أثناء العدّ.
     الحالة تُحفَظ في المتصفح (وقت الانتهاء) فيستمر العدّ بين الصفحات. --}}
@php
    $pmT = [
        'title' => __('student.pomodoro.title'), 'open' => __('student.pomodoro.open'), 'close' => __('student.pomodoro.close'),
        'start' => __('student.pomodoro.start'), 'pause' => __('student.pomodoro.pause'), 'reset' => __('student.pomodoro.reset'),
        'done' => __('student.pomodoro.done'), 'focus' => __('student.pomodoro.focus'), 'short' => __('student.pomodoro.short'), 'long' => __('student.pomodoro.long'),
    ];
@endphp
@include('student.partials.asset', ['file' => 'pomodoro'])
<script type="application/json" id="pm-config">@json($pmT, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@include('student.partials.asset', ['type' => 'js', 'file' => 'pomodoro'])
