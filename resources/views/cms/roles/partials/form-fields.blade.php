@php $p = $prefix; @endphp

<div class="form-grid">
    <div class="form-group">
        <label for="{{ $p }}_name" class="form-label">{{ __('roles.fields.name') }} <span class="required">*</span></label>
        <div class="input-icon">
            <i class="fa-solid fa-user-shield"></i>
            <input type="text" id="{{ $p }}_name" name="name" class="form-control" maxlength="100" required
                   placeholder="{{ __('roles.placeholders.name') }}">
        </div>
        <div class="field-error" data-error-for="name"></div>
    </div>

    <div class="form-group">
        <label for="{{ $p }}_guard" class="form-label">{{ __('roles.fields.guard_name') }}</label>
        <div class="input-icon">
            <i class="fa-solid fa-shield"></i>
            @if ($p === 'create')
                <select id="{{ $p }}_guard" name="guard_name" class="form-control" data-guard-select></select>
            @else
                <input type="text" id="{{ $p }}_guard" class="form-control" data-guard-display readonly>
            @endif
        </div>
        <small class="form-hint">{{ __('roles.hints.guard') }}</small>
        <div class="field-error" data-error-for="guard_name"></div>
    </div>

    <div class="form-group form-group--full">
        <span class="form-label">{{ __('roles.fields.permissions') }}</span>
        <div class="perm-groups" data-permission-groups></div>
        <div class="field-error" data-error-for="permissions"></div>
    </div>
</div>