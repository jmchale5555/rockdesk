<?php include __DIR__ . '/../partials/header.view.php' ?>

<article>
    <header>
        <h1><?= !empty($isStaffQueue) ? 'Ticket queue' : 'My tickets' ?></h1>
        <p><?= !empty($isStaffQueue) ? 'All submitted support tickets.' : 'Track your submitted support tickets.' ?></p>
    </header>

    <?php $success = message(null, true); ?>
    <?php if (!empty($success)): ?>
        <p><mark><?= esc($success) ?></mark></p>
    <?php endif; ?>

    <p>
        <a href="<?= ROOT ?>/tickets/create"
            role="button"
            hx-get="<?= ROOT ?>/tickets/create"
            hx-target="#page-content"
            hx-select="#page-content > *"
            hx-select-oob="#site-nav"
            hx-swap="innerHTML"
            hx-push-url="true">Submit ticket</a>
    </p>

    <?php if (empty($tickets)): ?>
        <p>No tickets found.</p>
    <?php else: ?>
        <figure>
            <table>
                <thead>
                    <tr>
                        <th>Ticket</th>
                        <th>Subject</th>
                        <?php if (!empty($isStaffQueue)): ?>
                            <th>Requester</th>
                        <?php endif; ?>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $row): ?>
                        <tr>
                            <td>
                                <a href="<?= ROOT ?>/tickets/show/<?= (int)$row->id ?>"
                                    hx-get="<?= ROOT ?>/tickets/show/<?= (int)$row->id ?>"
                                    hx-target="#page-content"
                                    hx-select="#page-content > *"
                                    hx-select-oob="#site-nav"
                                    hx-swap="innerHTML"
                                    hx-push-url="true"><?= esc($row->ticket_number) ?></a>
                            </td>
                            <td><?= esc($row->subject) ?></td>
                            <?php if (!empty($isStaffQueue)): ?>
                                <td><?= esc($row->requester_name) ?> <small>(<?= esc($row->requester_username) ?>)</small></td>
                            <?php endif; ?>
                            <td><?= esc(str_replace('_', ' ', $row->status)) ?></td>
                            <td><?= esc($row->priority) ?></td>
                            <td><?= esc($row->assignee_name ?: 'Unassigned') ?></td>
                            <td><?= esc($row->updated_at ?: $row->created_at) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </figure>
    <?php endif; ?>
</article>

<?php include __DIR__ . '/../partials/footer.view.php' ?>
