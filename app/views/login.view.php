<?php include 'partials/header.view.php' ?>

<article class="auth-panel" x-data="{ showPassword: false }">
    <header>
        <h1>Sign in</h1>
    </header>

    <?php if (!empty($errors)): ?>
        <p><mark><?= implode(' | ', $errors); ?></mark></p>
    <?php endif; ?>

    <form method="post">
        <?= csrf_field() ?>

        <label for="username">Username</label>
        <input type="text" name="username" id="username" value="<?= esc(old_value('username')); ?>" autocomplete="username" required>

        <label for="password">Password</label>
        <div class="password-input-wrap">
            <input x-bind:type="showPassword ? 'text' : 'password'" name="password" id="password" autocomplete="current-password" required>
            <button type="button" class="password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'">
                <img x-show="!showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                <img x-show="showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
            </button>
        </div>

        <button type="submit">Sign in</button>
    </form>
</article>

<?php include 'partials/footer.view.php' ?>
