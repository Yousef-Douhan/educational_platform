{{-- User details drawer — included by users/index.blade.php --}}
<div id="showUserModal" class="uu-drawer" aria-hidden="true">
    <div class="uu-drawer-scrim" data-drawer-close></div>

    <section class="uu-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="showUserTitle">
        <header class="uu-drawer-head">
            <span class="uu-drawer-icon"><i class="fa-solid fa-id-card" aria-hidden="true"></i></span>
            <div>
                <h2 id="showUserTitle" class="uu-drawer-title">User details</h2>
                <p class="uu-drawer-sub">Account information at a glance.</p>
            </div>
            <button type="button" class="uu-icon-btn uu-drawer-x" data-drawer-close aria-label="Close">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </header>

        <div class="uu-drawer-body">
            <div class="uu-profile">
                <span id="show-avatar"></span>
                <div>
                    <h3 class="uu-profile-name" id="show-name"></h3>
                    <p class="uu-profile-mail" id="show-email"></p>
                    <span id="show-status"></span>
                </div>
            </div>

            <dl class="uu-dl">
                <div class="uu-dl-row">
                    <dt>Created</dt>
                    <dd id="show-created"></dd>
                </div>
            </dl>
        </div>

        <footer class="uu-drawer-foot">
            <button type="button" class="uu-btn uu-btn--ghost" data-drawer-close>Close</button>
            <button type="button" class="uu-btn uu-btn--primary" id="show-edit-btn">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i> Edit user
            </button>
        </footer>
    </section>
</div>
