<?php

namespace Core;

defined('ROOTPATH') or exit('Access Denied');

class InboundMailCleaner
{
    public function cleanText(string $body): string
    {
        $original = trim(str_replace(["\r\n", "\r"], "\n", $body));
        if ($original === '')
        {
            return '';
        }

        $lines = explode("\n", $original);
        $cutAt = count($lines);

        foreach ($lines as $index => $line)
        {
            $trimmed = trim($line);

            if ($this->isQuoteBoundary($trimmed))
            {
                $cutAt = $index;
                break;
            }

            if ($this->looksLikeOutlookHeaderBlock($lines, $index))
            {
                $cutAt = $index;
                break;
            }
        }

        $cleanedLines = array_slice($lines, 0, $cutAt);
        $cleanedLines = array_values(array_filter($cleanedLines, fn ($line) => !preg_match('/^\s*>/', $line)));
        $cleaned = trim(implode("\n", $cleanedLines));
        $cleaned = $this->removeTrailingSignature($cleaned);

        return $this->safeResult($original, $cleaned);
    }

    public function cleanHtml(string $html): string
    {
        $original = trim($html);
        if ($original === '')
        {
            return '';
        }

        $cleaned = preg_replace('/<blockquote\b[^>]*>.*?<\/blockquote>/is', '', $original) ?? $original;
        $cleaned = preg_replace('/<div\b[^>]*(class|id)=["\'][^"\']*(gmail_quote|gmail_attr|yahoo_quoted|divRplyFwdMsg)[^"\']*["\'][^>]*>.*?<\/div>/is', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<hr\b[^>]*>\s*<div\b[^>]*>\s*<b>From:<\/b>[\s\S]*$/i', '', $cleaned) ?? $cleaned;
        $cleaned = preg_replace('/<div\b[^>]*>\s*On .+? wrote:\s*<\/div>[\s\S]*$/is', '', $cleaned) ?? $cleaned;
        $cleaned = sanitize_rich_text($cleaned);

        return $this->safeResult(sanitize_rich_text($original), $cleaned);
    }

    private function looksLikeOutlookHeaderBlock(array $lines, int $index): bool
    {
        if (!preg_match('/^\s*(From:|_{8,}\s*From:)\s+.+/i', (string)($lines[$index] ?? '')))
        {
            return false;
        }

        $window = implode("\n", array_slice($lines, $index, 8));

        return preg_match('/^\s*Sent:\s+.+/mi', $window)
            && preg_match('/^\s*To:\s+.+/mi', $window)
            && preg_match('/^\s*Subject:\s+.+/mi', $window);
    }

    private function removeTrailingSignature(string $body): string
    {
        $body = preg_replace('/\n--\s*\n[\s\S]{0,800}$/', '', $body) ?? $body;
        $body = preg_replace('/\n?(Sent from my iPhone|Sent from Outlook for iOS|Get Outlook for Android)\s*$/i', '', $body) ?? $body;

        return trim($body);
    }

    private function isQuoteBoundary(string $line): bool
    {
        if ($line === '-----Original Message-----' || preg_match('/^[-_]{8,}\s*Original Message\s*[-_]{8,}$/i', $line))
        {
            return true;
        }

        if (preg_match('/^[-_]{8,}\s*Forwarded message\s*[-_]{8,}$/i', $line))
        {
            return true;
        }

        if (preg_match('/^On .{3,300} wrote:$/i', $line))
        {
            return true;
        }

        return preg_match('/^From:\s+.+\n?$/i', $line) && preg_match('/Sent from Mail/i', $line);
    }

    private function safeResult(string $original, string $cleaned): string
    {
        $originalPlain = trim(rich_text_to_plain_text($original));
        $cleanedPlain = trim(rich_text_to_plain_text($cleaned));

        if ($cleanedPlain === '')
        {
            return $original;
        }

        if (mb_strlen($originalPlain) > 300 && mb_strlen($cleanedPlain) < 10)
        {
            return $original;
        }

        return trim($cleaned);
    }
}
