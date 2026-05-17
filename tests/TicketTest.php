<?php

use Model\Ticket;
use Model\TicketAttachment;
use Model\TicketComment;
use Model\TicketEvent;
use Core\TicketNotifier;
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
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $data['email_token']);
        $this->assertSame(12, $data['user_id']);
        $this->assertNull($data['assigned_to']);
        $this->assertSame('Laptop issue', $data['subject']);
        $this->assertSame('<p>It stopped booting.</p>', $data['body']);
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

    public function testTicketBodyHasPracticalLengthLimit(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateCreate([
            'user_id' => 12,
            'subject' => 'Long body',
            'body' => str_repeat('a', 20001),
        ]));
        $this->assertArrayHasKey('body', $ticket->errors);
    }

    public function testTicketBodyValidationRejectsEmptyRichText(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateCreate([
            'user_id' => 12,
            'subject' => 'Empty body',
            'body' => '<div><br></div>',
        ]));
        $this->assertArrayHasKey('body', $ticket->errors);
    }

    public function testRichTextSanitizerRemovesUnsafeHtml(): void
    {
        $html = sanitize_rich_text('<div>Hello <script>alert(1)</script><a href="javascript:alert(2)">bad</a></div>');

        $this->assertStringContainsString('Hello', $html);
        $this->assertStringNotContainsString('script', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testRichTextSanitizerOnlyAllowsLocalInlineAttachmentImages(): void
    {
        $html = sanitize_rich_text(
            '<p>Good</p><img src="http://localhost/tickets/attachment/12" alt="ok"><img src="https://example.com/bad.png" alt="bad">'
        );

        $this->assertStringContainsString('http://localhost/tickets/attachment/12', $html);
        $this->assertStringNotContainsString('https://example.com/bad.png', $html);
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

    public function testStaffStatusFilterAllowsActiveAnyAndExactStatuses(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->isValidStaffStatusFilter(Ticket::STATUS_FILTER_ACTIVE));
        $this->assertTrue($ticket->isValidStaffStatusFilter(''));
        $this->assertTrue($ticket->isValidStaffStatusFilter('resolved'));
        $this->assertFalse($ticket->isValidStaffStatusFilter('not-a-status'));
    }

    public function testTicketNotifierRecipientCleanupFiltersInvalidExcludedAndDuplicateEmails(): void
    {
        $notifier = new TicketNotifier;

        $recipients = $notifier->cleanRecipients([
            ['id' => 1, 'name' => 'Actor', 'email' => 'actor@example.com'],
            ['id' => 2, 'name' => 'Valid', 'email' => 'valid@example.com'],
            ['id' => 3, 'name' => 'Duplicate', 'email' => 'VALID@example.com'],
            ['id' => 4, 'name' => 'Bad', 'email' => 'not-an-email'],
            (object)['id' => 5, 'name' => 'Other', 'email' => 'other@example.com'],
        ], 1);

        $this->assertSame([
            ['id' => 2, 'name' => 'Valid', 'email' => 'valid@example.com'],
            ['id' => 5, 'name' => 'Other', 'email' => 'other@example.com'],
        ], $recipients);
    }

    public function testTicketNotifierBuildsPlusAddressReplyToWhenEnabled(): void
    {
        $notifier = new TicketNotifier;

        $this->assertSame('support+abc123@example.com', $notifier->replyToAddress('abc123'));
    }

    public function testTicketNotifierAddsLoopPreventionHeaders(): void
    {
        $notifier = new TicketNotifier;

        $this->assertSame([
            'Auto-Submitted' => 'auto-generated',
            'X-Auto-Response-Suppress' => 'All',
            'X-Loop' => 'rockdesk',
        ], $notifier->loopPreventionHeaders());
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

        $this->assertFalse($ticket->validateResolutionComment('resolved', str_repeat('a', 10001)));
        $this->assertArrayHasKey('resolution_comment', $ticket->errors);
    }

    public function testMessageComposerRequiresResolutionTextWhenResolving(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateMessageComposer('resolved', '<div><br></div>', false, true));
        $this->assertSame('Resolution text is required when resolving a ticket.', $ticket->errors['message']);
    }

    public function testMessageComposerRejectsPrivateResolutionMessage(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateMessageComposer('resolved', '<p>Fixed it.</p>', true, true));
        $this->assertSame('Resolution messages cannot be private. Uncheck private note to resolve this ticket.', $ticket->errors['message']);
    }

    public function testMessageComposerAllowsStatusOnlyUpdateWhenNotResolving(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->validateMessageComposer('in_progress', '', false, true));
    }

    public function testMessageComposerRequiresMessageWhenNoStatusChanges(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->validateMessageComposer('open', '', false, false));
        $this->assertSame('Enter a message.', $ticket->errors['message']);
    }

    public function testStaffEngagementOpensNewTicketWhenStatusIsLeftNew(): void
    {
        $ticket = new Ticket;

        $this->assertSame('open', $ticket->statusAfterStaffEngagement('new', 'new', true));
        $this->assertSame('new', $ticket->statusAfterStaffEngagement('new', 'new', false));
        $this->assertSame('in_progress', $ticket->statusAfterStaffEngagement('new', 'in_progress', true));
    }

    public function testPendingRequesterHelpersIdentifyAndClearPendingState(): void
    {
        $ticket = new Ticket;

        $this->assertTrue($ticket->isPendingRequester((object)['is_pending_requester' => 1]));
        $this->assertFalse($ticket->isPendingRequester((object)['is_pending_requester' => 0]));

        $data = $ticket->linkRequesterData(42);
        $this->assertSame(42, $data['user_id']);
        $this->assertNull($data['email_requester_name']);
        $this->assertNull($data['email_requester_email']);
        $this->assertSame(0, $data['is_pending_requester']);
        $this->assertArrayHasKey('updated_at', $data);
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

    public function testClosedTicketsCannotReceiveReplies(): void
    {
        $ticket = new Ticket;

        $this->assertFalse($ticket->canReply((object)['status' => 'closed']));
        $this->assertTrue($ticket->canReply((object)['status' => 'resolved']));
        $this->assertTrue($ticket->canReply((object)['status' => 'open']));
    }

    public function testUserReplyReopensResolvedAndWaitingTickets(): void
    {
        $ticket = new Ticket;
        $user = (object)['role' => 'user'];

        $this->assertTrue($ticket->shouldReopenOnUserReply((object)['status' => 'resolved'], $user));
        $this->assertTrue($ticket->shouldReopenOnUserReply((object)['status' => 'waiting_on_user'], $user));
        $this->assertFalse($ticket->shouldReopenOnUserReply((object)['status' => 'open'], $user));
    }

    public function testStaffReplyDoesNotAutomaticallyReopenResolvedTicket(): void
    {
        $ticket = new Ticket;
        $staff = (object)['role' => 'staff'];

        $this->assertFalse($ticket->shouldReopenOnUserReply((object)['status' => 'resolved'], $staff));
    }

    public function testReplyUpdateDataReopensUserReplyAndClearsResolvedAt(): void
    {
        $ticket = new Ticket;
        $data = $ticket->replyUpdateData((object)['status' => 'resolved'], (object)['role' => 'user']);

        $this->assertSame('open', $data['status']);
        $this->assertArrayHasKey('resolved_at', $data);
        $this->assertNull($data['resolved_at']);

        $staffData = $ticket->replyUpdateData((object)['status' => 'resolved'], (object)['role' => 'staff']);
        $this->assertArrayNotHasKey('status', $staffData);
        $this->assertArrayNotHasKey('resolved_at', $staffData);
    }

    public function testAutoCloseUpdateDataClosesTicketAndSetsTimestamps(): void
    {
        $ticket = new Ticket;
        $data = $ticket->autoCloseUpdateData();

        $this->assertSame('closed', $data['status']);
        $this->assertArrayHasKey('closed_at', $data);
        $this->assertArrayHasKey('updated_at', $data);
        $this->assertNotEmpty($data['closed_at']);
        $this->assertNotEmpty($data['updated_at']);
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

    public function testTicketCommentValidationAcceptsInternalComment(): void
    {
        $comment = new TicketComment;

        $this->assertTrue($comment->validateCreate([
            'ticket_id' => 4,
            'user_id' => 7,
            'body' => 'Staff-only context.',
            'is_internal' => 1,
        ]));
    }

    public function testTicketCommentBodyHasPracticalLengthLimit(): void
    {
        $comment = new TicketComment;

        $this->assertFalse($comment->validateCreate([
            'ticket_id' => 4,
            'user_id' => 7,
            'body' => str_repeat('a', 10001),
        ]));
        $this->assertArrayHasKey('body', $comment->errors);
    }

    public function testTicketAttachmentValidationAcceptsPngImage(): void
    {
        $attachment = new TicketAttachment;
        $path = tempnam(sys_get_temp_dir(), 'ticket-attachment-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $this->assertTrue($attachment->validateUpload([
            'name' => 'screenshot.png',
            'tmp_name' => $path,
            'size' => filesize($path),
            'error' => UPLOAD_ERR_OK,
        ]));
        $this->assertSame('png', $attachment->extensionForMimeType('image/png'));

        unlink($path);
    }

    public function testTicketAttachmentValidationRejectsNonImageFile(): void
    {
        $attachment = new TicketAttachment;
        $path = tempnam(sys_get_temp_dir(), 'ticket-attachment-');
        file_put_contents($path, 'plain text');

        $this->assertFalse($attachment->validateUpload([
            'name' => 'notes.txt',
            'tmp_name' => $path,
            'size' => filesize($path),
            'error' => UPLOAD_ERR_OK,
        ]));
        $this->assertArrayHasKey('attachment', $attachment->errors);

        unlink($path);
    }

    public function testTicketAttachmentValidationRejectsOversizedImage(): void
    {
        $attachment = new TicketAttachment;
        $path = tempnam(sys_get_temp_dir(), 'ticket-attachment-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='));

        $this->assertFalse($attachment->validateUpload([
            'name' => 'large.png',
            'tmp_name' => $path,
            'size' => TicketAttachment::MAX_BYTES + 1,
            'error' => UPLOAD_ERR_OK,
        ]));
        $this->assertArrayHasKey('attachment', $attachment->errors);

        unlink($path);
    }

    public function testTicketAttachmentOriginalNameIsSanitized(): void
    {
        $attachment = new TicketAttachment;

        $this->assertSame('invoice_.png', $attachment->safeOriginalName('../invoice?.png'));
        $this->assertSame('image', $attachment->safeOriginalName(''));
    }

    public function testTicketAttachmentAllowsInlineColumn(): void
    {
        $reflection = new ReflectionClass(TicketAttachment::class);
        $property = $reflection->getProperty('allowedColumns');
        $property->setAccessible(true);

        $this->assertContains('is_inline', $property->getValue(new TicketAttachment));
    }

    public function testTicketEventValidationAcceptsKnownEventTypesOnly(): void
    {
        $event = new TicketEvent;

        $this->assertTrue($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'created',
        ]));

        $this->assertTrue($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'closed_automatically',
        ]));

        $this->assertTrue($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'internal_note_added',
        ]));

        $this->assertTrue($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'attachment_uploaded',
        ]));

        $this->assertFalse($event->validateCreate([
            'ticket_id' => 4,
            'event_type' => 'invented_event',
        ]));
        $this->assertArrayHasKey('event_type', $event->errors);
    }
}
