<?php

use Core\InboundMailCleaner;
use Core\InboundMailInspector;
use Core\InboundMessage;
use Model\InboundEmail;
use PHPUnit\Framework\TestCase;

final class InboundMailTest extends TestCase
{
    public function testInboundMessageNormalizesHeadersAndRecipients(): void
    {
        $message = InboundMessage::fromArray([
            'message_id' => ' <abc@example.com> ',
            'mailbox_uid' => '42',
            'from_email' => 'USER@EXAMPLE.COM',
            'from_name' => 'User Example',
            'to' => 'Support <support@example.com>, other@example.com',
            'cc' => ['Copy <copy@example.com>', 'bad-address'],
            'subject' => ' Help ',
            'headers' => ['Auto-Submitted' => 'no'],
        ]);

        $this->assertSame('user@example.com', $message->fromEmail);
        $this->assertSame('no', $message->header('auto-submitted'));
        $this->assertSame(['support@example.com', 'other@example.com', 'copy@example.com'], $message->allRecipients());
    }

    public function testInboundInspectorIgnoresAutoSubmittedBulkAndLoopMessages(): void
    {
        $inspector = new InboundMailInspector;

        $this->assertSame('auto-submitted message', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['Auto-Submitted' => 'auto-replied'],
        ])));

        $this->assertSame('bulk/list precedence', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['Precedence' => 'bulk'],
        ])));

        $this->assertSame('rockdesk loop header', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['X-Loop' => 'rockdesk'],
        ])));
    }

    public function testInboundInspectorAllowsNormalMessages(): void
    {
        $inspector = new InboundMailInspector;

        $this->assertFalse($inspector->shouldIgnore(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['Auto-Submitted' => 'no'],
        ])));
    }

    public function testCleanerStripsPlainTextQuotedThreadConservatively(): void
    {
        $cleaner = new InboundMailCleaner;
        $body = "This is my reply.\n\nOn Sun, May 17, 2026 at 10:00 AM Support wrote:\n> Old support reply";

        $this->assertSame('This is my reply.', $cleaner->cleanText($body));
    }

    public function testCleanerStripsOutlookOriginalMessageBlock(): void
    {
        $cleaner = new InboundMailCleaner;
        $body = "Fixed after reboot.\n\nFrom: Support <support@example.com>\nSent: Sunday, May 17, 2026 10:00 AM\nTo: User <user@example.com>\nSubject: Ticket\n\nOld message";

        $this->assertSame('Fixed after reboot.', $cleaner->cleanText($body));
    }

    public function testCleanerDoesNotTreatNormalFromSentenceAsOutlookBlock(): void
    {
        $cleaner = new InboundMailCleaner;
        $body = "From: my perspective, this started yesterday.\nThe error is still happening.";

        $this->assertSame($body, $cleaner->cleanText($body));
    }

    public function testCleanerFallsBackWhenStrippingWouldRemoveEverything(): void
    {
        $cleaner = new InboundMailCleaner;
        $body = "> Quoted-only body";

        $this->assertSame($body, $cleaner->cleanText($body));
    }

    public function testCleanerStripsHtmlQuoteContainers(): void
    {
        $cleaner = new InboundMailCleaner;
        $cleaned = $cleaner->cleanHtml('<p>Current reply</p><blockquote><p>Old reply</p></blockquote>');

        $this->assertStringContainsString('Current reply', $cleaned);
        $this->assertStringNotContainsString('Old reply', $cleaned);
    }

    public function testInboundEmailNormalizesInvalidStatusToPending(): void
    {
        $email = new InboundEmail;

        $this->assertSame('processed', $email->normalizeStatus('processed'));
        $this->assertSame('pending', $email->normalizeStatus('unknown'));
    }
}
