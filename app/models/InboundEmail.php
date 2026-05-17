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
}
