<?php include 'partials/header.view.php' ?>

<article class="auth-panel" x-data="{ showPassword: false, showConfirm: false }">
    <header>
        <h1>Create account</h1>
    </header>

    <?php if (!empty($errors)): ?>
        <p><mark><?= implode(' | ', $errors); ?></mark></p>
    <?php endif; ?>

    <form method="post">
        <label for="name">Name</label>
        <input type="text" name="name" id="name" value="<?= esc(old_value('name')); ?>" required>

        <label for="email">Email</label>
        <input type="email" name="email" id="email" value="<?= esc(old_value('email')); ?>" required>

        <label for="password">Password</label>
        <div class="password-input-wrap">
            <input x-bind:type="showPassword ? 'text' : 'password'" name="password" id="password" required>
            <button type="button" class="password-toggle" x-on:click="showPassword = !showPassword" x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'">
                <img x-show="!showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                <img x-show="showPassword" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
            </button>
        </div>

        <label for="confirm">Confirm password</label>
        <div class="password-input-wrap">
            <input x-bind:type="showConfirm ? 'text' : 'password'" name="confirm" id="confirm" required>
            <button type="button" class="password-toggle" x-on:click="showConfirm = !showConfirm" x-bind:aria-label="showConfirm ? 'Hide confirm password' : 'Show confirm password'">
                <img x-show="!showConfirm" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                <img x-show="showConfirm" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
            </button>
        </div>

        <label>
            <input type="checkbox" required>
            I accept the terms.
        </label>

        <button type="submit">Create account</button>
    </form>

    <p>
        Already have an account? <a href="<?= ROOT ?>/login"
            hx-get="<?= ROOT ?>/login"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML"
            hx-push-url="true">Sign in</a>
    </p>
</article>

<?php include 'partials/footer.view.php' ?>
