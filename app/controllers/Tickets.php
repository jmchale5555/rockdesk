<?php

namespace Controller;

use Model\Ticket;
use Model\TicketAttachment;
use Model\TicketComment;
use Model\TicketEvent;
use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class Tickets
{
    use MainController;

    public function index()
    {
        require_login();

        $ticket = new Ticket;
        $defaultStatus = is_staff_or_admin() ? Ticket::STATUS_FILTER_ACTIVE : '';
        $filters = [
            'status' => $_GET['status'] ?? $defaultStatus,
            'priority' => $_GET['priority'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'requester' => $_GET['requester'] ?? '',
        ];
        if (is_staff_or_admin() && !$ticket->isValidStaffStatusFilter((string)$filters['status']))
        {
            $filters['status'] = Ticket::STATUS_FILTER_ACTIVE;
        }
        $tickets = is_staff_or_admin()
            ? $ticket->listForStaff($filters)
            : $ticket->listForUser((int)current_user_id());
        $user = new User;

        $this->view('tickets/index', [
            'tickets' => $tickets ?: [],
            'isStaffQueue' => is_staff_or_admin(),
            'filters' => $filters,
            'staffUsers' => $user->listAssignableStaff() ?: [],
        ]);
    }

    public function create()
    {
        require_login();

        $this->view('tickets/create');
    }

    public function store()
    {
        require_login();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/create');
        }

        require_csrf();

        $ticket = new Ticket;
        $ticketNumber = $ticket->nextTicketNumber();
        $data = $ticket->makeCreateData(
            (int)current_user_id(),
            (string)($_POST['subject'] ?? ''),
            sanitize_rich_text((string)($_POST['body'] ?? '')),
            $ticketNumber
        );

        if ($ticket->validateCreate($data))
        {
            $ticket->insert($data);
            $created = $ticket->first(['ticket_number' => $ticketNumber]);

            if ($created)
            {
                $event = new TicketEvent;
                $event->insert([
                    'ticket_id' => (int)$created->id,
                    'user_id' => (int)current_user_id(),
                    'event_type' => 'created',
                    'new_value' => 'new',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                message('Ticket created successfully.');
                redirect('tickets/show/' . (int)$created->id);
            }

            $ticket->errors['ticket'] = 'Ticket could not be loaded after creation';
        }

        $this->view('tickets/create', [
            'errors' => $ticket->errors,
        ]);
    }

    public function show($id = '')
    {
        require_login();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        require_ticket_access($row);
        $attachment = new TicketAttachment;
        $user = new User;
        $comment = new TicketComment;

        $this->view('tickets/show', [
            'ticket' => $row,
            'staffUsers' => $user->listAssignableStaff() ?: [],
            'comments' => $comment->listVisibleForTicket((int)$row->id, is_staff_or_admin()) ?: [],
            'attachments' => $attachment->listForTicket((int)$row->id, false) ?: [],
        ]);
    }

    public function reply($id = '')
    {
        require_login();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        require_ticket_access($row);

        if (!$ticket->canReply($row))
        {
            $this->renderShowWithErrors($row, ['reply' => 'Closed tickets are read-only']);
            return;
        }

        $comment = new TicketComment;
        $data = [
            'ticket_id' => (int)$row->id,
            'user_id' => (int)current_user_id(),
            'body' => sanitize_rich_text((string)($_POST['body'] ?? '')),
            'is_internal' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$comment->validateCreate($data))
        {
            $this->renderShowWithErrors($row, $comment->errors);
            return;
        }

        $comment->insert($data);
        $update = $ticket->replyUpdateData($row, current_user());
        $ticket->update((int)$row->id, $update);

        $this->recordEvent((int)$row->id, 'commented', null, null, trim($data['body']));

        if (($update['status'] ?? '') === 'open')
        {
            $this->recordEvent((int)$row->id, 'reopened_by_user_reply', (string)$row->status, 'open');
        }

        message('Reply added successfully.');
        redirect('tickets/show/' . (int)$row->id);
    }

    public function internal($id = '')
    {
        require_role(['staff', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        if (!$ticket->canReply($row))
        {
            $this->renderShowWithErrors($row, ['internal_note' => 'Closed tickets are read-only']);
            return;
        }

        $comment = new TicketComment;
        $data = [
            'ticket_id' => (int)$row->id,
            'user_id' => (int)current_user_id(),
            'body' => sanitize_rich_text((string)($_POST['internal_body'] ?? '')),
            'is_internal' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (!$comment->validateCreate($data))
        {
            $this->renderShowWithErrors($row, $comment->errors);
            return;
        }

        $comment->insert($data);
        $ticket->update((int)$row->id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent((int)$row->id, 'internal_note_added', null, null, trim($data['body']));

        message('Internal note added.');
        redirect('tickets/show/' . (int)$row->id);
    }

    public function message($id = '')
    {
        require_login();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        require_ticket_access($row);

        if (!$ticket->canReply($row))
        {
            $this->renderShowWithErrors($row, ['message' => 'Closed tickets are read-only']);
            return;
        }

        $isStaff = is_staff_or_admin();
        $body = sanitize_rich_text((string)($_POST['body'] ?? ''));
        $plainBody = rich_text_to_plain_text($body);
        $hasMessage = $plainBody !== '';
        $isInternal = $isStaff && !empty($_POST['is_internal']);
        $newStatus = $isStaff ? (string)($_POST['status'] ?? $row->status) : (string)$row->status;
        $newPriority = $isStaff ? (string)($_POST['priority'] ?? $row->priority) : (string)$row->priority;
        $priorityChanged = $isStaff && $newPriority !== (string)$row->priority;
        $assignedTo = $isStaff ? (int)($_POST['assigned_to'] ?? (int)($row->assigned_to ?? 0)) : (int)($row->assigned_to ?? 0);
        $oldAssignedTo = (int)($row->assigned_to ?? 0);
        $assignmentChanged = $isStaff && $assignedTo !== $oldAssignedTo;
        $newStatus = $isStaff
            ? $ticket->statusAfterStaffEngagement((string)$row->status, $newStatus, $hasMessage || $priorityChanged || $assignmentChanged)
            : $newStatus;
        $statusChanged = $isStaff && $newStatus !== (string)$row->status;
        $ticketChanged = $statusChanged || $priorityChanged || $assignmentChanged;
        $formData = $this->messageFormData($body, $isInternal, $newStatus, $newPriority, $assignedTo);

        if ($isStaff && $statusChanged && !$ticket->validateStatus($newStatus, true))
        {
            $this->renderShowWithErrors($row, $ticket->errors, $formData);
            return;
        }

        if ($isStaff && $priorityChanged && !$ticket->validatePriority($newPriority))
        {
            $this->renderShowWithErrors($row, $ticket->errors, $formData);
            return;
        }

        if ($isStaff && $assignmentChanged)
        {
            $user = new User;
            if ($assignedTo > 0 && !$user->isAssignableStaff($assignedTo))
            {
                $this->renderShowWithErrors($row, ['assigned_to' => 'Choose an active staff or admin user'], $formData);
                return;
            }
        }

        if (!$ticket->validateMessageComposer($newStatus, $body, $isInternal, $ticketChanged))
        {
            $this->renderShowWithErrors($row, $ticket->errors, $formData);
            return;
        }

        $updateData = [];

        if ($statusChanged)
        {
            $updateData = array_merge($updateData, $ticket->statusUpdateData((string)$row->status, $newStatus));
            $this->recordEvent((int)$row->id, 'status_changed', (string)$row->status, $newStatus, $newStatus === 'resolved' ? $body : null);
        }

        if ($priorityChanged)
        {
            $updateData['priority'] = $newPriority;
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->recordEvent((int)$row->id, 'priority_changed', (string)$row->priority, $newPriority);
        }

        if ($assignmentChanged)
        {
            $updateData['assigned_to'] = $assignedTo > 0 ? $assignedTo : null;
            $updateData['updated_at'] = date('Y-m-d H:i:s');
            $this->recordEvent((int)$row->id, 'assigned', (string)($row->assigned_to ?? ''), $assignedTo > 0 ? (string)$assignedTo : '');
        }

        if (!empty($updateData))
        {
            $ticket->update((int)$row->id, $updateData);
        }

        if ($hasMessage)
        {
            $comment = new TicketComment;
            $commentData = [
                'ticket_id' => (int)$row->id,
                'user_id' => (int)current_user_id(),
                'body' => $body,
                'is_internal' => $newStatus === 'resolved' ? 0 : ($isInternal ? 1 : 0),
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (!$comment->validateCreate($commentData))
            {
                $this->renderShowWithErrors($row, $comment->errors, $formData);
                return;
            }

            $comment->insert($commentData);

            if (!$statusChanged)
            {
                $update = $ticket->replyUpdateData($row, current_user());
                $ticket->update((int)$row->id, $update);

                if (($update['status'] ?? '') === 'open')
                {
                    $this->recordEvent((int)$row->id, 'reopened_by_user_reply', (string)$row->status, 'open');
                }
            }

            $this->recordEvent(
                (int)$row->id,
                (int)$commentData['is_internal'] === 1 ? 'internal_note_added' : 'commented',
                null,
                null,
                trim($body)
            );
        }

        message($this->messageSuccessText($hasMessage, $ticketChanged));
        redirect('tickets/show/' . (int)$row->id);
    }

    public function upload($id = '')
    {
        require_login();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        require_ticket_access($row);

        if (!$ticket->canReply($row))
        {
            $this->renderShowWithErrors($row, ['attachment' => 'Closed tickets are read-only']);
            return;
        }

        $file = $_FILES['attachment'] ?? [];

        $createdAttachment = $this->saveAttachment($row, $file, false);

        if (!$createdAttachment)
        {
            $this->renderShowWithErrors($row, ['attachment' => 'Image upload failed']);
            return;
        }

        message('Image attached successfully.');
        redirect('tickets/show/' . (int)$row->id);
    }

    public function inlineupload($id = '')
    {
        require_login();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            http_response_code(405);
            $this->json(['error' => 'Method not allowed']);
            return;
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->json(['error' => 'Ticket not found']);
            return;
        }

        require_ticket_access($row);

        if (!$ticket->canReply($row))
        {
            http_response_code(422);
            $this->json(['error' => 'Closed tickets are read-only']);
            return;
        }

        $createdAttachment = $this->saveAttachment($row, $_FILES['attachment'] ?? [], true);

        if (!$createdAttachment)
        {
            http_response_code(422);
            $this->json(['error' => 'Image upload failed']);
            return;
        }

        $url = ROOT . '/tickets/attachment/' . (int)$createdAttachment->id;
        $this->json([
            'url' => $url,
            'href' => $url,
        ]);
    }

    public function attachment($id = '')
    {
        require_login();

        $attachment = new TicketAttachment;
        $row = $attachment->findWithTicket((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        require_ticket_access((object)[
            'id' => (int)$row->ticket_id,
            'user_id' => (int)$row->ticket_user_id,
        ]);

        if (!$attachment->isAllowedMimeType((string)$row->mime_type))
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $path = $this->attachmentStoragePath((string)$row->stored_name);
        if (!is_file($path))
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        header('Content-Type: ' . $row->mime_type);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . addcslashes((string)$row->original_name, '"\\') . '"');
        readfile($path);
        exit;
    }

    public function status($id = '')
    {
        require_role(['staff', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $newStatus = (string)($_POST['status'] ?? '');
        $resolutionComment = sanitize_rich_text((string)($_POST['resolution_comment'] ?? ''));

        $ticket->validateStatus($newStatus, true);
        if (empty($ticket->errors))
        {
            $ticket->validateResolutionComment($newStatus, $resolutionComment);
        }

        if (!empty($ticket->errors))
        {
            $this->renderShowWithErrors($row, $ticket->errors);
            return;
        }

        $update = $ticket->statusUpdateData((string)$row->status, $newStatus);
        $ticket->update((int)$row->id, $update);

        if ($newStatus === 'resolved')
        {
            $comment = new TicketComment;
            $comment->insert([
                'ticket_id' => (int)$row->id,
                'user_id' => (int)current_user_id(),
                'body' => $resolutionComment,
                'is_internal' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->recordEvent((int)$row->id, 'status_changed', (string)$row->status, $newStatus, $newStatus === 'resolved' ? $resolutionComment : null);

        message('Ticket status updated.');
        redirect('tickets/show/' . (int)$row->id);
    }

    public function priority($id = '')
    {
        require_role(['staff', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $priority = (string)($_POST['priority'] ?? '');

        if (!$ticket->validatePriority($priority))
        {
            $this->renderShowWithErrors($row, $ticket->errors);
            return;
        }

        $ticket->update((int)$row->id, [
            'priority' => $priority,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->recordEvent((int)$row->id, 'priority_changed', (string)$row->priority, $priority);

        message('Ticket priority updated.');
        redirect('tickets/show/' . (int)$row->id);
    }

    public function assign($id = '')
    {
        require_role(['staff', 'admin']);

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('tickets/show/' . (int)$id);
        }

        require_csrf();

        $ticket = new Ticket;
        $row = $ticket->findWithRequester((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $assignedTo = (int)($_POST['assigned_to'] ?? 0);
        $user = new User;

        if ($assignedTo > 0 && !$user->isAssignableStaff($assignedTo))
        {
            $this->renderShowWithErrors($row, ['assigned_to' => 'Choose an active staff or admin user']);
            return;
        }

        $ticket->update((int)$row->id, [
            'assigned_to' => $assignedTo > 0 ? $assignedTo : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->recordEvent((int)$row->id, 'assigned', (string)($row->assigned_to ?? ''), $assignedTo > 0 ? (string)$assignedTo : '');

        message('Ticket assignment updated.');
        redirect('tickets/show/' . (int)$row->id);
    }

    private function recordEvent(int $ticketId, string $eventType, ?string $oldValue = null, ?string $newValue = null, ?string $body = null): void
    {
        $event = new TicketEvent;
        $event->insert([
            'ticket_id' => $ticketId,
            'user_id' => (int)current_user_id(),
            'event_type' => $eventType,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'body' => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function renderShowWithErrors(mixed $ticketRow, array $errors, array $formData = []): void
    {
        $user = new User;
        $comment = new TicketComment;
        $attachment = new TicketAttachment;
        $this->view('tickets/show', [
            'ticket' => $ticketRow,
            'staffUsers' => $user->listAssignableStaff() ?: [],
            'comments' => $comment->listVisibleForTicket((int)$ticketRow->id, is_staff_or_admin()) ?: [],
            'attachments' => $attachment->listForTicket((int)$ticketRow->id, false) ?: [],
            'errors' => $errors,
            'formData' => $formData,
        ]);
    }

    private function messageFormData(string $body, bool $isInternal, string $status, ?string $priority = null, ?int $assignedTo = null): array
    {
        $data = [
            'body' => $body,
            'is_internal' => $isInternal ? '1' : '0',
            'status' => $status,
        ];

        if ($priority !== null)
        {
            $data['priority'] = $priority;
        }

        if ($assignedTo !== null)
        {
            $data['assigned_to'] = (string)$assignedTo;
        }

        return $data;
    }

    private function messageSuccessText(bool $hasMessage, bool $ticketChanged): string
    {
        if ($hasMessage && $ticketChanged)
        {
            return 'Ticket updated and message added.';
        }

        if ($ticketChanged)
        {
            return 'Ticket updated.';
        }

        return 'Message added successfully.';
    }

    private function ensureAttachmentStorage(): bool
    {
        $directory = dirname($this->attachmentStoragePath('placeholder'));

        return is_dir($directory) || mkdir($directory, 0755, true);
    }

    private function attachmentStoragePath(string $storedName): string
    {
        return ROOTPATH . '../storage/ticket-attachments/' . basename($storedName);
    }

    private function saveAttachment(mixed $ticketRow, array $file, bool $isInline): mixed
    {
        $attachment = new TicketAttachment;

        if (!$attachment->validateUpload($file))
        {
            return false;
        }

        $mimeType = $attachment->detectMimeType((string)$file['tmp_name']);
        $storedName = bin2hex(random_bytes(16)) . '.' . $attachment->extensionForMimeType($mimeType);
        $storagePath = $this->attachmentStoragePath($storedName);

        if (!$this->ensureAttachmentStorage() || !move_uploaded_file((string)$file['tmp_name'], $storagePath))
        {
            $attachment->errors['attachment'] = 'Image could not be saved';
            return false;
        }

        $ticket = new Ticket;
        $originalName = $attachment->safeOriginalName((string)($file['name'] ?? 'image'));
        $attachment->insert([
            'ticket_id' => (int)$ticketRow->id,
            'user_id' => (int)current_user_id(),
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => (int)$file['size'],
            'is_inline' => $isInline ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $ticket->update((int)$ticketRow->id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent((int)$ticketRow->id, 'attachment_uploaded', null, $originalName);

        return $attachment->get_row(
            'select * from ticket_attachments where stored_name = :stored_name limit 1',
            ['stored_name' => $storedName]
        );
    }

    private function json(array $data): void
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
