<?php

namespace Controller;

defined('ROOTPATH') or exit('Access Denied');

/**
 * home class
 */
class Home
{

    use MainController;

    public function index($a = '', $b = '', $c = '', $d = '')
    {
        // dd($_SESSION['USER']->name);
        $data['name'] = empty($_SESSION['USER']) ? 'guest user' : $_SESSION['USER']->name;
        $data['funk'] = get_image('assets/images/peach.png');
        $this->view('home', $data);
    }

    public function edit($a = '', $b = '', $c = '', $d = '')
    {
        echo "<p>This is the home controller</p>";

        show('From the edit function');
        $this->view('home');
    }
}
