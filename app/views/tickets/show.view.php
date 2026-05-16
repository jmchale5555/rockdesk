<?php include __DIR__ . '/../partials/header.view.php' ?>

<article>
    <header>
        <p><a href="<?= ROOT ?>/tickets"
                hx-get="<?= ROOT ?>/tickets"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML"
                hx-push-url="true">Back to tickets</a></p>
        <h1><?= esc($ticket->subject) ?></h1>
        <p><?= esc($ticket->ticket_number) ?></p>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <dl>
        <dt>Status</dt>
        <dd><?= esc(str_replace('_', ' ', $ticket->status)) ?></dd>

        <dt>Priority</dt>
        <dd><?= esc($ticket->priority) ?></dd>

        <dt>Requester</dt>
        <dd><?= esc($ticket->requester_name) ?> <small>(<?= esc($ticket->requester_username) ?>)</small></dd>

        <dt>Assigned</dt>
        <dd><?= esc($ticket->assignee_name ?: 'Unassigned') ?></dd>

        <dt>Created</dt>
        <dd><?= esc($ticket->created_at) ?></dd>

        <dt>Updated</dt>
        <dd><?= esc($ticket->updated_at ?: '-') ?></dd>
    </dl>

    <hr>

    <h2>Request</h2>
    <p><?= nl2br(esc($ticket->body)) ?></p>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
