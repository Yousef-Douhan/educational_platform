@php
    $sort = request('sort', 'id');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col
        ? ($dir === 'asc'
            ? 'fa-arrow-up'
            : 'fa-arrow-down')
        : 'fa-arrows-up-down';
    $hasFilters = filled(request('search'));
@endphp

@if ($templates->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i
                            class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('certificate-templates.table.design') }}</th>
                    <th class="sortable {{ $sort === 'name' ? 'is-sorted' : '' }}" data-sort="name">
                        {{ __('certificate-templates.table.name') }} <i class="fa-solid {{ $arrow('name') }}"></i></th>
                    <th class="sortable {{ $sort === 'certificates_count' ? 'is-sorted' : '' }}"
                        data-sort="certificates_count">{{ __('certificate-templates.table.certificates') }} <i
                            class="fa-solid {{ $arrow('certificates_count') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">
                        {{ __('certificate-templates.table.created_at') }} <i
                            class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('certificate-templates.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr data-id="{{ $template->id }}">
                        <td class="text-muted">#{{ $template->id }}</td>
                        <td><img src="{{ $template->design_url }}" class="tpl-thumb" loading="lazy" alt="">
                        </td>
                        <td>
                            <div class="user-cell__name">{{ $template->name }}</div>
                            @if ($template->is_default)
                                <span class="badge badge--success"><i class="fa-solid fa-star"></i>
                                    {{ __('certificate-templates.table.default') }}</span>
                            @endif
                        </td>
                        <td><span
                                class="badge {{ $template->certificates_count ? 'badge--primary' : 'badge--muted' }}"><i
                                    class="fa-solid fa-award"></i> {{ $template->certificates_count }}</span></td>
                        <td class="text-muted">{{ $template->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('certificate-templates.table.actions') }}"><i
                                        class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show"
                                        data-id="{{ $template->id }}">
                                        <i class="fa-regular fa-eye"></i>
                                        {{ __('certificate-templates.actions.view') }}</button>
                                    <a class="action-link" role="menuitem"
                                        href="{{ route('cms.admin.certificate-templates.preview', $template) }}"
                                        target="_blank" rel="noopener">
                                        <i class="fa-solid fa-file-pdf"></i>
                                        {{ __('certificate-templates.actions.preview') }}</a>
                                    @can('certificate-templates.edit')
                                        <button type="button" role="menuitem" data-action="edit"
                                            data-id="{{ $template->id }}">
                                            <i class="fa-regular fa-pen-to-square"></i>
                                            {{ __('certificate-templates.actions.edit') }}</button>
                                    @endcan
                                    @can('certificate-templates.delete')
                                        @unless ($template->is_default)
                                            <hr>
                                            <button type="button" role="menuitem" class="is-danger" data-action="delete"
                                                data-id="{{ $template->id }}">
                                                <i class="fa-regular fa-trash-can"></i>
                                                {{ __('certificate-templates.actions.delete') }}</button>
                                        @endunless
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $templates->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-certificate' }}"></i>
        </div>
        <h3>{{ $hasFilters ? __('certificate-templates.empty.no_results_title') : __('certificate-templates.empty.title') }}
        </h3>
        <p>{{ $hasFilters ? __('certificate-templates.empty.no_results_text') : __('certificate-templates.empty.text') }}
        </p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i
                    class="fa-solid fa-rotate-left"></i> {{ __('certificate-templates.actions.reset') }}</button>
        @else
            @can('certificate-templates.create')
                <button type="button" class="btn btn-primary" data-action="open-create"><i class="fa-solid fa-plus"></i>
                    {{ __('certificate-templates.actions.add') }}</button>
            @endcan
        @endif
    </div>
@endif
