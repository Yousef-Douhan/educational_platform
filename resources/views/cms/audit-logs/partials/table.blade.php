@php
    $sort = request('sort', 'created_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('type')) || filled(request('event')) || filled(request('from')) || filled(request('to'));
    $tone = [
        'created' => 'success', 'updated' => 'info', 'deleted' => 'danger', 'force_deleted' => 'danger',
        'restored' => 'primary', 'login' => 'success', 'logout' => 'muted', 'login_failed' => 'danger',
    ];
    $typeLabel = fn ($t) => $t && \Illuminate\Support\Facades\Lang::has('audit_logs.types.' . $t) ? __('audit_logs.types.' . $t) : ($t ?? '—');
    $eventLabel = fn ($e) => \Illuminate\Support\Facades\Lang::has('audit_logs.events.' . $e) ? __('audit_logs.events.' . $e) : $e;
@endphp

@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('audit_logs.table.when') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th>{{ __('audit_logs.table.user') }}</th>
                    <th class="sortable {{ $sort === 'action' ? 'is-sorted' : '' }}" data-sort="action">{{ __('audit_logs.table.action') }} <i class="fa-solid {{ $arrow('action') }}"></i></th>
                    <th>{{ __('audit_logs.table.subject') }}</th>
                    <th>{{ __('audit_logs.table.ip') }}</th>
                    <th class="col-actions">{{ __('audit_logs.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $row)
                    <tr data-id="{{ $row->id }}">
                        <td class="text-muted" dir="ltr">{{ $row->created_at->format('Y-m-d H:i:s') }}</td>
                        <td>
                            @if ($row->user_name || $row->user_email)
                                <div class="log-text">{{ $row->user_name ?? '—' }}</div>
                                <div class="text-muted small log-text" dir="ltr">{{ $row->user_email }}</div>
                            @else
                                <span class="text-muted">{{ __('audit_logs.table.system') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="log-text">{{ $typeLabel($row->subject_type) }}</div>
                            <span class="badge badge--{{ $tone[$row->event] ?? 'muted' }}">{{ $eventLabel($row->event) }}</span>
                        </td>
                        <td>
                            <div class="log-text">{{ $row->subject_label ?? '—' }}</div>
                            @if ($row->subject_id)
                                <div class="text-muted small" dir="ltr">#{{ $row->subject_id }}</div>
                            @endif
                            @if ($row->description)
                                <div class="text-muted small log-text">{{ \Illuminate\Support\Str::limit($row->description, 80) }}</div>
                            @endif
                        </td>
                        <td class="text-muted" dir="ltr">{{ $row->ip_address ?? '—' }}</td>
                        <td class="col-actions">
                            <button type="button" class="btn btn-light btn-sm" data-action="show" data-id="{{ $row->id }}">
                                <i class="fa-regular fa-eye"></i> {{ __('audit_logs.actions.view') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-shield-halved' }}"></i></div>
        <h3>{{ $hasFilters ? __('audit_logs.empty.no_results_title') : __('audit_logs.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('audit_logs.empty.no_results_text') : __('audit_logs.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('audit_logs.actions.reset') }}</button>
        @endif
    </div>
@endif
