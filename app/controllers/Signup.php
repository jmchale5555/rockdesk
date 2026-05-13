<?php

namespace Controller;

use Model\User;
use Core\Request;

defined('ROOTPATH') or exit('Access Denied');

/**
 * Signup controller
 */
class Signup
{
    use MainController;

    public function index()
    {
        $data = [];

        $req = new Request;
        if ($req->posted())
        {

            $user = new User;
            if ($user->validate($req->post()))
            {
                // unset($req->post('confirm'));
                $hashed_password = password_hash($req->post('password'), PASSWORD_BCRYPT);
                $req->set('password', $hashed_password);
                $req->set('created_at', date("Y-m-d H:i:s"));
                $user->insert($req->post());
                message("Profile created successfully");
                redirect('login');
            }

            $data['errors'] = $user->errors;
        }


        $this->view('signup', $data);
    }
}
