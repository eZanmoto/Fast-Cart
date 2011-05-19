<?php
/*
Plugin Name: Fast Cart
Plugin URI: http://ezanmoto.wordpress.com/
Description: A minimal shopping cart plugin for Wordpress. See the Fast Cart settings page for details on how to use it.
Version: 1.0.1
Author: Se&aacute;n Kelleher
Author URI: http://ezanmoto.wordpress.com/
License: GPL2 and higher
*/

require ('fast_carts.php');

/**
 * Wordpress Shopping Cart Plugin
 *
 * Contains functions for starting and running a shopping cart in
 * Wordpress.
 *
 * Original code from {@link http://ezanmoto.wordpress.com Seán M.
 * Kelleher (ezanmoto@gmail.com)}
 *
 * @package eZanmoto
 * @subpackage FastCart
 * @since 0.1
 */
class fast_cart {

    /**
     * Prepended to various tags that fast_cart uses.
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
     * Sets up a session (required for the fast cart plugin).
     *
     * @uses debugger::fatal()
     * @since 0.1
     * @access public
     */
    function __construct() {
        global $debugger;

        $this->prefix = 'fast_cart_';

        // Turn on sessions
        if (session_id() == '') {

            if (!session_start()) {
                $debugger->fatal('Failed to start session');
            }
        }

        // Adds the admin pages
        add_action('admin_menu', array(&$this, 'admin_pages'));
        add_action('loop_start', array(&$debugger, 'show_alerts'));
    }

    /**
     * Installs everything needed for fast_cart to run when it is
     * activated for the first time.
     *
     * @uses fast_fields::install()
     * @since 0.1
     * @access private
     */
    function install() {
        global $fast_carts;
        $fast_carts->install();
    }

    function admin_page() {
?>
<div class="wrap">
    <h2>Fast Cart Settings</h2>
    <div>
        <h3>How to set up the Fast Cart plugin</h3>
        <p>
            The Fast Cart plugin is already configured for you to run
            an efficient shopping cart-based ecommerce system, all you
            have to do is enter the email address for your paypal
            seller account, turn off payment debugging, and enter your
            country and currency on the <em>Fast Carts</em> page.
        </p>
        <h3>How to add products</h3>
        <p>
            To add a product, simply go to the <em>Edit Post</em> or
            <em>Edit Page</em> page for the post or page you want to
            turn into a product and check the box indicating <em>This
            post is a product</em>. You should also edit the key
            fields for <em>Regular Price</em> and <em>Sale Price</em>
            while you're at it.
        </p>
        <h3>How to add product information</h3>
        <p>
            You add meta information to be displayed for products on
            either the Fast Fields page or the <em>Edit Post</em> page
            for the product. Key fields are displayed for all products
            and can only be output. Global fields can be input or
            output and are applied to all posts. By specifying a field
            name and value on a product's own <em>Edit Post</em> page,
            you are creating a field that is only used by that
            product. Product fields can be input or output.
        </p>
        <p>
            And that's all there is to start selling products with the
            Fast Cart plugin.
        </p>
        <h3>Warning:</h3>
        <p>
            Due to the nature of handling payment transactions with a
            third party, it cannot be guaranteed that a customer has
            paid for a good or service, even if that good or service
            is on the <em>Fast Sales</em> page. With this in mind,
            please ensure you have recieved payment for a good or
            service from the customer using the third party before
            completing the transaction.
        </p>
    </div>
</div>
<?php
    }

    /**
     * Sets up the admin pages for fast cart and the classes it
     * depends on.
     *
     * @uses fast_carts::admin_page()
     * @uses fast_carts::sales_page()
     * @uses fast_fields::admin_page()
     * @since 0.1
     * @access private
     */
    function admin_pages() {
        global $fast_carts, $fast_fields;

        add_menu_page('Fast Cart Settings', 'Fast Cart',
                      'manage_options', 'fast_cart',
                      array(&$this, 'admin_page'));

        add_submenu_page('fast_cart', 'Fast Cart Settings',
                         'Fast Carts', 'manage_options',
                         'fast_carts',
                         array(&$fast_carts, 'admin_page'));

        add_submenu_page('fast_cart', 'Fast Cart Settings',
                         'Fast Fields', 'manage_options',
                         'fast_fields',
                         array(&$fast_fields, 'admin_page'));

        add_submenu_page('fast_cart', 'Fast Cart Settings',
                         'Fast Sales', 'manage_options',
                         'fast_sales',
                         array(&$fast_carts, 'sales_page'));
    }
}

/**
 * An instance of the fast_cart class.
 *
 * @since 0.1
 * @access public
 */
global $fast_cart;
$fast_cart = new fast_cart();

// Calls fast_cart::install when the plugin is activated
register_activation_hook(__FILE__, array(&$fast_cart, 'install'));

// Shows any alerts or errors
add_action('admin_notices', array(&$debugger, 'show_errors'));
add_action('admin_notices', array(&$debugger, 'show_alerts'));


?>
