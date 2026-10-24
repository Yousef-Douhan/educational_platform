@if ($courses->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('courses.table.course') }}</th>
                    <th>{{ __('courses.table.category') }}</th>
                    <th>{{ __('courses.table.instructor') }}</th>
                    <th>{{ __('courses.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('courses.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($courses as $course)
                    <tr data-id="{{ $course->id }}">
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
                                <span class="badge {{ $course->category->trashed() ? 'badge--danger' : 'badge--info' }}">
                                    <i class="fa-solid fa-folder-tree"></i> {{ $course->category->name }}
                                </span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            {{ $course->instructor?->name ?? '—' }}
                            @if ($course->instructor?->trashed())
                                <span class="badge badge--danger">{{ __('courses.table.deleted_user') }}</span>
                            @endif
                        </td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $course->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('restore', $course)
                                <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $course->id }}">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('courses.actions.restore') }}
                                </button>
                                @endcan
                                @can('forceDelete', $course)
                                <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $course->id }}">
                                    <i class="fa-solid fa-skull-crossbones"></i> {{ __('courses.actions.force_delete') }}
                                </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $courses->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('courses.empty.trash_title') }}</h3>
        <p>{{ __('courses.empty.trash_text') }}</p>
    </div>
@endif