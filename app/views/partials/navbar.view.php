<?php $url = URL(0); ?>
<?php $section = URL(1); ?>

<nav id="site-nav" class="site-nav">
    <ul class="site-nav-primary">
        <li>
            <strong class="site-brand">
                <a href="<?= ROOT ?>/home"
                    hx-get="<?= ROOT ?>/home"
                    hx-target="#page-content"
                    hx-select="#page-content > *"
                    hx-select-oob="#site-nav"
                    hx-swap="innerHTML"
                    hx-push-url="true"><?= APP_NAME ?></a>
            </strong>
        </li>
        <li>
            <a href="<?= ROOT ?>/home"
                class="nav-tab"
                hx-get="<?= ROOT ?>/home"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true"
                <?= $url === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
        </li>
        <?php if (!empty($_SESSION['USER'])): ?>
            <li>
                <a href="<?= ROOT ?>/tickets"
                    class="nav-tab"
                    hx-get="<?= ROOT ?>/tickets"
                    hx-target="#page-content"
                    hx-select="#page-content > *"
                    hx-select-oob="#site-nav"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    <?= $url === 'tickets' && $section !== 'create' ? 'aria-current="page"' : '' ?>>Tickets</a>
            </li>

            <?php if (is_admin()): ?>
                <li>
                    <a href="<?= ROOT ?>/users"
                        class="nav-tab"
                        hx-get="<?= ROOT ?>/users"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML"
                        hx-push-url="true"
                        <?= $url === 'users' ? 'aria-current="page"' : '' ?>>Users</a>
                </li>
            <?php endif; ?>
        <?php else: ?>
            <li>
                <a href="<?= ROOT ?>/login"
                    class="nav-tab"
                    hx-get="<?= ROOT ?>/login"
                    hx-target="#page-content"
                    hx-select="#page-content > *"
                    hx-select-oob="#site-nav"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    <?= $url === 'login' ? 'aria-current="page"' : '' ?>>Login</a>
            </li>
        <?php endif; ?>
    </ul>

    <?php if (!empty($_SESSION['USER'])): ?>
    <ul class="site-nav-actions">
        <li>
            <a href="<?= ROOT ?>/tickets/create"
                class="nav-tab nav-tab-cta"
                hx-get="<?= ROOT ?>/tickets/create"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true"
                <?= $url === 'tickets' && $section === 'create' ? 'aria-current="page"' : '' ?>>New ticket</a>
        </li>
        <li class="nav-menu-wrap"
            x-data="{
                open: false,
                toggleMenu()
                {
                    this.open = !this.open;
                    if (!this.open)
                    {
                        this.$refs.menuButton.blur();
                    }
                },
                closeMenu()
                {
                    this.open = false;
                    this.$refs.menuButton.blur();
                }
            }"
            x-on:keydown.escape.window="closeMenu()">
            <button type="button"
                class="nav-menu-button"
                aria-label="Open account menu"
                x-ref="menuButton"
                x-on:click="toggleMenu()"
                x-bind:aria-expanded="open.toString()">
                <img class="nav-menu-icon" src="<?= ROOT ?>/assets/icons/lucide/menu.svg" alt="" width="18" height="18">
                Menu
            </button>

            <div class="nav-menu-panel" x-cloak x-show="open" x-on:click.outside="closeMenu()">
                <a href="<?= ROOT ?>/password"
                    x-on:click="closeMenu()"
                    hx-get="<?= ROOT ?>/password"
                    hx-target="#page-content"
                    hx-select="#page-content > *"
                    hx-select-oob="#site-nav"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    <?= $url === 'password' ? 'aria-current="page"' : '' ?>>Reset password</a>

                <a href="<?= ROOT ?>/logout" x-on:click="closeMenu()">Logout</a>
            </div>
        </li>
    </ul>
    <?php endif; ?>
</nav>
