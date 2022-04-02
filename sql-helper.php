<?php

/**
 * This file is part of the db-utils package.
 *
 * (c) a4smanjorg5
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

class SQL_WHERE_CLAUSE {
    const LOGICAL_OR = "|";
    const LOGICAL_AND = "&";
    const OPERATOR_EQUALWITH = "=";
    const OPERATOR_GREATERTHAN = ">";
    const OPERATOR_GREATEROREQUAL = ">=";
    const OPERATOR_LESSTHAN = "<";
    const OPERATOR_LESSOREQUAL = "<=";
    const OPERATOR_NOTEQUAL = "!=";
    const OPERATOR_WILDCARD = "%";
    const OPERATOR_LIKEWITH = "%=";
    const OPERATOR_NOTLIKE = "!%";
    const OPERATOR_INWITH = "@";
    const OPERATOR_NOTIN = "!@";
    const OPERATOR_BETWEENWITH = "~";
    const OPERATOR_NOTBETWEEN = "!~";
    const OPERATOR_ISNULL = "#";
    const OPERATOR_NOTNULL = "!#";

    private $clauses = array();
    private $op_logical = self::LOGICAL_AND;
    private $last_logical = null;
    private $rev = -1;

    function next() {
        if(func_num_args() < 1) {
            if($this -> rev < 0) return false;
            $clauses = $this -> clauses;
            if(count($clauses) > 1 && is_null($this -> last_logical) &&
             $this -> rev <= 0) {
                $this -> default_logical = $clauses[1];
                $this -> last_logical = $clauses[1];
                return $clauses[1];
            }
            if(is_array($clauses[$this -> rev])) {
                $clause = $clauses[$this -> rev];
                $result = array($clause['op'], $clause['col']);
                foreach($clause as $p => $v) if($p !== "op" &&
                 $p !== "col") $result[] = $v;
            } else {
                if($this -> op_logical != $clauses[$this -> rev])
                    $this -> default_logical = $clauses[$this -> rev];
                if($this -> last_logical != $clauses[$this -> rev]) {
                    $result = $clauses[$this -> rev];
                    $this -> last_logical = $clauses[$this -> rev];
                }
            }
            $this -> rev++;
            if(count($this -> clauses) <= $this -> rev) {
                $this -> last_logical = null;
                $this -> rev = -1;
            }
            return (isset($result) ? $result : $this -> next());
        } elseif(func_num_args() <= 1)
            throw new InvalidArgumentException("Missing argument 1.");
        if($this -> rev >= 0) return false;
        $clause = array("col" => func_get_arg(0), "op" => func_get_arg(1));
        switch($clause['op']) {
            case self::OPERATOR_EQUALWITH:
            case self::OPERATOR_GREATERTHAN:
            case self::OPERATOR_GREATEROREQUAL:
            case self::OPERATOR_LESSTHAN:
            case self::OPERATOR_LESSOREQUAL:
            case self::OPERATOR_NOTEQUAL:
            case self::OPERATOR_LIKEWITH:
            case self::OPERATOR_NOTLIKE:
                if(func_num_args() > 2)
                    $clause[] = func_get_arg(2);
                else $clause[] = null;
                break;
            case self::OPERATOR_INWITH:
            case self::OPERATOR_NOTIN:
                if(func_num_args() > 2)
                    $clause[] = func_get_arg(2);
                else $clause[] = null;
                for($i = 3; $i < func_num_args(); $i++)
                    $clause[] = func_get_arg($i);
                break;
            case self::OPERATOR_BETWEENWITH:
            case self::OPERATOR_NOTBETWEEN:
                if(func_num_args() > 2)
                    $clause[] = func_get_arg(2);
                else $clause[] = null;
                if(func_num_args() > 3)
                    $clause[] = func_get_arg(3);
                else $clause[] = null;
                break;
            case self::OPERATOR_ISNULL:
            case self::OPERATOR_NOTNULL:
                break;
            default:
                return false;
        }
        if(count($this -> clauses) > 0)
            $this -> clauses[] = $this -> op_logical;
        $this -> clauses[] = $clause;
        return true;
    }
    function __get($prop) {
        if($prop == "default_logical")
            return $this -> op_logical;
    }
    function __set($prop, $val) {
        if($prop == "default_logical") {
            if($val !== self::LOGICAL_AND &&
             $val !== self::LOGICAL_OR)
                throw new Exception("invalid value");
            $this -> op_logical = $val;
        }
    }
    function reverse($reset = true) {
        if(($this -> rev === 0 && !$reset) ||
         count($this -> clauses) > 0)
            $this -> last_logical = null;
        if($this -> rev >= 0 && !$reset)
            $this -> rev = -1;
        elseif(count($this -> clauses) > 0)
            $this -> rev = 0;
    }
    function to_clause($where_clause = true, $upper_clause = true) {
        $result = "";
        foreach($this -> clauses as $clause) {
            if(is_array($clause)) {
                $result .= SQL::fieldname_quote($clause['col']) . " ";
                $vals = array();
                foreach($clause as $p => $v)
                    if($p !== "col" && $p !== "op")
                        $vals[] = $v;
                switch($clause['op']) {
                    case self::OPERATOR_EQUALWITH:
                    case self::OPERATOR_GREATERTHAN:
                    case self::OPERATOR_GREATEROREQUAL:
                    case self::OPERATOR_LESSTHAN:
                    case self::OPERATOR_LESSOREQUAL:
                    case self::OPERATOR_NOTEQUAL:
                        $result .= "$clause[op] " .
                         SQL::escape_valstr($vals[0], $upper_clause);
                        break;
                    case self::OPERATOR_LIKEWITH:
                        $result .= ($upper_clause ? "LIKE" : "like") .
                         " " . SQL::value_query_quote($vals[0]);
                        break;
                    case self::OPERATOR_NOTLIKE:
                        $result .= ($upper_clause ? "NOT LIKE" : "not like") .
                         " " . SQL::value_query_quote($vals[0]);
                        break;
                    case self::OPERATOR_INWITH:
                        $result .= ($upper_clause ? "IN" : "in") .
                         "(" . implode(",", array_map(function($val) use ($upper_clause) {
                            return SQL::escape_valstr($val, $upper_clause);
                         }, $vals)) . ")";
                        break;
                    case self::OPERATOR_NOTIN:
                        $result .= ($upper_clause ? "NOT IN" : "not in") .
                         "(" . implode(",", array_map(function($val) use ($upper_clause) {
                            return SQL::escape_valstr($val, $upper_clause);
                         }, $vals)) . ")";
                        break;
                    case self::OPERATOR_BETWEENWITH:
                        $result .= ($upper_clause ? "BETWEEN" : "between") .
                         " " . SQL::escape_valstr($vals[0], $upper_clause) . " " .
                         ($upper_clause ? "AND" : "and") . " " .
                         SQL::escape_valstr($vals[1], $upper_clause);
                        break;
                    case self::OPERATOR_NOTBETWEEN:
                        $result .= ($upper_clause ? "NOT BETWEEN" : "not between") .
                         " " . SQL::escape_valstr($vals[0], $upper_clause) . " " .
                         ($upper_clause ? "AND" : "and") . " " .
                         SQL::escape_valstr($vals[1], $upper_clause);
                        break;
                    case self::OPERATOR_ISNULL:
                        $result .= $upper_clause ? "IS NULL" : "is null";
                        break;
                    case self::OPERATOR_NOTNULL:
                        $result .= $upper_clause ? "IS NOT NULL" : "is not null";
                        break;
                }
            } else switch($clause) {
                case self::LOGICAL_AND:
                    $result .= $upper_clause ? " AND " : " and ";
                    break;
                case self::LOGICAL_OR:
                    $result .= $upper_clause ? " OR " : " or ";
                    break;
            }
        }
        return ($where_clause ? ($upper_clause ? "WHERE" : "where") . " " .
          (empty($result) ? 1 : "") : "") . $result;
    }
    function __toString() {
        return $this -> to_clause();
    }
    private static function is_operator($value) {
        switch($value) {
            case self::OPERATOR_EQUALWITH:
            case self::OPERATOR_GREATERTHAN:
            case self::OPERATOR_GREATEROREQUAL:
            case self::OPERATOR_LESSTHAN:
            case self::OPERATOR_LESSOREQUAL:
            case self::OPERATOR_NOTEQUAL:
            case self::OPERATOR_WILDCARD:
            case self::OPERATOR_LIKEWITH:
            case self::OPERATOR_NOTLIKE:
            case self::OPERATOR_INWITH:
            case self::OPERATOR_NOTIN:
            case self::OPERATOR_BETWEENWITH:
            case self::OPERATOR_NOTBETWEEN:
            case self::OPERATOR_ISNULL:
            case self::OPERATOR_NOTNULL:
                return true;
        }
        return false;
    }
    static function create($init) {
        if(!is_array($init)) return false;
        $result = new SQL_WHERE_CLAUSE();
        $co_op = false;
        foreach($init as $clause) {
            if(is_array($clause)) {
                if(count($clause) < 2) $clause[] = null;
                if(self::is_operator($clause[0]) &&
                 !self::is_operator($clause[1])) {
                    $op = $clause[0];
                    $clause[0] = $clause[1];
                    $clause[1] = $op;
                }
                if($co_op && is_null($result -> op_logical))
                    return false;
                if(!call_user_func_array(array($result, "next"), $clause))
                    return false;
                $co_op = true;
            } else try { $result -> default_logical = $clause; } catch(Exception $_) {}
        }
        return $result;
    }
}

class SQL {
    private function __construct() {}
    static $upper_clause = true;
    static function delete_clause($tbl_name, $where) {
        $tbl = SQL::fieldname_quote($tbl_name);
        $w = is_a($where, "SQL_WHERE_CLAUSE") ? " " . $where -> to_clause(true, self::$upper_clause) : "";
        $sql = self::$upper_clause ? 'DELETE FROM' : 'delete from';
        return "$sql $tbl$w";
    }
    static function escape_valstr($val, $upper_clause = true) {
        if (is_null($val))
            return ($upper_clause ? "NULL" : "null");
        if (is_bool($val))
            return self::escape_valstr((int)$val);
        if ((is_numeric($val) && !is_string($val)))
            return (string)$val;
        if (is_array($val))
            return call_user_func_array(array(get_class(),
             "value_func_quote"),
             array_merge(array($upper_clause), $val));
        return self::value_query_quote($val, true);
    }
    static function fieldname_quote($qn, $quotes = true) {
        if($quotes) return "`" . SQL::fieldname_quote($qn, false) . "`";
        return str_replace('`', '``', $qn);
    }
    static function insert_clause($tbl_name, $values) {
        $tbl = SQL::fieldname_quote($tbl_name);
        $cols = ""; $vals = "";
        foreach($values as $col => $val) {
            $cols = "$cols" . (is_numeric($col) ? $col :
              SQL::fieldname_quote($col)) . ', ';
            $vals = "$vals" . SQL::escape_valstr($val, self::$upper_clause) . ", ";
        }
        $cols = substr($cols, 0, strlen($cols) - 2);
        $vals = substr($vals, 0, strlen($vals) - 2);
        if (self::$upper_clause) {
            $ic = 'INSERT INTO';
            $vc = 'VALUES';
        } else {
            $ic = 'insert into';
            $vc = 'values';
        }
        return "$ic $tbl ($cols) $vc ($vals)";
    }
    static function select_clause($tbl_name, $fields/*, $where|$orderBy|$descOrder|$limit|$offset, ...*/) {
        $tbl = SQL::fieldname_quote($tbl_name);
        if($fields == "*") $cols = $fields;
        else {
            $cols = "";
            foreach($fields as $fk => $fv) {
                $cols .= self::escape_fieldstr($fv);
                if (!is_numeric($fk))
                    $cols .= ' ' . (self::$upper_clause ? 'AS' : 'as') .
                     ' ' . SQL::fieldname_quote($fk);
                $cols .= ', ';
            }
            $cols = substr($cols, 0, strlen($cols) - 2);
        }
        $where = ""; $limit = ""; $offset = "";
        $orderBy = ""; $descOrder = false;
        for ($i=2; $i < func_num_args(); $i++) { 
            $arg = func_get_arg($i);
            if(is_a($arg, "SQL_WHERE_CLAUSE")) {
                if(is_a($where, "SQL_WHERE_CLAUSE")) {
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
                    $limit = ' ' . (self::$upper_clause ? 'LIMIT' : 'limit') . " $arg";
                elseif($offset == "")
                    $offset = ' ' . (self::$upper_clause ? 'OFFSET' : 'offset') . " $arg";
            } elseif(is_bool($arg))
                $descOrder = $arg;
            else $orderBy = SQL::fieldname_quote($arg);
        }
        if (self::$upper_clause) {
            $sc = 'SELECT';
            $fc = 'FROM';
            $descOrder = $descOrder ? 'DESC' : "ASC";
        } else {
            $sc = 'select';
            $fc = 'from';
            $descOrder = $descOrder ? 'desc' : "asc";
        }
        if ($orderBy != "")
            $orderBy = ' ' . (self::$upper_clause ? 'ORDER BY' : 'order by') .
             " $orderBy $descOrder";
        if($where !== "") $where = ' ' . $where -> to_clause(true, self::$upper_clause);
        return "$sc $cols $fc $tbl$where$orderBy$limit$offset";
    }
    static function truncate_clause($tbl_name) {
        $tbl = SQL::fieldname_quote($tbl_name);
        $sql = self::$upper_clause ? 'TRUNCATE TABLE' : 'truncate table';
        return "$sql $tbl";
    }
    static function update_clause($tbl_name, $col_vals, $where = "") {
        $tbl = SQL::fieldname_quote($tbl_name);
        $vals = "";
        foreach($col_vals as $col => $val)
            $vals = "$vals" . SQL::fieldname_quote($col) .
              " = " . SQL::escape_valstr($val, self::$upper_clause) . ", ";
        $vals = substr($vals, 0, strlen($vals) - 2);
        $w = is_a($where, "SQL_WHERE_CLAUSE") ? " " . $where -> to_clause(true, self::$upper_clause) : "";
        if (self::$upper_clause) {
            $uc = 'UPDATE';
            $sc = 'SET';
        } else {
            $uc = 'update';
            $sc = 'set';
        }
        return "$uc $tbl $sc $vals$w";
    }
    static function value_func_quote($upper_clause, $func_name) {
        if (func_num_args() > 3)
            $args = implode(',', array_map(function($val) use ($upper_clause) {
                return self::escape_valstr($val, $upper_clause);
            }, array_slice(func_get_args(), 3)));
        else $args = "";
        return ($upper_clause ? strtoupper($func_name) :
         strtolower($func_name)) . "($args)";
    }
    static function value_query_quote($val, $quotes = true) {
        if($quotes) return "'" . SQL::value_query_quote($val, false) . "'";
        return str_replace("'", "''", str_replace('\\', '\\\\', $val));
    }
}
?>
