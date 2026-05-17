<?php

namespace Model;

class TicketEmailMessage
{
    use Model;

    protected $table = 'ticket_email_messages';

    protected $allowedColumns = [
        'ticket_id',
        'message_id',
        'email_type',
        'recipients',
        'created_at',
    ];
}
