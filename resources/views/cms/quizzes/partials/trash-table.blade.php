@if ($quizzes->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('quizzes.table.quiz') }}</th>
                    <th>{{ __('quizzes.table.course_lesson') }}</th>
                    <th>{{ __('quizzes.table.questions') }}</th>
                    <th>{{ __('quizzes.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('quizzes.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quizzes as $quiz)
                    @php $course = $quiz->lesson?->section?->course; @endphp
                    <tr data-id="{{ $quiz->id }}">
                        <td>
                            <div class="user-cell__name" dir="rtl">{{ $quiz->title_ar }}</div>
                            <div class="user-cell__email" dir="ltr">{{ $quiz->title_en }}</div>
                        </td>
                        <td>
                            <div class="user-cell__name">{{ $course?->title_ar }}</div>
                            <div class="user-cell__email">{{ $quiz->lesson?->title_ar }}</div>
                        </td>
                        <td><span class="badge badge--primary">{{ $quiz->questions_count }}</span></td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $quiz->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('restore', $quiz)
                                <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $quiz->id }}">
                                    <i class="fa-solid fa-rotate-left"></i> {{ __('quizzes.actions.restore') }}</button>
                                @endcan
                                @can('forceDelete', $quiz)
                                <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $quiz->id }}">
                                    <i class="fa-solid fa-skull-crossbones"></i> {{ __('quizzes.actions.force_delete') }}</button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $quizzes->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('quizzes.empty.trash_title') }}</h3>
        <p>{{ __('quizzes.empty.trash_text') }}</p>
    </div>
@endif