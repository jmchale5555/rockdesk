**Scope**
Supporting “submit by email” and “reply by email” means adding an inbound mail pipeline. Outbound notifications are easy by comparison; inbound email needs identity matching, parsing, attachment handling, duplicate protection, and safe failure behavior.

**Main Approaches**
1. IMAP mailbox polling
Pros:
- Works with normal shared mailboxes like `support@example.com`.
- No public webhook endpoint required.
- Simple operational model: run a cron command every minute.

Cons:
- Needs PHP IMAP extension or a library.
- Polling latency.
- Mailbox state/locking can get messy.
- OAuth with Microsoft 365/Google can be more complex than basic IMAP.

Good fit if using an internal Exchange/M365/shared mailbox and cron is acceptable.

2. Provider webhook/API
Examples: Mailgun Routes, SendGrid Inbound Parse, Postmark Inbound, SES inbound.

Pros:
- Faster and more reliable than polling.
- Provider handles MIME ingestion.
- Easier attachment extraction depending on provider.
- Better logs.

Cons:
- Provider-specific integration.
- Requires public HTTPS endpoint.
- Adds webhook security concerns.
- More vendor lock-in.

Good fit if you already use an email service provider.

3. Local MTA pipe
Examples: Postfix pipes inbound mail into a PHP script.

Pros:
- Very direct.
- No polling.
- Full control.

Cons:
- More sysadmin work.
- Harder in Docker/web app deployments.
- Deliverability and spam handling become your problem.

I would avoid this unless this will run on infrastructure already managed for mail.

**Recommended Path**
Start with IMAP polling if your support address is a normal mailbox. Add a command like:

```bash
php scripts/import-mail.php
```

Run it from cron every 1-5 minutes.

Later, if needed, abstract the inbound source so provider webhooks can feed the same parser.

**Core Data Model**
Likely new tables:

```text
inbound_emails
- id
- message_id
- mailbox_uid
- from_email
- from_name
- subject
- received_at
- processed_at
- status: pending|processed|ignored|failed
- error
- raw_path or raw_body
- ticket_id nullable
- created_at
```

Optional:

```text
ticket_email_tokens
- ticket_id
- token
- created_at
```

Or store token directly on `tickets`.

Why:
- `message_id` prevents duplicate processing.
- `status/error` gives audit/debugging.
- Token helps securely map replies to tickets.
- Keeping raw MIME temporarily helps diagnose parsing bugs.

**Reply Matching**
Best practice: include both ticket number and a random token in outbound mail headers and/or reply address.

Options:

1. Subject ticket number matching only:
Example: `[Rockdesk] Re: TCK-2026-000123`

Pros:
- Simple.
- Human-readable.

Cons:
- Users edit subjects.
- Collisions unlikely but possible.
- Forwarded emails can confuse ownership/security.

Not enough by itself.

2. Reply-To plus token:
Example:

```text
Reply-To: support+TCK-2026-000123.abcd1234@example.com
```

Pros:
- Reliable matching.
- Safer than subject-only.
- Works well if mailbox accepts plus-addressing.

Cons:
- Depends on mail system accepting tagged aliases.
- Some systems rewrite addresses.

This is my preferred route if plus-addressing or catch-all aliases are available.

3. Hidden token in body:
Example:

```text
<!-- rockdesk-ticket-token: abc123 -->
```

Pros:
- Works even without plus-addressing.
- Can survive basic replies.

Cons:
- Some clients strip comments.
- Forward/reply chains can include old tokens.
- Less reliable than Reply-To token.

Good as a fallback, not primary.

4. In-Reply-To / References headers:
Pros:
- Standards-based.
- Good thread matching.

Cons:
- Users may start a new email instead of replying.
- Some clients/gateways strip/change headers.
- Need to store outbound Message-ID per ticket notification.

Useful secondary signal.

Recommended matching order:
1. `support+ticket-token@...` recipient token.
2. Stored outbound `Message-ID` via `In-Reply-To` / `References`.
3. Hidden body token.
4. Subject ticket number as fallback with stricter permission checks.

**Submit By Email**
Flow:
1. Poll inbound mailbox.
2. Parse message.
3. Normalize sender email.
4. Find active local user by email.
5. If found, create ticket:
   - subject from email subject
   - body from parsed plain/html body
   - requester user ID from matched user
   - status `new`
   - priority `normal`
6. Import valid image attachments.
7. Notify staff/admins using existing notifier.
8. Mark email processed.

Policy decisions:
- If sender email does not match an active user:
  - ignore and maybe auto-reply saying “email not recognized”
  - or create a pending/untrusted ticket
  - or allow public ticket creation

Given this app has admin-created users only, I recommend: only active known users can create tickets by email.

**Reply By Email**
Flow:
1. Poll inbound mailbox.
2. Parse message.
3. Match ticket using token/header/subject.
4. Find active local user by sender email.
5. Verify user can access ticket:
   - requester can reply to own ticket
   - staff/admin can reply to any ticket
6. Strip quoted history/signatures.
7. Add ticket comment:
   - requester reply: public
   - staff/admin reply: public by default
   - do not allow internal notes by normal email unless explicit special address/prefix is implemented
8. Apply current reply rules:
   - user reply to `resolved`/`waiting_on_user` reopens to `open`
   - staff/admin replying to `new` auto-opens to `open`
9. Import valid image attachments.
10. Trigger existing notifications.
11. Mark email processed.

**Email Parsing**
Need to parse:
- MIME multipart messages.
- HTML vs plain text.
- Character encodings.
- Inline images.
- Attachments.
- Quoted reply chains.
- Signatures.

Dependencies worth considering:
- `zbateson/mail-mime-parser`
- `ddeboer/imap` or `webklex/php-imap`
- `php-mime-mail-parser` if `mailparse` extension is acceptable

Likely combination:
- IMAP library for fetching messages.
- MIME parser for body/attachments.
- Custom small cleaning logic for quoted text.

**Quoted Text Gotchas**
This is one of the hardest parts.

Email clients quote replies differently:
- Gmail: `On Tue, Bob wrote:`
- Outlook: `From: Bob Sent: ...`
- Apple Mail: `On ... wrote:`
- Mobile clients add signatures.
- Some replies put new text below quoted text.
- HTML emails contain nested blockquotes.

You can do a decent first pass:
- Prefer plain text part if available.
- Strip everything after common reply markers.
- Remove lines starting with `>`.
- For HTML, remove `blockquote`, Gmail quote wrappers, Outlook quote sections where detectable.
- Sanitize using existing `sanitize_rich_text()`.

Tradeoff:
- Aggressive stripping can delete legitimate content.
- Conservative stripping can include entire email history in comments.

I’d start conservative but remove obvious quoted sections.

**Attachments**
Current attachment policy already helps:
- JPG/PNG/WebP only.
- 5 MB max.
- Private storage.
- Permission-checked serving.

Inbound email should reuse the same validation rules.

Gotchas:
- Inline logos/signature images can become junk attachments.
- Screenshots may be inline CID attachments rather than normal attachments.
- Some clients attach huge images.
- Need to ignore tiny tracking pixels/logos.

Recommended:
- Import image attachments only.
- Ignore images under a small size threshold, maybe `< 2 KB`.
- Ignore common filenames like `image001.png` only carefully, because Outlook screenshots can also be named that.
- Mark imported email attachments as inline/non-inline depending on whether inserted into body is practical. Simpler first pass: append them as normal ticket attachments.

**Security**
Key risks:
- Email spoofing.
- Someone replying from a forged `From`.
- Token leakage via forwarded emails.
- HTML/script injection.
- Attachment malware/oversized files.
- Auto-reply loops.

Mitigations:
- Only accept mail from active known user email addresses.
- Match ticket access before adding comments.
- Use random reply tokens, not just ticket numbers.
- Sanitize all HTML with existing sanitizer.
- Keep strict attachment validation.
- Ignore auto-submitted emails with headers:
  - `Auto-Submitted`
  - `Precedence: bulk`
  - common vacation/OOO headers
- Do not send auto-replies to auto-submitted messages.
- De-duplicate by `Message-ID`.
- Rate-limit or at least log repeated failures.

Important: SPF/DKIM/DMARC validation is usually handled by the mail server/provider, not the app. If using IMAP, the app mostly trusts what got delivered to the mailbox.

**Operational Concerns**
Need:
- Cron/worker setup.
- Locking so two import jobs don’t process the same message.
- Processed/failed mailbox folders or flags.
- Logging and admin visibility.
- Retry behavior.
- Timeout behavior.
- Secret storage for mailbox credentials.

For IMAP:
```env
INBOUND_MAIL_ENABLED=false
INBOUND_MAIL_HOST=imap.example.com
INBOUND_MAIL_PORT=993
INBOUND_MAIL_ENCRYPTION=ssl
INBOUND_MAIL_USERNAME=support@example.com
INBOUND_MAIL_PASSWORD=
INBOUND_MAIL_MAILBOX=INBOX
INBOUND_MAIL_PROCESSED_MAILBOX=Processed
INBOUND_MAIL_FAILED_MAILBOX=Failed
```

For M365/Google:
- Basic password auth may not work.
- App passwords may be disabled.
- OAuth2 may be required.
- OAuth2 adds refresh-token handling and more setup.

**Implementation Phases**
1. Outbound reply tokens:
- Add ticket email token.
- Set `Reply-To` to `support+token@domain` or configured inbound address.
- Add `Message-ID` tracking if going header-based.

2. Inbound schema and command:
- Add `inbound_emails` migration.
- Add `scripts/import-mail.php`.
- Add lock file or DB lock.
- Add config constants.

3. Parser abstraction:
- `Core\InboundMailClient`
- `Core\InboundMailParser`
- `Core\InboundTicketImporter`

4. Submit-by-email:
- Known active user by sender email.
- Create ticket.
- Import attachments.
- Notify staff.

5. Reply-by-email:
- Match token/header/subject.
- Validate sender access.
- Add public comment.
- Apply reopen/auto-open status rules.
- Import attachments.
- Notify opposite side.

6. Hardening:
- Duplicate detection.
- Auto-reply loop detection.
- Failed message handling.
- Better quoted text stripping.
- Admin diagnostics.

**Key Decisions Before Building**
1. What mailbox system will receive inbound mail?
Examples: Microsoft 365, Google Workspace, on-prem Exchange, generic IMAP, Mailgun/Postmark.

2. Can the mailbox support plus-addressing or catch-all aliases?
Example: `support+abc123@example.com`.

3. Should unknown senders be ignored, auto-replied to, or create pending tickets?

4. Should staff be able to create internal notes by email?
I recommend no for v1. Email replies should be public only.

5. Do you want inbound email attachments to appear as standalone attachments only, or also inline in comment bodies?
I recommend standalone for v1.
