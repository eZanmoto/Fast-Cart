<?php

{

    global $fast_carts;

    $pre = $fast_carts->prefix;

    $payment_info  = $fast_carts->get_payment_info();
    $hidden_fields = array();
    $debugging     = $fast_carts->is_debugging();
    $checkout_id   = get_option("{$pre}checkout_id");
    $checkout_url  = get_permalink ($checkout_id);
    $payment_info  = $fast_carts->get_payment_info();

    $gateway_url   = $debugging
                   ? 'https://www.sandbox.paypal.com/cgi-bin/webscr'
                   : 'https://www.paypal.com/cgi-bin/webscr';

    /*
     * If $checkout_url contains '?', variables should be appended
     * using '&', otherwise using '?'
     */
    $join_char     = preg_match('/\?/', $checkout_url) ? '&' : '?';
    $checkout_url .= "{$join_char}{$pre}transaction=";

    $hidden_fields['business']      = $payment_info['admin_email'];
    $hidden_fields['country']       = $payment_info['country_code'];
    $hidden_fields['currency_code'] = $payment_info['currency_code'];

    $hidden_fields['return']        = $checkout_url . 'success';
    $hidden_fields['cancel_return'] = $checkout_url . 'failure';
    $hidden_fields['notify_url']    = $checkout_url . 'ipn';

    $hidden_fields['upload'] = 1;
    $hidden_fields['rm']     = 2; // return method ="post"
    $hidden_fields['cmd']    = '_cart'; // or else '_xclick'

    // Replaced duplicate entries with empty strings to save on typing
    $user_data = array('email' => '', 'first_name' => '',
                       'last_name' => '', 'address1' => 'address_1',
                       'address2' => 'address_2', 'city' => '',
                       'state' => '', 'zip' => 'zip_code');

    // Populate $hidden_fields with user data
    foreach ($user_data as $paypal_name => $cart_name) {
        if ('' == $cart_name) {
            $cart_name = $paypal_name;
        }

        $value = $payment_info[$cart_name];

        if (!is_null($value)) {
            $hidden_fields[$paypal_name] = $value;
        }
    }

    // Populate $hidden_fields with product data
    $product_data = $payment_info['products'];
    $num_products = count($product_data);

    for ($i = 0; $i < $num_products; $i++) {
        $product = $product_data[$i];
        $item    = $i + 1;

        $hidden_fields["item_name_{$item}"]   = $product['name'];
        $hidden_fields["amount_{$item}"]      = $product['price'];
        $hidden_fields["quantity_{$item}"]    = $product['amount'];
        $hidden_fields["item_number_{$item}"] = $item;
    }

?>
<form action="<?php echo $gateway_url; ?>" method="post">
<?php

    if ($debugging) {
        echo '<p><em>Debugging Mode</em> is turned on for <em>Fast
             Carts</em>. <em>Debugging Mode</em> can be turned off on
             the <em>Fast Carts</em> settings page.</p>';
    }

    // If debugging is on, make the hidden fields visible
    $type     = $debugging ? 'text'   : 'hidden';
    $end      = $debugging ? '<br />' : '';
    foreach ($hidden_fields as $name => $value) {

        // For CSS styling
        $start = $debugging
                 ? "<span class=\"label\">{$name}:</span> " : '';

        // Output hidden fields
        echo "{$start}<input type=\"{$type}\" name=\"{$name}\"
                             value=\"{$value}\" />{$end}";
    }

?>
    <input type="submit" name="paypal_submit" value="Pay Now" />
</form>

<?php

    if ($debugging) {
?>
<a href="<?php echo $hidden_fields['return']; ?>">Emulate Success</a>
<a href="<?php echo $hidden_fields['cancel_return']; ?>">
    Emulate Failure
</a>
<?php
    }

}

?>
