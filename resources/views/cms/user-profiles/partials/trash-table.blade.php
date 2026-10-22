@if ($profiles->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('user-profiles.table.user') }}</th>
                    <th>{{ __('user-profiles.table.phone') }}</th>
                    <th>{{ __('user-profiles.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('user-profiles.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($profiles as $profile)
                    <tr data-id="{{ $profile->id }}">
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
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $profile->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $profile->id }}">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('user-profiles.actions.restore') }}
                                </button>
                                <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $profile->id }}">
                                    <i class="fa-solid fa-skull-crossbones"></i> {{ __('user-profiles.actions.force_delete') }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $profiles->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('user-profiles.empty.trash_title') }}</h3>
        <p>{{ __('user-profiles.empty.trash_text') }}</p>
    </div>
@endif