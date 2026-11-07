@include('student.partials.asset', ['file' => 'tokens'])
@include('student.partials.asset', ['file' => 'notes'])

<div class="st" id="st" data-mo-skip>
    <div class="st-head">
        <div>
            @unless ($hideHead ?? false)<h1>{{ __('student.notes.title') }}</h1>@endunless
            <p>{{ __('student.notes.subtitle') }}</p>
        </div>
        <div class="st-tabs" role="tablist">
            <button type="button" class="st-tab" role="tab" id="tab-notes" aria-selected="true" aria-controls="pane-notes"><i class="fa-solid fa-note-sticky"></i> {{ __('student.notes.tab_notes') }}</button>
            <button type="button" class="st-tab" role="tab" id="tab-board" aria-selected="false" aria-controls="pane-board"><i class="fa-solid fa-palette"></i> {{ __('student.notes.tab_board') }}</button>
        </div>
    </div>

    {{-- الملاحظات --}}
    <section id="pane-notes" role="tabpanel" aria-labelledby="tab-notes" class="st-notes">
        <aside class="st-card st-side" aria-label="{{ __('student.notes.folders') }}">
            <h2>{{ __('student.notes.folders') }}</h2>
            <div id="folders"></div>
        </aside>

        <div class="st-card st-editor" id="editor">
            <div class="st-ph" id="ph"><div><i class="fa-regular fa-note-sticky" style="font-size:2rem;opacity:.5"></i><p>{{ __('student.notes.select') }}</p></div></div>
            <div id="edit" hidden style="display:flex;flex-direction:column;flex:1;min-height:0">
                <div class="st-ehead">
                    <input type="text" id="n-title" class="st-title" maxlength="150" placeholder="{{ __('student.notes.title_ph') }}" aria-label="{{ __('student.notes.title_ph') }}">
                    <span class="st-status" id="n-status" aria-live="polite"></span>
                    <button type="button" class="st-btn" id="n-preview" aria-pressed="false">{{ __('student.notes.preview') }}</button>
                    <button type="button" class="st-btn" id="n-clear">{{ __('student.notes.clear') }}</button>
                    <button type="button" class="st-btn danger" id="n-del">{{ __('student.notes.delete') }}</button>
                    <button type="button" class="st-btn primary" id="n-save">{{ __('student.notes.save') }}</button>
                </div>
                <div class="st-toolbar" id="n-tb" role="toolbar"></div>
                <textarea id="n-body" class="st-body" maxlength="200000" placeholder="{{ __('student.notes.body_ph') }}"></textarea>
                <div id="n-prev" class="st-preview" hidden></div>
            </div>
        </div>
    </section>

    {{-- السبورة --}}
    <section id="pane-board" role="tabpanel" aria-labelledby="tab-board" hidden>
        <div class="st-card" id="b-card">
            <div class="st-bhead" id="b-pages" role="tablist"></div>
            <div class="st-bbar" id="b-bar">
                <span class="lbl">{{ __('student.notes.board.tool') }}</span>
                <button type="button" class="st-tool" data-tool="s" aria-pressed="false"><i class="fa-solid fa-arrow-pointer"></i> {{ __('student.notes.board.select') }}</button>
                <button type="button" class="st-tool" data-tool="p" aria-pressed="true"><i class="fa-solid fa-pencil"></i> {{ __('student.notes.board.pen') }}</button>
                <button type="button" class="st-tool" data-tool="h" aria-pressed="false"><i class="fa-solid fa-highlighter"></i> {{ __('student.notes.board.highlighter') }}</button>
                <button type="button" class="st-tool" data-tool="e" aria-pressed="false"><i class="fa-solid fa-eraser"></i> {{ __('student.notes.board.eraser') }}</button>
                <button type="button" class="st-tool" data-tool="l" aria-pressed="false"><i class="fa-solid fa-slash"></i> {{ __('student.notes.board.line') }}</button>
                <button type="button" class="st-tool" data-tool="r" aria-pressed="false"><i class="fa-regular fa-square"></i> {{ __('student.notes.board.rect') }}</button>
                <button type="button" class="st-tool" data-tool="o" aria-pressed="false"><i class="fa-regular fa-circle"></i> {{ __('student.notes.board.circle') }}</button>
                <span class="lbl">{{ __('student.notes.board.shape') }}</span>
                <button type="button" class="st-tool" id="b-img"><i class="fa-regular fa-image"></i> {{ __('student.notes.board.image') }}</button>
                <input type="file" id="b-file" accept="image/png,image/jpeg,image/webp" hidden>
                <span class="st-sep"></span>
                <span class="lbl">{{ __('student.notes.board.color') }}</span>
                <span id="b-sw" style="display:inline-flex;gap:6px;align-items:center"></span>
                <input type="color" id="b-color" class="st-color" value="#22d3ee" aria-label="{{ __('student.notes.board.color') }}">
                <span class="st-sep"></span>
                <span class="lbl">{{ __('student.notes.board.size') }}</span>
                <input type="range" id="b-size" class="st-range" min="1" max="60" value="6" aria-label="{{ __('student.notes.board.size') }}">
                <span class="lbl" id="b-sizev">6</span>
                <span class="st-sep"></span>
                <span class="lbl">{{ __('student.notes.board.opacity') }}</span>
                <input type="range" id="b-alpha" class="st-range" min="5" max="100" value="100" aria-label="{{ __('student.notes.board.opacity') }}">
                <span class="lbl" id="b-alphav">100%</span>
                <span class="lbl">{{ __('student.notes.board.stroke') }}</span>
                <select id="b-dash" class="st-select" aria-label="{{ __('student.notes.board.stroke') }}">
                    <option value="0">{{ __('student.notes.board.solid') }}</option>
                    <option value="1">{{ __('student.notes.board.dashed') }}</option>
                    <option value="2">{{ __('student.notes.board.dotted') }}</option>
                </select>
                <label class="st-check"><input type="checkbox" id="b-smooth" checked> {{ __('student.notes.board.smooth') }}</label>
                <span class="st-sep"></span>
                <button type="button" class="st-btn" id="b-undo"><i class="fa-solid fa-rotate-left"></i> {{ __('student.notes.board.undo') }}</button>
                <button type="button" class="st-btn" id="b-redo"><i class="fa-solid fa-rotate-right"></i> {{ __('student.notes.board.redo') }}</button>
                <button type="button" class="st-btn" id="b-clear"><i class="fa-regular fa-trash-can"></i> {{ __('student.notes.board.clear') }}</button>
                <label class="lbl" for="b-zoom">{{ __('student.notes.board.view_size') }}</label>
                <input type="range" id="b-zoom" class="st-range" min="50" max="100" step="5" value="100">
                <span class="lbl" id="b-zoomv">100%</span>
                <button type="button" class="st-btn" id="b-fs"><i class="fa-solid fa-expand"></i> {{ __('student.notes.board.fullscreen') }}</button>
                <button type="button" class="st-btn" id="b-dl"><i class="fa-solid fa-floppy-disk"></i> {{ __('student.notes.board.download') }}</button>
                <span class="st-bstatus" id="b-status" aria-live="polite"></span>
            </div>
            <div class="st-bbar st-imgbar" id="b-imgbar" hidden>
                <span class="lbl"><i class="fa-regular fa-image"></i> {{ __('student.notes.board.img.panel') }}</span>
                <label class="lbl" for="b-i-rot">{{ __('student.notes.board.img.rotate') }}</label>
                <input type="range" id="b-i-rot" class="st-range" min="-180" max="180" value="0">
                <span class="lbl" id="b-i-rotv">0°</span>
                <label class="lbl" for="b-i-alpha">{{ __('student.notes.board.opacity') }}</label>
                <input type="range" id="b-i-alpha" class="st-range" min="5" max="100" value="100">
                <span class="lbl" id="b-i-alphav">100%</span>
                <button type="button" class="st-btn" id="b-i-down" title="{{ __('student.notes.board.img.scale_down') }}" aria-label="{{ __('student.notes.board.img.scale_down') }}"><i class="fa-solid fa-magnifying-glass-minus"></i></button>
                <button type="button" class="st-btn" id="b-i-up" title="{{ __('student.notes.board.img.scale_up') }}" aria-label="{{ __('student.notes.board.img.scale_up') }}"><i class="fa-solid fa-magnifying-glass-plus"></i></button>
                <button type="button" class="st-btn" id="b-i-fx" title="{{ __('student.notes.board.img.flip_h') }}" aria-label="{{ __('student.notes.board.img.flip_h') }}"><i class="fa-solid fa-left-right"></i></button>
                <button type="button" class="st-btn" id="b-i-fy" title="{{ __('student.notes.board.img.flip_v') }}" aria-label="{{ __('student.notes.board.img.flip_v') }}"><i class="fa-solid fa-up-down"></i></button>
                <button type="button" class="st-btn" id="b-i-front" title="{{ __('student.notes.board.img.front') }}" aria-label="{{ __('student.notes.board.img.front') }}"><i class="fa-solid fa-arrow-up-from-bracket"></i></button>
                <button type="button" class="st-btn" id="b-i-back" title="{{ __('student.notes.board.img.back') }}" aria-label="{{ __('student.notes.board.img.back') }}"><i class="fa-solid fa-arrow-down-to-line"></i></button>
                <button type="button" class="st-btn" id="b-i-dup" title="{{ __('student.notes.board.img.duplicate') }}" aria-label="{{ __('student.notes.board.img.duplicate') }}"><i class="fa-regular fa-clone"></i></button>
                <label class="st-check"><input type="checkbox" id="b-i-lock" checked> {{ __('student.notes.board.img.lock') }}</label>
                <button type="button" class="st-btn" id="b-i-reset"><i class="fa-solid fa-rotate-left"></i> {{ __('student.notes.board.img.reset') }}</button>
                <button type="button" class="st-btn st-danger" id="b-i-del"><i class="fa-regular fa-trash-can"></i> {{ __('student.notes.board.img.delete') }}</button>
                <span class="lbl st-hint">{{ __('student.notes.board.img.hint') }}</span>
            </div>
            <div class="st-canvas-wrap" id="b-wrap"><canvas id="b-canvas" class="st-canvas" width="1600" height="900"></canvas>
                <div class="st-fsctl" id="b-fsctl" hidden>
                    <button type="button" class="st-fsbtn" id="b-fs-tools" aria-pressed="false" title="{{ __('student.notes.board.hide_tools') }}" aria-label="{{ __('student.notes.board.hide_tools') }}"><i class="fa-solid fa-sliders"></i></button>
                    <button type="button" class="st-fsbtn" id="b-fs-exit" title="{{ __('student.notes.board.exit_fullscreen') }}" aria-label="{{ __('student.notes.board.exit_fullscreen') }}"><i class="fa-solid fa-compress"></i></button>
                </div>
            </div>
        </div>
    </section>
</div>

@php
    $stConfig = [
        'labels' => __('student.notes'),
        'data'   => ['courses' => $courses, 'notes' => $notes, 'pages' => $pages],
        'csrf'   => csrf_token(),
        'urls'   => [
            'notes' => route('student.notes.store'), 'note' => route('student.notes.show', 0),
            'pages' => route('student.board.store'), 'page' => route('student.board.show', 0),
        ],
    ];
@endphp
<script type="application/json" id="st-config">@json($stConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@include('student.partials.asset', ['type' => 'js', 'file' => 'notes'])
