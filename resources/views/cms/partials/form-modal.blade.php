<div class="crud-modal {{ $modalClass ?? '' }}" id="{{ $modalId }}" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="{{ $modalId }}Title">    <form class="crud-modal__dialog {{ ($size ?? '') === 'lg' ? 'crud-modal__dialog--lg' : '' }}" id="{{ $formId }}" novalidate>
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-{{ $tone }}"><i class="fa-solid {{ $icon }}"></i></div>
            <div class="crud-modal__heading">
                <h2 id="{{ $modalId }}Title">{{ $title }}</h2>
                <p>{{ $desc }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ $closeLabel }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            @include($fields, ['prefix' => $prefix])
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ $cancelLabel }}</button>
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid {{ $submitIcon }} btn-icon"></i><span class="spinner"></span>
                {{ $submitLabel }}
            </button>
        </footer>
    </form>
</div>