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

    <?php if (!empty($errors)): ?>
        <p><mark><?= esc(implode(' | ', $errors)); ?></mark></p>
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

    <?php if (is_staff_or_admin()): ?>
        <hr>

        <h2>Staff controls</h2>

        <form method="post" action="<?= ROOT ?>/tickets/status/<?= (int)$ticket->id ?>">
            <label for="status">Status</label>
            <select name="status" id="status" required>
                <?php foreach (Model\Ticket::STAFF_SET_STATUSES as $status): ?>
                    <option value="<?= esc($status) ?>" <?= $ticket->status === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="resolution_comment">Resolution comment</label>
            <textarea name="resolution_comment" id="resolution_comment" rows="4" placeholder="Required when setting status to resolved"></textarea>

            <button type="submit">Update status</button>
        </form>

        <form method="post" action="<?= ROOT ?>/tickets/priority/<?= (int)$ticket->id ?>">
            <label for="priority">Priority</label>
            <select name="priority" id="priority" required>
                <?php foreach (Model\Ticket::PRIORITIES as $priority): ?>
                    <option value="<?= esc($priority) ?>" <?= $ticket->priority === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Update priority</button>
        </form>

        <form method="post" action="<?= ROOT ?>/tickets/assign/<?= (int)$ticket->id ?>">
            <label for="assigned_to">Assigned staff</label>
            <select name="assigned_to" id="assigned_to">
                <option value="0">Unassigned</option>
                <?php foreach ($staffUsers as $staff): ?>
                    <option value="<?= (int)$staff->id ?>" <?= (int)($ticket->assigned_to ?? 0) === (int)$staff->id ? 'selected' : '' ?>><?= esc($staff->name) ?> (<?= esc($staff->username) ?>)</option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Update assignment</button>
        </form>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
