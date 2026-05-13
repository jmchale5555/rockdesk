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
            $user = new User;
            $arr['email'] = $_POST['email'];

            $row = $user->first($arr);

            if ($row)
            {
                if (password_verify($_POST['password'], $row->password))
                {
                    $session = new Session;
                    $session->auth($row);
                    redirect('home');
                }
            }

            $user->errors['email'] = "Wrong email or password";

            $data['errors'] = $user->errors;
        }

        $this->view('login', $data);
    }
}
