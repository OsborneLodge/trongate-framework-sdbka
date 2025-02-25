<?php
class Model {

    private $host = HOST;
    private $port = '';
    private $user = USER;
    private $pass = PASSWORD;
    private $dbname = DATABASE;

    private $dbh;
    private $stmt;
    private $error;

    // Constant DEBUG defined in config/database.php
    private $debug = DEBUG;
    
    private $query_caveat = 'The query shown above is how the query would look <i>before</i> binding.';
    private $current_module;

    public function __construct($current_module = NULL) {

        $this->port = (defined('PORT') ? PORT : '3306');
        $this->current_module = $current_module;
        
        // the following if/else statement allows for the DSN to have been defined in config/database.php
        // NB: only MySQL and SQLite sql dialects have been catered for so far in Model.php and Api.php

        if (defined('DSN') && DSN <> '') {
            $dsn = DSN;
        } else {
            $dsn = 'mysql:host=' . $this->host . ';port=' . $this->port . ';dbname=' . $this->dbname;
        }

        $options = array(
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        );

        try{
            $this->dbh = new PDO($dsn, $this->user, $this->pass, $options);
        } catch(PDOException $e){
            $this->error = $e->getMessage();
            echo $this->error; die();
        }

    }

    private function get_param_type($value) {

        switch(true){
            case is_int($value):
            $type = PDO::PARAM_INT;
            break;
        case is_bool($value):
            $type = PDO::PARAM_BOOL;
            break;
        case is_null($value):
            $type = PDO::PARAM_NULL;
            break;
        default:
            $type = PDO::PARAM_STR;
        }

        return $type;       
    }

    function prepare_and_execute($sql, $data) {

        $this->stmt = $this->dbh->prepare($sql);

        if (isset($data[0])) { //unnamaed data
            return $this->stmt->execute($data);
        } else {

            foreach ($data as $key => $value) {
                $type = $this->get_param_type($value);
                $this->stmt->bindValue(":$key", $value, $type);
            }

            return $this->stmt->execute();

        }

    }

    private function get_table_from_url() {
        return $this->current_module;
    }

    private function correct_tablename($target_tbl) {
        $bits = explode('-', $target_tbl);
        $num_bits = count($bits);
        if ($num_bits>1) {
            $target_tbl = $bits[$num_bits-1];
        }

        return $target_tbl;
    }

    private function add_limit_offset($sql, $limit, $offset) {

        if ((is_numeric($limit)) && (is_numeric($offset))) {
            $limit_results = true;
            $sql.= " LIMIT $offset, $limit";
        }

        return $sql;

    }

    public function get($order_by=NULL, $target_tbl=NULL, $limit=NULL, $offset=NULL) {

        $limit_results = false;

        if (!isset($order_by)) {
            $order_by = 'id';
        }

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "SELECT * FROM $target_tbl order by $order_by";

        if ((isset($limit)) && (isset($offset))) {
            $sql = $this->add_limit_offset($sql, $limit, $offset);
        }

        if ($this->debug == true) {

            if ($limit_results == true) {
                $data['limit'] = $limit;
                $data['offset'] = $offset;
            } else {
                $data = [];
            }

            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $stmt = $this->dbh->prepare($sql);

        if ($limit_results == true) {
            $stmt->bindValue(":limit", $limit, PDO::PARAM_INT);
            $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        }

        $stmt->execute();
        $query = $stmt->fetchAll(PDO::FETCH_OBJ);
        return $query;

    }

    public function get_where_custom($column, $value, $operator='=', $order_by='id', $target_tbl=NULL, $limit=NULL, $offset=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $data[$column] = $value;
        $sql = "SELECT * FROM $target_tbl where $column $operator :$column order by $order_by";

        if ((isset($limit))) {

            if (!isset($offset)) {
                $offset = 0;
            }

            $sql = $this->add_limit_offset($sql, $limit, $offset);
        }

        if ($this->debug == true) {

            $operator = strtoupper($operator);
            if (($operator == 'LIKE') || ($operator == 'NOT LIKE')) {
                $value = '%'.$value.'%';
                $data[$column] = $value;
            }

            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);

        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $items = $this->stmt->fetchAll(PDO::FETCH_OBJ);
            return $items;
        }

    }

    //fetch a single record
    public function get_where($id, $target_tbl=NULL) {

        $data['id'] = $id;

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "SELECT * FROM $target_tbl where id = :id";

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $item = $this->stmt->fetch(PDO::FETCH_OBJ);
            return $item;            
        }

    }

    //fetch a single record (alternative version)
    public function get_one_where($column, $value, $target_tbl=NULL) {
        $data[$column] = $value;

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "SELECT * FROM $target_tbl where $column = :$column";

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $item = $this->stmt->fetch(PDO::FETCH_OBJ);
            return $item;            
        }
    }

    public function get_many_where($column, $value, $target_tbl=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $data[$column] = $value;
        $sql = 'select * from '.$target_tbl.' where '.$column.' = :'.$column;

        $query = $this->query_bind($sql, $data, 'object');

        return $query;
    }

    public function count($target_tbl=NULL) {
        //return number of rows on a table

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "SELECT COUNT(id) as total FROM $target_tbl";
        $data = [];

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data);
        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $obj = $this->stmt->fetch(PDO::FETCH_OBJ);
            return $obj->total;            
        }

    }

    public function count_where($column, $value, $operator='=', $order_by='id', $target_tbl=NULL, $limit=NULL, $offset=NULL) {
        //return number of rows on table (with query customisation)

        $query = $this->get_where_custom($column, $value, $operator, $order_by, $target_tbl, $limit, $offset);
        $num_rows = count($query);
        return $num_rows;
    }

    public function count_rows($column, $value, $target_tbl=NULL) {
        //simplified version of count_where (accepts one condition)

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $data[$column] = $value;
        $sql = 'SELECT COUNT(id) as total from '.$target_tbl.' where '.$column.' = :'.$column;

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data);
        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $obj = $this->stmt->fetch(PDO::FETCH_OBJ);
            return $obj->total;            
        }

    }

    public function get_max($target_tbl=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "SELECT MAX(id) AS max_id FROM $target_tbl";
        $data = [];

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data);
        }

        $result = $this->prepare_and_execute($sql, $data);

        if ($result == true) {
            $assoc = $this->stmt->fetch(PDO::FETCH_ASSOC);
            $max_id = $assoc['max_id'];
            return $max_id;
        }

    }

    //get result set as array of objects
    public function resultSet() {
    //   $this->execute();
    //   return $this->stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function show_query($query, $data, $caveat=NULL) {
        $keys = array();
        $values = $data;
        $named_params = true;

        # build a regular expression for each parameter
        foreach ($data as $key => $value) {

            if (is_string($key)) {
                $keys[] = '/:'.$key.'/';
            } else {
                $keys[] = '/[?]/';
                $named_params = false;
            }

            if (is_string($value))
                $values[$key] = "'" . $value . "'";

            if (is_array($value))
                $values[$key] = "'" . implode("','", $value) . "'";

            if (is_null($value))
                $values[$key] = 'NULL';
        }

        if ($named_params == true) {
            $query = preg_replace($keys, $values, $query);
        } else {

            $query = $query.' ';
            $bits = explode(' ? ', $query);

            $query = '';
            for ($i=0; $i < count($bits); $i++) { 
                $query.= $bits[$i];

                if (isset($values[$i])) {
                    $query.= ' '.$values[$i].' ';
                }

            }

        }

        if (!isset($caveat)) {
            $caveat_info = '';
        } else {

            $caveat_info = '<br><hr><div style="font-size: 0.8em;"><b>PLEASE NOTE:</b> '.$caveat;
            $caveat_info.= ' PDO currently has no means of displaying previous query executed.</div>';
        }

        // the following if/else statement caters for debug information to be output to a simple text file
        // according to the settings of constants DEBUG, DEBUG_TO_FILE and DEBUG_FILE_NAME in config/database.php
        // Defining DEBUG_TO_FILE == true allows for an unadulterated screen output and a comprehensive review of
        // database queries. It also opens the possibility for other debug information to be output and saved in
        // the file for review later. 

        if (DEBUG_TO_FILE == true) {
            $debug_start = "********************************************************************************\n";
            $debug_file = fopen(DEBUG_FILE_NAME, 'a') or die('Unable to open Debug file');
            $debug_time = date('Y-m-d H:i:s');
            $debug_text = $debug_start . $debug_time . ': QUERY TO BE EXECUTED -> ' .
                "\n\n" . $query . "\n\n";
            $debug_text = preg_replace('/[ ]{2,}/', ' ', $debug_text);

            fwrite($debug_file, $debug_text);
            fclose($debug_file);

        } else {
        echo '<div class="tg-rprt"><b>QUERY TO BE EXECUTED:</b><br><br>  -> ';
        echo $query.$caveat_info.'</div>';
        ?>

<style>
.tg-rprt {
color: #383623;
background-color: #efe79e;
font-family: "Lucida Console", Monaco, monospace;
padding: 1em;
border: 1px #383623 solid;
clear: both !important;
margin: 1em 0;
}        
</style>

    <?php
    }
}

    public function insert($data, $target_tbl=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = 'INSERT INTO '.$target_tbl.' (';
        $sql.= implode(", ", array_keys($data)).')';
        $sql.= ' VALUES (';

        foreach ($data as $key => $value) {
            $sql.=':'.$key.', ';
        }

        $sql = rtrim($sql, ', ');
        $sql.=')';

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $this->prepare_and_execute($sql, $data);
        $id = $this->dbh->lastInsertId();
        return $id;
    }

    public function update($update_id, $data, $target_tbl=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "UPDATE `$target_tbl` SET ";

        foreach ($data as $key => $value) {
            $sql.= "`$key` = :$key, ";
        }

        $sql = rtrim($sql, ', ');
        $sql.= " WHERE `$target_tbl`.`id` = :id";

        $data['id'] = $update_id;
        $data = $data;

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $this->prepare_and_execute($sql, $data);

    }

    public function delete($id, $target_tbl=NULL) {

        if (!isset($target_tbl)) {
            $target_tbl = $this->get_table_from_url();
        }

        $sql = "DELETE from `$target_tbl` WHERE id = :id ";
        $data['id'] = $id;

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat);
        }

        $this->prepare_and_execute($sql, $data);

    }

    public function query($sql, $return_type=false) {

        //WARNING: very high risk of SQL injection - use with caution!
        $data = [];

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data);
        }

        $this->prepare_and_execute($sql, $data);

        if (!isset($return_type)) {
            $return_type = 'object';
        }

        if ($return_type == 'object') {
            $query = $this->stmt->fetchAll(PDO::FETCH_OBJ);
            return $query;
        } else {
            $query = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            return $query;
        }

    }

    public function query_bind($sql, $data, $return_type=false) {

        if ($this->debug == true) {
            $query_to_execute = $this->show_query($sql, $data, $this->query_caveat); 
        }

        $this->prepare_and_execute($sql, $data);

        if ($return_type == 'object') {
            $query = $this->stmt->fetchAll(PDO::FETCH_OBJ);
            return $query;
        } elseif ($return_type == 'array') {
            $query = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            return $query;
        }

    }

    public function insert_batch($table, array $records) {

        //WARNING:  Never let your website visitors invoke this method!
        $fields = array_keys($records[0]);
        $placeHolders = substr(str_repeat(',?', count($fields)), 1);
        $values = [];
        foreach ($records as $record) {
            array_push($values, ...array_values($record));
        }

        $sql = 'INSERT INTO ' . $table . ' (';
        $sql .= implode(',', $fields);
        $sql .= ') VALUES (';
        $sql .= implode('),(', array_fill(0, count($records), $placeHolders));
        $sql .= ')';

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute($values);

        $count = $stmt->rowCount();
        return $count;
    }

    public function exec($sql) {
        if (ENV == 'dev') {
            //this gets used on auto module table setups
            $stmt = $this->dbh->prepare($sql);
            $stmt->execute();
        } else {
            echo 'Feature disabled, since not on \'dev\' mode.';
        }
    }

}
