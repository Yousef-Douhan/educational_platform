@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'user_id', 'from', 'to']))->filter()->isNotEmpty();
@endphp

@if ($profiles->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id">#
                        <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('user-profiles.table.user') }}</th>
                    <th class="sortable {{ $sort === 'phone' ? 'is-sorted' : '' }}" data-sort="phone">
                        {{ __('user-profiles.table.phone') }} <i class="fa-solid {{ $arrow('phone') }}"></i></th>
                    <th>{{ __('user-profiles.table.website') }}</th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">
                        {{ __('user-profiles.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="sortable {{ $sort === 'updated_at' ? 'is-sorted' : '' }}" data-sort="updated_at">
                        {{ __('user-profiles.table.updated_at') }} <i class="fa-solid {{ $arrow('updated_at') }}"></i></th>
                    <th class="col-actions">{{ __('user-profiles.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($profiles as $profile)
                    <tr data-id="{{ $profile->id }}">
                        <td class="text-muted">#{{ $profile->id }}</td>
                        <td>
                            <div class="user-cell">
                                @if ($profile->avatar_url)
                                    <img src="{{ $profile->avatar_url }}" alt="" class="avatar" loading="lazy">
                                @else
                                    <span class="avatar avatar--initial">{{ mb_strtoupper(mb_substr($profile->user?->name ?? '?', 0, 1)) }}</span>
                                @endif
                                <div>
                                    <div class="user-cell__name">{{ $profile->user?->name ?? '—' }}</div>
                                    <div class="user-cell__email">{{ $profile->user?->email ?? '—' }}</div>
                                </div>
                            </div>
                        </td>
                        <td dir="ltr" class="text-start">{{ $profile->phone ?: '—' }}</td>
                        <td>
                            @if ($profile->website)
                                <a href="{{ $profile->website }}" target="_blank" rel="noopener noreferrer" class="badge badge--info">
                                    <i class="fa-solid fa-globe"></i> {{ parse_url($profile->website, PHP_URL_HOST) ?: $profile->website }}
                                </a>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $profile->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-muted">{{ $profile->updated_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle
                                        aria-haspopup="menu" aria-label="{{ __('user-profiles.table.actions') }}">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $profile->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('user-profiles.actions.view') }}
                                    </button>
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $profile->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('user-profiles.actions.edit') }}
                                    </button>
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $profile->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('user-profiles.actions.delete') }}
                                    </button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $profiles->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-regular {{ $hasFilters ? 'fa-face-frown' : 'fa-address-card' }}"></i></div>
        <h3>{{ $hasFilters ? __('user-profiles.empty.no_results_title') : __('user-profiles.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('user-profiles.empty.no_results_text') : __('user-profiles.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('user-profiles.actions.reset') }}
            </button>
        @else
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('user-profiles.actions.add') }}
            </button>
        @endif
    </div>
@endif