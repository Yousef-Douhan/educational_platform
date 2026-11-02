@if ($templates->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('certificate-templates.table.design') }}</th>
                    <th>{{ __('certificate-templates.table.name') }}</th>
                    <th>{{ __('certificate-templates.table.certificates') }}</th>
                    <th>{{ __('certificate-templates.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('certificate-templates.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($templates as $template)
                    <tr data-id="{{ $template->id }}">
                        <td><img src="{{ $template->design_url }}" class="tpl-thumb" loading="lazy" alt=""></td>
                        <td class="user-cell__name">{{ $template->name }}</td>
                        <td><span class="badge badge--muted">{{ $template->certificates_count }}</span></td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $template->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('certificate-templates.edit')
                                <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $template->id }}">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('certificate-templates.actions.restore') }}</button>
                                @endcan
                                @can('certificate-templates.delete')
                                <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $template->id }}">
                                    <i class="fa-solid fa-skull-crossbones"></i> {{ __('certificate-templates.actions.force_delete') }}</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $templates->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('certificate-templates.empty.trash_title') }}</h3>
        <p>{{ __('certificate-templates.empty.trash_text') }}</p>
    </div>
@endif