@php
    $sort = request('sort', 'updated_at');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('state'));
    $tone = ['active' => 'success', 'abandoned' => 'danger', 'empty' => 'muted'];
    $icon = ['active' => 'fa-cart-arrow-down', 'abandoned' => 'fa-hourglass-end', 'empty' => 'fa-cart-shopping'];
@endphp

@if ($carts->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('carts.table.student') }}</th>
                    <th class="sortable {{ $sort === 'items_count' ? 'is-sorted' : '' }}" data-sort="items_count">{{ __('carts.table.items') }} <i class="fa-solid {{ $arrow('items_count') }}"></i></th>
                    <th class="sortable {{ $sort === 'total' ? 'is-sorted' : '' }}" data-sort="total">{{ __('carts.table.total') }} <i class="fa-solid {{ $arrow('total') }}"></i></th>
                    <th class="sortable {{ $sort === 'updated_at' ? 'is-sorted' : '' }}" data-sort="updated_at">{{ __('carts.table.updated_at') }} <i class="fa-solid {{ $arrow('updated_at') }}"></i></th>
                    <th>{{ __('carts.table.state') }}</th>
                    <th class="col-actions">{{ __('carts.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($carts as $cart)
                    @php $state = $cart->state(); @endphp
                    <tr data-id="{{ $cart->id }}">
                        <td>
                            <div class="user-cell__name">{{ $cart->user->name }}
                                @if ($cart->user->trashed())
                                    <span class="badge badge--danger">{{ __('carts.table.deleted_user') }}</span>
                                @endif
                            </div>
                            <div class="text-muted small" dir="ltr">{{ $cart->user->email }}</div>
                        </td>
                        <td><span class="badge {{ $cart->items_count ? 'badge--primary' : 'badge--muted' }}">{{ $cart->items_count }}</span></td>
                        <td dir="ltr" class="discount-cell">{{ number_format((float) $cart->total, 2) }}</td>
                        <td class="text-muted" title="{{ $cart->updated_at->format('Y-m-d H:i') }}">{{ $cart->updated_at->diffForHumans() }}</td>
                        <td><span class="badge badge--{{ $tone[$state] }}"><i class="fa-solid {{ $icon[$state] }}"></i> {{ __('carts.states.' . $state) }}</span></td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('carts.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $cart->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('carts.actions.view') }}</button>
                                    @can('carts.edit')
                                        @if ($cart->items_count)
                                            <hr>
                                            <button type="button" role="menuitem" class="is-danger" data-action="clear" data-id="{{ $cart->id }}">
                                                <i class="fa-solid fa-broom"></i> {{ __('carts.actions.clear_cart') }}</button>
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
    {{ $carts->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-cart-shopping' }}"></i></div>
        <h3>{{ $hasFilters ? __('carts.empty.no_results_title') : __('carts.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('carts.empty.no_results_text') : __('carts.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('carts.actions.reset') }}</button>
        @endif
    </div>
@endif
