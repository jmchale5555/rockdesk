<?php

namespace Model;

class Ticket
{
    use Model;

    public const STATUSES = ['new', 'open', 'in_progress', 'waiting_on_user', 'resolved', 'closed'];
    public const STAFF_SET_STATUSES = ['open', 'in_progress', 'waiting_on_user', 'resolved'];
    public const STATUS_FILTER_ACTIVE = 'active';
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $table = 'tickets';

    protected $allowedColumns = [
        'ticket_number',
        'email_token',
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
            'email_token' => $this->generateEmailToken(),
            'user_id' => $userId,
            'assigned_to' => null,
            'subject' => trim($subject),
            'body' => sanitize_rich_text($body),
            'status' => 'new',
            'priority' => 'normal',
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    public function listForUser(int $userId): array|bool
    {
        return $this->query(
            'select tickets.*, users.username as requester_username, users.name as requester_name, users.email as requester_email,
                    assignee.username as assignee_username, assignee.name as assignee_name, assignee.email as assignee_email
             from tickets
             join users on users.id = tickets.user_id
             left join users assignee on assignee.id = tickets.assigned_to
             where tickets.user_id = :user_id
             order by tickets.updated_at desc, tickets.created_at desc',
            ['user_id' => $userId]
        );
    }

    public function listForStaff(array $filters = []): array|bool
    {
        $where = [];
        $data = [];

        if (($filters['status'] ?? '') === self::STATUS_FILTER_ACTIVE)
        {
            $where[] = "tickets.status not in ('resolved', 'closed')";
        }
        else
        if (!empty($filters['status']) && $this->isValidStatus((string)$filters['status']))
        {
            $where[] = 'tickets.status = :status';
            $data['status'] = $filters['status'];
        }

        if (!empty($filters['priority']) && $this->isValidPriority((string)$filters['priority']))
        {
            $where[] = 'tickets.priority = :priority';
            $data['priority'] = $filters['priority'];
        }

        if (isset($filters['assigned_to']) && $filters['assigned_to'] !== '')
        {
            if ((string)$filters['assigned_to'] === 'unassigned')
            {
                $where[] = 'tickets.assigned_to is null';
            }
            else
            if ((int)$filters['assigned_to'] > 0)
            {
                $where[] = 'tickets.assigned_to = :assigned_to';
                $data['assigned_to'] = (int)$filters['assigned_to'];
            }
        }

        if (!empty($filters['requester']))
        {
            $where[] = 'users.username like :requester';
            $data['requester'] = '%' . trim((string)$filters['requester']) . '%';
        }

        $whereSql = empty($where) ? '' : ' where ' . implode(' and ', $where);

        return $this->query(
            'select tickets.*, users.username as requester_username, users.name as requester_name, users.email as requester_email,
                    assignee.username as assignee_username, assignee.name as assignee_name, assignee.email as assignee_email
             from tickets
             join users on users.id = tickets.user_id
             left join users assignee on assignee.id = tickets.assigned_to
             ' . $whereSql . '
             order by tickets.updated_at desc, tickets.created_at desc',
            $data
        );
    }

    public function findWithRequester(int $id): mixed
    {
        return $this->get_row(
            'select tickets.*, users.username as requester_username, users.name as requester_name, users.email as requester_email,
                    assignee.username as assignee_username, assignee.name as assignee_name, assignee.email as assignee_email
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

        $body = (string)($data['body'] ?? '');
        $plainBody = rich_text_to_plain_text($body);

        if ($plainBody === '')
        {
            $this->errors['body'] = 'Ticket details are required';
        }
        else
        if (mb_strlen(trim($body)) > 20000)
        {
            $this->errors['body'] = 'Ticket details must be 20000 characters or fewer';
        }

        return empty($this->errors);
    }

    public function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public function isValidStaffStatusFilter(string $status): bool
    {
        return $status === '' || $status === self::STATUS_FILTER_ACTIVE || $this->isValidStatus($status);
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

    public function validateResolutionComment(string $status, string $comment): bool
    {
        $this->errors = [];

        $plainComment = rich_text_to_plain_text($comment);

        if ($status === 'resolved' && $plainComment === '')
        {
            $this->errors['resolution_comment'] = 'Resolution comment is required when resolving a ticket';
        }
        else
        if (mb_strlen(trim($comment)) > 10000)
        {
            $this->errors['resolution_comment'] = 'Resolution comment must be 10000 characters or fewer';
        }

        return empty($this->errors);
    }

    public function validateMessageComposer(string $status, string $body, bool $isInternal, bool $ticketChanged): bool
    {
        $this->errors = [];
        $plainBody = rich_text_to_plain_text($body);

        if ($status === 'resolved' && $isInternal)
        {
            $this->errors['message'] = 'Resolution messages cannot be private. Uncheck private note to resolve this ticket.';
        }
        else
        if ($status === 'resolved' && $plainBody === '')
        {
            $this->errors['message'] = 'Resolution text is required when resolving a ticket.';
        }
        else
        if (!$ticketChanged && $plainBody === '')
        {
            $this->errors['message'] = 'Enter a message.';
        }

        if (mb_strlen(trim($body)) > 10000)
        {
            $this->errors['message'] = 'Message must be 10000 characters or fewer';
        }

        return empty($this->errors);
    }

    public function statusAfterStaffEngagement(string $currentStatus, string $requestedStatus, bool $hasEngagement): string
    {
        if ($currentStatus === 'new' && $requestedStatus === 'new' && $hasEngagement)
        {
            return 'open';
        }

        return $requestedStatus;
    }

    public function generateEmailToken(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function ensureEmailToken(mixed $ticket): string
    {
        $token = trim((string)($ticket->email_token ?? ''));
        if ($token !== '')
        {
            return $token;
        }

        $token = $this->generateEmailToken();
        $this->update((int)$ticket->id, [
            'email_token' => $token,
        ]);

        if (is_object($ticket))
        {
            $ticket->email_token = $token;
        }

        return $token;
    }

    public function statusUpdateData(string $oldStatus, string $newStatus): array
    {
        $now = date('Y-m-d H:i:s');
        $data = [
            'status' => $newStatus,
            'updated_at' => $now,
        ];

        if ($newStatus === 'resolved')
        {
            $data['resolved_at'] = $now;
        }
        else
        if ($oldStatus === 'resolved')
        {
            $data['resolved_at'] = null;
        }

        return $data;
    }

    public function canReply(mixed $ticket): bool
    {
        return !empty($ticket) && ($ticket->status ?? '') !== 'closed';
    }

    public function shouldReopenOnUserReply(mixed $ticket, mixed $user): bool
    {
        if (empty($ticket) || empty($user) || is_staff_or_admin($user))
        {
            return false;
        }

        return in_array((string)$ticket->status, ['resolved', 'waiting_on_user'], true);
    }

    public function replyUpdateData(mixed $ticket, mixed $user): array
    {
        $now = date('Y-m-d H:i:s');
        $data = ['updated_at' => $now];

        if ($this->shouldReopenOnUserReply($ticket, $user))
        {
            $data['status'] = 'open';

            if (($ticket->status ?? '') === 'resolved')
            {
                $data['resolved_at'] = null;
            }
        }

        return $data;
    }

    public function listResolvedReadyToClose(int $days): array|bool
    {
        return $this->query(
            'select *
             from tickets
             where status = :status
               and resolved_at is not null
               and resolved_at <= date_sub(now(), interval ' . max(1, $days) . ' day)
             order by resolved_at asc',
            ['status' => 'resolved']
        );
    }

    public function autoCloseUpdateData(): array
    {
        $now = date('Y-m-d H:i:s');

        return [
            'status' => 'closed',
            'closed_at' => $now,
            'updated_at' => $now,
        ];
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
