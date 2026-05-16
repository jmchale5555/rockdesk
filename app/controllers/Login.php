<?php

namespace Controller;

use Model\User;
use Core\Session;

defined('ROOTPATH') or exit('Access Denied');

/**
 * Login controller
 */
class Login
{
    use MainController;

    public function index()
    {
        $data = [];

        if ($_SERVER['REQUEST_METHOD'] == "POST")
        {
            require_csrf();

            $user = new User;
            $arr['username'] = $user->normalizeUsername((string)($_POST['username'] ?? ''));

            $row = $user->first($arr);

            if ($row)
            {
                if ((int)($row->is_active ?? 1) !== 1)
                {
                    $user->errors['username'] = "This account is inactive";
                    $data['errors'] = $user->errors;
                    $this->view('login', $data);
                    return;
                }

                if (!empty($row->password) && password_verify((string)($_POST['password'] ?? ''), $row->password))
                {
                    $now = date('Y-m-d H:i:s');
                    $user->update((int)$row->id, [
                        'last_login_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $row->last_login_at = $now;
                    $row->updated_at = $now;

                    $session = new Session;
                    $session->auth($row);
                    redirect('home');
                }
            }

            $user->errors['username'] = "Wrong username or password";

            $data['errors'] = $user->errors;
        }

        $this->view('login', $data);
    }
}
