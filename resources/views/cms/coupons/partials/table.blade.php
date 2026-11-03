@php
    $sort = request('sort', 'id');
    $dir = request('dir', 'desc');
    $arrow = fn(string $col) => $sort === $col ? ($dir === 'asc' ? 'fa-arrow-up' : 'fa-arrow-down') : 'fa-arrows-up-down';
    $hasFilters = filled(request('search')) || filled(request('status')) || filled(request('type'));
    $tone = ['active' => 'success', 'scheduled' => 'primary', 'expired' => 'danger', 'exhausted' => 'info', 'disabled' => 'muted'];
    $icon = ['active' => 'fa-circle-check', 'scheduled' => 'fa-clock', 'expired' => 'fa-hourglass-end', 'exhausted' => 'fa-battery-empty', 'disabled' => 'fa-ban'];
@endphp

@if ($coupons->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th class="sortable {{ $sort === 'code' ? 'is-sorted' : '' }}" data-sort="code">{{ __('coupons.table.code') }} <i class="fa-solid {{ $arrow('code') }}"></i></th>
                    <th class="sortable {{ $sort === 'discount_value' ? 'is-sorted' : '' }}" data-sort="discount_value">{{ __('coupons.table.discount') }} <i class="fa-solid {{ $arrow('discount_value') }}"></i></th>
                    <th class="sortable {{ $sort === 'times_used' ? 'is-sorted' : '' }}" data-sort="times_used">{{ __('coupons.table.usage') }} <i class="fa-solid {{ $arrow('times_used') }}"></i></th>
                    <th class="sortable {{ $sort === 'expires_at' ? 'is-sorted' : '' }}" data-sort="expires_at">{{ __('coupons.table.validity') }} <i class="fa-solid {{ $arrow('expires_at') }}"></i></th>
                    <th>{{ __('coupons.table.status') }}</th>
                    <th class="col-actions">{{ __('coupons.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    @php
                        $status = $coupon->status();
                        $pct = $coupon->usage_limit ? min(100, (int) round($coupon->times_used / $coupon->usage_limit * 100)) : null;
                    @endphp
                    <tr data-id="{{ $coupon->id }}">
                        <td><span class="coupon-code" dir="ltr">{{ $coupon->code }}</span></td>
                        <td>
                            <div class="discount-cell" dir="ltr">
                                @if ($coupon->discount_type === 'percentage')
                                    {{ rtrim(rtrim($coupon->discount_value, '0'), '.') }}%
                                @else
                                    {{ number_format((float) $coupon->discount_value, 2) }}
                                @endif
                            </div>
                            <div class="text-muted small">{{ __('coupons.types.' . $coupon->discount_type) }}</div>
                        </td>
                        <td>
                            <div class="usage-cell" dir="ltr">{{ $coupon->times_used }} / {{ $coupon->usage_limit ?? '∞' }}</div>
                            @if ($pct !== null)
                                <div class="usage-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><span style="width: {{ $pct }}%"></span></div>
                            @endif
                        </td>
                        <td class="text-muted validity-cell">
                            <div>{{ $coupon->starts_at?->format('Y-m-d H:i') ?? __('coupons.table.immediately') }}</div>
                            <div>→ {{ $coupon->expires_at?->format('Y-m-d H:i') ?? __('coupons.table.no_expiry') }}</div>
                        </td>
                        <td><span class="badge badge--{{ $tone[$status] }}"><i class="fa-solid {{ $icon[$status] }}"></i> {{ __('coupons.statuses.' . $status) }}</span></td>
                        <td class="col-actions">
                            <div class="action-dropdown">
                                <button type="button" class="action-trigger" data-dropdown-toggle aria-haspopup="menu"
                                    aria-label="{{ __('coupons.table.actions') }}"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu" role="menu">
                                    <button type="button" role="menuitem" data-action="show" data-id="{{ $coupon->id }}">
                                        <i class="fa-regular fa-eye"></i> {{ __('coupons.actions.view') }}</button>
                                    @can('coupons.edit')
                                        <button type="button" role="menuitem" data-action="edit" data-id="{{ $coupon->id }}">
                                            <i class="fa-regular fa-pen-to-square"></i> {{ __('coupons.actions.edit') }}</button>
                                        <button type="button" role="menuitem" data-action="toggle" data-id="{{ $coupon->id }}">
                                            <i class="fa-solid {{ $coupon->is_active ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                                            {{ $coupon->is_active ? __('coupons.actions.disable') : __('coupons.actions.enable') }}</button>
                                    @endcan
                                    @can('coupons.delete')
                                        <hr>
                                        <button type="button" role="menuitem" class="is-danger" data-action="delete" data-id="{{ $coupon->id }}">
                                            <i class="fa-regular fa-trash-can"></i> {{ __('coupons.actions.delete') }}</button>
                                    @endcan
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $coupons->links('cms.partials.pagination') }}
@else
    <div class="empty-state">
        <div class="empty-state__icon"><i class="fa-solid {{ $hasFilters ? 'fa-face-frown' : 'fa-ticket' }}"></i></div>
        <h3>{{ $hasFilters ? __('coupons.empty.no_results_title') : __('coupons.empty.title') }}</h3>
        <p>{{ $hasFilters ? __('coupons.empty.no_results_text') : __('coupons.empty.text') }}</p>
        @if ($hasFilters)
            <button type="button" class="btn btn-light" data-action="reset-filters"><i class="fa-solid fa-rotate-left"></i> {{ __('coupons.actions.reset') }}</button>
        @else
            @can('coupons.create')
                <button type="button" class="btn btn-primary" data-action="open-create"><i class="fa-solid fa-plus"></i> {{ __('coupons.actions.add') }}</button>
            @endcan
        @endif
    </div>
@endif
