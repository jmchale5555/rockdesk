# IT Support Ticket System Plan

## Goal

Build this PHP MVC starter kit into a simple web-based IT support ticket handling system while preserving the project philosophy:

- Server-rendered PHP MVC monolith.
- Simple controllers, models, migrations, and views.
- HTMX only for small server-driven enhancements.
- Alpine only for tiny local UI state.
- No SPA, no Node/npm build pipeline, no CDN/runtime internet dependency.

## Product Direction

The system will support admin-managed users, role-based access, ticket submission, staff ticket handling, ticket replies, audit events, and later Microsoft Active Directory authentication over LDAPS using LdapRecord.

MVP intentionally excludes email, WYSIWYG editing, attachments, inline images, internal staff notes, SLA rules, departments, and reporting.

## Locked Decisions

- Admins create users.
- Public signup should be removed from navigation and blocked.
- Admin-created local users receive a temporary password and must reset it themselves.
- Users authenticate with a unique username rather than email.
- Email can remain a profile/contact field but should not be the login ID.
- User roles are `user`, `staff`, and `admin`.
- `staff` can see every ticket.
- `admin` is also operational support staff.
- Only `admin` can change user roles.
- Ticket priority defaults to `normal`.
- Priority is changed by staff/admin, not by normal users.
- Ticket assignment is optional.
- Staff/admin cannot manually set tickets to `closed`.
- Staff/admin must add a public comment when setting a ticket to `resolved`.
- Resolved tickets auto-close after 14 days.
- The 14-day window may later become an admin setting.
- If a user replies to a `resolved` ticket, it automatically reopens to `open`.
- If a user replies to a `waiting_on_user` ticket, it automatically changes to `open`.
- `closed` tickets are read-only.
- Users should create a new ticket if a closed issue returns.
- Internal staff-only notes are deferred until after MVP.
- Microsoft Active Directory support is planned for a later phase.
- LDAP users can be created automatically on first successful AD login.
- LDAP-created users default to `role = 'user'`.
- Roles remain controlled locally in the application, not by AD groups for MVP.
- Login should try local authentication first, then LDAP later when LDAP support exists.
- LDAP passwords must not be stored or synced into the database.
- On successful LDAP auth, sync identity/profile metadata only.
- Ticket audit history should be included from the start.

## Recommended Route Shape

The existing router maps `/controller/method/param1/param2` to controller methods. Use simple route shapes that fit that convention.

- `/tickets` -> ticket list.
- `/tickets/create` -> create form.
- `/tickets/store` -> create action.
- `/tickets/show/{id}` -> detail page.
- `/tickets/reply/{id}` -> add reply.
- `/tickets/status/{id}` -> staff/admin status update.
- `/tickets/priority/{id}` -> staff/admin priority update.
- `/tickets/assign/{id}` -> staff/admin assignment update.
- `/users` -> admin user list.
- `/users/create` -> admin create-user form.
- `/users/store` -> admin create-user action.
- `/users/edit/{id}` -> admin edit-user form.
- `/users/update/{id}` -> admin update-user action.

## Phase 1 - User Schema And Auth Foundation

Purpose: replace admin boolean behavior with role-based access and prepare users for username login plus future AD auth.

- [x] Add `username` column to `users`.
- [x] Backfill existing users with a username derived from email or name.
- [x] Add unique index on `username`.
- [x] Keep `email` as optional or required contact/profile field.
- [x] Add `role` column with allowed values `user`, `staff`, `admin`.
- [x] Backfill current `is_admin = 1` users to `role = 'admin'`.
- [x] Backfill all others to `role = 'user'`.
- [x] Add index on `role`.
- [x] Add `auth_provider` with allowed values `local`, `ldap`, default `local`.
- [x] Add `is_active`, default `1`.
- [x] Add `must_reset_password`, default `0`.
- [x] Add `last_login_at`.
- [x] Add `directory_guid` nullable column.
- [x] Add `directory_domain` nullable column.
- [x] Add `directory_username` nullable column.
- [x] Add `directory_dn` nullable column.
- [x] Add `directory_synced_at` nullable column.
- [x] Add indexes for `auth_provider`, `directory_guid`, and `directory_username`.
- [x] Update the default admin seeder to include `username`, `role`, `auth_provider`, and `is_active`.
- [x] Update the `User` model allowed columns.
- [x] Update login to authenticate by `username`.
- [x] Update login to block inactive users.
- [x] Update login to set `last_login_at`.
- [x] Stop relying on `is_admin` in application code.
- [x] Leave `is_admin` in place temporarily only if needed for a safe migration path.
- [ ] Add a later cleanup task to drop `is_admin` once code no longer uses it.

Recommended `users` direction:

```sql
username VARCHAR(100) NOT NULL UNIQUE
email VARCHAR(190) NULL
role ENUM('user', 'staff', 'admin') NOT NULL DEFAULT 'user'
auth_provider ENUM('local', 'ldap') NOT NULL DEFAULT 'local'
is_active TINYINT(1) NOT NULL DEFAULT 1
must_reset_password TINYINT(1) NOT NULL DEFAULT 0
directory_guid VARCHAR(190) NULL
directory_domain VARCHAR(190) NULL
directory_username VARCHAR(190) NULL
directory_dn VARCHAR(500) NULL
directory_synced_at DATETIME NULL
last_login_at DATETIME NULL
```

## Phase 2 - Authorization Helpers

Purpose: keep permission checks direct and consistent.

- [x] Add `require_login()` helper.
- [x] Add `current_user()` helper.
- [x] Add `current_user_id()` helper.
- [x] Add `current_user_role()` helper.
- [x] Add `has_role(array|string $roles)` helper.
- [x] Add `require_role(array|string $roles)` helper.
- [x] Add `is_staff_or_admin()` helper.
- [x] Add `is_admin()` helper.
- [x] Add ticket ownership helper for normal users.
- [x] Add final-admin protection helper.
- [x] Redirect guests to login when accessing protected routes.
- [x] Return 404 or access denied for unauthorized ticket access.

Access rules:

- [ ] Guest users cannot access tickets. Helper exists; enforce when ticket routes are added.
- [ ] `user` can create tickets. Enforce when ticket routes are added.
- [ ] `user` can view only their own tickets. Helper exists; enforce when ticket routes are added.
- [ ] `user` can reply to their own non-closed tickets. Enforce when ticket routes are added.
- [ ] `user` cannot change status, priority, or assignment. Enforce when ticket routes are added.
- [ ] `staff` can view all tickets. Helper exists; enforce when ticket routes are added.
- [ ] `staff` can reply to all non-closed tickets. Enforce when ticket routes are added.
- [ ] `staff` can change status except to `closed`. Enforce when ticket routes are added.
- [ ] `staff` can change priority. Enforce when ticket routes are added.
- [ ] `staff` can assign tickets. Enforce when ticket routes are added.
- [ ] `staff` cannot change roles. Enforce when admin user routes are added.
- [ ] `admin` can perform all staff actions. Helper exists; enforce when routes are added.
- [ ] `admin` can create users. Enforce when admin user routes are added.
- [ ] `admin` can update users. Enforce when admin user routes are added.
- [ ] `admin` can change roles. Enforce when admin user routes are added.
- [ ] `admin` cannot demote or deactivate the final active admin. Helper exists; enforce when admin user routes are added.

## Phase 3 - Disable Public Signup And Add Admin User Management

Purpose: move from self-signup to admin-managed accounts.

- [x] Remove signup link from navigation.
- [x] Block direct access to `/signup` or repurpose it behind admin-only access.
- [x] Add `Users` controller for admin-only user management.
- [x] Add admin user list page.
- [x] Add admin create-user page.
- [x] Add admin edit-user page.
- [x] Admin can set username.
- [x] Admin can set email/contact details.
- [x] Admin can set temporary password for local users.
- [x] New admin-created local users get `must_reset_password = 1`.
- [x] Admin can set role.
- [x] Admin can activate/deactivate users.
- [x] Admin can reset a local user's password.
- [x] Admin cannot reset LDAP user passwords once LDAP support exists.
- [x] Admin cannot deactivate final active admin.
- [x] Admin cannot demote final active admin.
- [x] User list displays username, name, email, role, auth provider, active state, and last login.

## Phase 4 - Password Reset / Forced Password Change

Purpose: support temporary passwords safely enough for MVP.

- [x] Update password page to support forced reset after login.
- [x] If `must_reset_password = 1`, redirect user to password reset page.
- [x] Block normal app navigation until password is reset.
- [x] Clear `must_reset_password` after successful password change.
- [x] Password changes are only available for `auth_provider = 'local'`.
- [x] LDAP users later see a message that password is managed by Active Directory.
- [x] Require current password for normal self-service password changes.
- [x] For forced temporary-password reset, require current temporary password plus new password.

## Phase 5 - Ticket Data Model

Purpose: create ticket, comment, and audit-event tables.

- [x] Create `tickets` table.
- [x] Create `ticket_comments` table.
- [x] Create `ticket_events` table.
- [x] Add `Ticket` model.
- [x] Add `TicketComment` model.
- [x] Add `TicketEvent` model.
- [x] Add validation for ticket subject.
- [x] Add validation for ticket body.
- [x] Add validation for ticket status.
- [x] Add validation for ticket priority.
- [x] Generate human-friendly ticket numbers.

Recommended `tickets` table:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
ticket_number VARCHAR(32) NOT NULL
user_id BIGINT UNSIGNED NOT NULL
assigned_to BIGINT UNSIGNED NULL
subject VARCHAR(190) NOT NULL
body TEXT NOT NULL
status ENUM('new', 'open', 'in_progress', 'waiting_on_user', 'resolved', 'closed') NOT NULL DEFAULT 'new'
priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal'
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
resolved_at DATETIME NULL
closed_at DATETIME NULL
PRIMARY KEY (id)
UNIQUE KEY uq_tickets_ticket_number (ticket_number)
INDEX idx_tickets_user_id (user_id)
INDEX idx_tickets_assigned_to (assigned_to)
INDEX idx_tickets_status (status)
INDEX idx_tickets_priority (priority)
INDEX idx_tickets_created_at (created_at)
INDEX idx_tickets_updated_at (updated_at)
```

Recommended `ticket_comments` table:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
ticket_id BIGINT UNSIGNED NOT NULL
user_id BIGINT UNSIGNED NOT NULL
body TEXT NOT NULL
is_internal TINYINT(1) NOT NULL DEFAULT 0
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
updated_at DATETIME NULL
PRIMARY KEY (id)
INDEX idx_ticket_comments_ticket_id (ticket_id)
INDEX idx_ticket_comments_user_id (user_id)
INDEX idx_ticket_comments_created_at (created_at)
```

`is_internal` is included for future use but internal notes are not exposed in MVP.

Recommended `ticket_events` table:

```sql
id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT
ticket_id BIGINT UNSIGNED NOT NULL
user_id BIGINT UNSIGNED NULL
event_type VARCHAR(64) NOT NULL
old_value VARCHAR(190) NULL
new_value VARCHAR(190) NULL
body TEXT NULL
created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
PRIMARY KEY (id)
INDEX idx_ticket_events_ticket_id (ticket_id)
INDEX idx_ticket_events_user_id (user_id)
INDEX idx_ticket_events_event_type (event_type)
INDEX idx_ticket_events_created_at (created_at)
```

Recommended ticket statuses:

- `new`
- `open`
- `in_progress`
- `waiting_on_user`
- `resolved`
- `closed`

Recommended ticket priorities:

- `low`
- `normal`
- `high`
- `urgent`

## Phase 6 - User Ticket Submission MVP

Purpose: users can create and track their own tickets.

- [x] Add `Tickets` controller.
- [x] Add `/tickets` list route.
- [x] Add `/tickets/create` form route.
- [x] Add `/tickets/store` submit route.
- [x] Add `/tickets/show/{id}` detail route.
- [x] Add ticket create view.
- [x] Add ticket list view.
- [x] Add ticket detail view.
- [x] Add subject text input.
- [x] Add plain body textarea.
- [x] Validate subject is required.
- [x] Validate subject max length.
- [x] Validate body is required.
- [x] Add success/failure flash messages.
- [x] New ticket starts as `new`.
- [x] New ticket priority is `normal`.
- [x] New ticket assignment is `NULL`.
- [x] New ticket gets a human-friendly ticket number.
- [x] New ticket creates `ticket_events` row with `event_type = 'created'`.
- [x] User ticket list shows only the current user's tickets.
- [x] Staff/admin ticket list shows all tickets.
- [x] Ticket body is rendered as escaped plain text.
- [x] Preserve line breaks safely, for example escaped text plus `nl2br()`.

Recommended ticket-number format:

```text
TCK-YYYY-000001
```

## Phase 7 - Staff Ticket Handling MVP

Purpose: staff/admin can operate the queue.

- [x] Staff/admin ticket list shows all tickets.
- [x] Normal user ticket list shows only own tickets.
- [x] Add filter by status.
- [x] Add filter by priority.
- [x] Add filter by assigned staff.
- [x] Add filter by requester username.
- [x] Add staff/admin controls on ticket detail page.
- [x] Add assignment control.
- [x] Add priority control.
- [x] Add status control.
- [x] Allow status changes to `open`, `in_progress`, `waiting_on_user`, and `resolved`.
- [x] Block manual status changes to `closed`.
- [x] Require a public comment when changing status to `resolved`.
- [x] Store resolution comment in `ticket_comments`.
- [x] Set `resolved_at = NOW()` when status becomes `resolved`.
- [x] Clear `resolved_at` if ticket reopens.
- [x] Add ticket event when status changes.
- [x] Add ticket event when priority changes.
- [x] Add ticket event when assignment changes.
- [x] Update ticket `updated_at` when operational fields change.

## Phase 8 - Ticket Replies

Purpose: turn tickets into support conversations.

- [x] Add `/tickets/reply/{id}` route.
- [x] Add reply form on ticket detail.
- [x] Users can reply to own non-closed tickets.
- [x] Staff/admin can reply to any non-closed ticket.
- [x] Closed tickets are read-only.
- [x] Reply creates `ticket_comments` row.
- [x] Reply creates `ticket_events` row with `event_type = 'commented'`.
- [x] Reply updates ticket `updated_at`.
- [x] If normal user replies to `resolved`, set status to `open`.
- [x] If normal user replies to `waiting_on_user`, set status to `open`.
- [x] Reopen action creates `ticket_events` row with `event_type = 'reopened_by_user_reply'`.
- [x] Staff/admin reply does not automatically reopen resolved tickets unless explicitly changing status.
- [x] Ticket detail shows original request plus chronological public comments.

## Phase 9 - Auto-Close Resolved Tickets

Purpose: close resolved tickets automatically after 14 days.

- [x] Add config value for auto-close days with default `14`.
- [x] Add `scripts/close-resolved-tickets.php`.
- [x] Query tickets where `status = 'resolved'`.
- [x] Find tickets where `resolved_at <= NOW() - INTERVAL 14 DAY`.
- [x] Set status to `closed`.
- [x] Set `closed_at = NOW()`.
- [x] Update `updated_at`.
- [x] Create ticket event with `event_type = 'closed_automatically'`.
- [x] Add Make target for the script.
- [x] Document cron usage.

Future settings-page extension:

- [ ] Add app settings table.
- [ ] Store `ticket_auto_close_days` in settings.
- [ ] Add admin settings UI.
- [ ] Make auto-close script read setting from database.

## Phase 10 - HTMX Enhancements

Purpose: improve UX without changing the server-rendered architecture.

- [x] Keep all forms working as normal full-page POSTs first.
- [x] Add HTMX ticket-list filtering after basic filters work.
- [x] Add HTMX comment submission after normal reply works.
- [x] Add HTMX status/priority/assignment updates after normal POSTs work.
- [x] Return server-rendered fragments only.
- [x] Keep browser history usable for list/detail pages.

## Phase 11 - Security And Hardening

Purpose: make the MVP safe enough for real internal use.

- [x] Escape all user-generated output with `esc()`.
- [x] Validate role values server-side.
- [x] Validate status values server-side.
- [x] Validate priority values server-side.
- [x] Validate assignment target is staff/admin.
- [x] Validate ticket ownership on every user ticket route.
- [x] Add CSRF protection for POST routes.
- [x] Add form token helper.
- [x] Add CSRF validation helper.
- [x] Add max lengths for subject, username, name, and email.
- [x] Add practical max length for ticket body and comments.
- [x] Add inactive-user access block.
- [x] Add final-admin protection.
- [x] Ensure LDAP passwords are never stored when LDAP phase is implemented.
- [x] Ensure closed tickets are read-only.

## Phase 12 - Microsoft Active Directory / LDAPS Future Phase

Purpose: authenticate users against AD with LdapRecord and sync identity metadata locally.

- [x] Install `directorytree/ldaprecord` through Composer.
- [x] Add LDAP environment variables.
- [x] Add LDAP config loader.
- [x] Add LDAP auth helper/service.
- [x] Login attempts local auth first.
- [x] If local auth fails, attempt LDAP auth.
- [x] LDAP must use LDAPS, normally port `636`.
- [x] On successful LDAP auth, locate user by `directory_guid` first.
- [x] If no GUID match, locate by `username` or `directory_username`.
- [x] If no local user exists, create one automatically.
- [x] New LDAP users get `role = 'user'`.
- [x] New LDAP users get `auth_provider = 'ldap'`.
- [x] Sync display name.
- [x] Sync email if available.
- [x] Sync username.
- [x] Sync distinguished name.
- [x] Sync object GUID.
- [x] Set `directory_synced_at`.
- [x] Set `last_login_at`.
- [x] Do not save the LDAP password.
- [x] Do not overwrite local app role from AD in MVP.
- [x] Disable local password change for LDAP users.
- [x] Show LDAP metadata read-only in admin user UI.

Recommended LDAP env names:

```text
LDAP_HOST=
LDAP_PORT=636
LDAP_BASE_DN=
LDAP_USERNAME=
LDAP_PASSWORD=
LDAP_USE_SSL=true
LDAP_USE_TLS=false
LDAP_TIMEOUT=5
LDAP_DOMAIN=
LDAP_USER_FILTER_ATTRIBUTE=samaccountname
LDAP_TLS_REQUIRE_CERT=true
```

## Phase 13 - Post-MVP Improvements

### Internal Staff Notes

- [x] Expose `is_internal` comments to staff/admin only.
- [x] Hide internal comments from normal users.
- [x] Add visual distinction in ticket timeline.
- [x] Add audit event for internal notes.

### Attachments

- [ ] Add `ticket_attachments` table.
- [ ] Add upload directory strategy.
- [ ] Store files outside executable paths if practical.
- [ ] Validate file size.
- [ ] Validate MIME type.
- [ ] Generate safe stored filenames.
- [ ] Restrict downloads by ticket permissions.
- [ ] Add image preview for safe image types.
- [ ] Consider antivirus scanning for production use.

### WYSIWYG And Inline Images

- [ ] Choose local editor library with no CDN/runtime internet dependency.
- [ ] Add server-side HTML sanitizer.
- [ ] Define allowed tags and attributes.
- [ ] Treat inline images as attachments.
- [ ] Ensure inline images belong to the same ticket.
- [ ] Render sanitized HTML only.

### Email Notifications

- [ ] Add mailer dependency/config.
- [ ] Send ticket-created notification if desired.
- [ ] Send staff-reply notification to requester.
- [ ] Send user-reply notification to assigned staff.
- [ ] Add notification preferences later.
- [ ] Defer reply-by-email unless there is a strong need.

### Settings Page

- [ ] Add settings table.
- [ ] Add admin settings UI.
- [ ] Add editable auto-close days.
- [ ] Add future settings for assignment requirements, notification behavior, and upload limits.

### Departments, SLA, And Reporting

- [ ] Add departments/queues.
- [ ] Add team assignment.
- [ ] Add SLA target response times.
- [ ] Add escalation logic.
- [ ] Add dashboard cards.
- [ ] Add CSV exports.
- [ ] Add reports by status, priority, staff member, and resolution time.

## MVP Acceptance Checklist

- [ ] Admin can create a local user with a username and temporary password.
- [ ] Public signup is not available.
- [ ] User must reset temporary password after first login.
- [ ] User can create a ticket with subject and body.
- [ ] User can view their own tickets.
- [ ] User cannot view another user's ticket by changing the URL.
- [ ] Staff can view all tickets.
- [ ] Admin can view all tickets.
- [ ] Staff/admin can assign tickets.
- [ ] Staff/admin can change priority.
- [ ] Staff/admin can change status except to `closed`.
- [ ] Staff/admin must comment when resolving a ticket.
- [ ] User reply to resolved ticket reopens it.
- [ ] User reply to waiting-on-user ticket reopens it.
- [ ] Closed tickets are read-only.
- [ ] Auto-close script closes resolved tickets older than 14 days.
- [ ] Ticket events record creation, comments, status changes, priority changes, assignment changes, reopens, and auto-closes.
- [ ] Admin can change roles.
- [ ] Staff cannot change roles.
- [ ] Final active admin cannot be demoted or deactivated.
- [ ] All user-generated ticket content is escaped.
- [ ] POST routes are protected by CSRF tokens before real deployment.

## Recommended Build Order

1. User schema and username login.
2. Role helpers and access checks.
3. Disable public signup.
4. Admin user management.
5. Forced password reset for temporary passwords.
6. Ticket, comment, and event migrations.
7. Ticket models.
8. User ticket create/list/detail flow.
9. Staff/admin global queue and ticket controls.
10. Replies and reopen behavior.
11. Auto-close script.
12. CSRF and hardening.
13. HTMX enhancements.
14. LDAP/AD auth phase.

## Open Risks And Notes

- LDAP password hashes should not be stored locally. AD should remain the password authority for LDAP users.
- Username backfill from existing email/name must handle collisions.
- The current model trait is intentionally simple; more complex ticket list filtering may need targeted SQL methods on `Ticket` rather than forcing everything through generic helpers.
- CSRF protection is not currently present and should be added before production use.
- If ticket body later becomes rich text, server-side sanitization is mandatory.
- If attachments are added, download authorization is as important as upload validation.
