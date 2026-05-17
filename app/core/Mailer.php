<?php

namespace Core;

use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Mailer as SymfonyMailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

defined('ROOTPATH') or exit('Access Denied');

class Mailer
{
    public function isConfigured(): bool
    {
        return defined('MAIL_ENABLED')
            && MAIL_ENABLED
            && defined('MAILER_DSN')
            && MAILER_DSN !== ''
            && defined('MAIL_FROM_ADDRESS')
            && filter_var(MAIL_FROM_ADDRESS, FILTER_VALIDATE_EMAIL);
    }

    public function send(array $recipients, string $subject, string $html, string $text = '', array $options = []): bool
    {
        if (!$this->isConfigured())
        {
            return false;
        }

        $to = $this->normalizeRecipients($recipients);
        if (empty($to))
        {
            return false;
        }

        $email = (new Email)
            ->from(new Address(MAIL_FROM_ADDRESS, defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : APP_NAME))
            ->to(...$to)
            ->subject($subject)
            ->html($html)
            ->text($text !== '' ? $text : trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $html))));

        $replyTo = trim((string)($options['reply_to'] ?? ''));
        if (filter_var($replyTo, FILTER_VALIDATE_EMAIL))
        {
            $email->replyTo($replyTo);
        }

        $headers = $email->getHeaders();
        foreach (($options['headers'] ?? []) as $name => $value)
        {
            $headers->addTextHeader((string)$name, (string)$value);
        }

        $messageId = trim((string)($options['message_id'] ?? ''));
        if ($messageId !== '')
        {
            $headers->addIdHeader('Message-ID', $messageId);
        }

        try
        {
            $mailer = new SymfonyMailer(Transport::fromDsn(MAILER_DSN));
            $mailer->send($email);
            return true;
        }
        catch (TransportExceptionInterface $e)
        {
            error_log('Mail delivery failed: ' . $e->getMessage());
            return false;
        }
        catch (\Throwable $e)
        {
            error_log('Mail setup failed: ' . $e->getMessage());
            return false;
        }
    }

    private function normalizeRecipients(array $recipients): array
    {
        $to = [];
        $seen = [];

        foreach ($recipients as $recipient)
        {
            $email = '';
            $name = '';

            if (is_array($recipient))
            {
                $email = trim((string)($recipient['email'] ?? ''));
                $name = trim((string)($recipient['name'] ?? ''));
            }
            else
            if (is_object($recipient))
            {
                $email = trim((string)($recipient->email ?? ''));
                $name = trim((string)($recipient->name ?? ''));
            }

            $key = strtolower($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || isset($seen[$key]))
            {
                continue;
            }

            $seen[$key] = true;
            $to[] = new Address($email, $name);
        }

        return $to;
    }
}
