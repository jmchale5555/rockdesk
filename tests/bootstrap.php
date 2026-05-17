<?php

define('ROOTPATH', __DIR__ . '/../public/');
define('ROOT', 'http://localhost');
defined('APP_NAME') || define('APP_NAME', 'Rockdesk');
defined('MAIL_FROM_ADDRESS') || define('MAIL_FROM_ADDRESS', 'support@example.com');
defined('MAIL_FROM_NAME') || define('MAIL_FROM_NAME', 'Rockdesk');
defined('INBOUND_MAIL_ADDRESS') || define('INBOUND_MAIL_ADDRESS', 'support@example.com');
defined('INBOUND_MAIL_PLUS_ADDRESSING_ENABLED') || define('INBOUND_MAIL_PLUS_ADDRESSING_ENABLED', true);
defined('INBOUND_MAIL_PLUS_DELIMITER') || define('INBOUND_MAIL_PLUS_DELIMITER', '+');

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/core/Session.php';
require_once __DIR__ . '/../app/core/functions.php';
require_once __DIR__ . '/../app/core/Database.php';
require_once __DIR__ . '/../app/core/Model.php';
require_once __DIR__ . '/../app/core/Mailer.php';
require_once __DIR__ . '/../app/core/TicketNotifier.php';
require_once __DIR__ . '/../app/models/User.php';
require_once __DIR__ . '/../app/models/Ticket.php';
require_once __DIR__ . '/../app/models/TicketEmailMessage.php';
require_once __DIR__ . '/../app/models/TicketComment.php';
require_once __DIR__ . '/../app/models/TicketAttachment.php';
require_once __DIR__ . '/../app/models/TicketEvent.php';
