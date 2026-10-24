@if ($categories->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('categories.table.category') }}</th>
                    <th>{{ __('categories.table.slug') }}</th>
                    <th>{{ __('categories.table.parent') }}</th>
                    <th>{{ __('categories.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('categories.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr data-id="{{ $category->id }}">
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
                                <span class="badge {{ $category->parent->trashed() ? 'badge--danger' : 'badge--info' }}">
                                    <i class="fa-solid fa-sitemap"></i> {{ $category->parent->name }}
                                </span>
                            @else
                                <span class="badge badge--muted">{{ __('categories.table.root') }}</span>
                            @endif
                        </td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $category->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('categories.edit')
                                <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $category->id }}">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('categories.actions.restore') }}
                                </button>
                                @endcan
                                @can('categories.delete')
                                <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $category->id }}">
                                    <i class="fa-solid fa-skull-crossbones"></i> {{ __('categories.actions.force_delete') }}
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $categories->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('categories.empty.trash_title') }}</h3>
        <p>{{ __('categories.empty.trash_text') }}</p>
    </div>
@endif