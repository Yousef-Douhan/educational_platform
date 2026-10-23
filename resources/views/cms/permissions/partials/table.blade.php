@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'guard_name', 'role_id', 'from', 'to']))->filter()->isNotEmpty();
@endphp

@if ($permissions->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th class="sortable {{ $sort === 'name' ? 'is-sorted' : '' }}" data-sort="name">{{ __('permissions.table.name') }} <i class="fa-solid {{ $arrow('name') }}"></i></th>
                    <th>{{ __('permissions.table.guard_name') }}</th>
                    <th class="sortable {{ $sort === 'roles_count' ? 'is-sorted' : '' }}" data-sort="roles_count">{{ __('permissions.table.roles') }} <i class="fa-solid {{ $arrow('roles_count') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('permissions.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('permissions.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($permissions as $permission)
                    <tr data-id="{{ $permission->id }}">
                        <td class="text-muted">#{{ $permission->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar avatar--initial"><i class="fa-solid fa-key"></i></span>
                                <code class="perm-code" dir="ltr">{{ $permission->name }}</code>
                            </div>
                        </td>
                        <td><span class="badge badge--info">{{ $permission->guard_name }}</span></td>
                        <td>
                            <span class="badge {{ $permission->roles_count ? 'badge--success' : 'badge--muted' }}">
                                <i class="fa-solid fa-user-shield"></i> {{ $permission->roles_count }}
                            </span>
                        </td>
                        <td class="text-muted">{{ $permission->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('permissions.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $permission->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('permissions.actions.view') }}</button>
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $permission->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('permissions.actions.edit') }}</button>
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $permission->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('permissions.actions.delete') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $permissions->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-key' }}"></i></div>
        <h3>{{ $hasFilters ? __('permissions.empty.no_results_title') : __('permissions.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('permissions.empty.no_results_text') : __('permissions.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('permissions.actions.reset') }}</button>
        @else
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('permissions.actions.add') }}</button>
        @endif
    </div>
@endif