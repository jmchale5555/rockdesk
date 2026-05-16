<?php include __DIR__ . '/../partials/header.view.php' ?>

<article id="ticket-create">
    <header>
        <h1>Submit ticket</h1>
        <p>Describe the issue and support staff will pick it up.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
    <?php endif; ?>

    <form method="post" action="<?= ROOT ?>/tickets/store"
        hx-post="<?= ROOT ?>/tickets/store"
        hx-target="#page-content"
        hx-select="#page-content > *"
        hx-select-oob="#site-nav"
        hx-swap="innerHTML"
        hx-push-url="true">
        <?= csrf_field() ?>

        <label for="subject">Subject</label>
        <input type="text" name="subject" id="subject" value="<?= esc(old_value('subject')); ?>" maxlength="190" required>

        <label for="body">Details</label>
        <input type="hidden" name="body" id="body" value="<?= esc(old_value('body')); ?>">
        <trix-editor input="body" class="rich-text-editor"></trix-editor>

        <div class="form-actions">
            <button type="submit">Submit ticket</button>
            <a href="<?= ROOT ?>/tickets"
                role="button"
                class="outline secondary"
                hx-get="<?= ROOT ?>/tickets"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true">Cancel</a>
        </div>
    </form>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
