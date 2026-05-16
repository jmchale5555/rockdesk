<?php

namespace Controller;

use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class Users
{
    use MainController;

    public function index()
    {
        require_role('admin');

        $user = new User;
        $this->view('users/index', [
            'users' => $user->listForAdmin() ?: [],
        ]);
    }

    public function create()
    {
        require_role('admin');

        $this->view('users/create');
    }

    public function store()
    {
        require_role('admin');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('users/create');
        }

        require_csrf();

        $user = new User;
        $data = $this->userDataFromPost($user, true);

        if ($user->validateAdminCreate($data))
        {
            $this->validateUniqueUserFields($user, $data);
        }

        if (empty($user->errors))
        {
            $now = date('Y-m-d H:i:s');
            $user->insert([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?: null,
                'password' => password_hash($data['password'], PASSWORD_BCRYPT),
                'role' => $data['role'],
                'auth_provider' => 'local',
                'is_active' => $data['is_active'],
                'must_reset_password' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            message('User created successfully. Give them the temporary password and ask them to reset it immediately.');
            redirect('users');
        }

        $this->view('users/create', [
            'errors' => $user->errors,
        ]);
    }

    public function edit($id = '')
    {
        require_role('admin');

        $user = new User;
        $row = $user->findById((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        $this->view('users/edit', [
            'user' => $row,
        ]);
    }

    public function update($id = '')
    {
        require_role('admin');

        $user = new User;
        $row = $user->findById((int)$id);

        if (!$row)
        {
            http_response_code(404);
            $this->view('404');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST')
        {
            redirect('users/edit/' . (int)$row->id);
        }

        require_csrf();

        $data = $this->userDataFromPost($user, false);

        if ($user->validateAdminUpdate($data))
        {
            $this->validateUniqueUserFields($user, $data, (int)$row->id);
            $this->validateFinalAdminChange($user, $row, $data);

            if (($row->auth_provider ?? 'local') === 'ldap' && !empty($data['password']))
            {
                $user->errors['password'] = 'LDAP user passwords are managed by Active Directory';
            }
        }

        if (empty($user->errors))
        {
            $now = date('Y-m-d H:i:s');
            $update = [
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?: null,
                'role' => $data['role'],
                'is_active' => $data['is_active'],
                'updated_at' => $now,
            ];

            if (!empty($data['password']))
            {
                $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
                $update['must_reset_password'] = 1;
            }

            $user->update((int)$row->id, $update);

            if (!empty($_SESSION['USER']) && (int)$_SESSION['USER']->id === (int)$row->id)
            {
                foreach ($update as $key => $value)
                {
                    if ($key !== 'password')
                    {
                        $_SESSION['USER']->$key = $value;
                    }
                }
            }

            message('User updated successfully.');
            redirect('users/edit/' . (int)$row->id);
        }

        $updatedRow = (object)array_merge((array)$row, [
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $data['is_active'],
        ]);

        $this->view('users/edit', [
            'user' => $updatedRow,
            'errors' => $user->errors,
        ]);
    }

    private function userDataFromPost(User $user, bool $passwordRequired): array
    {
        return [
            'name' => trim((string)($_POST['name'] ?? '')),
            'username' => $user->normalizeUsername((string)($_POST['username'] ?? '')),
            'email' => trim((string)($_POST['email'] ?? '')),
            'password' => (string)($_POST['password'] ?? ''),
            'role' => (string)($_POST['role'] ?? 'user'),
            'is_active' => isset($_POST['is_active']) ? 1 : 0,
            'password_required' => $passwordRequired,
        ];
    }

    private function validateUniqueUserFields(User $user, array $data, int $ignoreUserId = 0): void
    {
        if (!empty($data['username']) && $user->usernameExists($data['username'], $ignoreUserId))
        {
            $user->errors['username'] = 'Username is already in use';
        }

        if (!empty($data['email']) && $user->emailExists($data['email'], $ignoreUserId))
        {
            $user->errors['email'] = 'Email is already in use';
        }
    }

    private function validateFinalAdminChange(User $user, mixed $row, array $data): void
    {
        $wouldRemoveAdminAccess = $data['role'] !== 'admin' || (int)$data['is_active'] !== 1;

        if ($wouldRemoveAdminAccess && is_final_active_admin($row, $user->activeAdminCount()))
        {
            $user->errors['role'] = 'You cannot demote or deactivate the final active admin';
        }
    }
}
