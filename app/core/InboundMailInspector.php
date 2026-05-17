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

        foreach (['List-Id', 'List-Unsubscribe', 'X-Autoreply', 'X-Autorespond'] as $header)
        {
            if ($message->header($header) !== '')
            {
                return strtolower($header) . ' header';
            }
        }

        return '';
    }

    public function shouldIgnore(InboundMessage $message): bool
    {
        return $this->ignoreReason($message) !== '';
    }
}
