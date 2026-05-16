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
        $filters = [
            'status' => $_GET['status'] ?? '',
            'priority' => $_GET['priority'] ?? '',
            'assigned_to' => $_GET['assigned_to'] ?? '',
            'requester' => $_GET['requester'] ?? '',
        ];
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
            (string)($_POST['body'] ?? ''),
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
            'attachments' => $attachment->listForTicket((int)$row->id) ?: [],
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
            'body' => (string)($_POST['body'] ?? ''),
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
            'body' => (string)($_POST['internal_body'] ?? ''),
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

        $attachment = new TicketAttachment;
        $file = $_FILES['attachment'] ?? [];

        if (!$attachment->validateUpload($file))
        {
            $this->renderShowWithErrors($row, $attachment->errors);
            return;
        }

        $mimeType = $attachment->detectMimeType((string)$file['tmp_name']);
        $storedName = bin2hex(random_bytes(16)) . '.' . $attachment->extensionForMimeType($mimeType);
        $storagePath = $this->attachmentStoragePath($storedName);

        if (!$this->ensureAttachmentStorage() || !move_uploaded_file((string)$file['tmp_name'], $storagePath))
        {
            $this->renderShowWithErrors($row, ['attachment' => 'Image could not be saved']);
            return;
        }

        $originalName = $attachment->safeOriginalName((string)($file['name'] ?? 'image'));
        $attachment->insert([
            'ticket_id' => (int)$row->id,
            'user_id' => (int)current_user_id(),
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => (int)$file['size'],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $ticket->update((int)$row->id, [
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->recordEvent((int)$row->id, 'attachment_uploaded', null, $originalName);

        message('Image attached successfully.');
        redirect('tickets/show/' . (int)$row->id);
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
        $resolutionComment = trim((string)($_POST['resolution_comment'] ?? ''));

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

    private function renderShowWithErrors(mixed $ticketRow, array $errors): void
    {
        $user = new User;
        $comment = new TicketComment;
        $attachment = new TicketAttachment;
        $this->view('tickets/show', [
            'ticket' => $ticketRow,
            'staffUsers' => $user->listAssignableStaff() ?: [],
            'comments' => $comment->listVisibleForTicket((int)$ticketRow->id, is_staff_or_admin()) ?: [],
            'attachments' => $attachment->listForTicket((int)$ticketRow->id) ?: [],
            'errors' => $errors,
        ]);
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
}
