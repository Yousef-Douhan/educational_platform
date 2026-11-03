@if ($records->count())
    <div class="table-wrap">
        <table class="crud-table note-list">
            <tbody>
                @foreach ($records as $row)
                    @php $view = \App\Services\NotificationPresenter::present($row); @endphp
                    <tr data-id="{{ $row->id }}" class="{{ $row->read_at ? '' : 'is-unread' }}">
                        <td class="note-icon"><span class="note-dot tone-bg--{{ $view['tone'] }}"><i class="fa-solid {{ $view['icon'] }}"></i></span></td>
                        <td>
                            <div class="note-title">{{ $view['title'] }}
                                @unless ($row->read_at)<span class="badge badge--warning">{{ __('notifications.mine.new') }}</span>@endunless
                            </div>
                            <div class="note-text">{{ $view['body'] }}</div>
                            <div class="text-muted small" dir="ltr">{{ $row->created_at->format('Y-m-d H:i') }}</div>
                        </td>
                        <td class="col-actions">
                            @unless ($row->read_at)
                                <button type="button" class="btn btn-light btn-sm" data-action="read" data-id="{{ $row->id }}">
                                    <i class="fa-solid fa-check"></i> {{ __('notifications.mine.mark_read') }}</button>
                            @endunless
                            <button type="button" class="btn btn-light btn-sm" data-action="delete" data-id="{{ $row->id }}">
                                <i class="fa-regular fa-trash-can"></i> {{ __('notifications.mine.delete') }}</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $records->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid fa-bell-slash"></i></div>
        <h3>{{ __('notifications.mine.empty_title') }}</h3>
        <p>{{ __('notifications.mine.empty_text') }}</p>
    </div>
@endif
