@php
    $sort = request('sort', 'id');
    $dir  = request('dir', 'desc');
    $arrow = fn (string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = collect(request()->only(['search', 'course_id', 'readiness']))->filter()->isNotEmpty();
@endphp

@if ($quizzes->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id"># <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th class="sortable {{ $sort === 'title_en' ? 'is-sorted' : '' }}" data-sort="title_en">{{ __('quizzes.table.quiz') }} <i class="fa-solid {{ $arrow('title_en') }}"></i></th>
                    <th>{{ __('quizzes.table.course_lesson') }}</th>
                    <th class="sortable {{ $sort === 'questions_count' ? 'is-sorted' : '' }}" data-sort="questions_count">{{ __('quizzes.table.questions') }} <i class="fa-solid {{ $arrow('questions_count') }}"></i></th>
                    <th class="sortable {{ $sort === 'pass_mark' ? 'is-sorted' : '' }}" data-sort="pass_mark">{{ __('quizzes.table.pass_mark') }} <i class="fa-solid {{ $arrow('pass_mark') }}"></i></th>
                    <th>{{ __('quizzes.table.time_limit') }}</th>
                    <th>{{ __('quizzes.table.attempts') }}</th>
                    <th>{{ __('quizzes.table.status') }}</th>
                    <th class="col-actions">{{ __('quizzes.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quizzes as $quiz)
                    @php
                        $lesson = $quiz->lesson;
                        $course = $lesson?->section?->course;
                    @endphp
                    <tr data-id="{{ $quiz->id }}">
                        <td class="text-muted">#{{ $quiz->id }}</td>
                        <td>
                            <div class="user-cell__name" dir="rtl">{{ $quiz->title_ar }}</div>
                            <div class="user-cell__email" dir="ltr">{{ $quiz->title_en }}</div>
                        </td>
                        <td>
                            <div class="user-cell__name">{{ $course?->title_ar }}
                                @if ($course?->trashed())<span class="badge badge--danger">{{ __('quizzes.table.deleted_course') }}</span>@endif
                            </div>
                            <div class="user-cell__email">{{ $lesson?->title_ar }}
                                @if ($lesson?->trashed())<span class="badge badge--danger">{{ __('quizzes.table.deleted_lesson') }}</span>@endif
                            </div>
                        </td>
                        <td>
                            <span class="badge badge--primary"><i class="fa-solid fa-circle-question"></i> {{ $quiz->questions_count }}</span>
                            <span class="badge badge--muted">{{ (int) $quiz->total_points }} {{ __('quizzes.table.points_short') }}</span>
                        </td>
                        <td><span class="badge badge--info">{{ $quiz->pass_mark }}%</span></td>
                        <td class="text-muted">{{ $quiz->time_limit_minutes > 0 ? $quiz->time_limit_minutes . ' ' . __('quizzes.table.minutes_short') : __('quizzes.table.unlimited') }}</td>
                        <td class="text-muted">{{ $quiz->attempts_allowed > 0 ? $quiz->attempts_allowed : __('quizzes.table.unlimited') }}</td>
                        <td>
                            @if ($quiz->questions_count > 0)
                                <span class="badge badge--success">{{ __('quizzes.table.ready') }}</span>
                            @else
                                <span class="badge badge--muted">{{ __('quizzes.table.not_ready') }}</span>
                            @endif
                        </td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                        aria-label="{{ __('quizzes.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    @can('view', $quiz)
                                    <button type="button" role="menuitem" data-action="questions" data-id="{{ $quiz->id }}">
                                        <i class="fa-solid fa-list-check"></i> {{ __('quizzes.actions.questions') }}</button>
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $quiz->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('quizzes.actions.view') }}</button>
                                    @endcan
                                    @can('update', $quiz)
                                    <button type="button" role="menuitem" data-action="edit" data-id="{{ $quiz->id }}">
                                        <i class="fa-regular fa-pen-to-square"></i> {{ __('quizzes.actions.edit') }}</button>
                                    @endcan
                                    @can('delete', $quiz)
                                    <hr>
                                    <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $quiz->id }}">
                                        <i class="fa-regular fa-trash-can"></i> {{ __('quizzes.actions.delete') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $quizzes->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-circle-question' }}"></i></div>
        <h3>{{ $hasFilters ? __('quizzes.empty.no_results_title') : __('quizzes.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('quizzes.empty.no_results_text') : __('quizzes.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters">
                <i class="fa-solid fa-rotate-left"></i> {{ __('quizzes.actions.reset') }}</button>
        @elsecan('create', \App\Models\Quiz::class)
            <button type="button" class="btn btn-primary" data-action="open-create">
                <i class="fa-solid fa-plus"></i> {{ __('quizzes.actions.add') }}</button>
        @endif
    </div>
@endif