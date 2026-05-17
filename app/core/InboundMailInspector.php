<?php

namespace Core;

defined('ROOTPATH') or exit('Access Denied');

class InboundMailInspector
{
    public function ignoreReason(InboundMessage $message): string
    {
        $autoSubmitted = strtolower($message->header('Auto-Submitted'));
        if ($autoSubmitted !== '' && $autoSubmitted !== 'no')
        {
            return 'auto-submitted message';
        }

        $precedence = strtolower($message->header('Precedence'));
        if (in_array($precedence, ['bulk', 'junk', 'list'], true))
        {
            return 'bulk/list precedence';
        }

        if (strtolower($message->header('X-Loop')) === 'rockdesk')
        {
            return 'rockdesk loop header';
        }

        foreach (['X-Autoreply', 'X-Autorespond', 'X-Auto-Response-Suppress'] as $header)
        {
            if ($message->header($header) !== '')
            {
                return strtolower($header) . ' header';
            }
        }

        foreach (['X-MS-Exchange-Inbox-Rules-Loop', 'X-Failed-Recipients'] as $header)
        {
            if ($message->header($header) !== '')
            {
                return strtolower($header) . ' header';
            }
        }

        $from = strtolower($message->fromEmail);
        if (preg_match('/^(mailer-daemon|postmaster|no-reply|noreply)@/', $from))
        {
            return 'automated sender';
        }

        if ($this->hasListHeader($message) && !$this->hasReplySignal($message))
        {
            return 'list header without ticket signal';
        }

        return '';
    }

    public function shouldIgnore(InboundMessage $message): bool
    {
        return $this->ignoreReason($message) !== '';
    }

    private function hasListHeader(InboundMessage $message): bool
    {
        foreach (['List-Id', 'List-Unsubscribe', 'List-Post', 'List-Help', 'List-Archive'] as $header)
        {
            if ($message->header($header) !== '')
            {
                return true;
            }
        }

        return false;
    }

    private function hasReplySignal(InboundMessage $message): bool
    {
        if ($message->header('In-Reply-To') !== '' || $message->header('References') !== '')
        {
            return true;
        }

        if (preg_match('/\bTCK-\d{4}-\d{6}\b/i', $message->subject . ' ' . $message->textBody . ' ' . $message->htmlBody))
        {
            return true;
        }

        if (preg_match('/rockdesk-ticket-token:\s*[a-f0-9]{32}/i', $message->textBody . ' ' . $message->htmlBody))
        {
            return true;
        }

        foreach ($message->allRecipients() as $recipient)
        {
            if (preg_match('/[+._-][a-f0-9]{32}@/i', $recipient))
            {
                return true;
            }
        }

        return false;
    }
}
