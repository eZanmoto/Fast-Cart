<?php

require ('fast_functions.php');
require ('debugger.php');

/**
 * The name of the fast_fields table.
 *
 * @since 1.0.2
 */
define('FAST_FIELDS_TABLE', 'fastfields');

/**
 * The name of the fast_fields meta table.
 *
 * @since 1.0.2
 */
define('FAST_FIELDS_META_TABLE', 'fastfieldsmeta');

/**
 * The debugger object.
 *
 * @since 0.1
 */
global $debugger;

$debugger = new debugger(get_option('ff_debugging'));

$debugger->set_alert_format('<div id="message" class="updated">', '</div>');
$debugger->set_error_format('<div id="message" class="error">', '</div>');

/**
 * Wordpress Custom Field Object
 *
 * Original code from
 * {@link http://ezanmoto.wordpress.com Seán M. Kelleher(ezanmoto@gmail.com)}
 *
 * @author Seán M. Kelleher
 * @version 1.0.2
 * @package eZanmoto
 * @subpackage Fields
 * @since 0.1
 */
class fast_fields{

    /**
     * The table name for the fast fields table.
     *
     * @since 0.1
     * @access public
     * @var string
     */
    var $table;

    /**
     * The table name for the fast fields meta table.
     *
     * @since 0.1
     * @access public
     * @var string
     */
    var $meta_table;

    /**
     * Prepended to HTML {@code id}s, {@code class}es, {@code name}s,
     * and keys put into the postmeta table.
     *
     * Change this to avoid namespace clashes with HTML attributes and
     * data in the postmeta table.
     *
     * The default javascript and CSS files associated with
     * fast_fields expect this variable to contain
     * {@code 'fast_fields_'}.
     *
     * @since 0.1
     * @access private
     * @var string
     * @final
     */
    var $prefix;

    /**
     * An indexed array of field attributes.
     *
     * @since 0.4
     * @access private
     * @var array
     */
    var $field_attributes;

    /**
     * The default constructor function.
     *
     * Hooks {@code fast_fields::html_header()} to the
     * {@code admin_head-fast-cart_page_fast_fields} action.
     *
     * Hooks {@code fast_fields::show_post_panel()} to the
     * {@code admin_menu} action.
     *
     * Hooks {@code fast_fields::save_post()} to the {@code save_post}
     * action.
     *
     * @uses fast_fields::$table
     * @uses fast_fields::$meta_table
     * @uses fast_fields::$prefix
     * @uses fast_fields::verify_tables()
     * @uses fast_fields::html_header()
     * @uses fast_fields::show_post_panel()
     * @uses fast_fields::save_post()
     * @uses wpdb::$prefix
     * @since 0.1
     * @access public
     */
    function __construct() {
        global $wpdb;

        $this->table = $wpdb->prefix . FAST_FIELDS_TABLE;
        $this->meta_table = $wpdb->prefix . FAST_FIELDS_META_TABLE;

        $this->prefix = 'fast_fields_';
        $this->field_attributes = array();

        add_action('admin_init', array(&$this, 'is_working'));

        add_action('admin_head', array(&$this, 'admin_html_header'));
        add_action('wp_head', array(&$this, 'html_header'));
        add_action('admin_menu', array(&$this, 'show_post_panel'));
        add_action('save_post', array(&$this, 'save_post'));
    }

    /**
     * Installs everything needed for fast_field to run when it is
     * activated for the first time.
     *
     * Creates the tables required for fast_fields.
     *
     * @uses wpdb::has_cap()
     * @uses wpdb::$charset
     * @uses wpdb::$collate
     * @uses wpdb::query()
     * @uses fast_functions::verify_tables()
     * @since 0.1
     * @access private
     */
    function install() {
        global $wpdb, $fast_fields;

        require_once (ABSPATH . 'wp-admin/includes/upgrade.php');

        // sets the character set for the new tables
        $charset_collate = '';
        if ($wpdb->has_cap('collation')) {
            if (!empty ($wpdb->charset)) {
                $charset_collate = "DEFAULT CHARACTER SET
                                    {$wpdb->charset}";
            }
            if (!empty ($wpdb->collate)) {
                $charset_collate = " COLLATE {$wpdb->collate}";
            }
        }

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->table . '(
                    field_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    post_id BIGINT UNSIGNED,
                    name VARCHAR(255) NOT NULL,
                    type VARCHAR(255) NOT NULL,
                    value VARCHAR(255) DEFAULT \'\',
                    order_id INT DEFAULT \'0\',
                    PRIMARY KEY (field_id)
                ) ' . $charset_collate . ';';
        $wpdb->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->meta_table
               . '(
                    meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    field_id BIGINT UNSIGNED NOT NULL,
                    attribute VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY (meta_id),
                    FOREIGN KEY (field_id) REFERENCES ' .
                    $this->table . '(field_id)
                ) ' . $charset_collate . ';';
        $wpdb->query($sql);

        $this->verify_tables(array($this->table, $this->meta_table));
    }

    /**
     * Outputs the CSS and javascript for fast_fields on admin pages.
     *
     * Hooked to the {@code admin_head} action.
     *
	 * @uses fast_fields::prefix
	 * @uses fast_functions::output_css_links()
	 * @uses fast_functions::output_js_links()
     * @since 0.1
     * @access private
     */
    function admin_html_header() {
		global $fast_functions;

		$admin_css = $this->prefix . 'admin';

		$css_files = array( $admin_css );
		$js_files  = array( 'jquery-1.4.3.min', 'fast_fields' );
		$fast_functions->output_css_links( $css_files );
		$fast_functions->output_js_links(  $js_files  );
    }

    /**
     * Outputs the CSS for fast_fields.
     *
     * Hooked to the {@code wp_head} action.
     *
	 * @uses fast_fields::prefix
	 * @uses fast_functions::output_css_links()
     * @since 0.6
     * @access private
     */
    function html_header() {
		global $fast_functions;
		$fast_fields_css = $this->prefix;
		$css_files       = array( $fast_fields_css );
		$fast_functions->output_css_links( $css_files );
    }

    /**
     * Display an error if the tables are not installed.
     *
     * @uses fast_functions::table_exists()
     * @uses debugger::add_error()
     * @since 0.1
     * @access private
     *
     * @param array $tables An array of tables to check.
     */
    function verify_tables($tables) {
        global $fast_functions, $debugger;

        $missing_tables = $fast_functions->tables_exist($tables);
        foreach ($missing_tables as $missing_table) {
            $debugger->add_error("<strong>{$missing_table}<strong>
                                 table not found.");
        }
    }

    /**
     * Checks to see if a key row, global row or post row exists with
     * the name specified.
     *
     * @uses wpdb::get_var()
     * @uses fast_fields::$table
     * @uses debugger::add_error()
     *
     * @param string $name The name to check.
     * @param int|NULL $post_id Also checks against this post_id if it
     *  isn't {@code NULL}.
     * @return bool Returns true if the field name is used.
     */
    function field_name_used($name, $post_id=NULL) {
        global $wpdb;

        $check_post = (!is_null($post_id) && $post_id)
                      ? " post_id = '{$post_id}' OR "
                      : '';
        $name = mysql_real_escape_string($name);

        $name_check = "SELECT name
                      FROM {$this->table}
                      WHERE name = '{$name}'
                      AND ( {$check_post}
                            post_id = 0
                            OR post_id IS NULL );";

        return !is_null($wpdb->get_var($name_check));
    }

    /**
     * Adds a new field.
     *
     * @uses wpdb::get_var
     * @uses wpdb::insert
     * @uses fast_fields::table
     * @uses debugger::add_error
     * @uses debugger::add_alert
     * @since 0.1
     * @access public
     *
     * @param string $name The name of the new field.
     * @param string $type The type of the new field.
     * @param string $value The value of the new field.
     * @param int|NULL $post_id Contains NULL for a key field, 0 for
     *  a global field, or any positive integer for a post  field.
     */
    function add_field($name, $type, $value, $post_id=NULL) {
        global $wpdb, $debugger;

        /*
         * Sanitize $name, $type and $value and put them into
         * values
         */
        $values = array();
        foreach (array('name', 'type', 'value') as $attribute) {
            $$attribute = mysql_real_escape_string($$attribute);
            $values[$attribute] = $$attribute;
        }

        $field_type = 'key';
        if (!is_null($post_id)) {
            $values['post_id'] = $post_id;
            $field_type = (0 == $post_id) ? 'global' : 'post';
        }

        if ($this->field_name_used($name, $post_id)) {
            $add_field = false;
            $debugger->add_error("There already exists a key field or
                                 post field with this name:
                                 '{$name}'");
        } else {

            // We can add the field
            if ($wpdb->insert($this->table, $values)) {
                // If field was added
                $field_name  = htmlspecialchars($name);
                $field_value = htmlspecialchars($value);
                $debugger->add_alert("Added {$field_type} field
                                     '<strong>{$field_name}</strong>':
                                     <em>{$field_value}</em>");
            } else {
                $debugger->add_error('Field was not added:<br />'
                                     . '<br />' . mysql_error());
            }
        }
    }

    /**
     * Checks to see if a new field should be added.
     * 
     * @uses fast_fields::$prefix
     * @uses fast_fields::add_field()
     * @since 0.1
     * @access private
     *
     * @param int|NULL $post_id Contains the post_id of the post that
     *  called this function if the new field is going to be local to
     *  that post.
     */
    function add_field_check($post_id=NULL) {
        global $wpdb, $debugger;

        // Declare and initialize $name, $type and $value
        foreach (array('name', 'type', 'value') as $variable) {
            $$variable = $_POST[$this->prefix . $variable];
        }

        if ($name != '') {
            $this->add_field($name, $type, $value, $post_id);
        }
    }

    /**
     * Returns the attributes of a given field.
     *
     * @uses fast_fields::$table
     * @uses wpdb::get_row()
     * @since 0.1
     * @access public
     * @deprecated deprecated since version 1.0.2
     *
     * @param int $field_id The field_id of the field to retrieve.
     * @return array An associative array containing the field
     *  attributes.
     */
    function get_field($field_id) {
        global $wpdb;

        $sql_retrieve = "SELECT *
                        FROM {$this->table}
                        WHERE field_id = '{$field_id}'";
        // Add error handling
        $field = $wpdb->get_row ($sql_retrieve, ARRAY_A);
        return $field;
    }

    /**
     * Returns the attributes of a given field.
     *
     * @uses fast_fields::$table
     * @uses wpdb::get_row()
     * @since 1.0.2
     * @access public
     *
     * @param int $field_id The field_id of the field to retrieve.
     * @return array An associative array containing the field
     *  attributes.
     */
    function get_field_attributes($field_id) {
        global $wpdb;

        $sql_retrieve = "SELECT *
                        FROM {$this->table}
                        WHERE field_id = '{$field_id}'";
        // Add error handling
        $field = $wpdb->get_row ($sql_retrieve, ARRAY_A);
        return $field;
    }

    /**
     * Returns the attributes of a given field in a safe HTML format.
     *
     * @uses fast_fields::get_fields()
     * @uses wpdb::get_row()
     * @since 0.3
     * @access public
     *
     * @param int $field_id The field_id of the field to retrieve.
     * @return array An associative array containing the field
     *  attributes.
     */
    function safe_get_field($field_id) {
        $field = $this->get_field_attributes($field_id);
        foreach ($field as $key => $value) {
            $field[$key] = htmlspecialchars($value);
        }
        return $field;
    }

    /**
     * Returns the name of a field.
     *
     * @uses wpdb::get_var()
     * @since 0.1
     * @access public
     *
     * @param int $field_id The field_id of the field to get the name
     *  of.
     * @return string The name of the field.
     */
    function get_field_name($field_id) {
        global $wpdb;
        $sql_retrieve = "SELECT name
                        FROM {$this->table}
                        WHERE field_id = '{$field_id}'";

        $field_name = $wpdb->get_var($sql_retrieve);
        $field_name = htmlspecialchars($field_name);
        return $field_name;
    }

    /**
     * Returns the field_id of a field.
     *
     * @uses wpdb::get_var()
     * @since 0.4
     * @access public
     *
     * @param int $field_id The field_id of the field to get the value of.
     * @return string The value of the field.
     */
    function get_field_id($field_name, $post_id=NULL) {
        global $wpdb;

        $post_condition = is_null($post_id)
                          ? '' : "AND post_id = {$post_id}";

        $sql_retrieve = "SELECT field_id
                        FROM {$this->table}
                        WHERE name = '{$field_name}'
                        {$post_condition};";

        $field_id = $wpdb->get_var($sql_retrieve);
        return $field_id;
    }

    /**
     * Returns the value of a field.
     *
     * @uses wpdb::get_var()
     * @since 0.3
     * @access public
     *
     * @param int $field_id The field_id of the field to get the value of.
     * @return string The value of the field.
     */
    function get_field_value($field_id) {
        global $wpdb;
        $sql_retrieve = "SELECT value
                        FROM {$this->table}
                        WHERE field_id = '{$field_id}'";

        $field_value = $wpdb->get_var($sql_retrieve);
        $field_value = htmlspecialchars($field_value);
        return $field_value;
    }

    /**
     * Edits the value of a key field or global field for a particular
     * post.
     *
     * @uses fast_fields::$prefix
     * @since 0.1
     * @access public
     *
     * @param int $post_id The post_id of the field.
     * @param string $field_id The field_id of the field whose value
     *  is to change.
     * @param string $value This post's value for this field.
     */
    function edit_post_field($post_id, $field_id, $value) {
        update_post_meta($post_id, $this->prefix . $field_id, $value);
    }

    /**
     * Returns the value of a key field or global field for a particular
     * post.
     *
     * @since 0.1
     * @access public
     *
     * @param int $post_id The post_id of the field.
     * @param string|NULL $field_id The field_id of the field whose
     *  value is to be retrieved.
     * @return string The value of the field for this post.
     */
    function get_post_field($post_id, $field_id) {
        $value = get_post_meta($post_id, $this->prefix . $field_id, true);
        return $value;
    }

    /**
     * Edits the value of one attribute of a field.
     *
     * @uses fast_fields::$table
     * @uses fast_fields::field_name_used()
     * @uses debugger::add_error()
     * @uses debugger::add_alert()
     * @uses wpdb::udpate()
     * @since 0.1
     * @access private
     *
     * @param int $field_id The field_id of the field whose attribute
     *  you want to edit.
     * @param string $attribute The attribute of the field you want to
     *  alter.
     * @param string $value The value to change the attribute to.
     */
    function alter_field($field_id, $attribute, $value) {
        global $wpdb, $debugger;

        $value = mysql_real_escape_string($value);

        if ('name' == $attribute && $this->field_name_used($value)) {
            $debugger->add_error("There already exists a key field or
                                 post field with this name:
                                 '{$value}'");
        } else {
            $wpdb->update($this->table, array($attribute => $value),
                          array('field_id' => $field_id));
            $safe_field  = $this->safe_get_field($field_id);
            $field_name  = $safe_field['name'];
            $field_value = $safe_field[$attribute];
            $debugger->add_alert("Edited field <strong>{$field_name}"
                                 . "</strong>.{$attribute}:
                                 <em>{$field_value}</em>");
        }
    }

    /**
     * Deletes a field from the fast_fields table.
     *
     * @uses fast_fields::$table
     * @uses fast_fields::get_field_name()
     * @uses debugger::add_error()
     * @uses debugger::add_alert()
     * @uses wpdb::query()
     * @since 0.1
     * @access private
     *
     * @param int $field_id The field_id of the field to delete.
     */
    function delete_field($field_id) {
        global $wpdb, $debugger;
        $field_name = $this->get_field_name($field_id);

        // Delete field
        $sql_delete = "DELETE FROM {$this->table}
                      WHERE field_id = '{$field_id}';";
        $wpdb->query($sql_delete);

        if ($error = mysql_error()) {
            $debugger->add_error($error);
        } else {
            $debugger->add_alert("Deleted field
                                 '<strong>{$field_name}</strong>'");
        }

        // Delete post values for that field
        $sql_delete = "DELETE FROM {$wpdb->postmeta}
                      WHERE meta_key = '{$this->prefix}{$field_id}'";
        $wpdb->query($sql_delete);

        if ($error = mysql_error()) {
            $debugger->add_error($error);
        }
    }

    /**
     * Checks to see if the new value for a field is identical to its
     * old value, and updates the field if not.
     *
     * @uses fast_fields::get_field()
     * @uses fast_fields::alter_field()
     * @uses fast_fields::edit_post_field()
     * @since 0.1
     * @access private
     *
     * @param int $field_id The field_id of the field to update.
     * @param string $attribute The attribute of the field to update.
     * @param string $new_value The new value of the attribute.
     * @param int|NULL $post_id If not NULL, the post_id of the post
     *  whose local value for this field is to be changed.
     */
    function update_field($field_id, $attribute, $new_value, $post_id=NULL) {

        $field = $this->get_field($field_id);
        $field_value = $field[$attribute];

        // Because $new_value might be escaped
        $escape = mysql_real_escape_string($field_value);

        if ($new_value != $field_value && $new_value != $escape) {
            if (is_null($post_id)) {
                $this->alter_field($field_id, $attribute, $new_value);
            } else {
                $this->edit_post_field($post_id, $field_id,
                                       $new_value);
            }
        }
    }

    /**
     * Checks to see if the values of any fields should be edited.
     *
     * @uses fast_fields::$prefix
     * @uses fast_fields::delete_field()
     * @uses fast_fields::update_field()
     * @since 0.1
     * @access private
     *
     * @param int $post_id The post_id of the field to edit.
     */
    function update_fields($post_id) {
        foreach ($_POST as $post_key => $post_data) {
            // Matches $_POST data for edit fields
            if (preg_match("/{$this->prefix}([\\d]+)_([\\w]*)/",
                           $post_key, $matches)) {
                $field_id  = $matches[1];
                $attribute = $matches[2];

                if ('remove'== $attribute && 'on' == $post_data) {
                    $this->delete_field($field_id);
                } elseif ($_POST["{$this->prefix}{$field_id}_remove"]
                            != 'on'
                            && ('name' == $attribute
                            || 'value' == $attribute)) {

                    /*
                     * If the field isn't being deleted and its name
                     * or value is to be changed.
                     */
                    $this->update_field($field_id, $attribute,
                                        $post_data);
                }

                foreach ($this->field_attributes as $att) {
                    $html_name = $this->prefix . $field_id;
                    $attribute_html = $this->attribute_html($att);
                    $post_value = "{$html_name}_{$attribute_html}";
                    $value = isset ($_POST[$post_value]);
                    $this->set_field_attribute($field_id, $att,
                                               $value);
                }

            // Matches $_POST data for input fields
            } elseif (preg_match("/{$this->prefix}([\\d]+)/",
                                 $post_key, $matches)) {
                $field_id = $matches[1];

                if (0 == $post_id) {
                    // We edit fast_field values
                    $this->update_field($field_id, 'value',
                                        $post_data);
                } else {
                    // We edit post meta values
                    $this->update_field($field_id, $attribute,
                                        $post_data, $post_id);
                }
            }
        }
    }

    /**
     * Outputs an "Add Field" panel.
     *
     * <p>The "Add Field" panel is used on admin pages and edit post pages.</p>
     * 
     * @uses fast_fields::$prefix
     * @since 0.1
     * @access public
     *
     * @param string $title The title to output on the panel.
     */
    function output_add_field_panel($title='Add New Field') {
        $pre = $this->prefix;
?>
<div id="<?php echo $pre; ?>add_field_panel">
    <h4><?php echo $title; ?></h4>
    <table>
        <thead>
            <th>
                <label for="<?php echo $pre; ?>name">Name</label>
            </th>
            <th>
                <label for="<?php echo $pre; ?>value">Value</label>
            </th>
        </thead>
        <tbody>
            <tr>
                <input type="hidden" id = "<?php echo $pre; ?>type"
                       name="<?php echo $pre; ?>type" />
                <td>
                    <input type="text"
                           id="<?php echo $pre; ?>name"
                           name="<?php echo $pre; ?>name" />
                </td>
                <td>
                    <input type="text"
                           id="<?php echo $pre; ?>value"
                           name="<?php echo $pre; ?>value" />
                </td>
            </tr>
        </tbody>
    </table>
</div>
<?php
    }

    /**
     * Retrieves all fields of a given type.
     *
     * <p>The field types are key, global and post.</p>
     *
     * @uses debugger::fatal()
     * @uses fast_fields::$table
     * @uses wpdb::get_results()
     * @since 0.1
     * @access public
     *
     * @param 'key'|'global'|'post' $type The type of fields to
     *  retrieve.
     * @param int|NULL $post_id Set to the {@code post_id} of a
     *  specific post if local values for a post are wanted.
     * @return array An associative array of the key fields in the
     *  fast_fields table.
     */
    function get_fields($type, $post_id=NULL) {
        global $wpdb, $debugger;

        switch ($type) {
            case 'key':
                $post_condition = 'IS NULL';
                break;

            case 'global':
                $post_condition = '= 0';
                break;

            case 'post':
                $post_condition = "= {$post_id}";
                break;

            default:
                $debugger->fatal("\$type variable must be 'key',
                                 'global' or 'post'. Got '{$type}'.");
                break;
        }

        $sql_retrieve = "SELECT *
                        FROM {$this->table}
                        WHERE post_id {$post_condition}
                        ORDER BY field_id, order_id ASC;";
        $fields = $wpdb->get_results ($sql_retrieve, ARRAY_A);

        if (!is_null($post_id) && $post_id > 0) {

            /*
             * If $post_id != NULL and $post_id > 0, the field must be
             * a post field, so replace all values with local values,
             * or else the default.
             */
            $post_fields = array();
            foreach ($fields as $field) {
                $name = $field['field_id'];
                $post_value = $this->get_post_field($post_id, $name);
                if ($post_value) {
                    // Post has its own value, replace the default
                    $field['value'] = $post_value;
                }
                $post_fields[] = $field;
            }
            $fields = $post_fields;
        }

        return $fields;
    }

    /**
     * Parse a field to an array of HTML formatted strings.
     *
     * @uses fast_fields::$prefix
     * @uses debugger::fatal()
     * @since 0.1
     * @access private
     *
     * @param 'edit'|'input'|'output' $type How the field is to be
     *  formatted.
     * @param array $field The field to parse.
     * @return array An array of HTML formatted strings.
     */
    function parse_field($type, $field) {
        global $debugger;

        $name      = $field['name'];
        $value     = $field['value'];
        $field_id  = $field['field_id'];
        $html_type = $field['type'];
        $html_name = $this->prefix . $field_id;

        $html = array();

        if ('edit' == $type) {
            // Edit fields
            $html[] = "<input type=\"text\" name=\"{$html_name}_name\"
                      value=\"{$name}\" />";
            $html[] = "<input type=\"text\"
                      name=\"{$html_name}_value\"
                      value=\"{$value}\" />";
            $html[] = "<input type=\"checkbox\"
                      name=\"{$html_name}_remove\" />";

            foreach ($this->field_attributes as $attribute) {
                $attribute_html = $this->attribute_html($attribute);
                $checked = $this->is_field_attribute_set($field_id,
                                                         $attribute)
                         ? 'checked="checked" ' : '';
                $html[] = "<input type=\"checkbox\"
                          name=\"{$html_name}_{$attribute_html}\"
                          {$checked}/>";
            }
        } elseif ('input' == $type) {
            // Input fields
            $name = htmlspecialchars($field['name']);
            $html[] = "<label for=\"{$html_name}\">
                      {$name}</label>\n";
            $html[] = "<input type=\"text\"
                      id=\"{$html_name}\" name=\"{$html_name}\"
                      value=\"{$field['value']}\" />";
        } elseif ('output' == $type) {
            // Output fields
            $html[] = "<span class=\"{$this->pre}label\">" .
                      htmlspecialchars($field['name']) . ':</span> ';
            $html[] = htmlspecialchars($field['value']);
        } else {
            $debugger->fatal("\$type variable must be 'edit', 'input'
                             or 'output'. Got '{$type}'.");
        }

        return $html;
    }

    /**
     * Parses an array of row components into a string in the format
     * of a HTML table row.
     *
     * @since 0.1
     * @access public
     *
     * @param array $components The components to be put into the
     *  table row.
     * @return string A string formatted to a HTML table row.
     */
    function parse_table_row($components) {
        $row = "<tr>\n";
        foreach ($components as $component) {
            $row .= "<td>{$component}</td>\n";
        }
        $row .= "</tr>\n";
        return $row;
    }

    /**
     * Outputs all fields of a specified type.
     *
     * <p>The field types are key, global and post.</p>
     *
     * @uses fast_fields::get_fields()
     * @uses fast_fields::parse_field()
     * @since 0.3
     * @access public
     *
     * @param 'key'|'global'|'post' $type The type of field to format.
     * @param 'edit'|'input'|'output' $format How to format the
     *  fields.
     * @param int|NULL $post_id The post_id of the post field, NULL if
     *  the field is not a post field.
     */
    function output_fields($type, $format, $post_id=NULL) {
        $fields = $this->get_fields($type, $post_id);

        foreach ($fields as $field) {
            $components = $this->parse_field($format, $field);

            foreach ($components as $component) {
                echo $component;
            }

            echo '<br />';
        }
    }

    /**
     * Outputs a table containing all fields of a specified type.
     *
     * <p>The field types are key, global and post.</p>
     *
     * @uses fast_fields::get_fields()
     * @uses fast_fields::parse_field()
     * @uses fast_fields::parse_table_row()
     * @since 0.1
     * @access public
     *
     * @param 'key'|'global'|'post' $type The type of field to format.
     * @param 'edit'|'input'|'output' $format How to format the
     *  fields.
     * @param int|NULL $post_id The post_id of the post field, NULL if
     *  the field is not a post field.
     */
    function output_field_table($type, $format, $post_id=NULL) {
        // Table head
        echo '<table><thead><tr><th>Name</th><th>Value</th>';
        if ('edit' == $format) {
            echo '<th>Remove</th>';
            foreach ($this->field_attributes as $attribute) {
                echo "<th>{$attribute}</th>";
            }
        }
        echo '</tr></thead>';

        // Table body
        echo '<tbody>';
        $fields = $this->get_fields($type, $post_id);
        foreach ($fields as $field) {
            // Gets a field formatted for editing, input or output
            $row = $this->parse_field($format, $field);
            echo $this->parse_table_row($row);
        }
        echo '</tbody></table>';
    }

    /**
     * Update fields on post pages.
     *
     * Hooked to the {@code save_post} action.
     *
     * Creates the {@code fast_fields::save_post} action hook.
     *
     * @uses fast_fields::add_field_check()
     * @uses fast_fields::update_fields()
     * @since 0.1
     * @access private
     *
     * @param int $post_id The post_id of the current post. Supplied
     *  by the 'save_post' action.
     */
    function save_post($post_id) {
        // Make sure the post_id we were given is correct.
        if ($post_id == $_POST['post_ID']) {
            do_action("{$this->prefix}save_post", $post_id);

            $this->add_field_check($post_id);
            $this->update_fields($post_id);
        }
    }

    /**
     * The fast_fields panel for post pages.
     *
     * Creates the {@code fast_fields::post_panel} action hook.
     *
     * @uses fast_fields::$prefix
     * @since 0.1
     * @access private
     */
    function post_panel() {
        $pre = $this->prefix;
        $post_id = mysql_real_escape_string($_REQUEST['post']);

        do_action("{$this->prefix}post_panel", $post_id);
?>
<div class="<?php echo $pre; ?>tabs">
    <ul class="<?php echo $pre; ?>tab_navigation">
        <li><a href="#<?php echo $pre; ?>key">Key Fields</a></li>
        <li>
            <a href="#<?php echo $pre; ?>global">Global Fields </a>
        </li>
        <li><a href="#<?php echo $pre; ?>post">Post Fields </a></li>
    </ul>

    <div id="<?php echo $pre; ?>key">
        <?php $this->output_field_table('key', 'input', $post_id); ?>
    </div>
    <div id="<?php echo $pre; ?>global">
        <?php $this->output_field_table('global', 'input',
                                        $post_id); ?>
    </div>
    <div id="<?php echo $pre; ?>post">
        <?php $this->output_field_table('post', 'edit', $post_id); ?>
        <?php $this->output_add_field_panel(); ?>
    </div>
</div>
<?php
    }

    /**
     * Outputs a meta box for fast_fields on post pages.
     *
     * Is hooked to the {@code admin_menu} action.
     *
     * @uses fast_fields::$prefix
     * @since 0.1
     * @access private
     */
    function show_post_panel() {
        if (function_exists('add_meta_box')) {
            foreach (array('post', 'page') as $page_type) {
                add_meta_box("{$this->prefix}metabox", 'Fast Fields',
                             array(&$this, 'post_panel'), $page_type,
                             'normal', 'high');
            }
        }
    }

    /**
     * The admin page for fast_fields.
     *
     * @uses fast_fields::$prefix
     * @uses fast_fields::add_field_check()
     * @uses fast_fields::output_field_table()
     * @uses fast_fields::output_field_panel()
     * @uses debugger::clear_alerts();
     * @uses debugger::clear_errors();
     * @uses debugger::show_alerts();
     * @uses debugger::show_errors();
     * @since 0.1
     * @access private
     */
    function admin_page() {
        global $debugger;
        $pre = $this->prefix;

        // To remove errors introduced before this function was called
        $debugger->clear_errors();
        $debugger->clear_alerts();

        // Check if we need to add a new field
        if ('Save Changes' == $_POST["{$this->prefix}save_changes"]) {
            $this->add_field_check(0);
            $this->update_fields(0);
        }

        /*
         * fast_fields::add_field_check() could have introduced new
         * alerts or errors
         */
        $debugger->show_errors(true);
        $debugger->show_alerts(true);

        // Outputs the key fields and the "Add Field" panel
?>
<form action="" method="post">
    <div class="<?php echo $pre; ?>tabs">
        <ul class="<?php echo $pre; ?>tab_navigation">
            <li><a href="#<?php echo $pre; ?>key">Key Fields</a></li>
            <li>
                <a href="#<?php echo $pre; ?>global">Global Fields</a>
            </li>
        </ul>

        <div id="<?php echo $pre; ?>key">
            <?php $this->output_field_table('key', 'input'); ?>
        </div>
        <div id="<?php echo $pre; ?>global">
            <?php $this->output_field_table('global', 'edit'); ?>
            <?php $this->output_add_field_panel(); ?>
        </div>
    </div>
    <input type="submit" name="<?php echo $pre; ?>save_changes"
           class="button-primary" value="Save Changes" />
</form>
<?php
    }

    /**
     * Verify that everything needed for fast_fields to work is
     * working.
     *
     * @uses fast_fields::$table
     * @uses fast_fields::$meta_table
     * @uses fast_fields::verify_tables()
     * @since 0.3
     * @access private
     */
    function is_working() {
        $this->verify_tables(array($this->table, $this->meta_table));
    }

    /**
     * Adds a field attribute to fast_fields.
     *
     * Every field attribute needs to be registered before its use.
     * This is to avoid clashes with attribute names.
     *
     * @uses fast_fields::$field_attributes
     * @uses debugger::fatal()
     * @since 0.4
     * @access public
     *
     * @param string $attribute The name of the attribute to add.
     */
    function add_field_attribute($attribute) {
        global $debugger;

        if (!in_array($attribute, $this->field_attributes)) {
            $this->field_attributes[] = $attribute;
        } else {
            $debugger->fatal("There is already a field attribute with
                             this name: '{$attribute}'");
        }
    }

    /**
     * Sets or unsets the value of a field attribute.
     *
     * @uses wpdb::insert()
     * @uses wpdb::query()
     * @since 0.4
     * @access public
     *
     * @param int $field_id The field_id of the field whose attribute
     *  is to be set.
     * @param string $attribute The attribute to set. This must be
     *  registered in {@code fast_fields::$field_attributes} using
     *  {@code fast_fields::add_field_attribute()} before this
     *  function is called.
     * @param bool $value Set the value of the attribute to this
     *  value.
     */
    function set_field_attribute($field_id, $attribute, $value) {
        global $wpdb, $debugger;

        if (in_array($attribute, $this->field_attributes)) {
            if ($value) {
                $wpdb->insert($this->meta_table,
                              array('field_id' => $field_id,
                                    'attribute' => $attribute),
                              array('%d', '%s'));
            } else {
                $sql_delete = "DELETE FROM {$this->meta_table}
                              WHERE field_id = {$field_id}
                              AND attribute = '{$attribute}';";
                $wpdb->query($sql_delete);
            }
        } else {
            $error = "You have not registered this attribute with
                     <em>fast_fields::add_field_attribute()</em>:
                     {$attribute}";
            $debugger->add_error($error);
        }
    }

    /**
     * Gets the value of a field attribute.
     *
     * @uses wpdb::get_row()
     * @since 0.4
     * @access public
     *
     * @param int $field_id The field_id of the field whose attribute
     *  is to be retrieved.
     * @param string $attribute The attribute to get. This must be
     *  registered in {@code fast_fields::$field_attributes} using
     *  {@code fast_fields::add_field_attribute()} before this
     *  function is called.
     * @return bool The value of the field's attribute.
     */
    function is_field_attribute_set($field_id, $attribute) {
        global $wpdb;

        $sql_retrieve = "SELECT *
                        FROM {$this->meta_table}
                        WHERE field_id = {$field_id}
                        AND attribute = '{$attribute}';";
        $value = $wpdb->get_row($sql_retrieve, ARRAY_A);

        // If the value is null, we return false
        return !is_null($value);
    }

    /**
     * Gets all fields that have the specified attribute set.
     *
     * @uses wpdb::get_col()
     * @since 0.4
     * @access public
     *
     * @param string $attribute The attribute to get. This must be
     *  registered in {@code fast_fields::$field_attributes} using
     *  {@code fast_fields::add_field_attribute()} before this
     *  function is called.
     * @return array An indexed array of field_ids of the fields with
     *  the specified attribute set.
     */
    function fields_with_attribute($attribute) {
        global $wpdb;

        $sql_retrieve = "SELECT DISTINCT field_id
                        FROM {$this->meta_table}
                        WHERE attribute = '{$attribute}'
                        ORDER BY field_id;";
        $field_ids = $wpdb->get_col($sql_retrieve);

        return $field_ids;
    }

    /**
     * Formats an attribute in a way that it can be safely output as
     * a HTML attribute.
     *
     * The way an attribute is formatted is:
     *  made lowercase
     *  spaces are replaced with underscores
     *
     * @since 0.4
     * @access private
     *
     * @param string $attribute The attribute to format.
     * @return string The formatted attribute.
     */
    function attribute_html($attribute) {
        $pass_1 = strtolower($attribute);
        $pass_2 = str_replace(' ', '_', $pass_1);
        return $pass_2;
    }

    /**
     * Gets the field_id of all fields of a specific type.
     *
     * <p>The field types are key, global and post.</p>
     *
     * @uses fast_fields::table
     * @uses debugger::fatal()
     * @uses wpdb::get_col()
     * @since 1.0.2
     * @access public
     *
     * @param 'key'|'global'|'post' $type the type of field to get the id of.
     * @param int $post_id the post_id of the field if the type is 'post'.
     * @return array the values of the fields.
     */
    function get_field_ids($type, $post_id=NULL) {
        global $wpdb;

        switch ($type) {
            case 'key':
                $post_condition = 'IS NULL';
                break;

            case 'global':
                $post_condition = '= 0';
                break;

            case 'post':
                $post_condition = "= {$post_id}";
                break;

            default:
                $debugger->fatal("\$type variable must be 'key',
                                 'global' or 'post'. Got '{$type}'.");
                break;
        }


        $sql_retrieve = "SELECT field_id
                        FROM {$this->table}
                        WHERE post_id {$post_condition};";

        $field_ids = $wpdb->get_col($sql_retrieve);
        return $field_ids;
    }

    /**
     * Returns the name of all fields of a specific type.
     *
     * <p>The field types are key, global and post.</p>
     *
     * @uses fast_fields::table
     * @uses debugger::fatal()
     * @uses wpdb::get_col()
     * @since 1.0.2
     * @access public
     *
     * @param 'key'|'global'|'post' $type the type of field to get the id of.
     * @param int $post_id the post_id of the field if the type is 'post'.
     * @return string the names of the fields.
     */
    function get_field_names($type, $post_id=NULL) {
        global $wpdb;

        switch ($type) {
            case 'key':
                $post_condition = 'IS NULL';
                break;

            case 'global':
                $post_condition = '= 0';
                break;

            case 'post':
                $post_condition = "= {$post_id}";
                break;

            default:
                $debugger->fatal("\$type variable must be 'key',
                                 'global' or 'post'. Got '{$type}'.");
                break;
        }

        $sql_retrieve = "SELECT name
                        FROM {$this->table}
                        WHERE post_id {$post_condition};";

        $field_ids = $wpdb->get_col($sql_retrieve);
        return $field_ids;
    }
}

/**
 * The fast_fields object.
 *
 * @since 0.1
 */
global $fast_fields;
$fast_fields = new fast_fields();


?>
