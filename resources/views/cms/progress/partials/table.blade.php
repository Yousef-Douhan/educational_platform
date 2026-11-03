@php
    $sort = request('sort', 'updated_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('state')) || filled(request('course_id'));
    $title = fn ($c) => app()->getLocale() === 'ar' ? $c->title_ar : $c->title_en;
@endphp

@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id">{{ __('progress.table.id') }} <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('progress.table.student') }}</th>
                    <th>{{ __('progress.table.course') }}</th>
                    <th class="sortable {{ $sort === 'progress_percentage' ? 'is-sorted' : '' }}" data-sort="progress_percentage">{{ __('progress.table.progress') }} <i class="fa-solid {{ $arrow('progress_percentage') }}"></i></th>
                    <th>{{ __('progress.table.state') }}</th>
                    <th class="sortable {{ $sort === 'updated_at' ? 'is-sorted' : '' }}" data-sort="updated_at">{{ __('progress.table.updated_at') }} <i class="fa-solid {{ $arrow('updated_at') }}"></i></th>
                    <th class="col-actions">{{ __('progress.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $row)
                    @php $pct = (float) $row->progress_percentage; @endphp
                    <tr data-id="{{ $row->id }}">
                        <td dir="ltr">#{{ $row->id }}</td>
                        <td>
                            <div class="user-cell__name">{{ $row->user->name }}
                                @if ($row->user->trashed())
                                    <span class="badge badge--danger">{{ __('progress.table.deleted') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small" dir="ltr">{{ $row->user->email }}</div>
                        </td>
                        <td>
                            <div class="user-cell__name">{{ $title($row->course) }}
                                @if ($row->course->trashed())
                                    <span class="badge badge--danger">{{ __('progress.table.deleted') }}</span>
                                @endif
                            </div>
                        </td>
                        <td>
                            <div class="progress-cell" dir="ltr">
                                <div class="progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}">
                                    <span style="width: {{ min(100, max(0, $pct)) }}%"></span>
                                </div>
                                <small>{{ rtrim(rtrim(number_format($pct, 2), '0'), '.') }}% · {{ $row->completed_lessons_count }}/{{ $row->total_lessons_count }}</small>
                            </div>
                        </td>
                        <td>
                            @if ($row->is_finished)
                                <span class="badge badge--success"><i class="fa-solid fa-circle-check"></i> {{ __('progress.states.finished') }}</span>
                            @else
                                <span class="badge badge--info"><i class="fa-solid fa-hourglass-half"></i> {{ __('progress.states.in_progress') }}</span>
                            @endif
                        </td>
                        <td class="text-muted" title="{{ $row->updated_at->format('Y-m-d H:i') }}">{{ $row->updated_at->diffForHumans() }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('progress.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $row->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('progress.actions.view') }}</button>
                                    @can('progress.recalculate')
                                        <button type="button" role="menuitem" data-action="recalculate" data-id="{{ $row->id }}">
                                            <i class="fa-solid fa-arrows-rotate"></i> {{ __('progress.actions.recalculate') }}</button>
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
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-chart-line' }}"></i></div>
        <h3>{{ $hasFilters ? __('progress.empty.no_results_title') : __('progress.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('progress.empty.no_results_text') : __('progress.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('progress.actions.reset') }}</button>
        @endif
    </div>
@endif
