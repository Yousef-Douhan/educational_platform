@php
    $sort = request('sort', 'created_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('status')) || filled(request('from')) || filled(request('to'));
    $tone = ['pending' => 'warning', 'paid' => 'success', 'failed' => 'danger', 'cancelled' => 'muted', 'refunded' => 'info'];
    $icon = ['pending' => 'fa-hourglass-half', 'paid' => 'fa-circle-check', 'failed' => 'fa-circle-xmark', 'cancelled' => 'fa-ban', 'refunded' => 'fa-rotate-left'];
    $canPay = auth()->user()->can('orders.mark-paid');
    $canCancel = auth()->user()->can('orders.edit');
    $canRefund = auth()->user()->can('orders.refund');
@endphp

@if ($orders->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'id' ? 'is-sorted' : '' }}" data-sort="id">{{ __('orders.table.number') }} <i class="fa-solid {{ $arrow('id') }}"></i></th>
                    <th>{{ __('orders.table.student') }}</th>
                    <th>{{ __('orders.table.items') }}</th>
                    <th class="sortable {{ $sort === 'total_amount' ? 'is-sorted' : '' }}" data-sort="total_amount">{{ __('orders.table.total') }} <i class="fa-solid {{ $arrow('total_amount') }}"></i></th>
                    <th class="sortable {{ $sort === 'status' ? 'is-sorted' : '' }}" data-sort="status">{{ __('orders.table.status') }} <i class="fa-solid {{ $arrow('status') }}"></i></th>
                    <th class="sortable {{ $sort === 'created_at' ? 'is-sorted' : '' }}" data-sort="created_at">{{ __('orders.table.created_at') }} <i class="fa-solid {{ $arrow('created_at') }}"></i></th>
                    <th class="col-actions">{{ __('orders.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($orders as $order)
                    <tr data-id="{{ $order->id }}">
                        <td dir="ltr"><span class="order-number">{{ $order->order_number }}</span></td>
                        <td>
                            <div class="user-cell__name">{{ $order->user->name }}
                                @if ($order->user->trashed())
                                    <span class="badge badge--danger">{{ __('orders.table.deleted_user') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small" dir="ltr">{{ $order->user->email }}</div>
                        </td>
                        <td><span class="badge badge--primary">{{ $order->items_count }}</span></td>
                        <td dir="ltr" class="money-cell">
                            {{ number_format((float) $order->total_amount, 2) }} <small class="text-muted">{{ $order->currency }}</small>
                            @if ((float) $order->discount_amount > 0)
                                <div class="text-muted small">−{{ number_format((float) $order->discount_amount, 2) }} · {{ $order->coupon_code }}</div>
                            @endif
                        </td>
                        <td><span class="badge badge--{{ $tone[$order->status] }}"><i class="fa-solid {{ $icon[$order->status] }}"></i> {{ __('orders.statuses.' . $order->status) }}</span></td>
                        <td class="text-muted" title="{{ $order->created_at->format('Y-m-d H:i') }}">{{ $order->created_at->diffForHumans() }}</td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('orders.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $order->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('orders.actions.view') }}</button>
                                    @if ($canPay && $order->canTransitionTo('paid'))
                                        <hr>
                                        <button type="button" role="menuitem" data-action="mark-paid" data-id="{{ $order->id }}">
                                            <i class="fa-solid fa-money-check-dollar"></i> {{ __('orders.actions.mark_paid') }}</button>
                                    @endif
                                    @if ($canCancel && $order->canTransitionTo('cancelled'))
                                        <button type="button" role="menuitem" class="is-danger" data-action="cancel" data-id="{{ $order->id }}">
                                            <i class="fa-solid fa-ban"></i> {{ __('orders.actions.cancel_order') }}</button>
                                    @endif
                                    @if ($canRefund && $order->canTransitionTo('refunded'))
                                        <hr>
                                        <button type="button" role="menuitem" class="is-danger" data-action="refund" data-id="{{ $order->id }}">
                                            <i class="fa-solid fa-rotate-left"></i> {{ __('orders.actions.refund') }}</button>
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $orders->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-receipt' }}"></i></div>
        <h3>{{ $hasFilters ? __('orders.empty.no_results_title') : __('orders.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('orders.empty.no_results_text') : __('orders.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('orders.actions.reset') }}</button>
        @endif
    </div>
@endif
