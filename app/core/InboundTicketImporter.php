<?php

namespace Core;

use Model\InboundEmail;
use Model\Ticket;
use Model\TicketComment;
use Model\TicketEmailMessage;
use Model\TicketEvent;
use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class InboundTicketImporter
{
    public function __construct(
        private ?Ticket $tickets = null,
        private ?TicketComment $comments = null,
        private ?TicketEvent $events = null,
        private ?TicketEmailMessage $emailMessages = null,
        private ?InboundEmail $inboundEmails = null,
        private ?User $users = null,
        private ?InboundMailCleaner $cleaner = null,
        private ?InboundMailInspector $inspector = null,
        private ?InboundAttachmentImporter $attachmentImporter = null,
        private ?TicketNotifier $notifier = null,
    ) {
        $this->tickets ??= new Ticket;
        $this->comments ??= new TicketComment;
        $this->events ??= new TicketEvent;
        $this->emailMessages ??= new TicketEmailMessage;
        $this->inboundEmails ??= new InboundEmail;
        $this->users ??= new User;
        $this->cleaner ??= new InboundMailCleaner;
        $this->inspector ??= new InboundMailInspector;
        $this->attachmentImporter ??= new InboundAttachmentImporter;
        $this->notifier ??= new TicketNotifier;
    }

    public function import(InboundMessage $message): array
    {
        if ($this->inboundEmails->isAlreadyTracked($message->messageId, $message->mailboxUid))
        {
            return ['status' => 'ignored', 'reason' => 'duplicate message'];
        }

        $ignoreReason = $this->inspector->ignoreReason($message);
        if ($ignoreReason !== '')
        {
            $this->recordInbound($message, 'ignored', $ignoreReason);
            return ['status' => 'ignored', 'reason' => $ignoreReason];
        }

        $body = $this->messageBody($message);
        if (rich_text_to_plain_text($body) === '')
        {
            $this->recordInbound($message, 'failed', 'message body is empty');
            return ['status' => 'failed', 'reason' => 'message body is empty'];
        }

        $actor = $this->users->findActiveByEmail($message->fromEmail);
        $ticket = $this->matchTicket($message);

        if ($ticket)
        {
            return $this->importReply($message, $ticket, $actor, $body);
        }

        return $this->importNewTicket($message, $actor, $body);
    }

    public function messageBody(InboundMessage $message): string
    {
        if (trim($message->textBody) !== '')
        {
            return sanitize_rich_text(nl2br(esc($this->cleaner->cleanText($message->textBody))));
        }

        return $this->cleaner->cleanHtml($message->htmlBody);
    }

    public function extractReplyToken(InboundMessage $message): string
    {
        foreach ($message->allRecipients() as $recipient)
        {
            $token = $this->tokenFromAddress($recipient);
            if ($token !== '')
            {
                return $token;
            }
        }

        $body = $message->htmlBody . "\n" . $message->textBody;
        if (preg_match('/rockdesk-ticket-token:\s*([a-f0-9]{32})/i', $body, $matches))
        {
            return strtolower($matches[1]);
        }

        return '';
    }

    private function importReply(InboundMessage $message, mixed $ticket, mixed $actor, string $body): array
    {
        if (!$actor || !$this->canSenderReply($ticket, $actor))
        {
            $this->recordInbound($message, 'failed', 'sender cannot access matched ticket', (int)$ticket->id);
            return ['status' => 'failed', 'reason' => 'sender cannot access matched ticket', 'ticket_id' => (int)$ticket->id];
        }

        if (!$this->tickets->canReply($ticket))
        {
            $this->recordInbound($message, 'failed', 'closed tickets are read-only', (int)$ticket->id);
            return ['status' => 'failed', 'reason' => 'closed tickets are read-only', 'ticket_id' => (int)$ticket->id];
        }

        $this->comments->insert([
            'ticket_id' => (int)$ticket->id,
            'user_id' => (int)$actor->id,
            'body' => $body,
            'is_internal' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $newStatus = $this->statusAfterEmailReply($ticket, $actor);
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        if ($newStatus !== (string)$ticket->status)
        {
            $update = array_merge($update, $this->tickets->statusUpdateData((string)$ticket->status, $newStatus));
        }
        $this->tickets->update((int)$ticket->id, $update);

        $this->recordEvent((int)$ticket->id, (int)$actor->id, 'commented', null, null, $body);
        if ($newStatus !== (string)$ticket->status)
        {
            $this->recordEvent((int)$ticket->id, (int)$actor->id, is_staff_or_admin($actor) ? 'status_changed' : 'reopened_by_user_reply', (string)$ticket->status, $newStatus);
        }

        $attachmentCount = $this->attachmentImporter->importForTicket($ticket, (int)$actor->id, $message->attachments);

        $updatedTicket = $this->tickets->findWithRequester((int)$ticket->id) ?: $ticket;
        if (is_staff_or_admin($actor))
        {
            $this->notifier->notifyStaffReply($updatedTicket, $actor, $body);
        }
        else
        {
            $this->notifier->notifyUserReply($updatedTicket, $actor, $body);
        }

        $this->recordInbound($message, 'processed', null, (int)$ticket->id);

        return ['status' => 'processed', 'action' => 'reply_created', 'ticket_id' => (int)$ticket->id, 'attachments_imported' => $attachmentCount];
    }

    private function importNewTicket(InboundMessage $message, mixed $actor, string $body): array
    {
        $guest = null;
        $isPending = false;

        if (!$actor)
        {
            $guest = $this->users->emailGuestUser();
            if (!$guest)
            {
                $this->recordInbound($message, 'failed', 'email guest user is missing');
                return ['status' => 'failed', 'reason' => 'email guest user is missing'];
            }

            $actor = $guest;
            $isPending = true;
        }

        $ticketNumber = $this->tickets->nextTicketNumber();
        $data = $this->tickets->makeEmailCreateData(
            (int)$actor->id,
            $this->subjectForTicket($message),
            $body,
            $ticketNumber,
            $message->fromName,
            $message->fromEmail,
            $isPending
        );

        if (!$this->tickets->validateCreate($data))
        {
            $this->recordInbound($message, 'failed', implode(' | ', $this->tickets->errors));
            return ['status' => 'failed', 'reason' => implode(' | ', $this->tickets->errors)];
        }

        $this->tickets->insert($data);
        $created = $this->tickets->findByTicketNumber($ticketNumber);
        if (!$created)
        {
            $this->recordInbound($message, 'failed', 'created ticket could not be loaded');
            return ['status' => 'failed', 'reason' => 'created ticket could not be loaded'];
        }

        $this->recordEvent((int)$created->id, $isPending ? null : (int)$actor->id, 'created', null, 'new', $body);
        $this->recordEvent((int)$created->id, $isPending ? null : (int)$actor->id, 'email_imported', null, $message->fromEmail);
        $attachmentCount = $this->attachmentImporter->importForTicket($created, (int)$actor->id, $message->attachments);
        $this->recordInbound($message, 'processed', null, (int)$created->id);
        $this->notifier->notifyTicketCreated($created, $actor);

        return ['status' => 'processed', 'action' => $isPending ? 'pending_ticket_created' : 'ticket_created', 'ticket_id' => (int)$created->id, 'attachments_imported' => $attachmentCount];
    }

    private function matchTicket(InboundMessage $message): mixed
    {
        $token = $this->extractReplyToken($message);
        if ($token !== '')
        {
            $ticket = $this->tickets->findByEmailToken($token);
            if ($ticket)
            {
                return $ticket;
            }
        }

        $ticketId = $this->emailMessages->findTicketIdByHeaderReference($message->header('In-Reply-To') . ' ' . $message->header('References'));
        if ($ticketId > 0)
        {
            $ticket = $this->tickets->findWithRequester($ticketId);
            if ($ticket)
            {
                return $ticket;
            }
        }

        if (preg_match('/\b(TCK-\d{4}-\d{6})\b/i', $message->subject, $matches))
        {
            return $this->tickets->findByTicketNumber(strtoupper($matches[1]));
        }

        return false;
    }

    private function tokenFromAddress(string $address): string
    {
        $support = defined('INBOUND_MAIL_ADDRESS') ? strtolower(INBOUND_MAIL_ADDRESS) : '';
        if (!filter_var($support, FILTER_VALIDATE_EMAIL) || !defined('INBOUND_MAIL_PLUS_ADDRESSING_ENABLED') || !INBOUND_MAIL_PLUS_ADDRESSING_ENABLED)
        {
            return '';
        }

        [$supportLocal, $supportDomain] = explode('@', $support, 2);
        [$local, $domain] = explode('@', strtolower($address), 2);
        $delimiter = preg_quote(defined('INBOUND_MAIL_PLUS_DELIMITER') ? INBOUND_MAIL_PLUS_DELIMITER : '+', '/');

        if ($domain !== $supportDomain || !preg_match('/^' . preg_quote($supportLocal, '/') . $delimiter . '([a-f0-9]{32})$/', $local, $matches))
        {
            return '';
        }

        return strtolower($matches[1]);
    }

    private function canSenderReply(mixed $ticket, mixed $actor): bool
    {
        return is_staff_or_admin($actor) || (int)$ticket->user_id === (int)$actor->id;
    }

    private function statusAfterEmailReply(mixed $ticket, mixed $actor): string
    {
        if (is_staff_or_admin($actor))
        {
            return $this->tickets->statusAfterStaffEngagement((string)$ticket->status, (string)$ticket->status, true);
        }

        $update = $this->tickets->replyUpdateData($ticket, $actor);

        return (string)($update['status'] ?? $ticket->status);
    }

    private function subjectForTicket(InboundMessage $message): string
    {
        $subject = trim(preg_replace('/^(re|fw|fwd):\s*/i', '', $message->subject) ?? $message->subject);

        return mb_substr($subject !== '' ? $subject : 'Email support request', 0, 190);
    }

    private function recordInbound(InboundMessage $message, string $status, ?string $error = null, ?int $ticketId = null): void
    {
        try
        {
            $this->inboundEmails->insert([
                'message_id' => $message->messageId !== '' ? trim($message->messageId, '<>') : null,
                'mailbox_uid' => $message->mailboxUid !== '' ? $message->mailboxUid : null,
                'from_email' => $message->fromEmail,
                'from_name' => $message->fromName !== '' ? $message->fromName : null,
                'subject' => $message->subject !== '' ? mb_substr($message->subject, 0, 255) : null,
                'received_at' => $message->receivedAt,
                'processed_at' => date('Y-m-d H:i:s'),
                'status' => $this->inboundEmails->normalizeStatus($status),
                'error' => $error !== null ? mb_substr($error, 0, 5000) : null,
                'ticket_id' => $ticketId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        catch (\Throwable $e)
        {
            error_log('Inbound email tracking failed: ' . $e->getMessage());
        }
    }

    private function recordEvent(int $ticketId, ?int $userId, string $eventType, ?string $oldValue = null, ?string $newValue = null, ?string $body = null): void
    {
        $this->events->insert([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'event_type' => $eventType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
