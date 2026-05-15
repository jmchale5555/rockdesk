<?php

namespace Controller;

use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class Password
{
    use MainController;

    public function index()
    {
        require_login();

        $sessionUser = $_SESSION['USER'];
        $isForcedReset = password_reset_required($sessionUser);
        $canChangePassword = can_change_local_password($sessionUser);
        $data = [
            'isForcedReset' => $isForcedReset,
            'canChangePassword' => $canChangePassword,
        ];

        if (!$canChangePassword)
        {
            $this->view('password', $data);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $user = new User;
            $currentHash = (string)$sessionUser->password;

            if ($user->validatePasswordChange($_POST, $currentHash))
            {
                $now = date('Y-m-d H:i:s');
                $newHash = password_hash($_POST['password'], PASSWORD_BCRYPT);

                $user->update((int)$sessionUser->id, [
                    'password' => $newHash,
                    'must_reset_password' => 0,
                    'updated_at' => $now,
                ]);

                $_SESSION['USER']->password = $newHash;
                $_SESSION['USER']->must_reset_password = 0;
                $_SESSION['USER']->updated_at = $now;

                message('Password updated successfully');
                redirect($isForcedReset ? 'home' : 'password');
            }

            $data['errors'] = $user->errors;
        }

        $this->view('password', $data);
    }
}
