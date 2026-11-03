@php
    $sort = request('sort', 'created_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('status')) || filled(request('gateway')) || filled(request('from')) || filled(request('to'));
    $tone = ['pending' => 'warning', 'success' => 'success', 'failed' => 'danger', 'refunded' => 'info'];
    $icon = ['pending' => 'fa-hourglass-half', 'success' => 'fa-circle-check', 'failed' => 'fa-circle-xmark', 'refunded' => 'fa-rotate-left'];
    $gatewayName = fn (string $g) => __('orders.gateways.' . $g) !== 'orders.gateways.' . $g ? __('orders.gateways.' . $g) : $g;
@endphp

@if ($payments->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id">{{ __('payments.table.id') }} <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('payments.table.order') }}</th>
                    <th>{{ __('payments.table.student') }}</th>
                    <th>{{ __('payments.table.method') }}</th>
                    <th class="sortable {{ $sort === 'amount' ? 'is-sorted' : '' }}" data-sort="amount">{{ __('payments.table.amount') }} <i class="fa-solid {{ $arrow('amount') }}"></i></th>
                    <th class="sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" data-sort="status">{{ __('payments.table.status') }} <i class="fa-solid {{ $arrow('status') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('payments.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('payments.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr data-id="{{ $payment->id }}">
                        <td dir="ltr">#{{ $payment->id }}</td>
                        <td dir="ltr"><span class="order-number">{{ $payment->order->order_number }}</span></td>
                        <td>
                            <div class="user-cell__name">{{ $payment->order->user->name }}
                                @if ($payment->order->user->trashed())
                                    <span class="badge badge--danger">{{ __('payments.table.deleted_user') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small" dir="ltr">{{ $payment->order->user->email }}</div>
                        </td>
                        <td>
                            <div>{{ $gatewayName($payment->gateway) }}</div>
                            @if ($payment->transaction_reference)
                                <div class="text-muted small" dir="ltr">{{ $payment->transaction_reference }}</div>
                            @endif
                        </td>
                        <td dir="ltr" class="money-cell">{{ number_format((float) $payment->amount, 2) }} <small class="text-muted">{{ $payment->currency }}</small></td>
                        <td><span class="badge badge--{{ $tone[$payment->status] }}"><i class="fa-solid {{ $icon[$payment->status] }}"></i> {{ __('payments.statuses.' . $payment->status) }}</span></td>
                        <td class="text-muted" title="{{ $payment->created_at->format('Y-m-d H:i') }}">{{ $payment->created_at->diffForHumans() }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('payments.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $payment->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('payments.actions.view') }}</button>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $payments->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-credit-card' }}"></i></div>
        <h3>{{ $hasFilters ? __('payments.empty.no_results_title') : __('payments.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('payments.empty.no_results_text') : __('payments.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('payments.actions.reset') }}</button>
        @endif
    </div>
@endif
