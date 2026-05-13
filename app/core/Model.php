<?php

namespace Model;

trait Model
{
    use \Model\Database;

    protected $limit        = 70;
    protected $offset       = 0;
    protected $order_type   = "desc";
    protected $order_column = "id";
    public $errors          = [];

    public function all()
    {

        $query = "select * from $this->table limit $this->limit offset $this->offset";

        return $this->query($query);
    }

    public function where(array $where_array = [], array $where_not_array = [], array $greater_than_array = []): array|bool
    {

        $query = "select * from $this->table where ";

        if (!empty($where_array))
        {
            foreach ($where_array as $key => $value)
            {
                $query .= $key . "= :" . $key . " && ";
            }
        }

        if (!empty($where_not_array))
        {
            foreach ($where_not_array as $key => $value)
            {
                $query .= $key . "!= :" . $key . " && ";
            }
        }

        if (!empty($greater_than_array))
        {
            foreach ($greater_than_array as $key => $value)
            {
                $query .= $key . "> :" . $key . " && ";
            }
        }

        $query = trim($query, " && ");
        $query .= " order by $this->order_column $this->order_type limit $this->limit offset $this->offset";

        $data = array_merge($where_array, $where_not_array, $greater_than_array);
        // dd($data);
        return $this->query($query, $data);
    }

    // public function where($data, $data_not = [])
    // {
    //     $keys = array_keys($data);
    //     $keys_not = array_keys($data_not);
    //     $query = "select * from $this->table where ";
    //     foreach ($keys as $key)
    //     {
    //         $query .= $key . "= :" . $key . " && ";
    //     }
    //     foreach ($keys_not as $key)
    //     {
    //         $query .= $key . "!= :" . $key . " && ";
    //     }

    //     $query = trim($query, " && ");
    //     $query .= " order by $this->order_column $this->order_type limit $this->limit offset $this->offset";
    //     $data = array_merge($data, $data_not);
    //     return $this->query($query, $data);
    // }


    public function first($data, $data_not = [])
    {
        $keys = array_keys($data);
        $keys_not = array_keys($data_not);
        $query = "select * from $this->table where ";
        foreach ($keys as $key)
        {
            $query .= $key . "= :" . $key . " && ";
        }
        foreach ($keys_not as $key)
        {
            $query .= $key . "!= :" . $key . " && ";
        }

        $query = trim($query, " && ");
        $query .= " limit $this->limit offset $this->offset";
        $data = array_merge($data, $data_not);
        $result = $this->query($query, $data);
        if ($result)
            return $result[0];

        return false;
    }

    public function between($dataGreater = [], $dataLess = [])
    {
        $keysGreater = array_keys($dataGreater);
        $keysLess = array_keys($dataLess);
        $query = "select * from $this->table where ";
        foreach ($keysGreater as $key)
        {
            $query .= $key . "> :" . $key . " && ";
        }
        foreach ($keysLess as $key)
        {
            $query .= $key . "< :" . $key . " && ";
        }

        $query = trim($query, " && ");
        $query .= " limit $this->limit offset $this->offset";
        $data = array_merge($dataLess, $dataGreater);
        $result = $this->query($query, $data);
        if ($result)
            return $result;

        return false;
    }

    public function insert($data)
    {
        // ** remove unwanted data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "insert into $this->table (" . implode(",", $keys) . " ) values (:" . implode(",:", $keys) . ")";
        $this->query($query, $data);

        return false;
    }

    public function update($id, $data, $id_column = 'id')
    {
        // ** remove disallowed data **/
        if (!empty($this->allowedColumns))
        {
            foreach ($data as $key => $value)
            {
                if (!in_array($key, $this->allowedColumns))
                {
                    unset($data[$key]);
                }
            }
        }
        $keys = array_keys($data);
        $query = "update $this->table set ";
        foreach ($keys as $key)
        {
            $query .= $key . " = :" . $key . ", ";
        }

        $query = trim($query, ", ");
        $query .= " where $id_column = :$id_column";

        $data[$id_column] = $id;

        $this->query($query, $data);
        return false;
    }

    public function delete($id, $id_column = 'id')
    {
        $data[$id_column] = $id;
        $query = "delete from $this->table where $id_column = :$id_column";

        $data = array_merge($data);
        // echo $query;
        $this->query($query, $data);
        return false;
    }
}
