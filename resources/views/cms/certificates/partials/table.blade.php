@php
    $sort = request('sort', 'id');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col
        ? ($dir === 'asc'
            ? 'fa-arrow-up'
            : 'fa-arrow-down')
        : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'status', 'course_id', 'from', 'to']))->filter()->isNotEmpty();
@endphp

@if ($certificates->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'certificate_code' ? 'is-sorted' : '' }}"
                        data-sort="certificate_code">{{ __('certificates.table.code') }} <i
                            class="fa-solid {{ $arrow('certificate_code') }}"></i></th>
                    <th class="sortable {{ $sort === 'student_name' ? 'is-sorted' : '' }}" data-sort="student_name">
                        {{ __('certificates.table.student') }} <i class="fa-solid {{ $arrow('student_name') }}"></i></th>
                    <th>{{ __('certificates.table.course') }}</th>
                    <th>{{ __('certificates.table.template') }}</th>
                    <th>{{ __('certificates.table.status') }}</th>
                    <th class="sortable {{ $sort === 'issued_at' ? 'is-sorted' : '' }}" data-sort="issued_at">
                        {{ __('certificates.table.issued_at') }} <i class="fa-solid {{ $arrow('issued_at') }}"></i>
                    </th>
                    <th class="col-actions">{{ __('certificates.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($certificates as $c)
                    <tr data-id="{{ $c->id }}">
                        <td><code class="cert-code" dir="ltr">{{ $c->certificate_code }}</code></td>
                        <td>
                            <div class="user-cell__name">{{ $c->student_name }}</div>
                            <div class="user-cell__email" dir="ltr">{{ $c->user?->email }}</div>
                            @if ($c->user?->trashed())
                                <span class="badge badge--danger">{{ __('certificates.table.deleted_user') }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="user-cell__name" dir="rtl">{{ $c->course_title_ar }}</div>
                            <div class="user-cell__email" dir="ltr">{{ $c->course_title_en }}</div>
                        </td>
                        <td class="text-muted">{{ $c->template?->name ?? __('certificates.table.default_design') }}
                        </td>
                        <td>
                            @if ($c->is_revoked)
                                <span class="badge badge--danger"><i class="fa-solid fa-ban"></i>
                                    {{ __('certificates.statuses.revoked') }}</span>
                            @else
                                <span class="badge badge--success"><i class="fa-solid fa-circle-check"></i>
                                    {{ __('certificates.statuses.valid') }}</span>
                            @endif
                        </td>
                        <td class="text-muted">{{ $c->issued_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('certificates.table.actions') }}"><i
                                        class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    @can('view', $c)
                                        <button type="button" role="menuitem" data-action="show"
                                            data-id="{{ $c->id }}">
                                            <i class="fa-regular fa-eye"></i>
                                            {{ __('certificates.actions.view') }}</button>
                                        <a class="action-link" role="menuitem"
                                            href="{{ route('cms.admin.certificates.download', $c) }}">
                                            <i class="fa-solid fa-file-pdf"></i>
                                            {{ __('certificates.actions.download') }}</a>
                                        <a class="action-link" role="menuitem"
                                            href="{{ route('cms.admin.certificates.download', [$c, 'lang' => 'ar']) }}">
                                            <i class="fa-solid fa-language"></i>
                                            {{ __('certificates.actions.download_ar') }}</a>
                                        <a class="action-link" role="menuitem"
                                            href="{{ route('cms.admin.certificates.download', [$c, 'lang' => 'en']) }}">
                                            <i class="fa-solid fa-language"></i>
                                            {{ __('certificates.actions.download_en') }}</a>
                                    @endcan
                                    @can('update', $c)
                                        <hr>
                                        @if ($c->is_revoked)
                                            <button type="button" role="menuitem" data-action="reinstate"
                                                data-id="{{ $c->id }}">
                                                <i class="fa-solid fa-rotate-left"></i>
                                                {{ __('certificates.actions.reinstate') }}</button>
                                        @else
                                            <button type="button" role="menuitem" class="is-danger" data-action="revoke"
                                                data-id="{{ $c->id }}">
                                                <i class="fa-solid fa-ban"></i>
                                                {{ __('certificates.actions.revoke') }}</button>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $certificates->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-award' }}"></i></div>
        <h3>{{ $hasFilters ? __('certificates.empty.no_results_title') : __('certificates.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('certificates.empty.no_results_text') : __('certificates.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i
                    class="fa-solid fa-rotate-left"></i> {{ __('certificates.actions.reset') }}</button>
        @elsecan('create', \App\Models\Certificate::class)
            <button type="button" class="btn btn-primary" data-action="open-create"><i class="fa-solid fa-plus"></i>
                {{ __('certificates.actions.issue') }}</button>
        @endif
    </div>
@endif
