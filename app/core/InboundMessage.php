<?php

namespace Core;

defined('ROOTPATH') or exit('Access Denied');

class InboundMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $mailboxUid,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly array $to,
        public readonly array $cc,
        public readonly string $subject,
        public readonly string $textBody,
        public readonly string $htmlBody,
        public readonly array $headers,
        public readonly array $attachments = [],
        public readonly ?string $receivedAt = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            trim((string)($data['message_id'] ?? '')),
            trim((string)($data['mailbox_uid'] ?? '')),
            strtolower(trim((string)($data['from_email'] ?? ''))),
            trim((string)($data['from_name'] ?? '')),
            self::normalizeAddressList($data['to'] ?? []),
            self::normalizeAddressList($data['cc'] ?? []),
            trim((string)($data['subject'] ?? '')),
            (string)($data['text_body'] ?? ''),
            (string)($data['html_body'] ?? ''),
            self::normalizeHeaders($data['headers'] ?? []),
            is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
            isset($data['received_at']) ? trim((string)$data['received_at']) : null,
        );
    }

    public function header(string $name): string
    {
        return $this->headers[strtolower($name)] ?? '';
    }

    public function allRecipients(): array
    {
        return array_values(array_unique(array_merge($this->to, $this->cc)));
    }

    private static function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value)
        {
            $normalized[strtolower(trim((string)$name))] = trim(is_array($value) ? implode(', ', $value) : (string)$value);
        }

        return $normalized;
    }

    private static function normalizeAddressList(mixed $addresses): array
    {
        if (is_string($addresses))
        {
            $addresses = preg_split('/[,;]/', $addresses) ?: [];
        }

        if (!is_array($addresses))
        {
            return [];
        }

        $normalized = [];
        foreach ($addresses as $address)
        {
            $email = strtolower(trim((string)$address));
            if (preg_match('/<([^>]+)>/', $email, $matches))
            {
                $email = strtolower(trim($matches[1]));
            }

            if (filter_var($email, FILTER_VALIDATE_EMAIL))
            {
                $normalized[] = $email;
            }
        }

        return array_values(array_unique($normalized));
    }
}
