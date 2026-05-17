# Inbound Email Implementation TODO

## Stage 1: Outbound Reply Token Foundation

- Add stable ticket email tokens.
- Add configurable inbound support address.
- Add optional plus-address `Reply-To` support.
- Add outbound hidden/body token where useful for later reply matching.
- Add outbound loop-prevention headers:
  - `Auto-Submitted: auto-generated`
  - `X-Auto-Response-Suppress: All`
  - `X-Loop: rockdesk`
- Store outbound message IDs where practical for later `In-Reply-To` / `References` matching.
- Keep outbound email delivery synchronous and no-op when mail is disabled.

## Stage 2: Inbound Schema And Parser Contract

- Add `inbound_emails` tracking table.
- Define normalized inbound message shape.
- Add auto-reply/list-message detection helpers.
- Add conservative quote-reply stripping helpers.
- Add parser/unit tests using representative fixtures.

## Stage 3: Importer Logic Without Mailbox

- Implement importer against normalized messages.
- Create normal tickets for known active users.
- Create pending/unverified tickets for unknown senders.
- Match replies by token/header/body token/subject fallback.
- Add public comments only; no internal notes by email.
- Reuse existing status rules for reopen and staff auto-open.
- Trigger existing notifications.

## Stage 4: First Source Driver And Import Command

- Add first source driver, likely IMAP or Microsoft Graph depending on deployment priority.
- Add `scripts/import-mail.php`.
- Add import locking.
- Mark or move processed/failed messages where supported.
- Add operational logging.

## Stage 5: Standalone Inbound Attachments

- Import standalone image attachments only.
- Reuse existing image validation: JPG, PNG, WebP, 5 MB max.
- Store privately using current attachment storage.
- Add small-image/logo filtering after observing real messages.

## Stage 6: Pending Sender Triage UX

- Clearly show unverified requester name/email on pending email-created tickets.
- Add staff/admin workflow to link pending requester to an existing user.
- Add staff/admin workflow to create a user from pending requester details.
- Add import diagnostics if needed.

## Stage 7: Hardening

- Improve quote stripping from real email samples.
- Tune auto-reply/list detection.
- Add duplicate and retry edge-case handling.
- Add Graph/IMAP-specific retry behavior.
- Add provider webhook source driver if needed.
