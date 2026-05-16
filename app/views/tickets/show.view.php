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
    <div class="rich-text-content"><?= render_rich_text((string)$ticket->body) ?></div>

    <hr>

    <h2>Conversation</h2>

    <?php if (empty($comments)): ?>
        <p>No replies yet.</p>
    <?php else: ?>
        <?php foreach ($comments as $comment): ?>
            <article class="conversation-card <?= (int)($comment->is_internal ?? 0) === 1 ? 'internal-note' : '' ?>">
                <header>
                    <strong><?= esc($comment->name) ?></strong>
                    <?php if ((int)($comment->is_internal ?? 0) === 1): ?>
                        <mark>Internal note</mark>
                    <?php endif; ?>
                    <small><?= esc($comment->username) ?> · <?= esc($comment->role) ?> · <?= esc($comment->created_at) ?></small>
                </header>
                <div class="rich-text-content"><?= render_rich_text((string)$comment->body) ?></div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($attachments)): ?>
        <h2>Attachments</h2>
        <div class="attachment-grid">
            <?php foreach ($attachments as $attachment): ?>
                <figure class="attachment-card">
                    <a href="<?= ROOT ?>/tickets/attachment/<?= (int)$attachment->id ?>" target="_blank" rel="noopener">
                        <img src="<?= ROOT ?>/tickets/attachment/<?= (int)$attachment->id ?>" alt="<?= esc($attachment->original_name) ?>">
                    </a>
                    <figcaption>
                        <strong><?= esc($attachment->original_name) ?></strong>
                        <small><?= esc($attachment->username) ?> · <?= esc(number_format((int)$attachment->file_size / 1024, 1)) ?> KB · <?= esc($attachment->created_at) ?></small>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($ticket->status === 'closed'): ?>
        <p><mark>This ticket is closed and read-only.</mark></p>
    <?php else: ?>
        <?php
            $messageBody = $formData['body'] ?? old_value('body');
            $selectedMessageStatus = $formData['status'] ?? $ticket->status;
            $messageIsInternal = ($formData['is_internal'] ?? '0') === '1';
            $messageError = $errors['message'] ?? '';
        ?>
        <form method="post" action="<?= ROOT ?>/tickets/message/<?= (int)$ticket->id ?>"
            class="ticket-message-composer"
            data-message-composer
            hx-post="<?= ROOT ?>/tickets/message/<?= (int)$ticket->id ?>"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML">
            <?= csrf_field() ?>

            <?php if (is_staff_or_admin()): ?>
                <label for="message_status">Status</label>
                <select name="status" id="message_status" data-message-status required>
                    <?php if (!in_array($ticket->status, Model\Ticket::STAFF_SET_STATUSES, true)): ?>
                        <option value="<?= esc($ticket->status) ?>" <?= $selectedMessageStatus === $ticket->status ? 'selected' : '' ?>>Keep <?= esc(str_replace('_', ' ', $ticket->status)) ?></option>
                    <?php endif; ?>
                    <?php foreach (Model\Ticket::STAFF_SET_STATUSES as $status): ?>
                        <option value="<?= esc($status) ?>" <?= $selectedMessageStatus === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <label for="body">Message</label>
            <input type="hidden" name="body" id="body" value="<?= esc($messageBody) ?>" data-message-body>
            <trix-editor input="body" class="rich-text-editor <?= !empty($messageError) ? 'is-invalid' : '' ?>" data-message-editor data-upload-url="<?= ROOT ?>/tickets/inlineupload/<?= (int)$ticket->id ?>"></trix-editor>
            <small>Tip: drag or paste screenshots directly into your message.</small>
            <p class="form-error" data-message-error <?= empty($messageError) ? 'hidden' : '' ?>><?= esc($messageError) ?></p>

            <?php if (is_staff_or_admin()): ?>
                <label class="message-private-toggle">
                    <input type="checkbox" name="is_internal" value="1" data-message-private <?= $messageIsInternal ? 'checked' : '' ?>>
                    Private staff-only note
                </label>
                <small data-resolution-public-help hidden>Resolution messages are visible to the requester.</small>
            <?php endif; ?>

            <div class="form-actions">
                <button type="submit"><?= is_staff_or_admin() ? 'Update ticket' : 'Add reply' ?></button>
            </div>
        </form>

        <details class="separate-attachment-panel" hidden>
            <summary>Attach image separately</summary>
            <form method="post" action="<?= ROOT ?>/tickets/upload/<?= (int)$ticket->id ?>" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <label for="attachment">Image file</label>
                <input type="hidden" name="MAX_FILE_SIZE" value="5242880">
                <input type="file" name="attachment" id="attachment" accept="image/jpeg,image/png,image/webp" required>
                <small>Prefer dragging or pasting screenshots into the message above. Use this only when an image needs to stand alone.</small>

                <div class="form-actions">
                    <button type="submit">Upload separate image</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <?php if (is_staff_or_admin()): ?>
        <hr>

        <h2>Staff controls</h2>

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
