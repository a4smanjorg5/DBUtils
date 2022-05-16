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

    private $mysql = null;
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
            throw new \InvalidArgumentException("Missing argument 1.");
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
                throw new \Exception("invalid value");
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
    function to_clause($where_clause = true, $mysql = null, $upper_clause = true) {
        $result = "";
        if (!$mysql) $mysql = $this -> mysql;
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
                         SQL::escape_valstr($vals[0], $upper_clause, $mysql);
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
                            return SQL::escape_valstr($val, $upper_clause, $mysql);
                         }, $vals)) . ")";
                        break;
                    case self::OPERATOR_NOTIN:
                        $result .= ($upper_clause ? "NOT IN" : "not in") .
                         "(" . implode(",", array_map(function($val) use ($upper_clause) {
                            return SQL::escape_valstr($val, $upper_clause, $mysql);
                         }, $vals)) . ")";
                        break;
                    case self::OPERATOR_BETWEENWITH:
                        $result .= ($upper_clause ? "BETWEEN" : "between") .
                         " " . SQL::escape_valstr($vals[0], $upper_clause, $mysql) . " " .
                         ($upper_clause ? "AND" : "and") . " " .
                         SQL::escape_valstr($vals[1], $upper_clause, $mysql);
                        break;
                    case self::OPERATOR_NOTBETWEEN:
                        $result .= ($upper_clause ? "NOT BETWEEN" : "not between") .
                         " " . SQL::escape_valstr($vals[0], $upper_clause, $mysql) . " " .
                         ($upper_clause ? "AND" : "and") . " " .
                         SQL::escape_valstr($vals[1], $upper_clause, $mysql);
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
    static function create($init, $mysql = null) {
        if(!is_array($init)) return false;
        $result = new SQL_WHERE_CLAUSE();
        if (is_a($mysql, __NAMESPACE__ . '\MYSQL'))
            $result -> mysql = $mysql;
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

?>
