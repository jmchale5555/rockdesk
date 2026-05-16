<?php

namespace Controller;

use Model\Ticket;
use Model\TicketEvent;

defined('ROOTPATH') or exit('Access Denied');

class Tickets
{
    use MainController;

    public function index()
    {
        require_login();

        $ticket = new Ticket;
        $tickets = is_staff_or_admin()
            ? $ticket->listForStaff()
            : $ticket->listForUser((int)current_user_id());

        $this->view('tickets/index', [
            'tickets' => $tickets ?: [],
            'isStaffQueue' => is_staff_or_admin(),
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

        $this->view('tickets/show', [
            'ticket' => $row,
        ]);
    }
}
