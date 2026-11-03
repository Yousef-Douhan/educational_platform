@php
    $sort = request('sort', 'created_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('kind')) || filled(request('state')) || filled(request('from')) || filled(request('to'));
@endphp

@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('notifications.table.when') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th>{{ __('notifications.table.recipient') }}</th>
                    <th>{{ __('notifications.table.notification') }}</th>
                    <th class="sortable {{ $sort === 'read_at' ? 'is-sorted' : '' }}" data-sort="read_at">{{ __('notifications.table.state') }} <i class="fa-solid {{ $arrow('read_at') }}"></i></th>
                    <th class="col-actions">{{ __('notifications.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $row)
                    @php $view = \App\Services\NotificationPresenter::present($row); @endphp
                    <tr data-id="{{ $row->id }}">
                        <td class="text-muted" dir="ltr">{{ $row->created_at->format('Y-m-d H:i') }}</td>
                        <td>
                            @if ($row->notifiable)
                                <div class="note-text">{{ $row->notifiable->name }}</div>
                                <div class="text-muted small note-text" dir="ltr">{{ $row->notifiable->email }}</div>
                                @if (method_exists($row->notifiable, 'trashed') && $row->notifiable->trashed())
                                    <span class="badge badge--muted">{{ __('notifications.ui.deleted_user') }}</span>
                                @endif
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="note-title"><i class="fa-solid {{ $view['icon'] }} tone-text--{{ $view['tone'] }}"></i> {{ $view['title'] }}</div>
                            <div class="text-muted small note-text">{{ \Illuminate\Support\Str::limit($view['body'], 110) }}</div>
                        </td>
                        <td>
                            @if ($row->read_at)
                                <span class="badge badge--muted">{{ __('notifications.states.read') }}</span>
                            @else
                                <span class="badge badge--warning">{{ __('notifications.states.unread') }}</span>
                            @endif
                        </td>
                        <td class="col-actions">
                            <button type="button" class="btn btn-light btn-sm" data-action="show" data-id="{{ $row->id }}">
                                <i class="fa-regular fa-eye"></i> {{ __('notifications.actions.view') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-bell-slash' }}"></i></div>
        <h3>{{ $hasFilters ? __('notifications.empty.no_results_title') : __('notifications.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('notifications.empty.no_results_text') : __('notifications.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('notifications.actions.reset') }}</button>
        @endif
    </div>
@endif
