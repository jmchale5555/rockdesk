<?php

namespace Core;

defined('ROOTPATH') or exit('Access Denied');

class ImapInboundMailSource
{
    private mixed $connection = null;

    public function isConfigured(): bool
    {
        return defined('INBOUND_IMAP_HOST') && INBOUND_IMAP_HOST !== ''
            && defined('INBOUND_IMAP_USERNAME') && INBOUND_IMAP_USERNAME !== ''
            && defined('INBOUND_IMAP_PASSWORD') && INBOUND_IMAP_PASSWORD !== '';
    }

    public function extensionLoaded(): bool
    {
        return extension_loaded('imap');
    }

    public function mailboxPath(?string $mailbox = null): string
    {
        $flags = '/imap';
        $encryption = defined('INBOUND_IMAP_ENCRYPTION') ? INBOUND_IMAP_ENCRYPTION : 'ssl';

        if ($encryption === 'ssl')
        {
            $flags .= '/ssl';
        }
        else
        if ($encryption === 'tls')
        {
            $flags .= '/tls';
        }
        else
        {
            $flags .= '/notls';
        }

        if (defined('INBOUND_IMAP_VALIDATE_CERT') && !INBOUND_IMAP_VALIDATE_CERT)
        {
            $flags .= '/novalidate-cert';
        }

        return '{' . INBOUND_IMAP_HOST . ':' . INBOUND_IMAP_PORT . $flags . '}' . ($mailbox ?? INBOUND_IMAP_MAILBOX);
    }

    public function open(): bool
    {
        if (!$this->extensionLoaded() || !$this->isConfigured())
        {
            return false;
        }

        $this->connection = @imap_open($this->mailboxPath(), INBOUND_IMAP_USERNAME, INBOUND_IMAP_PASSWORD);

        return $this->connection !== false;
    }

    /** @return array<int, InboundMessage> */
    public function fetch(int $limit = 25): array
    {
        if (!$this->connection)
        {
            return [];
        }

        $messageNumbers = imap_search($this->connection, 'UNSEEN') ?: [];
        sort($messageNumbers);
        $messages = [];

        foreach (array_slice($messageNumbers, 0, max(1, $limit)) as $messageNumber)
        {
            $messages[(int)$messageNumber] = $this->message((int)$messageNumber);
        }

        return $messages;
    }

    public function markProcessed(int $messageNumber): void
    {
        $this->moveOrFlag($messageNumber, defined('INBOUND_IMAP_PROCESSED_MAILBOX') ? INBOUND_IMAP_PROCESSED_MAILBOX : '');
    }

    public function markFailed(int $messageNumber): void
    {
        $this->moveOrFlag($messageNumber, defined('INBOUND_IMAP_FAILED_MAILBOX') ? INBOUND_IMAP_FAILED_MAILBOX : '');
    }

    public function close(): void
    {
        if ($this->connection)
        {
            imap_close($this->connection, CL_EXPUNGE);
            $this->connection = null;
        }
    }

    private function message(int $messageNumber): InboundMessage
    {
        $overview = imap_fetch_overview($this->connection, (string)$messageNumber, 0)[0] ?? null;
        $headerInfo = imap_headerinfo($this->connection, $messageNumber);
        $headers = $this->headers($messageNumber);
        $structure = imap_fetchstructure($this->connection, $messageNumber);
        $parts = $this->parts($messageNumber, $structure);

        return InboundMessage::fromArray([
            'message_id' => trim((string)($overview->message_id ?? ''), '<>'),
            'mailbox_uid' => (string)imap_uid($this->connection, $messageNumber),
            'from_email' => $this->address($headerInfo->from[0] ?? null),
            'from_name' => $this->personalName($headerInfo->from[0] ?? null),
            'to' => $this->addressList($headerInfo->to ?? []),
            'cc' => $this->addressList($headerInfo->cc ?? []),
            'subject' => $this->decodeHeader((string)($overview->subject ?? '')),
            'text_body' => $parts['text'],
            'html_body' => $parts['html'],
            'headers' => $headers,
            'attachments' => $parts['attachments'],
            'received_at' => !empty($overview->date) ? date('Y-m-d H:i:s', strtotime((string)$overview->date)) : null,
        ]);
    }

    private function headers(int $messageNumber): array
    {
        $raw = imap_fetchheader($this->connection, $messageNumber) ?: '';
        $headers = [];

        foreach (preg_split('/\r?\n(?!\s)/', $raw) ?: [] as $line)
        {
            if (!str_contains($line, ':'))
            {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = $this->decodeHeader(trim(preg_replace('/\r?\n\s+/', ' ', $value) ?? $value));
        }

        return $headers;
    }

    private function parts(int $messageNumber, mixed $structure, string $partNumber = ''): array
    {
        $result = ['text' => '', 'html' => '', 'attachments' => []];

        if (!$structure)
        {
            return $result;
        }

        if (!empty($structure->parts))
        {
            foreach ($structure->parts as $index => $part)
            {
                $child = $this->parts($messageNumber, $part, $partNumber === '' ? (string)($index + 1) : $partNumber . '.' . ($index + 1));
                $result['text'] .= $child['text'];
                $result['html'] .= $child['html'];
                $result['attachments'] = array_merge($result['attachments'], $child['attachments']);
            }

            return $result;
        }

        $body = imap_fetchbody($this->connection, $messageNumber, $partNumber !== '' ? $partNumber : '1') ?: imap_body($this->connection, $messageNumber) ?: '';
        $body = $this->decodeBody($body, (int)($structure->encoding ?? 0));
        $filename = $this->filename($structure);
        $subtype = strtolower((string)($structure->subtype ?? ''));
        $type = (int)($structure->type ?? 0);

        if ($filename !== '')
        {
            $result['attachments'][] = [
                'filename' => $filename,
                'mime_type' => $this->mimeType($structure),
                'content' => $body,
                'size' => strlen($body),
            ];
        }
        else
        if ($type === TYPETEXT && $subtype === 'plain')
        {
            $result['text'] .= $this->convertCharset($body, $structure);
        }
        else
        if ($type === TYPETEXT && $subtype === 'html')
        {
            $result['html'] .= $this->convertCharset($body, $structure);
        }

        return $result;
    }

    private function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body, true) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    private function filename(mixed $part): string
    {
        foreach (['dparameters', 'parameters'] as $property)
        {
            foreach (($part->{$property} ?? []) as $parameter)
            {
                if (in_array(strtolower((string)$parameter->attribute), ['filename', 'name'], true))
                {
                    return $this->decodeHeader((string)$parameter->value);
                }
            }
        }

        return '';
    }

    private function mimeType(mixed $part): string
    {
        $types = ['text', 'multipart', 'message', 'application', 'audio', 'image', 'video', 'other'];
        $type = $types[(int)($part->type ?? 7)] ?? 'application';
        $subtype = strtolower((string)($part->subtype ?? 'octet-stream'));

        return $type . '/' . $subtype;
    }

    private function convertCharset(string $body, mixed $part): string
    {
        $charset = '';
        foreach (($part->parameters ?? []) as $parameter)
        {
            if (strtolower((string)$parameter->attribute) === 'charset')
            {
                $charset = (string)$parameter->value;
                break;
            }
        }

        if ($charset !== '' && strtoupper($charset) !== 'UTF-8')
        {
            return mb_convert_encoding($body, 'UTF-8', $charset);
        }

        return $body;
    }

    private function decodeHeader(string $value): string
    {
        $decoded = '';

        foreach (imap_mime_header_decode($value) ?: [] as $part)
        {
            $charset = strtoupper((string)($part->charset ?? 'default'));
            $text = (string)($part->text ?? '');
            $decoded .= $charset !== 'DEFAULT' && $charset !== 'UTF-8'
                ? mb_convert_encoding($text, 'UTF-8', $charset)
                : $text;
        }

        return $decoded !== '' ? $decoded : $value;
    }

    private function address(mixed $address): string
    {
        if (!$address || empty($address->mailbox) || empty($address->host))
        {
            return '';
        }

        return strtolower((string)$address->mailbox . '@' . (string)$address->host);
    }

    private function personalName(mixed $address): string
    {
        return $address && !empty($address->personal) ? $this->decodeHeader((string)$address->personal) : '';
    }

    private function addressList(array $addresses): array
    {
        return array_values(array_filter(array_map(fn ($address) => $this->address($address), $addresses)));
    }

    private function moveOrFlag(int $messageNumber, string $mailbox): void
    {
        if (!$this->connection)
        {
            return;
        }

        if ($mailbox !== '')
        {
            @imap_mail_move($this->connection, (string)$messageNumber, $mailbox);
            return;
        }

        imap_setflag_full($this->connection, (string)$messageNumber, '\\Seen');
    }
}
