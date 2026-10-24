@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'type', 'parent_id', 'from', 'to']))->filter()->isNotEmpty();
@endphp

@if ($categories->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th class="sortable {{ $sort === 'name_en' ? 'is-sorted' : '' }}" data-sort="name_en">{{ __('categories.table.category') }} <i class="fa-solid {{ $arrow('name_en') }}"></i></th>
                    <th class="sortable {{ $sort === 'slug' ? 'is-sorted' : '' }}" data-sort="slug">{{ __('categories.table.slug') }} <i class="fa-solid {{ $arrow('slug') }}"></i></th>
                    <th>{{ __('categories.table.parent') }}</th>
                    <th class="sortable {{ $sort === 'children_count' ? 'is-sorted' : '' }}" data-sort="children_count">{{ __('categories.table.children') }} <i class="fa-solid {{ $arrow('children_count') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('categories.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('categories.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr data-id="{{ $category->id }}">
                        <td class="text-muted">#{{ $category->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar avatar--initial"><i class="{{ $category->icon ?: 'fa-solid fa-folder' }}"></i></span>
                                <div>
                                    <div class="user-cell__name" dir="rtl">{{ $category->name_ar }}</div>
                                    <div class="user-cell__email" dir="ltr">{{ $category->name_en }}</div>
                                </div>
                            </div>
                        </td>
                        <td><code class="slug-code">{{ $category->slug }}</code></td>
                        <td>
                            @if ($category->parent)
                                <span class="badge badge--info"><i class="fa-solid fa-sitemap"></i> {{ $category->parent->name }}</span>
                            @else
                                <span class="badge badge--muted">{{ __('categories.table.root') }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $category->children_count ? 'badge--primary' : 'badge--muted' }}">
                                <i class="fa-solid fa-code-branch"></i> {{ $category->children_count }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $category->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('categories.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    @can('categories.view')
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $category->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('categories.actions.view') }}</button>
                                    @endcan
                                    @can('categories.edit')
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $category->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('categories.actions.edit') }}</button>
                                    @endcan
                                    @can('categories.delete')
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $category->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('categories.actions.delete') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $categories->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-folder-tree' }}"></i></div>
        <h3>{{ $hasFilters ? __('categories.empty.no_results_title') : __('categories.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('categories.empty.no_results_text') : __('categories.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('categories.actions.reset') }}</button>
        @else
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('categories.actions.add') }}</button>
        @endif
    </div>
@endif