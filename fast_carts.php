<?php

require ('fast_products.php');

define('FAST_CARTS_TABLE', 'fastcarts');
define('FAST_CARTS_META_TABLE', 'fastcartsmeta');
define('FAST_CARTS_SALES_TABLE', 'fastcartssales');

define('CHECKOUT_PAGE_NAME', 'Checkout');
define('CHECKOUT_PAGE_CONTENT', '');

define('GUEST_NAME', 'Guest');

define('PAYMENT_DIRECTORY', 'payment_plugins/');

/**
 * Wordpress Shopping Cart Object
 *
 * Original code from {@link http://ezanmoto.wordpress.com Seán M.
 * Kelleher (ezanmoto@gmail.com)}
 *
 * @package eZanmoto
 * @subpackage Carts
 * @since 0.3
 */
class fast_carts{

    /**
     * The table name for the fast carts table.
     *
     * @since 0.3
     * @access public
     * @var string
     */
    var $table;

    /**
     * The table name for the fast carts meta table.
     *
     * @since 0.3
     * @access public
     * @var string
     */
    var $meta_table;

    /**
     * The table name for the fast carts sales table.
     *
     * @since 0.3
     * @access public
     * @var string
     */
    var $sales_table;

    /**
     * Prepended to various tags that fast_carts uses.
     *
     * Change this to avoid namespace clashes with HTML attributes and
     * data in other classes.
     *
     * @since 0.3
     * @access public
     * @var string
     */
    var $prefix;

    /**
     * The user_id of the current user or else the session_id.
     *
     * The user_id of the current user. If there is no user currently
     * logged in, this contains the session_id.
     *
     * @since 0.3
     * @access public
     * @var int
     */
    var $user_id;

    /**
     * The username of the current user or else 'Guest'.
     *
     * The username of the current user. If there is no user currently
     * logged in, this contains the string 'Guest'.
     *
     * @since 0.3
     * @access public
     * @var string
     */
    var $username;

    /**
     * {@code true} if the current user is logged in.
     *
     * @since 1.0.1
     * @access private
     */
    var $is_logged_in;

    /**
     * The default constructor function.
     *
     * Hooks {@code fast_carts::html_header()} to the {@code wp_head}
     * action.
     *
     * Hooks {@code fast_carts::is_working()} to the
     * {@code admin-init} action.
     *
     * Hooks {@code fast_carts::format_product()} to the
     * {@code the_content} action.
     *
     * @uses fast_carts::$prefix
     * @uses fast_carts::$table
     * @uses fast_carts::$meta_table
     * @uses fast_carts::$sales_table
     * @uses fast_carts::is_working()
     * @uses fast_products::$prefix
     * @uses fast_fields::$prefix
     * @uses fast_fields::add_field_attribute()
     * @uses debugger::fatal()
     * @uses wpdb::$prefix
     * @since 0.3
     * @access public
     */
    function __construct() {
        global $wpdb, $debugger, $fast_fields, $fast_products;

        $this->table       = $wpdb->prefix . FAST_CARTS_TABLE;
        $this->meta_table  = $wpdb->prefix . FAST_CARTS_META_TABLE;
        $this->sales_table = $wpdb->prefix . FAST_CARTS_SALES_TABLE;

        $this->prefix       = 'fast_carts_';
        $this->username     = 'Guest';
        $this->is_logged_in = false;

        $fast_fields->add_field_attribute('In Checkout');

        foreach (array('fast_fields', 'fast_products') as $class) {
            if ($this->prefix == $$class->prefix) {
                $debugger->fatal("fast_carts and {$class} have
                                 conflicting prefixes:
                                 <em>{$this->prefix}</em>");
            }
        }

        add_action('init', array(&$this, 'register_cart_widget'));
        add_action('admin_init', array(&$this, 'is_working'));
        add_action('wp_head', array(&$this, 'html_header'));
        add_action('the_content', array(&$this, 'format_product'));
        add_action('the_content', array(&$this, 'is_checkout_page'));
        add_action('set_current_user', array(&$this,
                                             'set_current_user'));

        $this->clean_table();
    }

    /**
     * Installs everything needed for fast_carts to run when it is
     * activated for the first time.
     *
     * Creates the tables required for fast_carts.
     *
     * @uses wpdb::has_cap()
     * @uses wpdb::$charset
     * @uses wpdb::$collate
     * @uses wpdb::query()
     * @uses fast_functions::verify_tables()
     * @since 0.3
     * @access private
     */
    function install() {
        global $wpdb, $fast_fields;

        $fast_fields->install();

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
                    cart_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    user_id CHAR(32) NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    amount INT NOT NULL,
                    date_added DATE,
                    PRIMARY KEY (cart_id),
                    FOREIGN KEY (product_id) REFERENCES ' .
                    $wpdb->posts . '(ID)
                ) ' . $charset_collate . ';';
        $wpdb->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->meta_table
               . '(
                    meta_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    cart_id BIGINT UNSIGNED NOT NULL,
                    meta_key VARCHAR(255) DEFAULT NULL,
                    meta_value LONGTEXT DEFAULT NULL,
                    PRIMARY KEY (meta_id)
                ) ' . $charset_collate . ';';
        $wpdb->query($sql);

        $sql = 'CREATE TABLE IF NOT EXISTS ' . $this->sales_table . '(
                    cart_id BIGINT UNSIGNED NOT NULL,
                    user_id CHAR(32) NOT NULL,
                    product_id BIGINT UNSIGNED NOT NULL,
                    amount INT NOT NULL,
                    date_sold DATE,
                    PRIMARY KEY (cart_id),
                    FOREIGN KEY (product_id) REFERENCES ' .
                    $wpdb->posts . '(ID)
                ) ' . $charset_collate . ';';
        $wpdb->query($sql);

        // Install the key fields
        $curdir = dirname(__FILE__);
        if ($key_fields = fopen("{$curdir}/key_fields", 'r')) {
            while (!feof($key_fields)) {
                $field = fgets($key_fields);
                $char = substr($field, 0, 1);
                if ($char != '#') {
                    $attributes = explode(' ', $field);
                    $attributes = str_replace('+', ' ', $attributes);
                    $name = $attributes[1];
                    if (!$fast_fields->field_name_used($name)) {
                        $fast_fields->add_field($name, $attributes[0],
                                                chop($attributes[2]));
                    }
                }
            }
        }
        fclose($key_fields);

        $sale_price_id = $fast_fields->get_field_id('Sale Price');
        $fast_fields->set_field_attribute($sale_price_id,
                                          'In Checkout', true);

        $fast_fields->verify_tables(array($this->table,
                                    $this->meta_table,
                                    $this->sales_table));

        // Debugging mode should be on for payment plugin by default
        update_option("{$this->prefix}debugging", 'on');

        if (!$this->checkout_exists()) {
            $this->create_checkout();
        }
    }

    /**
     * Outputs the CSS and javascript for fast_carts.
     *
     * Hooked to the {@code wp_head} action.
     *
	 * @uses fast_functions::output_css_links()
	 * @uses fast_functions::output_js_links()
     * @since 0.4
     * @access private
     */
    function html_header() {
		global $fast_functions;

		$css_files = array( 'fast_carts', 'checkout',
							'smoothness/jquery-ui-1.8.6.custom' );
		$js_files  = array( 'jquery-ui-1.8.6.custom.min', 'checkout' );

		$fast_functions->output_css_links( $css_files );
		$fast_functions->output_js_links(  $js_files  );
    }

    /**
     * Sets the current user.
     *
     * Hooked to the {@code set_current_user} action.
     *
     * @uses fast_carts::$table
     * @uses fast_carts::$user_id
     * @uses fast_carts::$username
     * @uses debugger::add_error()
     * @uses wpdb::get_results()
     * @uses wpdb::update()
     * @since 0.3
     * @access private
     */
    function set_current_user() {
        global $wpdb, $current_user, $debugger;

        wp_get_current_user();

        // If user is not logged in
        if (0 == $current_user->ID) {
            $this->user_id = session_id();
        } else {
            $this->user_id      = $current_user->ID;
            $this->username     = $current_user->display_name;
            $this->is_logged_in = true;
            $session_id         = session_id();

            // Check if the user just logged in
            $sql_retrieve = "SELECT *
                            FROM {$this->table}
                            WHERE user_id = '{$session_id}';";
            $guest_carts = $wpdb->get_results($sql_retrieve, ARRAY_A);
            if ($guest_carts) {
                // Update session_id in carts table to user_id
                $wpdb->update($this->table,
                              array('user_id' => $this->user_id),
                              array('user_id' => $session_id),
                              '%s', '%s');

            }
        }

        if (!$this->user_id) {
            $debugger->add_error("fast_carts::{$user_id} was not set
                                 properly.");
        }

        $this->add_to_cart();
    }

    /**
     * Creates the fast_carts checkout page.
     *
     * @uses fast_carts::$prefix
     * @uses fast_carts::checkout_exists()
     * @uses fast_carts::verify_checkout_content()
     * @since 0.3
     * @access private
     */
    function create_checkout() {
        if (!$this->checkout_exists()
                && !$this->verify_checkout_content) {
            $checkout_page = array();
            $checkout_page['post_title'] = CHECKOUT_PAGE_NAME;
            $checkout_page['post_content'] = CHECKOUT_PAGE_CONTENT;
            $checkout_page['post_status'] = 'publish';
            $checkout_page['post_author'] = 1;
            $checkout_page['comment_status'] = 'closed';
            $checkout_page['post_type'] = 'page';

            $post_id = wp_insert_post($checkout_page);
            update_option("{$this->prefix}checkout_id", $post_id);
        }
    }

    /**
     * Returns {@code true} if a published page with a name
     * {@code CHECKOUT_PAGE_NAME} exists.
     *
     * @uses wpdb::get_row()
     * @since 0.3
     * @access public
     *
     * @param bool {@code true} if a page with a name
     *  {@code CHECKOUT_PAGE_NAME} exists.
     */
    function checkout_exists() {
        global $wpdb;

        $checkout_name = CHECKOUT_PAGE_NAME;
        $sql_check = "SELECT *
                     FROM {$wpdb->posts}
                     WHERE post_title = '{$checkout_name}'
                     AND post_type = 'page'
                     AND post_status = 'publish';";
        $checkout_exists = $wpdb->get_row($sql_check, ARRAY_A);

        // If $checkout_exists is NULL, the checkout doesn't exist
        return !is_null($checkout_exists);
    }

    /**
     * Returns {@code true} if the checkout page contains the content
     * {@code CHECKOUT_PAGE_CONTENT}.
     *
     * @uses wpdb::get_row()
     * @since 0.3
     * @access public
     *
     * @param bool {@code true} if the checkout page contains the
     *  content {@code CHECKOUT_PAGE_CONTENT}.
     */
    function verify_checkout_content() {
        global $wpdb;

        $checkout_name = CHECKOUT_PAGE_NAME;
        $sql_retrieve = "SELECT *
                        FROM {$wpdb->posts}
                        WHERE post_title = '{$checkout_name}'
                        AND post_type = 'page'
                        AND post_status = 'publish';";
        $checkout_page = $wpdb->get_row($sql_retrieve, ARRAY_A);
        $checkout_page_content = $checkout_page['post_content'];

        return $checkout_page_content == CHECKOUT_PAGE_CONTENT;
    }

    /**
     * Returns the post_id of the fast_carts checkout page.
     *
     * @uses fast_carts::$prefix
     * @since 0.3
     * @access public
     *
     * @return int The post_id of the fast_carts checkout page.
     */
    function get_checkout_post_id() {
        $post_id = get_option("{$this->prefix}checkout_id");
        return $post_id;
    }

    /**
     * Verify that everything needed for fast_carts to work is
     * working.
     *
     * @uses fast_carts::$table
     * @uses fast_carts::$meta_table
     * @uses fast_carts::$sales_table
     * @uses fast_carts::checkout_exists()
     * @uses fast_carts::verify_checkout_content()
     * @uses fast_fields::verify_tables()
     * @uses debugger::add_error()
     * @since 0.3
     * @access private
     */
    function is_working() {
        global $debugger, $fast_fields;

        $fast_fields->verify_tables(array($this->table,
                                    $this->meta_table,
                                    $this->sales_table));

        if (!$this->checkout_exists()) {
            $error = 'The checkout page required for fast_carts to
                     work doesn\'t exist. Reinstall fast_carts to
                     create the page.';
            $debugger->add_error($error);
        } elseif (!$this->verify_checkout_content()) {
            $error = 'The checkout page required for fast_carts to
                     work doesn\'t contain the expected content.
                     Content should be \'' . CHECKOUT_PAGE_CONTENT .
                     '\'. Reinstall fast_carts or change the content
                     of the checkout page to fix this error.';
            $debugger->add_error($error);
        }
    }

    /**
     * Formats a post as a product if the post is identified as a
     * product.
     *
     * Hooked to the {@code fast_fields::the_content} filter.
     *
     * @uses fast_carts::$prefix
     * @uses fast_products::output_product_fields()
     * @since 0.3
     * @access private
     *
     * $param string $content The content of the post, recieved from
     *  the 'the_content' filter.
     */
    function format_product($content) {
        global $fast_products;

        $post_id    = get_the_ID();
        $cart_added = false;
        $pre        = $this->prefix;

        if ($fast_products->is_product($post_id)) {
?>
<div class="<?php echo $pre; ?>">
    <form action="" method="post">
        <?php echo $content; ?>
        <?php $fast_products->output_product_fields($post_id); ?>
<?php
            if (!$cart_added) {
                $this->output_add_to_cart_button($post_id);
            }
?>
    </form>
</div>
<?php
        } else {
            echo $content;
        }
    }

    /**
     * Outputs an "Add to Cart" button.
     *
     * @uses fast_carts::$prefix
     * @since 0.3
     * @access private
     *
     * @param int $product_id The post_id of the post currently being
     *  output.
     */
    function output_add_to_cart_button($product_id) {
        $pre = $this->prefix;

?>
<input type="hidden" name="<?php echo $pre; ?>product_id"
       value="<?php echo $product_id; ?>" /> 
<input type="submit" class="<?php echo $pre; ?>button"
       name="<?php echo $pre; ?>add_to_cart"
       value="Add to Cart" />
<?php
    }

    /**
     * Adds a product to the cart table.
     *
     * @uses fast_carts::$prefix
     * @uses fast_carts::increase_product_amount()
     * @since 0.3
     * @access private
     */
    function add_to_cart() {
        $pre = $this->prefix;
        $increment = true;
        global $debugger;

        if (isset ($_POST["{$pre}add_to_cart"])
                && isset ($_POST["{$pre}product_id"])) {

            $product_id = $_POST["{$pre}product_id"];
            $this->add_product($product_id);

        } elseif (isset ($_POST["{$pre}cart_id"])) {
            $cart_id = $_POST["{$pre}cart_id"];

            if (isset ($_POST["{$pre}sub"])) {
                $this->product_increment($cart_id, false);
            } elseif (isset ($_POST["{$pre}add"])) {
                $this->product_increment($cart_id);
            } elseif (isset ($_POST["{$pre}rem"])) {
                $this->remove_product($cart_id);
            }

        }
    }

    /**
     * Removes a product from the cart table.
     *
     * @uses fast_carts::$table
     * @uses wpdb::update()
     * @since 0.3
     * @access private
     *
     * @param int $cart_id The cart_id of the cart to delete.
     */
    function remove_product($cart_id) {
        global $wpdb;

        $wpdb->update($this->table, array('amount' => 0),
                      array('cart_id' => $cart_id), '%d', '%d');

        $this->clean_table();
    }

    /**
     * Adds a product to the fast_carts table.
     *
     * @uses wpdb::query()
     * @uses debugger::error()
     * @since 0.3
     * @access private
     *
     * @param int $product_id The product_id of the product to enter
     *  into the fast_carts table.
     */
    function add_product($product_id) {
        global $wpdb, $debugger;

        $sql_retrieve = "SELECT amount
                        FROM {$this->table}
                        WHERE product_id = '{$product_id}'
                        AND user_id = '{$this->user_id}';";
        $amount = $wpdb->get_var($sql_retrieve);

        if (is_null($amount)) {
            $sql_insert = "INSERT INTO {$this->table}(user_id,
                          product_id, amount, date_added)
                          VALUES (
                              '{$this->user_id}',
                              {$product_id},
                              1,
                              CURDATE() );";
            $wpdb->query($sql_insert);
            $cart_id = $wpdb->insert_id;
            $this->add_product_meta($cart_id, $product_id);

            if ($error = mysql_error()) {
                $debugger->error ($error);
            }
        } else {
            $debugger->add_alert('This item is already in your cart.
                                 To add another, please use the
                                 sidebar. This feature is to ensure
                                 you don\'t add extra items
                                 accidentally.');
        }
    }

    function delete_product($cart_id) {
    }

    /**
     * Increases or decreases the amount of a product in the product
     * table.
     *
     * @uses fast_carts::$prefix
     * @uses fast_carts::$table
     * @since 0.3
     * @access private
     *
     * @param int $cart_id The cart_id of the tuple that containts the
     *  product.
     * @param bool $increase If false, decrease instead of increase.
     */
    function product_increment($cart_id, $increase=true) {
        global $wpdb, $debugger;
        $amount = $this->get_product_amount($cart_id);

        $amount += $increase ? 1 : -1;
        $wpdb->update($this->table, array('amount' => $amount),
                      array('cart_id' => $cart_id), '%d', '%d');

        if (!$increase) {
            $this->clean_table();
        }
    }

    /**
     * Returns the total amount of one product the current user has in
     * their cart.
     *
     * @uses wpdb::get_var()
     * @since 0.3
     * @access public
     *
     * @param int $cart_id The cart_id that contains the product.
     * @return int The amount of a product the current user has.
     */
    function get_product_amount($cart_id) {
        global $wpdb;

        $sql_retrieve = "SELECT amount
                        FROM {$this->table}
                        WHERE cart_id = '{$cart_id}';";
        $amount = $wpdb->get_var($sql_retrieve);

        if (is_null($amount)) {
            $amount = 0;
        }

        return $amount;
    }

    /**
     * Returns all rows from the fast_carts table that belong to the
     * specified user.
     *
     * @uses wpdb::get_results
     * @since 0.3
     * @access public
     *
     * @param int $user_id The user_id of the user whose cart is to be
     *  retrieved.
     * @return array An associative array containing the rows of the
     *  current user's cart.
     */
    function get_users_cart($user_id) {
        global $wpdb;

        $sql_retrieve = "SELECT *
                        FROM {$this->table}
                        WHERE user_id = '{$user_id}';";
        $cart = $wpdb->get_results($sql_retrieve, ARRAY_A);

        return $cart;
    }

    /**
     * Outputs the contents of the current user's shopping cart.
     *
     * @since 0.3
     * @access private
     */
    function output_shopping_cart() {
        global $fast_products;

        $cart_rows = $this->get_users_cart($this->user_id);
        
        if ($cart_rows) {
            $checkout_field_names = $this->get_checkout_field_names();
?>
<table>
    <thead>
        <th>&nbsp;</th><th>&nbsp;</th><th>&nbsp;</th><th>Product</th>
<?php
            foreach ($checkout_field_names as $field_name) {
                if ('Sale Price' == $field_name) {
                    $field_name = 'Price';
                }
                echo "<th>{$field_name}</th>";
            }
?>
        <th>&nbsp;</th>
    </thead>
    <tbody>
<?php
        foreach ($cart_rows as $row) {
            $pre        = $this->prefix;
            $product_id = $row['product_id'];
            $cart_id    = $row['cart_id'];
            $name       = $fast_products->get_product_name($product_id);

?>
        <form action="" method="post">
            <tr>
                    <input type="hidden" value="<?php echo $cart_id?>"
                           name="<?php echo $pre; ?>cart_id" />
                <td>
                    <input type="submit" name="<?php echo $pre; ?>sub"
                                         value="-" />
                </td>
                <td><?php echo $row['amount']; ?></td>
                <td>
                    <input type="submit" name="<?php echo $pre; ?>add"
                                         value="+" />
                </td>
                <td class="<?php echo $pre; ?>data">
                    <?php echo $name; ?>
                </td>

<?php
                foreach ($checkout_field_names as $name) {
                    echo "<td class=\"{$pre}data\">";
                    echo $this->get_cart_meta($product_id, $name);
                    echo '</td>';
                }
?>

                <td>
                    <input type="submit" name="<?php echo $pre; ?>rem"
                                         value="X" />
                </td>
            </tr>
        </form>
<?php
            }
?>
    </thead>
</table>
<?php
        } else {
            echo '<p>Your cart is empty.</p>';
        }
    }

    /**
     * Creates and registers the shopping cart widget.
     *
     * @since 0.3
     * @access private
     */
    function register_cart_widget() {
        if (function_exists('wp_register_sidebar_widget')) {

            /**
             * The shopping cart widget.
             *
             * @uses fast_carts::$username
             * @uses fast_carts::output_shopping_cart();
             * @since 0.3
             * @access private
             */
            function fast_carts_widget() {
                global $fast_carts;

                echo "<div id=\"{$fast_carts->prefix}widget\">";
                echo "<h2>{$fast_carts->username}'s Shopping
                     Cart</h2><br />";

                $fast_carts->output_shopping_cart();

                $checkout_option = "{$fast_carts->prefix}checkout_id";
                $checkout_page_id = get_option($checkout_option);
                $checkout_url = get_permalink ($checkout_page_id);
                echo '<br /><a href="' . $checkout_url . '">';
                echo 'Proceed to Checkout &gt;&gt;</a></div>';
            }

            wp_register_sidebar_widget ('fast_carts', 'Shopping Cart',
                                        'fast_carts_widget');
        }
    }

    /**
     * Removes old or redundant entries from the fast_carts table.
     *
     * @uses fast_carts::$table
     * @uses fast_carts::$meta_table
     * @uses wpdb::get_col()
     * @uses wpdb::query()
     * @since 0.3
     * @access private
     */
    function clean_table() {
        global $wpdb;

        $sql_get_empty_carts = "SELECT cart_id
                               FROM {$this->table}
                               WHERE amount <= 0;";
        $empty_cart_ids = $wpdb->get_col($sql_get_empty_carts);

        if (count($empty_cart_ids) > 0) {
            $cart_ids  = '(\'';
            $cart_ids .= implode('\',\'', $empty_cart_ids);
            $cart_ids .= '\')';

            // Delete rows referencing an empty cart from both tables
            foreach (array($this->meta_table, $this->table)
                     as $table) {
                $sql_delete = "DELETE FROM {$table}
                              WHERE cart_id IN {$cart_ids};";
                $wpdb->query($sql_delete);
            }
        }
    }

    /**
     * Returns whether a particular field should be output in checkout
     * contexts.
     *
     * If true, this field is output in the widget section and the
     * checkout_page.
     *
     * @uses fast_fields::is_field_attribute_set()
     * @since 0.4
     * @access public
     *
     * @param int $field_id The field_id of the field whose
     *  'In Checkout' attribute is to be checked.
     * @return bool {@code true} if the field should be displayed in
     *  checkout contexts.
     */
    function is_checkout_field($field_id) {
        global $fast_fields;

        $bool = $fast_fields->is_field_attribute_set($field_id,
                                                     'In Checkout');
        return $bool;
    }

    /**
     * Returns the field_ids of all fields which should be output in
     * checkout contexts.
     *
     * @uses fast_fields::is_field_attribute_set()
     * @since 0.4
     * @access public
     *
     * @return array An indexed array of all field_ids whose fields
     *  should be displayed in a checkout context.
     */
    function get_checkout_field_ids() {
        global $fast_fields;

        $attribute = 'In Checkout';
        $field_ids = $fast_fields->fields_with_attribute($attribute);
        return $field_ids;
    }

    /**
     * Returns the names of all fields which should be output in
     * checkout contexts.
     *
     * @uses fast_carts::get_checkout_field_ids()
     * @uses fast_fields::get_field_name()
     * @since 0.4
     * @access public
     *
     * @return array An indexed array of all field names whose fields
     *  should be displayed in a checkout context.
     */
    function get_checkout_field_names() {
        global $fast_fields;

        $field_names = array();
        $field_ids   = $this->get_checkout_field_ids();

        foreach ($field_ids as $field_id) {
            $field_names[] = $fast_fields->get_field_name($field_id);
        }

        return $field_names;
    }

    /**
     * Resets the tables to their original values.
     *
     * Must be called after {@code fast_carts::$meta_table},
     * {@code fast_carts::$sales_table} and {@code fast_carts::$table}
     * have been initialized.
     *
     * @uses fast_carts::install()
     * @uses fast_carts::$meta_table
     * @uses fast_carts::$sales_table
     * @uses fast_carts::$table
     * @uses fast_fields::$meta_table
     * @uses fast_fields::$table
     * @uses wpdb::query()
     * @since 0.4
     * @access private
     */
    function reset_tables() {
        global $wpdb, $fast_fields;

        foreach (array($fast_fields->meta_table, $fast_fields->table,
                       $this->sales_table, $this->meta_table,
                       $this->table) as $table) {
            $sql_delete = "DROP TABLE {$table};";
            $wpdb->query($sql_delete);
        }

        $this->install();

        foreach (array($fast_fields->prefix, $this->prefix)
                 as $prefix) {
            $sql_delete = "DELETE FROM {$wpdb->postmeta}
                          WHERE meta_key LIKE '{$prefix}%';";
            $wpdb->query($sql_delete);
        }
    }

    /**
     * Checks if the current page is the checkout page and calls
     * {@code output_checkout_page()} if it is.
     *
     * @since 0.5
     * @access private
     */
    function is_checkout_page() {
        global $fast_carts;

        $prefix = $fast_carts->prefix;
        $checkout_post_id = get_option("{$prefix}checkout_id");

        if (is_page($checkout_post_id)) {
            require ('checkout.php');
        }
    }

    function add_product_meta($cart_id, $product_id) {
        global $fast_fields, $fast_products;

        foreach (array('key', 'global') as $field_type) {
            $field_ids = $fast_fields->get_field_ids($field_type);

            // Add product meta pertaining to this product into meta table
            foreach ($field_ids as $field_id) {

                /*
                 * If the field isn't an input field we have to supply
                 * meta information for it.
                 */
                if (!$fast_fields->is_field_attribute_set($field_id,
                                                          'input')) {

                    $name  = $fast_fields->get_field_name($field_id);

                    $pid   = $product_id; // just to fit next line
                    $value = $fast_products->get_product_meta($pid,
                                                              $name);

                    $this->add_meta_field($cart_id, $name, $value);
                }
            }
        }

        /*
         * Scan through $_POST and see if there are any fields
         * relating to this product
         */
        foreach ($_POST as $post_key => $value) {
            if (preg_match("/{$fast_fields->prefix}([\\d]+)/",
                           $post_key, $matches)) {
                $field_id = $matches[1];

                $field_name = $fast_fields->get_field_name($field_id);
                $this->add_meta_field($cart_id, $field_name, $value);
            }
        }
    }

    /**
     * Adds meta data about a product in a user's cart.
     *
     * @uses wpdb::query()
     * @since 0.5
     * @access private
     *
     * @Param int $cart_id The cart to associate this meta data with.
     * @param string $meta_key The key to file this meta data under.
     * @param string $meta_value The meta data.
     */
    function add_meta_field($cart_id, $meta_key, $meta_value) {
        global $wpdb;

        $sql_insert = "INSERT INTO {$this->meta_table} (cart_id,
                          meta_key, meta_value) VALUES
                      ({$cart_id}, '{$meta_key}', '{$meta_value}');";
        $wpdb->query($sql_insert);
    }

    function get_cart_ids($product_id) {
        global $wpdb;

        $sql_retrieve = "SELECT cart_id
                        FROM {$this->table}
                        WHERE user_id = '{$this->user_id}'
                        AND product_id = {$product_id};";
        $cart_id = $wpdb->get_col($sql_retrieve);

        return $cart_id;
    }

    function get_cart_meta($product_id, $field_name) {
        global $wpdb, $fast_products;

        $cart_ids = $this->get_cart_ids($product_id);
        $cart_id = $cart_ids[0];

        $sql_retrieve = "SELECT meta_value
                        FROM {$this->meta_table}
                        WHERE cart_id = {$cart_id}
                        AND meta_key = '{$field_name}';";
        $meta_value = $wpdb->get_var($sql_retrieve);

        if (is_null($meta_value)) {
            $meta_value = $fast_products->get_product_meta($product_id,
                                                           $field_name);
        }

        return $meta_value;
    }

    /**
     * The admin page for fast_carts.
     *
     * @uses fast_fields::$prefix
     * @uses fast_fields::add_field_check()
     * @uses fast_fields::output_field_table()
     * @uses fast_fields::output_field_panel()
     * @uses debugger::clear_alerts();
     * @uses debugger::clear_errors();
     * @uses debugger::show_alerts();
     * @uses debugger::show_errors();
     * @since 0.6
     * @access private
     */
    function admin_page() {
        require ('codes.php');

        global $debugger;

        $pre = $this->prefix;

        $save    = 'Save Changes' == $_POST["{$pre}save_changes"];
        $options = array('payment_plugin', 'debugging',
                         'payment_email', 'country_code',
                         'currency_code');

        foreach ($options as $option) {
            $option_name = $pre . $option;

            if ($save) {
                update_option($option_name, $_POST[$option_name]);
            }
            $$option = get_option($option_name);
        }

        $curdir         = dirname(__FILE__);
        $payment_dir    = "{$curdir}/" . PAYMENT_DIRECTORY;
        $debugging = $debugging ? 'checked="checked"' : '';

?>
<div class="wrap">
	<h2>Fast Carts Settings</h2>
    <form action="" method="post">
        <table class="form-table">
            <tr valign="top">
                <th scope="row">
                    Payment plugin:
                </th>
                <td>
                    <select name="<?php echo $pre; ?>payment_plugin">
<?php
            foreach (glob ($payment_dir . '*.php') as $pathname) {
                $filename = basename ($pathname, '.php');
                $selected = '';
                
                $selected = $payment_plugin == $filename
                            ? 'selected="selected"' : '';

                echo "<option value=\"{$filename}\"
                     {$selected}>{$filename}</option>";
            }
?>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th>
                    Debugging Mode
                </th>
                <td>
                    <input type="checkbox"
                           name="<?php echo $pre; ?>debugging"
                           <?php echo $debugging; ?>/>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    email: <em>for seller account</em>
                </th>
                <td>
                    <input type="text" size="27"
                           name="<?php echo $pre; ?>payment_email"
                           value="<?php echo $payment_email; ?>" />
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    Country: <em>of the seller</em>
                </th>
                <td>
                    <select name="<?php echo $pre; ?>country_code">
<?php
            foreach ($country_codes as $code => $country) {
                $selected = '';
                
                $selected = $country_code == $code
                            ? 'selected="selected"' : '';

                echo "<option value=\"{$code}\"
                     {$selected}>{$country}</option>";
            }
?>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">
                    Currency: <em>of the seller</em>
                </th>
                <td>
                    <select name="<?php echo $pre; ?>currency_code">
<?php
            foreach ($currency_codes as $currency => $code) {
                $selected = '';
                
                $selected = $currency_code == $code
                            ? 'selected="selected"' : '';

                echo "<option value=\"{$code}\"
                     {$selected}>{$currency}</option>";
            }
?>
                    </select>
                </td>
            </tr>
        </table>
    <p class="submit">
        <input type='submit' class="button-primary"
                             name="<?php echo $pre; ?>save_changes"
                             value="Save Changes" />
    </p>
</form>
<?php

    }

    function is_debugging() {
        $is_debugging = get_option("{$this->prefix}debugging");
        return (bool) $is_debugging;
    }

    function get_product_price($product_id) {
        $price = $this->get_cart_meta($product_id, 'Sale Price');
        return $price;
    }


    function get_payment_info() {
        global $fast_products;

        $pre     = $this->prefix;
        $user_id = $this->user_id;
        $payment = array();

        $payment['admin_email']   = get_option("{$pre}payment_email");
        $payment['country_code']  = get_option("{$pre}country_code");
        $payment['currency_code'] = get_option("{$pre}currency_code");

        $user_data = array('email', 'first_name', 'last_name',
                           'country', 'address_1', 'address_2',
                           'city', 'state', 'zip_code');

        $billing_prefix = $pre . "billing_";
        foreach ($user_data as $data) {
            $value = get_user_meta($user_id, $billing_prefix . $data,
                                   true);
            if ($value) {
                $payment[$data] = $value;
            }
        }

        $products     = $this->get_users_cart($user_id);
        $num_products = count($products);
        for ($index = 0; $index < $num_products; $index++) {
            $product_id   = $products[$index]['product_id'];

            $name  = $fast_products->get_product_name($product_id);
            $price = $this->get_product_price($product_id);

            $products[$index]['name'] = $name;
            $products[$index]['price'] = $price;
        }

        $payment['products'] = $products;

        return $payment;
    }

    /**
     * Outputs all completed sales, sorted by the most recent, on an
     * admin page.
     *
     * @since 0.6
     * @access private
     */
    function sales_page() {
?>
<div class="wrap">
	<h2>Fast Cart Sales</h2>
    <div>
        <div>
<?php

        $sales = $this->get_sales();
        $current_sale = 0;
        foreach ($sales as $sale) {
            $sale_id = $sale['cart_id'];
            if ($current_sale != $sale_id) {
                $current_sale = $sale_id;

                global $wpdb;
                $sql_get_user_id = "SELECT user_id
                                   FROM {$this->sales_table}
                                   WHERE cart_id = {$sale_id};";
                $user_id = $wpdb->get_var($sql_get_user_id);

                echo '</div><br /><br /><div class="break">';
                echo "<h3>Sale {$current_sale}: {$date}</h3>";

                $pre = $this->prefix;
                echo '<strong>Shipping Information:</strong><br />';
                $fields = array('First Name', 'Last Name', 'Country',
                                'Address 1', 'Address 2', 'City',
                                'State', 'ZIP Code', 'Telephone',
                                'Mobile', 'Email');
                foreach ($fields as $field) {
                    $field_format = str_replace (' ', '_',
                                                 strtolower ($field));
                    $ship_name = $pre . "shipping_" . $field_format;

                    $value = get_user_meta($user_id, $ship_name, true);
                    echo "<span class=\"label\">{$field}:</span> ";
                    echo "'{$value}'<br />";
                }
                echo '<br /><strong>Billing Infomation:
                     </strong><br />';
                $fields = array('First Name', 'Last Name', 'Country',
                                'Address 1', 'Address 2', 'City',
                                'State', 'ZIP Code');
                foreach ($fields as $field) {
                    $field_format = str_replace (' ', '_',
                                                 strtolower ($field));
                    $bill_name = $pre . "billing_" . $field_format;

                    $value = get_user_meta($user_id, $bill_name, true);
                    echo "<span class=\"label\">{$field}:</span> ";
                    echo "'{$value}'<br />";
                }
                echo '<br /><strong>Product Information:</strong><br />';
            }
            echo "<span class=\"label\">{$sale['meta_key']}:</span> ";
            echo "'{$sale['meta_value']}'<br />";
        }

?>
            <br /><br />
        </div>
    </div>
</div>
<?php
    }

    function get_sales() {
        global $wpdb;

        $sql_retrieve = "SELECT cart_id
                        FROM {$this->sales_table};";
        $sale_ids = $wpdb->get_col($sql_retrieve);

        $cart_ids  = '(\'';
        $cart_ids .= implode('\',\'', $sale_ids);
        $cart_ids .= '\')';

        $sql_retrieve = "SELECT *
                        FROM {$this->meta_table}
                        WHERE cart_id IN {$cart_ids};";
        $sales = $wpdb->get_results($sql_retrieve, ARRAY_A);

        return $sales;
    }

    function process_sale() {
        global $wpdb;

        $sql_retrieve = "SELECT *
                        FROM {$this->table}
                        WHERE user_id = '{$this->user_id}';";
        $carts = $wpdb->get_results($sql_retrieve, ARRAY_A);

        foreach ($carts as $cart) {
            $sql_insert = "INSERT INTO {$this->sales_table} VALUES
                          (
                              {$cart['cart_id']},
                              '{$cart['user_id']}',
                              {$cart['product_id']},
                              {$cart['amount']},
                              CURDATE()
                          );";
            $wpdb->query($sql_insert);
        }

        $sql_delete = "DELETE FROM {$this->table}
                      WHERE user_id = '{$this->user_id}';";
        $wpdb->query($sql_delete);
    }

    /**
     * @uses fast_carts::$is_logged_in
     * @since 1.0.1
     * @access public
     */
    function is_logged_in() {
        $is_logged_in = $this->is_logged_in;

        return $is_logged_in;
    }
}

/**
 * The fast_fields object.
 *
 * @since 0.1
 */
global $fast_carts;
$fast_carts = new fast_carts();

?>
