<?php

declare(strict_types=1);

define('ROOTPATH', __DIR__ . '/../public/');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../app/core/config.php';
require __DIR__ . '/../app/core/functions.php';
require __DIR__ . '/../app/core/Database.php';
require __DIR__ . '/../app/core/Model.php';
require __DIR__ . '/../app/core/Mailer.php';
require __DIR__ . '/../app/core/TicketNotifier.php';
require __DIR__ . '/../app/core/InboundMessage.php';
require __DIR__ . '/../app/core/InboundMailCleaner.php';
require __DIR__ . '/../app/core/InboundMailInspector.php';
require __DIR__ . '/../app/core/InboundTicketImporter.php';
require __DIR__ . '/../app/core/ImapInboundMailSource.php';
require __DIR__ . '/../app/models/User.php';
require __DIR__ . '/../app/models/Ticket.php';
require __DIR__ . '/../app/models/TicketComment.php';
require __DIR__ . '/../app/models/TicketEvent.php';
require __DIR__ . '/../app/models/TicketEmailMessage.php';
require __DIR__ . '/../app/models/InboundEmail.php';
require __DIR__ . '/../app/models/TicketAttachment.php';

use Core\ImapInboundMailSource;
use Core\InboundTicketImporter;

if (!INBOUND_MAIL_ENABLED)
{
    echo "inbound mail disabled\n";
    exit(0);
}

if (INBOUND_MAIL_DRIVER !== 'imap')
{
    fwrite(STDERR, "unsupported inbound mail driver: " . INBOUND_MAIL_DRIVER . "\n");
    exit(1);
}

$lockPath = __DIR__ . '/../storage/import-mail.lock';
$lock = fopen($lockPath, 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB))
{
    echo "mail import already running\n";
    exit(0);
}

$source = new ImapInboundMailSource;
if (!$source->extensionLoaded())
{
    fwrite(STDERR, "PHP imap extension is not installed\n");
    exit(1);
}

if (!$source->isConfigured())
{
    fwrite(STDERR, "IMAP inbound mail is not fully configured\n");
    exit(1);
}

if (!$source->open())
{
    fwrite(STDERR, "Could not connect to IMAP mailbox\n");
    exit(1);
}

$importer = new InboundTicketImporter;
$processed = 0;
$failed = 0;
$ignored = 0;

try
{
    foreach ($source->fetch(INBOUND_IMAP_MAX_MESSAGES) as $messageNumber => $message)
    {
        $result = $importer->import($message);
        $status = (string)($result['status'] ?? 'failed');

        if ($status === 'failed')
        {
            $source->markFailed((int)$messageNumber);
            $failed++;
        }
        else
        {
            $source->markProcessed((int)$messageNumber);
            $status === 'ignored' ? $ignored++ : $processed++;
        }

        $detail = $result['action'] ?? $result['reason'] ?? 'done';
        echo "{$status}: {$detail}\n";
    }
}
finally
{
    $source->close();
    flock($lock, LOCK_UN);
    fclose($lock);
}

echo "mail import complete: {$processed} processed, {$ignored} ignored, {$failed} failed\n";
