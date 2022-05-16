<?php

/**
 * This file is part of the db-utils package.
 *
 * (c) a4smanjorg5
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace a4smanjorg5\DBUtils;

class MYSQL {
    private $koneksi;
    private function __construct($koneksi) {
        $this -> koneksi = $koneksi;
    }
    static function connect($host = null, $username = null, $passwd = null, $port = null) {
        $args = array();
        if (!is_null($host)) $args[] = $host;
        if (!is_null($username)) $args[] = $username;
        if (!is_null($passwd)) $args[] = $passwd;
        if(PHP_VERSION_ID < 50500) {
            if (!is_null($port)) $args[0] .= ":" . (int)$port;
            $koneksi = call_user_func_array("mysql_connect", $args);
        } else {
            if (!is_null($port)) {
                $args[] = "";
                $args[] = (int)$port;
            }
            $koneksi = call_user_func_array("mysqli_connect", $args);
        }
        if($koneksi) return new MYSQL($koneksi);
    }
    function __call($name, $arguments) {
        if(PHP_VERSION_ID < 50500)
            return call_user_func_array("mysql_$name",
             array_merge($arguments, array($this -> koneksi)));
        return call_user_func_array("mysqli_$name",
         array_merge(array($this -> koneksi), $arguments));
    }
    function create_db($dbname) {
        return $this -> query("CREATE DATABASE " . SQL::fieldname_quote($dbname));
    }
    function getFields($tbl_name, $fields) {
        $res = $this -> query_select($tbl_name, $fields, 0);
        $result = array();
        while($field = $res -> fetch_field())
            $result[$field -> name] = $field;
        return $result;
    }
    function getTables($tbl_name) {
        if(func_num_args() > 1)
            $res = $this -> show_tables($tbl_name, func_get_arg(1));
        else $res = $this -> show_tables($tbl_name);
        if(!$res) return null;
        $result = array();
        while($row = $res -> fetch_row())
            $result[] = $row[0];
        return $result;
    }
    function show_tables($tbl_name) {
        $query = "SHOW TABLES";
        if(func_num_args() > 1) $query .= " FROM " .
            SQL::fieldname_quote(func_get_arg(1));
        return $this -> query($query);
    }
    function query_delete($tbl_name, $where) {
        $tbl = SQL::fieldname_quote($tbl_name);
        $w = is_a($where, __NAMESPACE__ .'\SQL_WHERE_CLAUSE') ? " " . $where -> to_clause(true, $this) : "";
        return $this -> query("DELETE FROM $tbl$w");
    }
    function query_insert($tbl_name, $values) {
        $tbl = SQL::fieldname_quote($tbl_name);
        $cols = ""; $vals = "";
        foreach($values as $col => $val) {
            $cols = "$cols" . (is_numeric($col) ? $col :
              SQL::fieldname_quote($col)) . ', ';
            $vals = "$vals" . SQL::escape_valstr($val, true, $this) . ", ";
        }
        $cols = substr($cols, 0, strlen($cols) - 2);
        $vals = substr($vals, 0, strlen($vals) - 2);
        return $this -> query("INSERT INTO $tbl ($cols) VALUES ($vals)");
    }
    private static function field_func_quote($upper_clause, $func_name) {
        if (func_num_args() > 2)
            $args = implode(',', array_map(function($val) use ($upper_clause) {
                return self::escape_fieldstr($val, $upper_clause);
            }, array_slice(func_get_args(), 2)));
        else $args = "";
        return ($upper_clause ? strtoupper($func_name) :
         strtolower($func_name)) . "($args)";
    }
    private static function escape_fieldstr($field, $upper_clause = true) {
        if (is_null($field))
            return ($upper_clause ? "NULL" : "null");
        if (is_bool($field))
            return self::escape_valstr((int)$field);
        if ((is_numeric($field) && !is_string($field)))
            return (string)$field;
        if (is_array($field))
            return call_user_func_array(array(get_class(),
             "field_func_quote"),
             array_merge(array($upper_clause), $field));
        return SQL::fieldname_quote((string)$field);
    }
    function query_select($tbl_name, $fields/*, $where|$orderBy|$descOrder|$limit|$offset, ...*/) {
        $tbl = SQL::fieldname_quote($tbl_name);
        if($fields == "*") $cols = $fields;
        else {
            $cols = "";
            foreach($fields as $fk => $fv) {
                $cols .= self::escape_fieldstr($fv);
                if (!is_numeric($fk))
                    $cols .= ' AS ' . SQL::fieldname_quote($fk);
                $cols .= ', ';
            }
            $cols = substr($cols, 0, strlen($cols) - 2);
        }
        $where = ""; $limit = ""; $offset = "";
        $orderBy = array(); $descOrder = array();
        for ($i=2; $i < func_num_args(); $i++) { 
            $arg = func_get_arg($i);
            if(is_a($arg, __NAMESPACE__ .'\SQL_WHERE_CLAUSE')) {
                if(is_a($where, __NAMESPACE__ .'\SQL_WHERE_CLAUSE')) {
                    $arg -> reverse();
                    while ($expr = $arg -> next()) {
                        if (is_array($expr)) {
                            array_splice($expr, 1, 0, array_shift($expr));
                            call_user_func_array(array($where, 'next'), $expr);
                        } else $where -> default_logical = $expr;
                    }
                } else $where = $arg;
            } elseif(is_numeric($arg)) {
                if($limit == "")
                    $limit = " LIMIT $arg";
                elseif($offset == "")
                    $offset = " OFFSET $arg";
            } elseif(is_bool($arg))
                $descOrder[] = $arg;
            else $orderBy[] = SQL::fieldname_quote($arg);
        }
        if (count($orderBy) > 0)
            $orderBy = " ORDER BY " . implode(', ', array_map(function($orderBy, $descOrder) {
                return "$orderBy " . ($descOrder ? "DESC" : "ASC");
            }, $orderBy, $descOrder));
        if($where !== "") $where = ' ' . $where -> to_clause(true, $this);
        return $this -> query("SELECT $cols FROM $tbl$where$orderBy$limit$offset");
    }
    function query_update($tbl_name, $col_vals, $where = "") {
        $tbl = SQL::fieldname_quote($tbl_name);
        $vals = "";
        foreach($col_vals as $col => $val)
            $vals = "$vals" . SQL::fieldname_quote($col) .
              " = " . SQL::escape_valstr($val, true, $this) . ", ";
        $vals = substr($vals, 0, strlen($vals) - 2);
        $w = is_a($where, __NAMESPACE__ .'\SQL_WHERE_CLAUSE') ? " " . $where -> to_clause(true, $this) : "";
        return $this -> query("UPDATE $tbl SET $vals$w");
    }
    function query_truncate($tbl_name) {
        $tbl = SQL::fieldname_quote($tbl_name);
        return $this -> query("TRUNCATE TABLE $tbl");
    }
    function query($query) {
        if(PHP_VERSION_ID < 50500)
            $result = mysql_query($query, $this -> koneksi);
        else $result = mysqli_query($this -> koneksi, $query);
        if(is_bool($result)) return $result;
        return new QUERY_FETCH($result);
    }
}

class QUERY_FETCH {
    private $link;
    function __construct($query) {
        $this -> link = $query;
    }
    function __call($name, $arguments) {
        return call_user_func_array(array($this -> link, $name), $arguments);
    }
    function fetch_array() {
        if(PHP_VERSION_ID < 50500)
            return mysql_fetch_array($this -> link);
        return mysqli_fetch_array($this -> link);
    }
    function fetch_assoc() {
        if(PHP_VERSION_ID < 50500)
            return mysql_fetch_assoc($this -> link);
        return mysqli_fetch_assoc($this -> link);
    }
    function fetch_row() {
        if(PHP_VERSION_ID < 50500)
            return mysql_fetch_row($this -> link);
        return mysqli_fetch_row($this -> link);
    }
    function fetch_field() {
        if(PHP_VERSION_ID < 50500)
            return mysql_fetch_field($this -> link);
        return mysqli_fetch_field($this -> link);
    }
    function num_rows() {
        if(PHP_VERSION_ID < 50500)
            return mysql_num_rows($this -> link);
        return mysqli_num_rows($this -> link);
    }
}
?>
