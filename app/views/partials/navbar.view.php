<?php $url = URL(0); ?>

<nav id="site-nav">
    <ul>
        <li>
            <strong>
                <a href="<?= ROOT ?>/home"
                    hx-get="<?= ROOT ?>/home"
                    hx-target="#page-content"
                    hx-select="#page-content > *"
                    hx-select-oob="#site-nav"
                    hx-swap="innerHTML"
                    hx-push-url="true"><?= APP_NAME ?></a>
            </strong>
        </li>
    </ul>
    <ul>
        <li>
            <a href="<?= ROOT ?>/home"
                hx-get="<?= ROOT ?>/home"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true"
                <?= $url === 'home' ? 'aria-current="page"' : '' ?>>Home</a>
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
                <?php if (empty($_SESSION['USER'])): ?>
                    <a href="<?= ROOT ?>/login"
                        x-on:click="closeMenu()"
                        hx-get="<?= ROOT ?>/login"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML"
                        hx-push-url="true"
                        <?= $url === 'login' ? 'aria-current="page"' : '' ?>>Login</a>
                <?php else: ?>
                    <a href="<?= ROOT ?>/tickets"
                        x-on:click="closeMenu()"
                        hx-get="<?= ROOT ?>/tickets"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML"
                        hx-push-url="true"
                        <?= $url === 'tickets' ? 'aria-current="page"' : '' ?>>Tickets</a>

                    <a href="<?= ROOT ?>/tickets/create"
                        x-on:click="closeMenu()"
                        hx-get="<?= ROOT ?>/tickets/create"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML"
                        hx-push-url="true">New ticket</a>

                    <?php if (is_admin()): ?>
                        <a href="<?= ROOT ?>/users"
                            x-on:click="closeMenu()"
                            hx-get="<?= ROOT ?>/users"
                            hx-target="#page-content"
                            hx-select="#page-content > *"
                            hx-select-oob="#site-nav"
                            hx-swap="innerHTML"
                            hx-push-url="true"
                            <?= $url === 'users' ? 'aria-current="page"' : '' ?>>Users</a>
                    <?php endif; ?>

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
                <?php endif; ?>
            </div>
        </li>
    </ul>
</nav>
