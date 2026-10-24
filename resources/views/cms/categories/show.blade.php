<div class="crud-modal" id="showModal" aria-hidden="true" role="dialog" aria-modal="true" aria-labelledby="showModalTitle">
    <div class="crud-modal__dialog">
        <header class="crud-modal__header">
            <div class="crud-modal__icon tone-info"><i class="fa-solid fa-folder-tree"></i></div>
            <div class="crud-modal__heading">
                <h2 id="showModalTitle">{{ __('categories.modal.show_title') }}</h2>
                <p>{{ __('categories.modal.show_desc') }}</p>
            </div>
            <button type="button" class="crud-modal__close" data-modal-close aria-label="{{ __('categories.actions.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="crud-modal__body">
            <div class="profile-hero">
                <div class="avatar avatar--xl avatar--initial"><i data-show-icon class="fa-solid fa-folder"></i></div>
                <div>
                    <h3 data-show="name_ar" dir="rtl">—</h3>
                    <span class="profile-hero__email" data-show="name_en" dir="ltr">—</span>
                </div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-circle-info"></i> {{ __('categories.modal.info_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('categories.fields.slug') }}</dt><dd data-show="slug" dir="ltr">—</dd></div>
                    <div><dt>{{ __('categories.fields.parent') }}</dt><dd data-show="parent_name">—</dd></div>
                    <div class="detail-grid__full"><dt>{{ __('categories.table.children') }}</dt><dd data-show="children_count">—</dd></div>
                </dl>
            </div>

            <div class="detail-section">
                <h4><i class="fa-solid fa-code-branch"></i> {{ __('categories.modal.children_section') }}</h4>
                <div class="cat-chips" data-show-children></div>
            </div>

            <div class="detail-section">
                <h4><i class="fa-regular fa-clock"></i> {{ __('categories.modal.dates_section') }}</h4>
                <dl class="detail-grid">
                    <div><dt>{{ __('categories.table.created_at') }}</dt><dd data-show="created_at">—</dd></div>
                    <div><dt>{{ __('categories.table.updated_at') }}</dt><dd data-show="updated_at">—</dd></div>
                </dl>
            </div>
        </div>

        <footer class="crud-modal__footer">
            <button type="button" class="btn btn-light" data-modal-close>{{ __('categories.actions.close') }}</button>
        </footer>
    </div>
</div>