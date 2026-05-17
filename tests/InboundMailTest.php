<?php

use Core\InboundMailCleaner;
use Core\InboundMailInspector;
use Core\InboundMessage;
use Core\InboundAttachmentImporter;
use Core\InboundTicketImporter;
use Core\ImapInboundMailSource;
use Model\Ticket;
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

    public function testInboundInspectorIgnoresListMessagesWithoutTicketSignal(): void
    {
        $inspector = new InboundMailInspector;

        $this->assertSame('list header without ticket signal', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'newsletter@example.com',
            'headers' => ['List-Id' => 'updates.example.com'],
            'subject' => 'Monthly update',
        ])));
    }

    public function testInboundInspectorAllowsListMessageWithTicketSignal(): void
    {
        $inspector = new InboundMailInspector;

        $this->assertSame('', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['List-Id' => 'internal.example.com'],
            'subject' => 'Re: TCK-2026-000123',
        ])));
    }

    public function testInboundInspectorIgnoresAutomatedSendersAndMicrosoftLoopHeaders(): void
    {
        $inspector = new InboundMailInspector;

        $this->assertSame('automated sender', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'mailer-daemon@example.com',
        ])));

        $this->assertSame('x-ms-exchange-inbox-rules-loop header', $inspector->ignoreReason(InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'headers' => ['X-MS-Exchange-Inbox-Rules-Loop' => 'support@example.com'],
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

    public function testCleanerStripsForwardedMessageBoundary(): void
    {
        $cleaner = new InboundMailCleaner;
        $body = "Please see this.\n\n---------- Forwarded message ---------\nFrom: Someone <someone@example.com>\nOld content";

        $this->assertSame('Please see this.', $cleaner->cleanText($body));
    }

    public function testCleanerStripsOutlookHtmlReplyHeader(): void
    {
        $cleaner = new InboundMailCleaner;
        $cleaned = $cleaner->cleanHtml('<p>Current reply</p><hr><div><b>From:</b> Support<br>Old reply</div>');

        $this->assertStringContainsString('Current reply', $cleaned);
        $this->assertStringNotContainsString('Old reply', $cleaned);
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

    public function testImporterExtractsReplyTokenFromPlusAddress(): void
    {
        $token = '1234567890abcdef1234567890abcdef';
        $importer = new InboundTicketImporter;
        $message = InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'to' => ['support+' . $token . '@example.com'],
        ]);

        $this->assertSame($token, $importer->extractReplyToken($message));
    }

    public function testImporterExtractsReplyTokenFromBodyFallback(): void
    {
        $token = 'abcdefabcdefabcdefabcdefabcdefab';
        $importer = new InboundTicketImporter;
        $message = InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'html_body' => '<p>Reply</p><!-- rockdesk-ticket-token: ' . $token . ' -->',
        ]);

        $this->assertSame($token, $importer->extractReplyToken($message));
    }

    public function testImporterPrefersCleanedPlainTextBody(): void
    {
        $importer = new InboundTicketImporter;
        $message = InboundMessage::fromArray([
            'from_email' => 'user@example.com',
            'text_body' => "Current reply\n\nOn Sun, Support wrote:\n> Old reply",
            'html_body' => '<p>HTML body</p>',
        ]);

        $body = $importer->messageBody($message);

        $this->assertStringContainsString('Current reply', $body);
        $this->assertStringNotContainsString('Old reply', $body);
        $this->assertStringNotContainsString('HTML body', $body);
    }

    public function testTicketEmailCreateDataStoresPendingRequesterDetails(): void
    {
        $ticket = new Ticket;
        $data = $ticket->makeEmailCreateData(99, 'Email issue', '<p>Help</p>', 'TCK-2026-999999', 'Email Sender', 'SENDER@EXAMPLE.COM', true);

        $this->assertSame('email', $data['source']);
        $this->assertSame('Email Sender', $data['email_requester_name']);
        $this->assertSame('sender@example.com', $data['email_requester_email']);
        $this->assertSame(1, $data['is_pending_requester']);
    }

    public function testImapSourceBuildsMailboxPathFromConfig(): void
    {
        $source = new ImapInboundMailSource;

        $this->assertSame('{outlook.office365.com:993/imap/ssl}INBOX', $source->mailboxPath());
        $this->assertSame('{outlook.office365.com:993/imap/ssl}Processed', $source->mailboxPath('Processed'));
    }

    public function testImapSourceReportsConfiguredWhenRequiredValuesExist(): void
    {
        $source = new ImapInboundMailSource;

        $this->assertTrue($source->isConfigured());
    }

    public function testInboundAttachmentImporterAcceptsAllowedImageContent(): void
    {
        $importer = new InboundAttachmentImporter;
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        $this->assertTrue($importer->isImportable([
            'filename' => 'screenshot.png',
            'mime_type' => 'image/png',
            'content' => $png,
            'size' => strlen($png),
        ]));
    }

    public function testInboundAttachmentImporterRejectsUnsupportedContent(): void
    {
        $importer = new InboundAttachmentImporter;

        $this->assertFalse($importer->isImportable([
            'filename' => 'notes.txt',
            'mime_type' => 'text/plain',
            'content' => 'not an image',
            'size' => 12,
        ]));
    }

    public function testInboundAttachmentImporterRejectsSmallSignatureImages(): void
    {
        $importer = new InboundAttachmentImporter;

        $this->assertFalse($importer->isImportable([
            'filename' => 'tracking.png',
            'mime_type' => 'image/png',
            'content' => 'x',
            'size' => 0,
        ]));
    }
}
