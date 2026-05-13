<?php

namespace Controller;

trait MainController
{
    public function view($viewname, $data = [])
    {

        if (!empty($data))
            extract($data);

        $filename = "../app/views/" . $viewname . ".view.php";
        if (file_exists($filename))
        {
            require $filename;
        }
        else
        {
            $filename = "../app/views/404.view.php";
            require $filename;
        }
    }
}
