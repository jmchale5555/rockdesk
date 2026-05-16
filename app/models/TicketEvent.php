<?php

namespace Model;

class TicketEvent
{
    use Model;

    public const EVENT_TYPES = [
        'created',
        'commented',
        'status_changed',
        'priority_changed',
        'assigned',
        'resolved',
        'closed_automatically',
        'reopened_by_user_reply',
        'internal_note_added',
        'attachment_uploaded',
    ];

    protected $table = 'ticket_events';

    protected $allowedColumns = [
        'ticket_id',
        'user_id',
        'event_type',
        'old_value',
        'new_value',
        'body',
        'created_at',
    ];

    public function isValidEventType(string $eventType): bool
    {
        return in_array($eventType, self::EVENT_TYPES, true);
    }

    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        if (empty($data['ticket_id']) || (int)$data['ticket_id'] < 1)
        {
            $this->errors['ticket_id'] = 'A ticket is required';
        }

        if (empty($data['event_type']) || !$this->isValidEventType((string)$data['event_type']))
        {
            $this->errors['event_type'] = 'Choose a valid event type';
        }

        return empty($this->errors);
    }
}
