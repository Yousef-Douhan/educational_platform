@if ($coupons->count())
    <div class="table-wrap">
        <table class="crud-table">
            <thead>
                <tr>
                    <th>{{ __('coupons.table.code') }}</th>
                    <th>{{ __('coupons.table.discount') }}</th>
                    <th>{{ __('coupons.table.usage') }}</th>
                    <th>{{ __('coupons.table.deleted_at') }}</th>
                    <th class="col-actions">{{ __('coupons.table.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($coupons as $coupon)
                    <tr data-id="{{ $coupon->id }}">
                        <td><span class="coupon-code" dir="ltr">{{ $coupon->code }}</span></td>
                        <td dir="ltr">{{ $coupon->discount_type === 'percentage' ? rtrim(rtrim($coupon->discount_value, '0'), '.') . '%' : number_format((float) $coupon->discount_value, 2) }}</td>
                        <td dir="ltr"><span class="badge badge--muted">{{ $coupon->times_used }} / {{ $coupon->usage_limit ?? '∞' }}</span></td>
                        <td><span class="badge badge--danger"><i class="fa-regular fa-clock"></i> {{ $coupon->deleted_at?->format('Y-m-d H:i') }}</span></td>
                        <td class="col-actions">
                            <div class="inline-actions">
                                @can('coupons.edit')
                                    <button type="button" class="btn btn-success-soft btn-sm" data-action="restore" data-id="{{ $coupon->id }}">
                                        <i class="fa-solid fa-rotate-left"></i> {{ __('coupons.actions.restore') }}</button>
                                @endcan
                                @can('coupons.delete')
                                    @if ($coupon->hasBeenUsed())
                                        <span class="badge badge--muted" title="{{ __('coupons.messages.in_use') }}"><i class="fa-solid fa-lock"></i> {{ __('coupons.table.kept') }}</span>
                                    @else
                                        <button type="button" class="btn btn-danger-soft btn-sm" data-action="force-delete" data-id="{{ $coupon->id }}">
                                            <i class="fa-solid fa-skull-crossbones"></i> {{ __('coupons.actions.force_delete') }}</button>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    {{ $coupons->links('cms.partials.pagination') }}
@else
    <div class="empty-state empty-state--compact">
        <div class="empty-state__icon"><i class="fa-regular fa-circle-check"></i></div>
        <h3>{{ __('coupons.empty.trash_title') }}</h3>
        <p>{{ __('coupons.empty.trash_text') }}</p>
    </div>
@endif
