<?php

namespace Core;

use Model\Ticket;
use Model\TicketEmailMessage;
use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class TicketNotifier
{
    public function __construct(
        private ?Mailer $mailer = null,
        private ?User $users = null,
        private ?Ticket $tickets = null,
        private ?TicketEmailMessage $emailMessages = null
    ) {
        $this->mailer ??= new Mailer;
        $this->users ??= new User;
        $this->tickets ??= new Ticket;
        $this->emailMessages ??= new TicketEmailMessage;
    }

    public function notifyTicketCreated(mixed $ticket, mixed $actor): void
    {
        $recipients = $this->cleanRecipients($this->users->listActiveStaffWithEmail() ?: [], (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'ticket_created', 'New support ticket created', [
            'A new support ticket has been submitted.',
            'Requester: ' . (string)($ticket->requester_name ?? $actor->name ?? 'Unknown'),
            'Status: ' . $this->label((string)($ticket->status ?? 'new')),
        ]);
    }

    public function notifyStaffReply(mixed $ticket, mixed $actor, string $body): void
    {
        $recipient = $this->requesterRecipient($ticket, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipient, $ticket, 'staff_reply', 'New reply on your support ticket', [
            (string)($actor->name ?? 'Support') . ' replied to your ticket.',
            $this->summary($body),
        ]);
    }

    public function notifyUserReply(mixed $ticket, mixed $actor, string $body): void
    {
        $recipients = [];
        $assignedTo = (int)($ticket->assigned_to ?? 0);

        if ($assignedTo > 0)
        {
            $assignee = $this->users->findActiveUserWithEmail($assignedTo);
            $recipients = $assignee ? [$assignee] : [];
        }
        else
        {
            $recipients = $this->users->listActiveStaffWithEmail() ?: [];
        }

        $recipients = $this->cleanRecipients($recipients, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'user_reply', 'Requester replied to a support ticket', [
            (string)($actor->name ?? 'The requester') . ' replied to a ticket.',
            $this->summary($body),
        ]);
    }

    public function notifyAssignmentChanged(mixed $ticket, int $assignedTo, mixed $actor): void
    {
        if ($assignedTo < 1)
        {
            return;
        }

        $assignee = $this->users->findActiveUserWithEmail($assignedTo);
        $recipients = $this->cleanRecipients($assignee ? [$assignee] : [], (int)($actor->id ?? 0));
        $this->sendTicketMail($recipients, $ticket, 'assignment_changed', 'Support ticket assigned to you', [
            'A support ticket has been assigned to you.',
            'Assigned by: ' . (string)($actor->name ?? 'Unknown'),
        ]);
    }

    public function notifyResolved(mixed $ticket, mixed $actor, string $body): void
    {
        $recipient = $this->requesterRecipient($ticket, (int)($actor->id ?? 0));
        $this->sendTicketMail($recipient, $ticket, 'ticket_resolved', 'Your support ticket was resolved', [
            'Your support ticket has been marked resolved.',
            $this->summary($body),
        ]);
    }

    public function cleanRecipients(array $users, int $excludeUserId = 0): array
    {
        $recipients = [];
        $seen = [];

        foreach ($users as $user)
        {
            $id = (int)(is_array($user) ? ($user['id'] ?? 0) : ($user->id ?? 0));
            $email = trim((string)(is_array($user) ? ($user['email'] ?? '') : ($user->email ?? '')));
            $name = trim((string)(is_array($user) ? ($user['name'] ?? '') : ($user->name ?? '')));
            $key = strtolower($email);

            if (($excludeUserId > 0 && $id === $excludeUserId) || !filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = true;
            $recipients[] = ['id' => $id, 'name' => $name, 'email' => $email];
        }

        return $recipients;
    }

    private function requesterRecipient(mixed $ticket, int $excludeUserId = 0): array
    {
        return $this->cleanRecipients([
            [
                'id' => (int)($ticket->user_id ?? 0),
                'name' => (string)($ticket->requester_name ?? ''),
                'email' => (string)($ticket->requester_email ?? ''),
            ],
        ], $excludeUserId);
    }

    public function replyToAddress(string $token): string
    {
        $address = defined('INBOUND_MAIL_ADDRESS') && INBOUND_MAIL_ADDRESS !== '' ? INBOUND_MAIL_ADDRESS : (defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : '');
        if (!filter_var($address, FILTER_VALIDATE_EMAIL))
        {
            return '';
        }

        if (!defined('INBOUND_MAIL_PLUS_ADDRESSING_ENABLED') || !INBOUND_MAIL_PLUS_ADDRESSING_ENABLED)
        {
            return $address;
        }

        [$local, $domain] = explode('@', $address, 2);
        $delimiter = defined('INBOUND_MAIL_PLUS_DELIMITER') ? INBOUND_MAIL_PLUS_DELIMITER : '+';

        return $local . $delimiter . $token . '@' . $domain;
    }

    public function messageId(mixed $ticket, string $emailType): string
    {
        $host = parse_url(ROOT, PHP_URL_HOST) ?: 'localhost';
        $ticketId = (int)($ticket->id ?? 0);

        return 'rockdesk-' . $ticketId . '-' . $emailType . '-' . bin2hex(random_bytes(8)) . '@' . $host;
    }

    public function loopPreventionHeaders(): array
    {
        return [
            'Auto-Submitted' => 'auto-generated',
            'X-Auto-Response-Suppress' => 'All',
            'X-Loop' => 'rockdesk',
        ];
    }

    private function sendTicketMail(array $recipients, mixed $ticket, string $emailType, string $subject, array $lines): void
    {
        if (empty($recipients))
        {
            return;
        }

        $token = $this->tickets->ensureEmailToken($ticket);
        $ticketNumber = (string)($ticket->ticket_number ?? 'Ticket');
        $ticketSubject = (string)($ticket->subject ?? 'Support ticket');
        $url = ROOT . '/tickets/show/' . (int)($ticket->id ?? 0);
        $htmlLines = array_map(fn ($line) => '<p>' . esc($line) . '</p>', array_filter($lines));
        $html = '<h1>' . esc($ticketNumber . ': ' . $ticketSubject) . '</h1>'
            . implode('', $htmlLines)
            . '<p><a href="' . esc($url) . '">View ticket</a></p>'
            . '<!-- rockdesk-ticket-token: ' . esc($token) . ' -->';
        $text = $ticketNumber . ': ' . $ticketSubject . "\n\n"
            . implode("\n\n", array_filter($lines))
            . "\n\nView ticket: " . $url;
        $messageId = $this->messageId($ticket, $emailType);
        $sent = $this->mailer->send(
            $recipients,
            '[' . APP_NAME . '] ' . $subject . ': ' . $ticketNumber,
            $html,
            $text,
            [
                'reply_to' => $this->replyToAddress($token),
                'headers' => $this->loopPreventionHeaders(),
                'message_id' => $messageId,
            ]
        );

        if ($sent)
        {
            $this->emailMessages->insert([
                'ticket_id' => (int)$ticket->id,
                'message_id' => $messageId,
                'email_type' => $emailType,
                'recipients' => json_encode(array_values(array_map(fn ($recipient) => $recipient['email'] ?? '', $recipients))),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function summary(string $body): string
    {
        $plain = rich_text_to_plain_text($body);

        if ($plain === '')
        {
            return '';
        }

        return mb_strlen($plain) > 500 ? mb_substr($plain, 0, 497) . '...' : $plain;
    }

    private function label(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }
}
