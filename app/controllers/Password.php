<?php

namespace Controller;

use Model\User;

defined('ROOTPATH') or exit('Access Denied');

class Password
{
    use MainController;

    public function index()
    {
        if (empty($_SESSION['USER']))
        {
            redirect('login');
        }

        $data = [];

        if ($_SERVER['REQUEST_METHOD'] == 'POST')
        {
            $user = new User;
            $sessionUser = $_SESSION['USER'];
            $currentHash = (string)$sessionUser->password;

            if ($user->validatePasswordChange($_POST, $currentHash))
            {
                $newHash = password_hash($_POST['password'], PASSWORD_BCRYPT);

                $user->update((int)$sessionUser->id, [
                    'password' => $newHash,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                $_SESSION['USER']->password = $newHash;
                $_SESSION['USER']->updated_at = date('Y-m-d H:i:s');

                message('Password updated successfully');
                redirect('password');
            }

            $data['errors'] = $user->errors;
        }

        $this->view('password', $data);
    }
}
