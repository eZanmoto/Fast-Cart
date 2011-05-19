<?php

{ // so the variables declared here aren't in the global scope

    global $wpdb, $fast_carts;
    $curdir  = dirname(__FILE__);
    $pre     = $fast_carts->prefix;
    $user_id = $fast_carts->user_id;

    $transaction = $_GET["{$pre}transaction"];
    if ('success' == $transaction) {
        $fast_carts->process_sale();
        echo "<h3 class=\"{$pre}success\">Your payment has been
             processed successfully.</h3>";
    } elseif ('failure' == $transaction) {
        echo "<h3 class=\"{$pre}failure\">Something went wrong with
             the transaction. Your payment has not been
             processed.</h3>";
    }

    // clean, local version of the superglobal
    $_post   = array();

    foreach ($_POST as $post_key => $post_value) {
        $_post[$post_key] = mysql_real_escape_string($post_value);
    }

?>
<div id="<?php echo $pre; ?>checkout">
    <div id="<?php echo $pre; ?>cart">
        <h3><a href="#">Shopping Cart</a></h3>
        <div>
<?php

    $fast_carts->output_shopping_cart();

?>
            <a href="/">Continue Shopping&gt;&gt;</a>
        </div>
    </div>
<?php

    $fields = array('First Name', 'Last Name', 'Country', 'Address 1',
                    'Address 2', 'City', 'State', 'ZIP Code',
                    'Telephone', 'Mobile', 'Email');

?>
    <div id="<?php echo $pre; ?>shipping">
        <h3><a href="#">Shipping Information</a></h3>
        <div>
            <form action="" method="post">
<?php

    // Check if shipping details are the same as billing details
    $sib_label    = "{$pre}shipping_is_billing";
    $ship_is_bill = get_user_meta($user_id, $sib_label, true);
    if ($_post["{$pre}shipping"]) {
        $ship_is_bill = isset($_post[$sib_label]);
        update_user_meta($user_id, $sib_label, $_post[$sib_label]);
    }
    $sib_checked  = $ship_is_bill ? 'checked="checked"' : '';

    /*
     * For each field, check if the field has been updated and output
     * the input area for that field.
     */
    foreach ($fields as $field) {
        // Format field name in a HTML-friendly way
        $field_format = str_replace (' ', '_', strtolower ($field));
        $field_name   = $pre . "shipping_" . $field_format;

        $old_value = get_user_meta ($user_id, $field_name, true);
        $new_value = $_post[$field_name];

        /*
         * So we use the old value if there is no new value, like if
         * we just visited the page and the $_POST value is not set.
         */
        $field_value = $old_value;

        if ($_post["{$pre}shipping"] && $new_value != $old_value) {
            update_user_meta ($user_id, $field_name, $new_value);
            $field_value = $new_value;
            if ($ship_is_bill) {
                $field_name = $pre . "billing_" . $field_format;
                update_user_meta ($user_id, $field_name, $new_value);
            }
        }

?>
<div>
    <label for="<?php echo $field_name; ?>"><?php echo $field; ?></label>
    <input type="text" id="<?php echo $field_name; ?>"
           name="<?php echo $field_name; ?>"
           value="<?php echo $field_value; ?>" />
</div>
<?php

    }

?>
                <p>
                    My billing address is the same as my shipping
                    address
                    <input type="checkbox"
                           name="<?php echo $sib_label; ?>"
                           <?php echo $sib_checked; ?> />
                </p>

                <input type="submit"
                       name="<?php echo $pre; ?>shipping"
                       value="Update Information" />
            </form>
        </div>
    </div>

    <div id="<?php echo $pre; ?>billing">
        <h3><a href="#">Billing Information</a></h3>
        <div>
            <form action="" method="post">
<?php

    $bill_fields = array ('First Name', 'Last Name', 'Country',
                          'Address 1', 'Address 2', 'City', 'State',
                          'ZIP Code', 'email');

    /*
     * For each field, check if the field has been updated and output
     * the input area for that field.
     */
    foreach ($bill_fields as $field) {
        // Format field name in a HTML-friendly way
        $field_name = str_replace (' ', '_', strtolower ($field));
        $field_name = $pre . "billing_" . $field_name;

        $old_value = get_user_meta ($user_id, $field_name, true);
        $new_value = $_post[$field_name];

        /*
         * So we use the old value if there is no new value, like if
         * we just visited the page and the $_POST value is not set.
         */
        $field_value = $old_value;
        
        if ($_post["{$pre}billing"] && $new_value != $field_value)
        {
            update_user_meta ($user_id, $field_name, $new_value);
            $field_value = $new_value;
        }

?>
<div>
    <label for="<?php echo $field_name; ?>">
        <?php echo $field; ?>
    </label>
    <input type="text" id="<?php echo $field_name; ?>"
           name="<?php echo $field_name; ?>"
           value="<?php echo $field_value; ?>" />
</div>
<?php

    }

?>
                <input type="submit" name="<?php echo $pre; ?>billing"
                       value="Update Information" />
            </form>
        </div>
    </div>

    <div id="<?php echo $pre; ?>payment">
        <h3><a href="#">Payment</a></h3>
        <div>
<?php

    if ($fast_carts->is_logged_in()) {
        $payment_plugin = get_option("{$pre}payment_plugin") . '.php';
        $payment_dir    = "{$curdir}/" . PAYMENT_DIRECTORY;
        require ($payment_dir . $payment_plugin);
    } else {
        echo '<p>You need to be logged in to make a payment.</p>';
    }

?>
        </div>
    </div>
</div>
<?php

} // End the outer block

?>
