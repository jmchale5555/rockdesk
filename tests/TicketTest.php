<?php

use Model\Ticket;
use Model\TicketComment;
use Model\TicketEvent;
use PHPUnit\Framework\TestCase;

final class TicketTest extends TestCase
{
    public function testTicketCreateValidationRequiresRequesterSubjectAndBody(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateCreate([
            'user_id' => 0,
            'subject' => '',
            'body' => '',
        ]));
        $this->assertArrayHasKey('user_id', $ticket->errors);
        $this->assertArrayHasKey('subject', $ticket->errors);
        $this->assertArrayHasKey('body', $ticket->errors);
    }

    public function testTicketCreateValidationAcceptsValidTicket(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->validateCreate([
            'user_id' => 12,
            'subject' => 'Laptop will not boot',
            'body' => 'It stopped booting this morning.',
        ]));
    }

    public function testTicketCreateDataUsesMvpDefaults(): void
    {
        $ticket = new Ticket;
        $data = $ticket->makeCreateData(12, '  Laptop issue  ', '  It stopped booting.  ', 'TCK-2026-000001');

        $this->assertSame('TCK-2026-000001', $data['ticket_number']);
        $this->assertSame(12, $data['user_id']);
        $this->assertNull($data['assigned_to']);
        $this->assertSame('Laptop issue', $data['subject']);
        $this->assertSame('It stopped booting.', $data['body']);
        $this->assertSame('new', $data['status']);
        $this->assertSame('normal', $data['priority']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function testTicketSubjectIsLimitedToOneHundredNinetyCharacters(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateCreate([
            'user_id' => 12,
            'subject' => str_repeat('a', 191),
            'body' => 'Details provided.',
        ]));
        $this->assertArrayHasKey('subject', $ticket->errors);
    }

    public function testTicketStatusValidationIncludesClosedButStaffCannotSetClosed(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->isValidStatus('new'));
        $this->assertTrue($ticket->isValidStatus('closed'));
        $this->assertFalse($ticket->isStaffSettableStatus('closed'));
        $this->assertFalse($ticket->isStaffSettableStatus('new'));
        $this->assertTrue($ticket->isStaffSettableStatus('resolved'));
    }

    public function testTicketPriorityValidationAllowsKnownPrioritiesOnly(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->validatePriority('normal'));
        $this->assertFalse($ticket->validatePriority('critical'));
        $this->assertArrayHasKey('priority', $ticket->errors);
    }

    public function testResolutionCommentIsRequiredOnlyWhenResolving(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateResolutionComment('resolved', ''));
        $this->assertArrayHasKey('resolution_comment', $ticket->errors);

        $this->assertTrue($ticket->validateResolutionComment('resolved', 'Issue fixed by reinstalling the driver.'));
        $this->assertTrue($ticket->validateResolutionComment('in_progress', ''));
    }

    public function testStatusUpdateDataSetsAndClearsResolvedAt(): void
    {
        $ticket = new Ticket;

        $resolvedData = $ticket->statusUpdateData('in_progress', 'resolved');
        $this->assertSame('resolved', $resolvedData['status']);
        $this->assertArrayHasKey('resolved_at', $resolvedData);
        $this->assertNotNull($resolvedData['resolved_at']);

        $reopenedData = $ticket->statusUpdateData('resolved', 'open');
        $this->assertSame('open', $reopenedData['status']);
        $this->assertArrayHasKey('resolved_at', $reopenedData);
        $this->assertNull($reopenedData['resolved_at']);
    }

    public function testTicketNumberUsesHumanFriendlyFormat(): void
    {
        $ticket = new Ticket;

        $this->assertSame('TCK-2026-000042', $ticket->generateTicketNumber(42, 2026));
    }

    public function testTicketCommentValidationRequiresTicketUserAndBody(): void
    {
        $comment = new TicketComment;

        $this->assertFalse($comment->validateCreate([
            'ticket_id' => 0,
            'user_id' => 0,
            'body' => '',
        ]));
        $this->assertArrayHasKey('ticket_id', $comment->errors);
        $this->assertArrayHasKey('user_id', $comment->errors);
        $this->assertArrayHasKey('body', $comment->errors);
    }

    public function testTicketCommentValidationAcceptsPublicComment(): void
    {
        $comment = new TicketComment;

        $this->assertTrue($comment->validateCreate([
            'ticket_id' => 4,
            'user_id' => 7,
            'body' => 'I have tried restarting.',
            'is_internal' => 0,
        ]));
    }

    public function testTicketEventValidationAcceptsKnownEventTypesOnly(): void
    {
        $event = new TicketEvent;

        $this->assertTrue($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'created',
        ]));

        $this->assertFalse($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'invented_event',
        ]));
        $this->assertArrayHasKey('event_type', $event->errors);
    }
}
