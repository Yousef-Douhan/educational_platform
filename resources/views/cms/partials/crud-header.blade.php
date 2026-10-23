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
        <button type="button" class="btn btn-primary" data-action="open-create">
            <i class="fa-solid fa-plus"></i> {{ $addLabel }}
        </button>
    </div>
</header>