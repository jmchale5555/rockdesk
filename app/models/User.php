<?php

namespace Model;

class User
{

    use Model;

    protected $table = 'users';

    protected $allowedColumns = [
        'name',
        'email',
        'password',
        'updated_at',
    ];

    public function validate($data)
    {
        $this->errors = [];

        if (empty($data['name']))
        {
            $this->errors['name'] = "Name is required";
        }
        else
        if (empty($data['email']))
        {
            $this->errors['email'] = "Email is required";
        }
        else
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL))
        {
            $this->errors['email'] = "Enter a valid email address";
        }

        if (empty($data['password']))
        {
            $this->errors['password'] = "Password is required";
        }

        if ($data['confirm'])
        {
            if ($data['confirm'] !== $data['password'])
            {
                $this->errors['confirm'] = "Passwords do not match";
            }
            else
                unset($data[2]);
        }

        if (empty($this->errors))
        {
            return true;
        }

        return false;
    }

    public function validatePasswordChange(array $data, string $currentPasswordHash): bool
    {
        $this->errors = [];

        if (empty($data['current_password']))
        {
            $this->errors['current_password'] = "Current password is required";
        }
        else
        if (!password_verify($data['current_password'], $currentPasswordHash))
        {
            $this->errors['current_password'] = "Current password is incorrect";
        }

        if (empty($data['password']))
        {
            $this->errors['password'] = "New password is required";
        }
        else
        if (strlen($data['password']) < 8)
        {
            $this->errors['password'] = "New password must be at least 8 characters";
        }

        if (empty($data['confirm']))
        {
            $this->errors['confirm'] = "Please confirm your new password";
        }
        else
        if (!empty($data['password']) && $data['confirm'] !== $data['password'])
        {
            $this->errors['confirm'] = "Passwords do not match";
        }

        if (!empty($data['password']) && password_verify($data['password'], $currentPasswordHash))
        {
            $this->errors['password'] = "New password must be different from your current password";
        }

        return empty($this->errors);
    }
}
