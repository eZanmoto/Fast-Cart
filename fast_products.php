<?php

require ('fast_fields.php');

/**
 * Wordpress Product Object
 *
 * Original code from {@link http://ezanmoto.wordpress.com Seán M.
 * Kelleher (ezanmoto@gmail.com)}
 *
 * @package eZanmoto
 * @subpackage Products
 * @since 0.2
 */
class fast_products{

    /**
     * This is prepended to entries into the postmeta table.
     *
     * @since 0.2
     * @access private
     * @var string
     */
    var $prefix;

    /**
     * What identifies a particular post or page as a product.
     *
     * @since 0.2
     * @access private
     * @var string
     */
    var $type;

    /**
     * The default constructor function.
     *
     * Hooks {@code fast_products::post_panel()} to the
     * {@code fast_fields::post_panel} action.
     *
     * Hooks {@code fast_products::save_product_check()} to the
     * {@code fast_fields::save_post} action.
     *
     * @uses fast_fields::$prefix
     * @uses fast_products::$prefix
     * @since 0.2
     * @access public
     */
    function __construct() {
        global $debugger, $fast_fields;

        $this->prefix = 'fast_products_';
        $this->type = get_option("{$this->prefix}type");

        $fast_fields->add_field_attribute('Input');

        if ($this->prefix == $fast_fields->prefix) {
            $debugger->fatal("fast_products and fast_fields have
                             conflicting prefixes:
                             <em>{$this->prefix}</em>");
        }

        add_action("{$fast_fields->prefix}post_panel",
                   array(&$this, 'post_panel'));
        add_action("{$fast_fields->prefix}save_post",
                   array(&$this, 'save_product_check'));
    }

    /**
     * Identifies a post as being a product to {@code fast_products}.
     *
     * @uses fast_products::$prefix
     * @since 0.2
     * @access private
     *
     * @param int $post_id The post_id of the post to be identified as
     *  a product.
     */
    function post_to_product($post_id) {
        update_post_meta($post_id, "{$this->prefix}isproduct",
                         'true');
    }

    /**
     * No longer identifies a post as being a product to
     * {@code fast_products}.
     *
     * @uses fast_products::$prefix
     * @since 0.2
     * @access private
     *
     * @param int $post_id The post_id of the post to be no longer
     *  identified as a product.
     */
    function product_to_post($post_id) {
        update_post_meta($post_id, "{$this->prefix}isproduct",
                         'false');
    }

    /**
     * Checks if a particular post is a product.
     *
     * @uses fast_products::$prefix
     * @since 0.2
     * @access public
     *
     * @param int $post_id The post_id of the post to check.
     * @return bool Returns {@code true} if this post is a product.
     */
    function is_product($post_id) {
        $value = get_post_meta($post_id, "{$this->prefix}isproduct",
                               true);
        return $value == 'true';
    }

    /**
     * Outputs the fast_products product panel inside the fast_fields
     * post panel.
     *
     * Hooked to the {@code fast_fields::post_panel} action.
     *
     * @uses fast_products::is_product()
     * @since 0.2
     * @access private
     *
     * @param int $post_id Checks if this post is a product.
     */
    function post_panel($post_id) {
        $checked = $this->is_product($post_id)
                   ? ' checked="checked" '
                   : ' ';

        echo "<p>This is post is a product <input type=\"checkbox\"
             name=\"{$this->prefix}isproduct\"{$checked}/></p>";
    }

    /**
     * Checks whether a post is to be saved as a product.
     *
     * Hooked to the {@code fast_fields::save_post} action.
     *
     * @uses fast_products::$prefix
     * @uses fast_products::post_to_product()
     * @uses fast_products::product_to_post()
     * @since 0.2
     * @access private
     *
     * @param int $post_id The post_id of the post to check.
     */
    function save_product_check ($post_id) {
        // Make sure the post_id we were given is correct.
        if ($post_id == $_POST['post_ID']) {
            if ($_POST["{$this->prefix}isproduct"]) {
                $this->post_to_product($post_id);
            } else {
                $this->product_to_post($post_id);
            }
        }
    }

    /**
     * Outputs the fast fields associated with a certain product.
     *
     * @since 0.3
     * @access public
     *
     * @param int $post_id Used to see what product to output fields
     *  for.
     */
    function output_product_fields($post_id) {
        global $fast_fields;

        $fast_fields->output_fields('key', 'output', $post_id);

        $global_fields = $fast_fields->get_fields('global', $post_id);
        foreach ($global_fields as $field) {
            $format = $this->is_input($field['field_id'])
                      ? 'input' : 'output';
            $components = $fast_fields->parse_field($format, $field);

            foreach ($components as $component) {
                echo $component;
            }

            echo '<br />';
        }

        $post_fields = $fast_fields->get_fields('post', $post_id);
        foreach ($post_fields as $field) {
            $format = $this->is_input($field['field_id'])
                      ? 'input' : 'output';
            $components = $fast_fields->parse_field($format, $field);
            foreach ($components as $component) {
                echo $component;
            }

            echo '<br />';
        }
    }

    /**
     * Returns whether a particular field is an output field.
     *
     * @uses fast_fields::is_field_attribute_set()
     * @since 0.4
     * @access public
     *
     * @param int $field_id The field_id of the field to query.
     * @return bool {@code true} if the field is an output field.
     */
    function is_input($field_id) {
        global $fast_fields;

        $bool = $fast_fields->is_field_attribute_set($field_id,
                                                     'Input');
        return $bool;
    }

    /**
     * Returns the name of a product.
     *
     * @since 0.3
     * @access public
     *
     * @param int $post_id The post_id of the product to get the name of.
     * @return string The name of the product.
     */
    function get_product_name ($post_id) {
        $product = get_post($post_id, ARRAY_A);
        $product_name = $product['post_title'];
        return $product_name;
    }

    /**
     * Returns one field value of a product.
     *
     * @uses fast_products::is_product()
     * @uses fast_fields::get_field()
     * @uses fast_fields::get_post_field()
     * @uses fast_fields::$table
     * @uses wpdb::get_var()
     * @since 0.3
     * @access public
     *
     * @param int $post_id Used to see what product is to be
     *  retrieved.
     * @param int $field_name The name of the field whose value is to
     *  be retrieved.
     */
    function get_product_meta($post_id, $field_name) {
        global $wpdb, $fast_fields;

        $meta = '';
        $field_name = mysql_real_escape_string($field_name);

        if ($this->is_product ($post_id)) {
            // First check the post for its own value for the field
            $field_id = $fast_fields->get_field_id($field_name);
            $meta = $fast_fields->get_post_field($post_id,
                                                  $field_id);

            if ('' == $meta) {
                // Then check the post field's default
                if ($id = $fast_fields->get_field_id($field_name,
                                                     $post_id)) {
                    $field_id = $id;
                }
                $meta = $fast_fields->get_post_field($post_id,
                                                  $field_id);

                // Then check the key and global defaults
                if ('' == $meta) {
                    $field = $fast_fields->get_field($field_id);
                    $meta = $field['value'];
                }
            }
        }

        return $meta;
    }
}

/**
 * The fast_products object.
 *
 * @since 0.2
 */
global $fast_products;
$fast_products = new fast_products();

?>
