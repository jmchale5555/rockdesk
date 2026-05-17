<?php

namespace Model;

class InboundEmail
{
    use Model;

    protected $table = 'inbound_emails';

    protected $allowedColumns = [
        'message_id',
        'mailbox_uid',
        'from_email',
        'from_name',
        'subject',
        'received_at',
        'processed_at',
        'status',
        'error',
        'raw_path',
        'ticket_id',
        'created_at',
    ];

    public function normalizeStatus(string $status): string
    {
        return in_array($status, ['pending', 'processed', 'ignored', 'failed'], true) ? $status : 'pending';
    }

    public function findByMessageId(string $messageId): mixed
    {
        $messageId = trim($messageId, " <>\t\n\r\0\x0B");
        if ($messageId === '')
        {
            return false;
        }

        return $this->first(['message_id' => $messageId]);
    }

    public function findByMailboxUid(string $mailboxUid): mixed
    {
        $mailboxUid = trim($mailboxUid);
        if ($mailboxUid === '')
        {
            return false;
        }

        return $this->first(['mailbox_uid' => $mailboxUid]);
    }

    public function isAlreadyTracked(string $messageId, string $mailboxUid): bool
    {
        return (bool)($this->findByMessageId($messageId) ?: $this->findByMailboxUid($mailboxUid));
    }
}
