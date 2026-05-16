<?php

namespace Model;

class Ticket
{
    use Model;

    public const STATUSES = ['new', 'open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'];
    public const STAFF_SET_STATUSES = ['open', 'in_progress', 'waiting_on_user', 'resolved'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'tickets';

    protected $allowedColumns = [
        'ticket_number',
        'user_id',
        'assigned_to',
        'subject',
        'body',
        'status',
        'priority',
        'created_at',
        'updated_at',
        'resolved_at',
        'closed_at',
    ];

    public function validateCreate(array $data): bool
    {
        $this->errors = [];

        if (empty($data['user_id']) || (int)$data['user_id'] < 1)
        {
            $this->errors['user_id'] = 'A requester is required';
        }

        $this->validateSubjectAndBody($data);

        return empty($this->errors);
    }

    public function makeCreateData(int $userId, string $subject, string $body, string $ticketNumber): array
    {
        $now = date('Y-m-d H:i:s');

        return [
            'ticket_number' => $ticketNumber,
            'user_id' => $userId,
            'assigned_to' => null,
            'subject' => trim($subject),
            'body' => trim($body),
            'status' => 'new',
            'priority' => 'normal',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function listForUser(int $userId): array|bool
    {
        return $this->query(
            'select tickets.*, users.username as requester_username, users.name as requester_name,
                    assignee.username as assignee_username, assignee.name as assignee_name
             from tickets
             join users on users.id = tickets.user_id
             left join users assignee on assignee.id = tickets.assigned_to
             where tickets.user_id = :user_id
             order by tickets.updated_at desc, tickets.created_at desc',
            ['user_id' => $userId]
        );
    }

    public function listForStaff(): array|bool
    {
        return $this->query(
            'select tickets.*, users.username as requester_username, users.name as requester_name,
                    assignee.username as assignee_username, assignee.name as assignee_name
             from tickets
             join users on users.id = tickets.user_id
             left join users assignee on assignee.id = tickets.assigned_to
             order by tickets.updated_at desc, tickets.created_at desc'
        );
    }

    public function findWithRequester(int $id): mixed
    {
        return $this->get_row(
            'select tickets.*, users.username as requester_username, users.name as requester_name,
                    assignee.username as assignee_username, assignee.name as assignee_name
             from tickets
             join users on users.id = tickets.user_id
             left join users assignee on assignee.id = tickets.assigned_to
             where tickets.id = :id
             limit 1',
            ['id' => $id]
        );
    }

    public function validateSubjectAndBody(array $data): bool
    {
        if (empty(trim((string)($data['subject'] ?? ''))))
        {
            $this->errors['subject'] = 'Subject is required';
        }
        else
        if (mb_strlen(trim((string)$data['subject'])) > 190)
        {
            $this->errors['subject'] = 'Subject must be 190 characters or fewer';
        }

        if (empty(trim((string)($data['body'] ?? ''))))
        {
            $this->errors['body'] = 'Ticket details are required';
        }

        return empty($this->errors);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function isStaffSettableStatus(string $status): bool
    {
        return in_array($status, self::STAFF_SET_STATUSES, true);
    }

    public function isValidPriority(string $priority): bool
    {
        return in_array($priority, self::PRIORITIES, true);
    }

    public function validateStatus(string $status, bool $staffSettableOnly = false): bool
    {
        $this->errors = [];

        $isValid = $staffSettableOnly
            ? $this->isStaffSettableStatus($status)
            : $this->isValidStatus($status);

        if (!$isValid)
        {
            $this->errors['status'] = 'Choose a valid status';
        }

        return empty($this->errors);
    }

    public function validatePriority(string $priority): bool
    {
        $this->errors = [];

        if (!$this->isValidPriority($priority))
        {
            $this->errors['priority'] = 'Choose a valid priority';
        }

        return empty($this->errors);
    }

    public function generateTicketNumber(int $nextId, ?int $year = null): string
    {
        $year = $year ?: (int)date('Y');

        return sprintf('TCK-%d-%06d', $year, $nextId);
    }

    public function nextTicketNumber(): string
    {
        $row = $this->get_row('select coalesce(max(id), 0) + 1 as next_id from tickets');

        return $this->generateTicketNumber((int)($row->next_id ?? 1));
    }
}
