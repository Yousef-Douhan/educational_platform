<header class="page-header">
    <div class="page-header__main">
        <div class="page-header__icon"><i class="fa-solid {{ $icon }}"></i></div>
        <div>
            <ol class="breadcrumb-nav">
                <li>{{ $home }}</li>
                <li>{{ $group }}</li>
                <li aria-current="page">{{ $title }}</li>
            </ol>
            <h1>{{ $title }}</h1>
            <p>{{ $subtitle }}</p>
        </div>
    </div>
    <div class="page-header__actions">
        @isset($trashLabel)
            <button type="button" class="btn btn-light" data-action="open-trash">
                <i class="fa-solid fa-trash-can-arrow-up"></i> {{ $trashLabel }}
            </button>
        @endisset
        @if ($canAdd ?? true)
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ $addLabel }}
            </button>
        @endif
    </div>
</header>