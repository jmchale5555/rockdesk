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

    <?php if (is_staff_or_admin() && (int)($ticket->is_pending_requester ?? 0) === 1): ?>
        <section class="pending-requester-panel">
            <header>
                <h2>Pending Email Requester</h2>
                <mark class="status-pill pending-requester-pill">Unverified</mark>
            </header>
            <p>This ticket was created from an email address that is not linked to an active user account.</p>
            <dl>
                <dt>Name</dt>
                <dd><?= esc($ticket->email_requester_name ?: 'Unknown') ?></dd>
                <dt>Email</dt>
                <dd><?= esc($ticket->email_requester_email ?: 'Unknown') ?></dd>
            </dl>

            <?php if (is_admin()): ?>
                <div class="pending-requester-actions">
                    <form method="post" action="<?= ROOT ?>/tickets/linkrequester/<?= (int)$ticket->id ?>"
                        hx-post="<?= ROOT ?>/tickets/linkrequester/<?= (int)$ticket->id ?>"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML">
                        <?= csrf_field() ?>
                        <label for="pending_user_id">Link to existing user
                            <select name="user_id" id="pending_user_id" required>
                                <option value="">Choose user</option>
                                <?php foreach (($requesterUsers ?? []) as $requesterUser): ?>
                                    <option value="<?= (int)$requesterUser->id ?>"><?= esc($requesterUser->name) ?> (<?= esc($requesterUser->username) ?><?= !empty($requesterUser->email) ? ' - ' . esc($requesterUser->email) : '' ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button type="submit">Link Requester</button>
                    </form>

                    <form method="post" action="<?= ROOT ?>/tickets/createrequester/<?= (int)$ticket->id ?>"
                        hx-post="<?= ROOT ?>/tickets/createrequester/<?= (int)$ticket->id ?>"
                        hx-target="#page-content"
                        hx-select="#page-content > *"
                        hx-select-oob="#site-nav"
                        hx-swap="innerHTML">
                        <?= csrf_field() ?>
                        <label for="pending_name">Create user name
                            <input type="text" name="name" id="pending_name" value="<?= esc($ticket->email_requester_name ?: '') ?>" required>
                        </label>
                        <label for="pending_email">Create user email
                            <input type="email" name="email" id="pending_email" value="<?= esc($ticket->email_requester_email ?: '') ?>" required>
                        </label>
                        <label for="pending_username">Username
                            <input type="text" name="username" id="pending_username" value="<?= esc($pendingRequesterUsername ?? '') ?>" required>
                        </label>
                        <label for="pending_password">Temporary password
                            <input type="text" name="password" id="pending_password" required>
                        </label>
                        <button type="submit">Create User And Link</button>
                    </form>
                </div>
            <?php else: ?>
                <p><small>An admin can link this requester to an existing user or create a new user account.</small></p>
            <?php endif; ?>
        </section>
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
            <dd>
                <?= esc($ticket->requester_name) ?>
                <?php if ((int)($ticket->is_pending_requester ?? 0) === 1): ?>
                    <small><?= esc($ticket->requester_email ?? '') ?></small>
                <?php else: ?>
                    <small>(<?= esc($ticket->requester_username) ?>)</small>
                <?php endif; ?>
            </dd>
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
            $selectedPriority = $formData['priority'] ?? $ticket->priority;
            $selectedAssignedTo = (int)($formData['assigned_to'] ?? (int)($ticket->assigned_to ?? 0));
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
                <div class="ticket-control-row">
                    <label for="message_status">Status
                        <select name="status" id="message_status" data-message-status required>
                            <?php if (!in_array($ticket->status, Model\Ticket::STAFF_SET_STATUSES, true)): ?>
                                <option value="<?= esc($ticket->status) ?>" <?= $selectedMessageStatus === $ticket->status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $ticket->status)) ?></option>
                            <?php endif; ?>
                            <?php foreach (Model\Ticket::STAFF_SET_STATUSES as $status): ?>
                                <option value="<?= esc($status) ?>" <?= $selectedMessageStatus === $status ? 'selected' : '' ?>><?= esc(str_replace('_', ' ', $status)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label for="priority">Priority
                        <select name="priority" id="priority" required>
                            <?php foreach (Model\Ticket::PRIORITIES as $priority): ?>
                                <option value="<?= esc($priority) ?>" <?= $selectedPriority === $priority ? 'selected' : '' ?>><?= esc($priority) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label for="assigned_to">Assigned staff
                        <select name="assigned_to" id="assigned_to">
                            <option value="0">Unassigned</option>
                            <?php foreach ($staffUsers as $staff): ?>
                                <option value="<?= (int)$staff->id ?>" <?= $selectedAssignedTo === (int)$staff->id ? 'selected' : '' ?>><?= esc($staff->name) ?> (<?= esc($staff->username) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>
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
                <button type="submit"><?= is_staff_or_admin() ? 'Update Ticket' : 'Add reply' ?></button>
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

</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
