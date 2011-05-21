<?php

/**
 * A library of general functions.
 *
 * Original code from {@link http://ezanmoto.wordpress.com Seán M.
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

	/**
	 * Outputs the HTML tags for linking to CSS sheets.
	 *
	 */
	function output_css_links( $css_links ) {
        $url = get_bloginfo('wpurl');

		if ( ! is_array( $css_links ) ) {
			$css_links = array( $css_links );
		}

		foreach ( $css_links as $css ) {
			echo "<link type='text/css' rel='stylesheet' href="
				 . "'{$url}/wp-content/plugins/fast_cart/css/{$css}.css' />\n";
		}
	}

	/**
	 * Outputs the HTML tags for linking to CSS sheets.
	 *
	 */
	function output_js_links( $js_links ) {
        $url = get_bloginfo('wpurl');

		if ( ! is_array( $js_links ) ) {
			$js_links = array( $js_links );
		}

		foreach ( $js_links as $js ) {
			echo "<script type='text/javascript' src="
				 . "'{$url}/wp-content/plugins/fast_cart/js/{$js}.js'>"
				 . "</script>\n";
		}
	}
}

global $fast_functions;
$fast_functions = new fast_functions();

?>
