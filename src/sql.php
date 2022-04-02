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

class SQL {
    private function __construct() {}
    static function fieldname_quote($qn, $quotes = true) {
        if($quotes) return "`" . SQL::fieldname_quote($qn, false) . "`";
        return str_replace('`', '``', $qn);
    }
    static function value_func_quote($upper_clause, $mysql = null, $func_name) {
        if (func_num_args() > 3)
            $args = implode(',', array_map(function($val) use ($upper_clause) {
                return self::escape_valstr($val, $upper_clause, $mysql);
            }, array_slice(func_get_args(), 3)));
        else $args = "";
        return ($upper_clause ? strtoupper($func_name) :
         strtolower($func_name)) . "($args)";
    }
    static function escape_valstr($val, $upper_clause = true, $mysql = null) {
        if (is_null($val))
            return ($upper_clause ? "NULL" : "null");
        if (is_bool($val))
            return self::escape_valstr((int)$val);
        if ((is_numeric($val) && !is_string($val)))
            return (string)$val;
        if (is_array($val))
            return call_user_func_array(array(get_class(),
             "value_func_quote"),
             array_merge(array($upper_clause, $mysql), $val));
        return self::value_query_quote($val, true, $mysql);
    }
    static function value_query_quote($val, $quotes = true, $mysql = null) {
        if (!is_null($mysql) && !is_a($mysql, 'a4smanjorg5\DBUtils\MYSQL'))
            throw new \InvalidArgumentException('$mysql must a MYSQL instance');
        if($quotes) return "'" . SQL::value_query_quote($val, false, $mysql) . "'";
        if (!is_null($mysql)) return $mysql -> real_escape_string((string)$val);
        return str_replace("'", "''", str_replace('\\', '\\\\', $val));
    }
}
?>
