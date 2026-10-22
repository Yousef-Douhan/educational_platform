{{-- Edit user drawer — included by users/index.blade.php --}}
<div id="editUserModal" class="uu-drawer" aria-hidden="true">
    <div class="uu-drawer-scrim" data-drawer-close></div>

    <section class="uu-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="editUserTitle">
        <form id="editUserForm" class="uu-drawer-form">
            <input type="hidden" id="edit-user-id" name="id">

            <header class="uu-drawer-head">
                <span class="uu-drawer-icon"><i class="fa-solid fa-user-pen" aria-hidden="true"></i></span>
                <div>
                    <h2 id="editUserTitle" class="uu-drawer-title">Edit user</h2>
                    <p class="uu-drawer-sub">Update this account's details or status.</p>
                </div>
                <button type="button" class="uu-icon-btn uu-drawer-x" data-drawer-close aria-label="Close">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </header>

            <div class="uu-drawer-body">
                <div class="uu-field">
                    <label class="uu-label" for="edit-name">{{ __('users.name') }}</label>
                    <input type="text" id="edit-name" name="name" class="uu-input" required autocomplete="off" data-autofocus>
                    <p class="uu-error" data-field="name"></p>
                </div>

                <div class="uu-field">
                    <label class="uu-label" for="edit-email">{{ __('users.email') }}</label>
                    <input type="email" id="edit-email" name="email" class="uu-input" required autocomplete="off">
                    <p class="uu-error" data-field="email"></p>
                </div>

                <div class="uu-field">
                    <label class="uu-label" for="edit-password">New {{ strtolower(__('users.password')) }}</label>
                    <div class="uu-pw">
                        <input type="password" id="edit-password" name="password" class="uu-input" placeholder="Leave blank to keep the current one" autocomplete="new-password">
                        <button type="button" class="uu-icon-btn" data-toggle-password aria-label="Show password"><i class="fa-solid fa-eye"></i></button>
                    </div>
                    <p class="uu-error" data-field="password"></p>
                </div>

                <div class="uu-field">
                    <span class="uu-label">{{ __('users.status') }}</span>
                    <div class="uu-seg" role="radiogroup" aria-label="{{ __('users.status') }}">
                        <label class="uu-seg-opt">
                            <input type="radio" name="status" value="active" checked>
                            <span><i class="uu-dot uu-dot--ok"></i> Active</span>
                        </label>
                        <label class="uu-seg-opt">
                            <input type="radio" name="status" value="inactive">
                            <span><i class="uu-dot uu-dot--warn"></i> Inactive</span>
                        </label>
                        <label class="uu-seg-opt">
                            <input type="radio" name="status" value="suspended">
                            <span><i class="uu-dot uu-dot--bad"></i> Suspended</span>
                        </label>
                    </div>
                    <p class="uu-error" data-field="status"></p>
                </div>
            </div>

            <footer class="uu-drawer-foot">
                <button type="button" class="uu-btn uu-btn--ghost" data-drawer-close>Cancel</button>
                <button type="submit" class="uu-btn uu-btn--primary">Save changes</button>
            </footer>
        </form>
    </section>
</div>
