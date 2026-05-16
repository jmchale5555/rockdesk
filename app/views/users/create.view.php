<?php include __DIR__ . '/../partials/header.view.php' ?>

<article class="auth-panel" x-data="{ showPassword: false }">
    <header>
        <h1>Create user</h1>
        <p>Create a local user with a temporary password.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
    <?php endif; ?>

    <form method="post" action="<?= ROOT ?>/users/store">
        <?= csrf_field() ?>

        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="<?= esc(old_value('name')); ?>" maxlength="120" required>

        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="<?= esc(old_value('username')); ?>" maxlength="100" required>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?= esc(old_value('email')); ?>" maxlength="190">

        <label for="role">Role</label>
        <select name="role" id="role" required>
            <option value="user" <?= old_select('role', 'user', 'user') ?>>User</option>
            <option value="staff" <?= old_select('role', 'staff') ?>>Staff</option>
            <option value="admin" <?= old_select('role', 'admin') ?>>Admin</option>
        </select>

        <label>
            <input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', '1', '1') ?>>
            Active
        </label>

        <label for="password">Temporary password</label>
        <div class="password-input-wrap">
            <input x-bind:type="showPassword ? 'text' : 'password'" name="password" id="password" minlength="8" required>
            <button type="button" class="password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'">
                <img x-show="!showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                <img x-show="showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
            </button>
        </div>

        <button type="submit">Create user</button>
        <a href="<?= ROOT ?>/users"
            hx-get="<?= ROOT ?>/users"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML"
            hx-push-url="true">Cancel</a>
    </form>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
