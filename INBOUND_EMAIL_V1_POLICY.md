# Inbound Email V1 Policy

## Direction

Rockdesk should support inbound email in a mailbox-flexible way, with Microsoft 365 treated as the likely primary deployment target but not hardwired into the ticket import logic.

The app should use a source-driver architecture:

- `InboundMailSource`: fetches messages and marks them processed or failed.
- `InboundMailParser`: normalizes raw/provider messages into a common parsed message object.
- `InboundTicketImporter`: applies Rockdesk ticket rules, creates tickets/replies, imports attachments, and triggers notifications.

This keeps ticket behavior independent from whether messages arrive by IMAP, Microsoft Graph, or a provider webhook.

## Driver Strategy

Supported design targets:

- Generic IMAP polling for broad mailbox compatibility.
- Microsoft Graph Mail API for a stronger long-term Microsoft 365 path.
- Provider webhook adapters later if needed, such as Mailgun, Postmark, SendGrid, or SES.

IMAP can be the first implementation if acceptable for the deployment, but Microsoft 365 basic-auth IMAP should not be assumed long-term. Microsoft 365 may require OAuth2 or Graph.

## Plus Addressing

Plus addressing must be configurable.

Example config:

```env
INBOUND_MAIL_PLUS_ADDRESSING_ENABLED=false
INBOUND_MAIL_ADDRESS=support@example.com
INBOUND_MAIL_PLUS_DELIMITER=+
```

When enabled, outbound `Reply-To` can use a tokenized address like:

```text
support+<ticket-token>@example.com
```

When disabled, outbound `Reply-To` remains the base support address:

```text
support@example.com
```

Reply matching should still use ticket tokens even when plus addressing is disabled.

## Reply Matching

Reply tokens should be added before inbound email is implemented so outbound notifications can include them.

Recommended matching order:

1. Token in recipient address when plus addressing is enabled.
2. Stored outbound `Message-ID` matched against inbound `In-Reply-To` or `References` headers.
3. Hidden body token included in outbound HTML.
4. Ticket number in subject as a final fallback, with strict sender/access checks.

Subject-only matching is not sufficient because users can edit subjects and forwarded messages can create security ambiguity.

## Unknown Senders

Unknown senders should create pending/unverified tickets in V1.

Policy:

- Known active users are matched by sender email and create normal tickets.
- Unknown senders create tickets visible to staff/admins for triage.
- Unknown sender tickets should clearly show the original sender name and email.
- Unknown sender tickets should not imply the sender has web access.
- Staff/admins should later be able to link the ticket to an existing user or create a user if needed.

Implementation preference:

- Avoid making `tickets.user_id` nullable unless necessary.
- Prefer a system user such as `Email Guest` for unknown senders.
- Store the real sender name/email on the ticket or related inbound email record.
- Add a clear pending/unverified requester flag or field.

## Email Replies

Inbound email replies are public only in V1.

Policy:

- Requester replies become public ticket comments.
- Staff/admin replies become public ticket comments.
- Internal notes by email are not supported.
- Staff should use the web UI for internal notes.

This avoids confusion and reduces the chance of accidentally sending private/internal content to requesters.

## Ticket Workflow Rules

Inbound email should reuse current web workflow behavior:

- User reply to `resolved` or `waiting_on_user` reopens the ticket to `open`.
- Staff/admin reply to a `new` ticket automatically changes status to `open`.
- Closed tickets remain read-only unless a future explicit reopen policy is added.
- Staff/admin public resolution replies can notify the requester.

## Attachments

Inbound attachments should be standalone in V1.

Policy:

- Import image attachments only.
- Reuse current attachment rules: JPG, PNG, WebP, 5 MB max.
- Store attachments privately using the existing attachment storage pattern.
- Ignore or reject unsupported file types.
- Consider ignoring very small images, such as tracking pixels or logos, after practical testing.

Inline inbound attachment reconstruction is out of scope for V1 because CID images, signatures, logos, and client-specific HTML make it fragile.

## Outbound Email Content

Outbound emails from staff to users should preserve useful rich HTML from the web composer.

V1 behavior:

- Include sanitized rich-text message HTML.
- Include a clear `View ticket` link.
- Keep inline images as app/ticket links initially.

CID-embedding inline images can be considered later, but it adds complexity and larger emails. Auth-protected app links are safer for V1.

## Security

Inbound email must be treated as untrusted input.

Required protections:

- Sanitize all HTML before storing or rendering.
- Only allow known safe attachment types and sizes.
- De-duplicate by inbound `Message-ID` where available.
- Match ticket access before adding replies.
- Use random reply tokens, not just ticket numbers.
- Ignore auto-submitted messages where possible.
- Avoid auto-reply loops.
- Log failed or ignored messages for diagnostics.

Headers to consider for auto-reply/bulk detection:

- `Auto-Submitted`
- `Precedence: bulk`
- common vacation/out-of-office headers

SPF, DKIM, and DMARC validation are expected to be handled by the receiving mail system or provider. The app should not assume that the `From` header alone is trustworthy.

## Configuration Shape

General inbound config:

```env
INBOUND_MAIL_ENABLED=false
INBOUND_MAIL_DRIVER=imap
INBOUND_MAIL_ADDRESS=support@example.com
INBOUND_MAIL_PLUS_ADDRESSING_ENABLED=false
INBOUND_MAIL_PLUS_DELIMITER=+
INBOUND_MAIL_UNKNOWN_SENDER_POLICY=pending_ticket
INBOUND_MAIL_REPLY_MATCHING=token,headers,body_token,subject
INBOUND_MAIL_ATTACHMENTS_ENABLED=true
INBOUND_MAIL_ATTACHMENT_MIN_BYTES=2048
```

Generic IMAP config:

```env
INBOUND_IMAP_HOST=outlook.office365.com
INBOUND_IMAP_PORT=993
INBOUND_IMAP_ENCRYPTION=ssl
INBOUND_IMAP_USERNAME=support@example.com
INBOUND_IMAP_PASSWORD=
INBOUND_IMAP_MAILBOX=INBOX
INBOUND_IMAP_PROCESSED_MAILBOX=Processed
INBOUND_IMAP_FAILED_MAILBOX=Failed
```

Future Microsoft Graph config:

```env
INBOUND_GRAPH_TENANT_ID=
INBOUND_GRAPH_CLIENT_ID=
INBOUND_GRAPH_CLIENT_SECRET=
INBOUND_GRAPH_MAILBOX=support@example.com
```

## Operational Requirements

Inbound email requires a worker or command, not request-time processing.

Likely command:

```bash
php scripts/import-mail.php
```

Operational needs:

- Run from cron or a scheduler every 1-5 minutes.
- Use locking to prevent concurrent imports.
- Track processed, ignored, and failed messages.
- Move or flag processed/failed mailbox messages where supported.
- Store enough message metadata for troubleshooting.
- Keep mailbox credentials in environment or deployment secrets.

## V1 Implementation Phases

1. Add outbound reply tokens and optional plus-address `Reply-To` support.
2. Add inbound email tracking schema.
3. Add parser/source/importer abstractions.
4. Implement submit-by-email for known users and pending tickets for unknown senders.
5. Implement reply-by-email with public comments only.
6. Import standalone image attachments.
7. Add duplicate detection, auto-reply filtering, failure logging, and import locking.
8. Add admin diagnostics and user-linking workflow for pending unknown-sender tickets.
