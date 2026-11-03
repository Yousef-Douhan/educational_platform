@php
    $sort = request('sort', 'started_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('status')) || filled(request('result')) || filled(request('quiz_id')) || filled(request('from')) || filled(request('to'));
    $tone = ['in_progress' => 'info', 'submitted' => 'success', 'expired' => 'warning', 'voided' => 'muted'];
    $icon = ['in_progress' => 'fa-hourglass-half', 'submitted' => 'fa-paper-plane', 'expired' => 'fa-clock', 'voided' => 'fa-ban'];
    $quizTitle = fn ($q) => app()->getLocale() === 'ar' ? $q->title_ar : $q->title_en;
@endphp

@if ($attempts->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id">{{ __('quiz_attempts.table.id') }} <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('quiz_attempts.table.student') }}</th>
                    <th>{{ __('quiz_attempts.table.quiz') }}</th>
                    <th class="sortable {{ $sort === 'score' ? 'is-sorted' : '' }}" data-sort="score">{{ __('quiz_attempts.table.score') }} <i class="fa-solid {{ $arrow('score') }}"></i></th>
                    <th class="sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" data-sort="status">{{ __('quiz_attempts.table.status') }} <i class="fa-solid {{ $arrow('status') }}"></i></th>
                    <th class="sortable {{ $sort === 'started_at' ? 'is-sorted' : '' }}" data-sort="started_at">{{ __('quiz_attempts.table.started_at') }} <i class="fa-solid {{ $arrow('started_at') }}"></i></th>
                    <th class="col-actions">{{ __('quiz_attempts.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($attempts as $attempt)
                    @php $gradedRow = in_array($attempt->status, ['submitted', 'expired'], true); @endphp
                    <tr data-id="{{ $attempt->id }}">
                        <td dir="ltr">#{{ $attempt->id }}</td>
                        <td>
                            <div class="user-cell__name">{{ $attempt->user->name }}
                                @if ($attempt->user->trashed())
                                    <span class="badge badge--danger">{{ __('quiz_attempts.table.deleted_user') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small" dir="ltr">{{ $attempt->user->email }}</div>
                        </td>
                        <td>
                            <div class="user-cell__name">{{ $quizTitle($attempt->quiz) }}
                                @if ($attempt->quiz->trashed())
                                    <span class="badge badge--danger">{{ __('quiz_attempts.table.deleted_quiz') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small">{{ __('quiz_attempts.table.attempt_n', ['n' => $attempt->attempt_number]) }}</div>
                        </td>
                        <td>
                            @if ($gradedRow)
                                <div class="score-cell" dir="ltr">{{ rtrim(rtrim(number_format((float) $attempt->score, 2), '0'), '.') }}%
                                    <small class="text-muted">({{ $attempt->points_earned }}/{{ $attempt->points_total }})</small></div>
                                <span class="badge badge--{{ $attempt->is_passed ? 'success' : 'danger' }}">
                                    {{ $attempt->is_passed ? __('quiz_attempts.results.passed') : __('quiz_attempts.results.failed') }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td><span class="badge badge--{{ $tone[$attempt->status] }}"><i class="fa-solid {{ $icon[$attempt->status] }}"></i> {{ __('quiz_attempts.statuses.' . $attempt->status) }}</span></td>
                        <td class="text-muted" title="{{ $attempt->started_at->format('Y-m-d H:i') }}">{{ $attempt->started_at->diffForHumans() }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('quiz_attempts.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $attempt->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('quiz_attempts.actions.view') }}</button>
                                    @if ($attempt->status !== 'voided')
                                        @can('quiz-attempts.void')
                                            <button type="button" role="menuitem" class="is-danger" data-action="void" data-id="{{ $attempt->id }}">
                                                <i class="fa-solid fa-ban"></i> {{ __('quiz_attempts.actions.void') }}</button>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $attempts->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-clipboard-question' }}"></i></div>
        <h3>{{ $hasFilters ? __('quiz_attempts.empty.no_results_title') : __('quiz_attempts.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('quiz_attempts.empty.no_results_text') : __('quiz_attempts.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('quiz_attempts.actions.reset') }}</button>
        @endif
    </div>
@endif
