@php
    $sort = request('sort', 'scheduled_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('phase')) || filled(request('course_id'));
    $title = fn ($m) => app()->getLocale() === 'ar' ? $m->title_ar : $m->title_en;
    $tone = ['upcoming' => 'info', 'live' => 'success', 'ended' => 'muted', 'cancelled' => 'danger'];
    $icon = ['upcoming' => 'fa-clock', 'live' => 'fa-tower-broadcast', 'ended' => 'fa-flag-checkered', 'cancelled' => 'fa-ban'];
@endphp

@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('live_sessions.table.session') }}</th>
                    <th>{{ __('live_sessions.table.course') }}</th>
                    <th class="sortable {{ $sort === 'scheduled_at' ? 'is-sorted' : '' }}" data-sort="scheduled_at">{{ __('live_sessions.table.when') }} <i class="fa-solid {{ $arrow('scheduled_at') }}"></i></th>
                    <th class="sortable {{ $sort === 'duration_minutes' ? 'is-sorted' : '' }}" data-sort="duration_minutes">{{ __('live_sessions.table.duration') }} <i class="fa-solid {{ $arrow('duration_minutes') }}"></i></th>
                    <th>{{ __('live_sessions.table.status') }}</th>
                    <th class="col-actions">{{ __('live_sessions.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $row)
                    @php $phase = $row->phase(); @endphp
                    <tr data-id="{{ $row->id }}">
                        <td><strong class="session-title">{{ $title($row) }}</strong></td>
                        <td class="session-title">
                            {{ $row->course ? $title($row->course) : '—' }}
                            @if ($row->course?->trashed())
                                <span class="badge badge--muted">{{ __('live_sessions.table.deleted') }}</span>
                            @endif
                        </td>
                        <td class="text-muted" dir="ltr">
                            <div>{{ $row->scheduled_at->format('Y-m-d H:i') }}</div>
                            <div>→ {{ $row->ends_at->format('H:i') }}</div>
                        </td>
                        <td dir="ltr">{{ $row->duration_minutes }} {{ __('live_sessions.table.min') }}</td>
                        <td><span class="badge badge--{{ $tone[$phase] }}"><i class="fa-solid {{ $icon[$phase] }}"></i> {{ __('live_sessions.phases.' . $phase) }}</span></td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('live_sessions.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $row->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('live_sessions.actions.view') }}</button>
                                    @can('live-sessions.edit')
                                        @if ($row->isOpenForChanges())
                                            <button type="button" role="menuitem" data-action="edit" data-id="{{ $row->id }}">
                                                <i class="fa-regular fa-pen-to-square"></i> {{ __('live_sessions.actions.edit') }}</button>
                                            <button type="button" role="menuitem" data-action="cancel" data-id="{{ $row->id }}">
                                                <i class="fa-solid fa-ban"></i> {{ __('live_sessions.actions.cancel_session') }}</button>
                                        @endif
                                    @endcan
                                    @can('live-sessions.delete')
                                        <hr>
                                        <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $row->id }}">
                                            <i class="fa-regular fa-trash-can"></i> {{ __('live_sessions.actions.delete') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-video' }}"></i></div>
        <h3>{{ $hasFilters ? __('live_sessions.empty.no_results_title') : __('live_sessions.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('live_sessions.empty.no_results_text') : __('live_sessions.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('live_sessions.actions.reset') }}</button>
        @else
            @can('live-sessions.create')
                <button type="button" class="btn btn-primary" data-action="open-create"><i class="fa-solid fa-plus"></i> {{ __('live_sessions.actions.add') }}</button>
            @endcan
        @endif
    </div>
@endif
