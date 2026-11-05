@php
    $scLabels = [
        'days' => __('student.schedule.days'), 'kinds' => __('student.schedule.kinds'),
        'confirmDelete' => __('student.schedule.confirm_delete'), 'confirmClear' => __('student.schedule.confirm_clear'),
        'conflictOne' => __('student.schedule.conflict_one'), 'noConflict' => __('student.schedule.no_conflict'),
        'conflictBadge' => __('student.schedule.conflict_badge'), 'failed' => __('student.schedule.saving_failed'),
        'new' => __('student.schedule.new'), 'edit' => __('student.schedule.edit'),
        'time' => __('student.schedule.time'), 'endAfterStart' => __('student.schedule.end_after_start'),
    ];
@endphp
@include('student.partials.asset', ['file' => 'tokens'])
@include('student.partials.asset', ['file' => 'schedule'])

<div class="sc" id="sc-root" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
    <div class="sc-head">
        <h1>📅 {{ __('student.schedule.title') }}</h1>
        <p>{{ __('student.schedule.subtitle') }}</p>
    </div>

    <div class="sc-card sc-bar">
        <button type="button" class="sc-btn primary" id="sc-add">＋ {{ __('student.schedule.add') }}</button>
        <button type="button" class="sc-btn" id="sc-print">🖨 {{ __('student.schedule.print') }}</button>
        <button type="button" class="sc-btn danger" id="sc-clear">🗑 {{ __('student.schedule.clear_all') }}</button>
    </div>

    <div id="sc-status" class="sc-note ok" role="status" aria-live="polite"></div>

    <div class="sc-card sc-print">
        <div class="sc-printtitle">{{ __('student.schedule.title') }}</div>
        <div class="sc-wrap"><div class="sc-grid" id="sc-grid"></div></div>
    </div>
    <div class="sc-empty" id="sc-empty" hidden>{{ __('student.schedule.empty') }}</div>
</div>

<div class="sc-modal" id="sc-modal" role="dialog" aria-modal="true" aria-labelledby="sc-mt">
    <form class="sc-dlg" id="sc-form" novalidate>
        <h2 id="sc-mt"></h2>
        <p class="sc-err" id="sc-err" hidden></p>
        <label class="sc-f">{{ __('student.schedule.name') }}
            <input type="text" id="sc-title" maxlength="100" placeholder="{{ __('student.schedule.name_ph') }}" required>
        </label>
        <div class="sc-row">
            <label class="sc-f">{{ __('student.schedule.kind') }}
                <select id="sc-kind">
                    @foreach (__('student.schedule.kinds') as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                </select>
            </label>
            <label class="sc-f">{{ __('student.schedule.day') }}
                <select id="sc-day">
                    @foreach (__('student.schedule.days') as $i => $v)<option value="{{ $i }}">{{ $v }}</option>@endforeach
                </select>
            </label>
        </div>
        <div class="sc-row">
            <label class="sc-f">{{ __('student.schedule.from') }}<input type="time" id="sc-start" value="08:00" required></label>
            <label class="sc-f">{{ __('student.schedule.to') }}<input type="time" id="sc-end" value="09:00" required></label>
        </div>
        <label class="sc-f">{{ __('student.schedule.room') }}<input type="text" id="sc-room" maxlength="60"></label>
        <div class="sc-f">{{ __('student.schedule.color') }}<div class="sc-sw" id="sc-sw"></div></div>
        <div class="sc-acts">
            <button type="submit" class="sc-btn primary" id="sc-save">{{ __('student.schedule.save') }}</button>
            <button type="button" class="sc-btn" id="sc-cancel">{{ __('student.schedule.cancel') }}</button>
            <span class="sp"></span>
            <button type="button" class="sc-btn danger" id="sc-del" hidden>{{ __('student.schedule.delete') }}</button>
        </div>
    </form>
</div>

@php
    $scConfig = ['labels' => $scLabels, 'items' => $items, 'csrf' => csrf_token(), 'url' => route('student.schedule.store')];
@endphp
<script type="application/json" id="sc-config">{!! json_encode($scConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@include('student.partials.asset', ['type' => 'js', 'file' => 'schedule'])
