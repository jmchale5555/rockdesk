<?php

namespace Controller;

defined('ROOTPATH') or exit('Access Denied');

/**
 * logout class
 */
class Logout
{

    use MainController;

    public function index($a = '', $b = '', $c = '', $d = '')
    {

        if (!empty($_SESSION['USER']))
        {
            unset($_SESSION['USER']);
        }

        redirect('home');
    }
}
