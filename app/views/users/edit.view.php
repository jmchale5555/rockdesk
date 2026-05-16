<?php include __DIR__ . '/../partials/header.view.php' ?>

<article class="auth-panel" x-data="{ showPassword: false }">
    <header>
        <h1>Edit user</h1>
        <p><?= esc($user->username) ?></p>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
    <?php endif; ?>

    <form method="post" action="<?= ROOT ?>/users/update/<?= (int)$user->id ?>">
        <?= csrf_field() ?>

        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="<?= esc(old_value('name', $user->name)); ?>" maxlength="120" required>

        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="<?= esc(old_value('username', $user->username)); ?>" maxlength="100" required>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?= esc(old_value('email', $user->email)); ?>" maxlength="190">

        <label for="role">Role</label>
        <select name="role" id="role" required>
            <option value="user" <?= old_select('role', 'user', $user->role) ?>>User</option>
            <option value="staff" <?= old_select('role', 'staff', $user->role) ?>>Staff</option>
            <option value="admin" <?= old_select('role', 'admin', $user->role) ?>>Admin</option>
        </select>

        <label>
            <input type="checkbox" name="is_active" value="1" <?= old_checked('is_active', '1', (int)$user->is_active === 1 ? '1' : '0') ?>>
            Active
        </label>

        <p><small>Auth provider: <?= esc($user->auth_provider ?? 'local') ?></small></p>

        <?php if (($user->auth_provider ?? 'local') === 'local'): ?>
            <label for="password">New temporary password</label>
            <div class="password-input-wrap">
                <input x-bind:type="showPassword ? 'text' : 'password'" name="password" id="password" minlength="8" aria-describedby="password-help">
                <button type="button" class="password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'">
                    <img x-show="!showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                    <img x-show="showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
                </button>
            </div>
            <small id="password-help">Leave blank to keep the current password. Setting a new password forces the user to reset it after login.</small>
        <?php else: ?>
            <p><small>Password is managed by Active Directory for LDAP users.</small></p>
        <?php endif; ?>

        <button type="submit">Update user</button>
        <a href="<?= ROOT ?>/users"
            hx-get="<?= ROOT ?>/users"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML"
            hx-push-url="true">Back to users</a>
    </form>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
