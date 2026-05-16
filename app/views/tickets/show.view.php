<?php include __DIR__ . '/../partials/header.view.php' ?>

<article id="ticket-detail">
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

    <dl class="ticket-meta-grid">
        <div class="ticket-meta-card">
            <dt>Status</dt>
            <dd><?= esc(str_replace('_', ' ', $ticket->status)) ?></dd>
        </div>

        <div class="ticket-meta-card">
            <dt>Priority</dt>
            <dd><?= esc($ticket->priority) ?></dd>
        </div>

        <div class="ticket-meta-card">
            <dt>Requester</dt>
            <dd><?= esc($ticket->requester_name) ?> <small>(<?= esc($ticket->requester_username) ?>)</small></dd>
        </div>

        <div class="ticket-meta-card">
            <dt>Assigned</dt>
            <dd><?= esc($ticket->assignee_name ?: 'Unassigned') ?></dd>
        </div>

        <div class="ticket-meta-card">
            <dt>Created</dt>
            <dd><?= esc($ticket->created_at) ?></dd>
        </div>

        <div class="ticket-meta-card">
            <dt>Updated</dt>
            <dd><?= esc($ticket->updated_at ?: '-') ?></dd>
        </div>
    </dl>

    <hr>

    <h2>Request</h2>
    <p><?= nl2br(esc($ticket->body)) ?></p>

    <hr>

    <h2>Conversation</h2>

    <?php if (empty($comments)): ?>
        <p>No replies yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <article>
                <header>
                    <strong><?= esc($comment->name) ?></strong>
                    <small><?= esc($comment->username) ?> · <?= esc($comment->role) ?> · <?= esc($comment->created_at) ?></small>
                </header>
                <p><?= nl2br(esc($comment->body)) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($ticket->status === 'closed'): ?>
        <p><mark>This ticket is closed and read-only.</mark></p>
    <?php else: ?>
        <form method="post" action="<?= ROOT ?>/tickets/reply/<?= (int)$ticket->id ?>"
            hx-post="<?= ROOT ?>/tickets/reply/<?= (int)$ticket->id ?>"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML">
            <?= csrf_field() ?>

            <label for="body">Add reply</label>
            <textarea name="body" id="body" rows="5" maxlength="10000" required><?= esc(old_value('body')) ?></textarea>
            <div class="form-actions">
                <button type="submit">Add reply</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if (is_staff_or_admin()): ?>
        <hr>

        <h2>Staff controls</h2>

        <form method="post" action="<?= ROOT ?>/tickets/status/<?= (int)$ticket->id ?>"
            hx-post="<?= ROOT ?>/tickets/status/<?= (int)$ticket->id ?>"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML">
            <?= csrf_field() ?>

            <label for="status">Status</label>
            <select name="status" id="status" required>
                <?php foreach (Model\Ticket::STAFF_SET_STATUSES as $status): ?>
                    <option value="<?= esc($status) ?>" <?= $ticket->status === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="resolution_comment">Resolution comment</label>
            <textarea name="resolution_comment" id="resolution_comment" rows="4" maxlength="10000" placeholder="Required when setting status to resolved"></textarea>

            <div class="form-actions">
                <button type="submit">Update status</button>
            </div>
        </form>

        <div class="staff-control-grid">
            <form method="post" action="<?= ROOT ?>/tickets/priority/<?= (int)$ticket->id ?>"
                hx-post="<?= ROOT ?>/tickets/priority/<?= (int)$ticket->id ?>"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML">
                <?= csrf_field() ?>

                <label for="priority">Priority</label>
                <select name="priority" id="priority" required>
                    <?php foreach (Model\Ticket::PRIORITIES as $priority): ?>
                        <option value="<?= esc($priority) ?>" <?= $ticket->priority === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="form-actions">
                    <button type="submit">Update priority</button>
                </div>
            </form>

            <form method="post" action="<?= ROOT ?>/tickets/assign/<?= (int)$ticket->id ?>"
                hx-post="<?= ROOT ?>/tickets/assign/<?= (int)$ticket->id ?>"
                hx-target="#page-content"
                hx-select="#page-content > *"
                hx-select-oob="#site-nav"
                hx-swap="innerHTML">
                <?= csrf_field() ?>

                <label for="assigned_to">Assigned staff</label>
                <select name="assigned_to" id="assigned_to">
                    <option value="0">Unassigned</option>
                    <?php foreach ($staffUsers as $staff): ?>
                        <option value="<?= (int)$staff->id ?>" <?= (int)($ticket->assigned_to ?? 0) === (int)$staff->id ? 'selected' : '' ?>><?= esc($staff->name) ?> (<?= esc($staff->username) ?>)</option>
                    <?php endforeach; ?>
                </select>

                <div class="form-actions">
                    <button type="submit">Update assignment</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
