<?php include 'partials/header.view.php' ?>

<article class="auth-panel" x-data="{ showCurrent: false, showNext: false, showConfirm: false }">
    <header>
        <h1>Reset password</h1>
        <?php if (!empty($isForcedReset)): ?>
            <p>You must reset your temporary password before continuing.</p>
        <?php else: ?>
            <p>Update your account password.</p>
        <?php endif; ?>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
    <?php endif; ?>

    <?php if (empty($canChangePassword)): ?>
        <p>Your password is managed by your directory account. Please change it in Active Directory.</p>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>

            <label for="current_password">Current password</label>
            <div class="password-input-wrap">
                <input x-bind:type="showCurrent ? 'text' : 'password'" name="current_password" id="current_password" autocomplete="current-password" required>
                <button type="button" class="password-toggle" x-on:click="showCurrent = !showCurrent" x-bind:aria-label="showCurrent ? 'Hide current password' : 'Show current password'">
                    <img x-show="!showCurrent" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                    <img x-show="showCurrent" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
                </button>
            </div>

            <label for="password">New password</label>
            <div class="password-input-wrap">
                <input x-bind:type="showNext ? 'text' : 'password'" name="password" id="password" minlength="8" autocomplete="new-password" required>
                <button type="button" class="password-toggle" x-on:click="showNext = !showNext" x-bind:aria-label="showNext ? 'Hide new password' : 'Show new password'">
                    <img x-show="!showNext" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                    <img x-show="showNext" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
                </button>
            </div>

            <label for="confirm">Confirm new password</label>
            <div class="password-input-wrap">
                <input x-bind:type="showConfirm ? 'text' : 'password'" name="confirm" id="confirm" minlength="8" autocomplete="new-password" required>
                <button type="button" class="password-toggle" x-on:click="showConfirm = !showConfirm" x-bind:aria-label="showConfirm ? 'Hide confirm password' : 'Show confirm password'">
                    <img x-show="!showConfirm" src="<?= ROOT ?>/assets/icons/lucide/eye.svg" alt="" width="16" height="16">
                    <img x-show="showConfirm" src="<?= ROOT ?>/assets/icons/lucide/eye-off.svg" alt="" width="16" height="16">
                </button>
            </div>

            <button type="submit">Update password</button>
        </form>
    <?php endif; ?>
</article>

<?php include 'partials/footer.view.php' ?>
