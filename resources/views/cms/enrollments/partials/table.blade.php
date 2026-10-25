@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'status', 'course_id', 'from', 'to']))->filter()->isNotEmpty();
    $tone = ['active' => 'success', 'completed' => 'primary', 'expired' => 'muted', 'cancelled' => 'danger'];
@endphp

@if ($enrollments->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('enrollments.table.student') }}</th>
                    <th>{{ __('enrollments.table.course') }}</th>
                    <th class="sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" data-sort="status">{{ __('enrollments.table.status') }} <i class="fa-solid {{ $arrow('status') }}"></i></th>
                    <th>{{ __('enrollments.table.access') }}</th>
                    <th class="sortable {{ $sort === 'enrolled_at' ? 'is-sorted' : '' }}" data-sort="enrolled_at">{{ __('enrollments.table.enrolled_at') }} <i class="fa-solid {{ $arrow('enrolled_at') }}"></i></th>
                    <th class="sortable {{ $sort === 'expires_at' ? 'is-sorted' : '' }}" data-sort="expires_at">{{ __('enrollments.table.expires_at') }} <i class="fa-solid {{ $arrow('expires_at') }}"></i></th>
                    <th class="col-actions">{{ __('enrollments.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($enrollments as $e)
                    <tr data-id="{{ $e->id }}">
                        <td class="text-muted">#{{ $e->id }}</td>
                        <td>
                            <div class="user-cell">
                                <span class="avatar avatar--initial">{{ mb_strtoupper(mb_substr($e->user?->name ?? '?', 0, 1)) }}</span>
                                <div>
                                    <div class="user-cell__name">{{ $e->user?->name ?? '—' }}</div>
                                    <div class="user-cell__email" dir="ltr">{{ $e->user?->email }}</div>
                                    @if ($e->user?->trashed())
                                        <span class="badge badge--danger">{{ __('enrollments.table.deleted_user') }}</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell__name" dir="rtl">{{ $e->course?->title_ar }}</div>
                            <div class="user-cell__email" dir="ltr">{{ $e->course?->title_en }}</div>
                            @if ($e->course?->trashed())
                                <span class="badge badge--danger">{{ __('enrollments.table.deleted_course') }}</span>
                            @endif
                        </td>
                        <td><span class="badge badge--{{ $tone[$e->status] ?? 'muted' }}">{{ __('enrollments.statuses.' . $e->status) }}</span></td>
                        <td>
                            @if ($e->has_access)
                                <span class="badge badge--success"><i class="fa-solid fa-lock-open"></i> {{ __('enrollments.table.access_yes') }}</span>
                            @else
                                <span class="badge badge--muted"><i class="fa-solid fa-lock"></i> {{ __('enrollments.table.access_no') }}</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $e->enrolled_at?->format('Y-m-d H:i') }}</td>
                        <td>
                            @if ($e->expires_at)
                                <span class="text-muted">{{ $e->expires_at->format('Y-m-d H:i') }}</span>
                                @if ($e->status === 'active' && $e->expires_at->isPast())
                                    <span class="badge badge--danger">{{ __('enrollments.table.overdue') }}</span>
                                @endif
                            @else
                                <span class="badge badge--muted">{{ __('enrollments.table.no_expiry') }}</span>
                            @endif
                        </td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('enrollments.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    @can('view', $e)
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $e->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('enrollments.actions.view') }}</button>
                                    @endcan
                                    @can('update', $e)
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $e->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('enrollments.actions.edit') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $enrollments->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-user-graduate' }}"></i></div>
        <h3>{{ $hasFilters ? __('enrollments.empty.no_results_title') : __('enrollments.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('enrollments.empty.no_results_text') : __('enrollments.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('enrollments.actions.reset') }}</button>
        @elsecan('create', \App\Models\Enrollment::class)
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('enrollments.actions.add') }}</button>
        @endif
    </div>
@endif