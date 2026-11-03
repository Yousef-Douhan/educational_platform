@php $title = fn ($m) => app()->getLocale() === 'ar' ? $m->title_ar : $m->title_en; @endphp

@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('live_sessions.table.session') }}</th>
                    <th>{{ __('live_sessions.table.course') }}</th>
                    <th>{{ __('live_sessions.table.when') }}</th>
                    <th>{{ __('live_sessions.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('live_sessions.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $row)
                    <tr data-id="{{ $row->id }}">
                        <td><strong class="session-title">{{ $title($row) }}</strong></td>
                        <td class="session-title">{{ $row->course ? $title($row->course) : '—' }}</td>
                        <td class="text-muted" dir="ltr">{{ $row->scheduled_at->format('Y-m-d H:i') }}</td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $row->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('live-sessions.edit')
                                    <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $row->id }}">
                                        <i class="fa-solid fa-rotate-left"></i> {{ __('live_sessions.actions.restore') }}</button>
                                @endcan
                                @can('live-sessions.delete')
                                    <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $row->id }}">
                                        <i class="fa-solid fa-skull-crossbones"></i> {{ __('live_sessions.actions.force_delete') }}</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('live_sessions.empty.trash_title') }}</h3>
        <p>{{ __('live_sessions.empty.trash_text') }}</p>
    </div>
@endif
