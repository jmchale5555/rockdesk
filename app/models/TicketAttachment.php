<?php

namespace Model;

class TicketAttachment
{
    use Model;

    public const MAX_BYTES = 5242880;
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    protected $table = 'ticket_attachments';

    protected $allowedColumns = [
        'ticket_id',
        'user_id',
        'original_name',
        'stored_name',
        'mime_type',
        'file_size',
        'created_at',
    ];

    public function validateUpload(array $file): bool
    {
        $this->errors = [];

        if (empty($file) || !isset($file['error']))
        {
            $this->errors['attachment'] = 'Choose an image to upload';
            return false;
        }

        if ((int)$file['error'] !== UPLOAD_ERR_OK)
        {
            $this->errors['attachment'] = $this->uploadErrorMessage((int)$file['error']);
            return false;
        }

        if (empty($file['tmp_name']) || !is_file((string)$file['tmp_name']))
        {
            $this->errors['attachment'] = 'Uploaded file could not be read';
            return false;
        }

        if ((int)($file['size'] ?? 0) < 1)
        {
            $this->errors['attachment'] = 'Uploaded file is empty';
        }
        else
        if ((int)$file['size'] > self::MAX_BYTES)
        {
            $this->errors['attachment'] = 'Image must be 5 MB or smaller';
        }

        $mimeType = $this->detectMimeType((string)$file['tmp_name']);
        if (!$this->isAllowedMimeType($mimeType))
        {
            $this->errors['attachment'] = 'Only JPG, PNG, and WebP images are allowed';
        }

        return empty($this->errors);
    }

    public function isAllowedMimeType(string $mimeType): bool
    {
        return array_key_exists($mimeType, self::ALLOWED_MIME_TYPES);
    }

    public function extensionForMimeType(string $mimeType): string
    {
        return self::ALLOWED_MIME_TYPES[$mimeType] ?? 'bin';
    }

    public function detectMimeType(string $path): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($path);

        return is_string($mimeType) ? $mimeType : '';
    }

    public function safeOriginalName(string $name): string
    {
        $name = trim(basename($name));
        $name = preg_replace('/[^a-zA-Z0-9._ -]+/', '_', $name);
        $name = trim((string)$name, '. ');

        return mb_substr($name !== '' ? $name : 'image', 0, 255);
    }

    public function listForTicket(int $ticketId): array|bool
    {
        return $this->query(
            'select ticket_attachments.*, users.name, users.username
             from ticket_attachments
             join users on users.id = ticket_attachments.user_id
             where ticket_attachments.ticket_id = :ticket_id
             order by ticket_attachments.created_at asc, ticket_attachments.id asc',
            ['ticket_id' => $ticketId]
        );
    }

    public function findWithTicket(int $id): mixed
    {
        return $this->get_row(
            'select ticket_attachments.*, tickets.user_id as ticket_user_id, tickets.status as ticket_status
             from ticket_attachments
             join tickets on tickets.id = ticket_attachments.ticket_id
             where ticket_attachments.id = :id
             limit 1',
            ['id' => $id]
        );
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image must be 5 MB or smaller',
            UPLOAD_ERR_PARTIAL => 'Image only partially uploaded, please try again',
            UPLOAD_ERR_NO_FILE => 'Choose an image to upload',
            default => 'Image upload failed, please try again',
        };
    }
}
