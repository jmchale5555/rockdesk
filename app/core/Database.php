<?php

namespace Model;

use PDO;

trait Database
{
    private function connect()
    {
        $string = "mysql:host=" . DBHOST . ";port=" . DBPORT . ";dbname=" . DBNAME . ";charset=utf8mb4";
        $con = new PDO($string, DBUSER, DBPASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        ]);
        return $con;
    }

    public function query($query, $data = [])
    {
        $con = $this->connect();
        $stm = $con->prepare($query);

        $check = $stm->execute($data);
        if ($check)
        {
            $result = $stm->fetchAll();
            if (is_array($result) && count($result))
            {
                return $result;
            }
        }

        return false;
    }

    public function get_row($query, $data = [])
    {
        $con = $this->connect();
        $stm = $con->prepare($query);

        $check = $stm->execute($data);
        if ($check)
        {
            $result = $stm->fetchAll();
            if (is_array($result) && count($result))
            {
                return $result[0];
            }
        }

        return false;
    }
}

// show($con);
