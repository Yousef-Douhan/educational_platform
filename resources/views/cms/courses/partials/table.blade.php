@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'status', 'level', 'category_id', 'instructor_id', 'pricing', 'from', 'to']))->filter()->isNotEmpty();
    $tone = ['draft' => 'muted', 'pending_review' => 'warning', 'published' => 'success', 'archived' => 'danger'];
@endphp

@if ($courses->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th class="sortable {{ $sort === 'title_en' ? 'is-sorted' : '' }}" data-sort="title_en">{{ __('courses.table.course') }} <i class="fa-solid {{ $arrow('title_en') }}"></i></th>
                    <th>{{ __('courses.table.category') }}</th>
                    <th>{{ __('courses.table.instructor') }}</th>
                    <th class="sortable {{ $sort === 'price' ? 'is-sorted' : '' }}" data-sort="price">{{ __('courses.table.price') }} <i class="fa-solid {{ $arrow('price') }}"></i></th>
                    <th class="sortable {{ $sort === 'level' ? 'is-sorted' : '' }}" data-sort="level">{{ __('courses.table.level') }} <i class="fa-solid {{ $arrow('level') }}"></i></th>
                    <th class="sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" data-sort="status">{{ __('courses.table.status') }} <i class="fa-solid {{ $arrow('status') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('courses.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('courses.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($courses as $course)
                    <tr data-id="{{ $course->id }}">
                        <td class="text-muted">#{{ $course->id }}</td>
                        <td>
                            <div class="user-cell">
                                @if ($course->image_src)
                                    <img src="{{ $course->image_src }}" class="course-thumb" loading="lazy" alt="">
                                @else
                                    <span class="course-thumb course-thumb--empty"><i class="fa-solid fa-graduation-cap"></i></span>
                                @endif
                                <div>
                                    <div class="user-cell__name" dir="rtl">{{ $course->title_ar }}</div>
                                    <div class="user-cell__email" dir="ltr">{{ $course->title_en }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @if ($course->category)
                                <span class="badge badge--info"><i class="fa-solid fa-folder-tree"></i> {{ $course->category->name }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div>{{ $course->instructor?->name ?? '—' }}</div>
                            @if ($course->instructor?->trashed())
                                <span class="badge badge--danger">{{ __('courses.table.deleted_user') }}</span>
                            @endif
                            @if ($course->co_instructors_count)
                                <span class="badge badge--muted"><i class="fa-solid fa-user-group"></i> +{{ $course->co_instructors_count }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($course->is_free)
                                <span class="badge badge--success">{{ __('courses.free') }}</span>
                            @else
                                <div class="price-cell">
                                    <strong>{{ number_format((float) $course->final_price, 2) }}</strong>
                                    @if ((float) $course->final_price < (float) $course->price)
                                        <s>{{ number_format((float) $course->price, 2) }}</s>
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td><span class="badge badge--primary">{{ __('courses.levels.' . $course->level) }}</span></td>
                        <td><span class="badge badge--{{ $tone[$course->status] ?? 'muted' }}">{{ __('courses.statuses.' . $course->status) }}</span></td>
                        <td class="text-muted">{{ $course->created_at?->format('Y-m-d H:i') }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('courses.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    @can('view', $course)
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $course->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('courses.actions.view') }}</button>
                                    @endcan
                                    @can('update', $course)
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $course->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('courses.actions.edit') }}</button>
                                    @endcan
                                    @can('delete', $course)
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $course->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('courses.actions.delete') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $courses->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-graduation-cap' }}"></i></div>
        <h3>{{ $hasFilters ? __('courses.empty.no_results_title') : __('courses.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('courses.empty.no_results_text') : __('courses.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('courses.actions.reset') }}</button>
        @else
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('courses.actions.add') }}</button>
        @endif
    </div>
@endif