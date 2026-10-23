@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'guard_name', 'from', 'to']))->filter()->isNotEmpty();
@endphp

@if ($roles->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th class="sortable {{ $sort === 'name' ? 'is-sorted' : '' }}" data-sort="name">{{ __('roles.table.name') }} <i class="fa-solid {{ $arrow('name') }}"></i></th>
                    <th>{{ __('roles.table.guard_name') }}</th>
                    <th class="sortable {{ $sort === 'permissions_count' ? 'is-sorted' : '' }}" data-sort="permissions_count">{{ __('roles.table.permissions') }} <i class="fa-solid {{ $arrow('permissions_count') }}"></i></th>
                    <th>{{ __('roles.table.users') }}</th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('roles.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('roles.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roles as $role)
                    <tr data-id="{{ $role->id }}">
                        <td class="text-muted">#{{ $role->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar avatar--initial"><i class="fa-solid fa-user-shield"></i></span>
                                <div class="user-cell__name">{{ $role->name }}</div>
                            </div>
                        </td>
                        <td><span class="badge badge--info">{{ $role->guard_name }}</span></td>
                        <td>
                            <span class="badge {{ $role->permissions_count ? 'badge--success' : 'badge--muted' }}">
                                <i class="fa-solid fa-key"></i> {{ $role->permissions_count }}
                            </span>
                        </td>
                        <td><span class="badge badge--primary"><i class="fa-solid fa-users"></i> {{ $role->users_count }}</span></td>
                        <td class="text-muted">{{ $role->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('roles.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $role->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('roles.actions.view') }}</button>
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $role->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('roles.actions.edit') }}</button>
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $role->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('roles.actions.delete') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $roles->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-user-shield' }}"></i></div>
        <h3>{{ $hasFilters ? __('roles.empty.no_results_title') : __('roles.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('roles.empty.no_results_text') : __('roles.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('roles.actions.reset') }}</button>
        @else
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('roles.actions.add') }}</button>
        @endif
    </div>
@endif