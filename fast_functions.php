<?php

/**
 * A library of general functions.
 *
 * Original code from {@link http://ezanmoto.wordpress.com Se�n M.
 * Kelleher (ezanmoto@gmail.com)}
 *
 * @package eZanmoto
 * @subpackage Util
 * @since 0.1
 */
class fast_functions {

    /**
     * Checks if a table with the specified name exists.
     *
     * @uses wpdb::get_var()
     * @since 0.1
     * @access public
     * @final
     *
     * @param string $table_name The table name to check.
     * @return bool {@code true} if a table with the specified name
     *  exists.
     */
    function table_exists($table_name) {
        global $wpdb;
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_name}';")
               == $table_name;
    }

    /**
     * Display an error if the tables are not installed.
     *
     * @uses fast_functions::table_exists()
     * @uses debugger::add_error()
     * @since 0.4
     * @access public
     * @final
     *
     * @param array $tables An array of tables to check.
     * @return array An array of tables that do not exists.
     */
    function tables_exist($tables) {
        $missing_tables = array();

        foreach ($tables as $table) {
            if (!$this->table_exists($table)) {
                $missing_tables[] = $table;
            }
        }

        return $missing_tables;
    }
}

global $fast_functions;
$fast_functions = new fast_functions();

?>
