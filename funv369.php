<?php
/**
 * Enqueue script and styles for child theme
 */
function woodmart_child_enqueue_styles() {
    wp_enqueue_style( 'child-style', get_stylesheet_directory_uri() . '/style.css', array( 'woodmart-style' ), woodmart_get_theme_info( 'Version' ) );
}
add_action( 'wp_enqueue_scripts', 'woodmart_child_enqueue_styles', 10010 );

// Function to check national code validity
function check_national_code($code) {
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
    for ($i = 0; $i < 10; $i++) {
        if (preg_match('/^' . $i . '{10}$/', $code)) {
            return false;
        }
    }
    for ($i = 0, $sum = 0; $i < 9; $i++) {
        $sum += ((10 - $i) * intval(substr($code, $i, 1)));
    }
    $ret = $sum % 11;
    $parity = intval(substr($code, 9, 1));
    return ($ret < 2 && $ret == $parity) || ($ret >= 2 && $ret == 11 - $parity);
}

// Verify national code and phone number with Ehraz API
function verify_national_code_and_phone($national_code, $phone_number) {
    $url = 'https://ehraz.io/api/v1/match/national-with-mobile'; // API endpoint
    $token = 'Token c709f9e508633b86984892caa7f4e2e613bd0ead'; // Your token

    $data = array(
        'nationalCode' => $national_code,
        'mobileNumber' => $phone_number,
    );

    $response = wp_remote_post($url, array(
        'headers' => array(
            'Authorization' => $token,
            'Content-Type' => 'application/json',
        ),
        'body' => json_encode($data),
    ));

    if (is_wp_error($response)) {
        return 'خطا در ارتباط با سرور'; // Error connecting to the server
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);

    if (isset($body['matched'])) {
        return $body['matched'] ? 'کد ملی و شماره تلفن با هم مطابقت دارند.' : 'کد ملی و شماره تلفن با هم مطابقت ندارند.'; // Match or not match
    } else if (isset($body['code'])) {
        switch ($body['code']) {
            case 'nationalCode.not_valid':
                return 'کد ملی وارد شده معتبر نیست.'; // Invalid national code
            case 'mobileNumber.not_valid':
                return 'شماره تلفن وارد شده معتبر نیست.'; // Invalid phone number
            case 'query_parameters.too_many':
                return 'تعداد پارامترهای ورودی بیش از حد مجاز است.'; // Too many input parameters
            case 'providers.not_available':
                return 'سرویس دهنده در دسترس نیست.'; // Provider not available
            default:
                return 'خطا در پردازش درخواست'; // General error processing request
        }
    } else {
        return 'خطا در پردازش درخواست'; // Error processing the request
    }
}

// Hook to verify national code on checkout
function verify_national_code_on_checkout() {
    // Read the national code from WooCommerce field
    if (isset($_POST['puiw_billing_uin']) && isset($_POST['billing_phone'])) {
        $national_code = sanitize_text_field($_POST['puiw_billing_uin']);
        $phone_number = sanitize_text_field($_POST['billing_phone']);

        // Step 1: Validate the national code format
        if (!check_national_code($national_code)) {
            wc_add_notice(__('کد ملی وارد شده معتبر نیست.'), 'error');
            return; // Exit to prevent checkout
        }

        // Step 2: Verify national code and phone number using the Ehraz API
        $verification_message = verify_national_code_and_phone($national_code, $phone_number);

        // Step 3: If the national code and phone number do not match, prevent the order from being processed
        if (strpos($verification_message, 'با هم مطابقت ندارند') !== false) {
            wc_add_notice($verification_message, 'error');
            return; // Exit to prevent checkout
        }
        
        // If matched, add a notice but allow checkout to proceed
        wc_add_notice($verification_message, 'success');
    }
}
add_action('woocommerce_checkout_process', 'verify_national_code_on_checkout');


//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//

// Save the national code in the order
function save_custom_national_code_field($order_id) {
    if (!empty($_POST['puiw_billing_uin'])) {
        update_post_meta($order_id, 'puiw_billing_uin', sanitize_text_field($_POST['puiw_billing_uin']));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'save_custom_national_code_field');

// Display the national code in the admin orders section
function checkout_field_display_admin_order_meta($order) {
    echo '<p><strong>' . __('ورود مجدد کد ملی') . ':</strong> ' . get_post_meta($order->get_id(), 'puiw_billing_uin', true) . '</p>';
}
add_action('woocommerce_admin_order_data_after_billing_address', 'checkout_field_display_admin_order_meta', 10, 1);

// Add national code field to My Account page
function add_national_code_to_my_account() {
    $user_id = get_current_user_id();
    $national_code = get_user_meta($user_id, 'puiw_billing_uin', true);
    ?>
    <h4><?php _e('کد ملی', 'your-theme-domain'); ?></h4>
    <p>
        <input type="text" class="input-text" name="puiw_billing_uin" id="puiw_billing_uin" value="<?php echo esc_attr($national_code); ?>" />
    </p>
    <?php
}
add_action('woocommerce_edit_account_form', 'add_national_code_to_my_account');

// Save the national code in My Account page
function save_national_code_in_my_account($user_id) {
    if (isset($_POST['puiw_billing_uin'])) {
        update_user_meta($user_id, 'puiw_billing_uin', sanitize_text_field($_POST['puiw_billing_uin']));
    }
}
add_action('woocommerce_save_account_details', 'save_national_code_in_my_account');

// Display the national code in the admin user profile
function display_national_code_in_admin_user_profile($user) {
    $national_code = get_user_meta($user->ID, 'puiw_billing_uin', true);
    ?>
    <h4><?php _e('کد ملی', 'your-theme-domain'); ?></h4>
    <table class="form-table">
        <tr>
            <th><label for="puiw_billing_uin"><?php _e('کد ملی', 'your-theme-domain'); ?></label></th>
            <td>
                <input type="text" name="puiw_billing_uin" id="puiw_billing_uin" value="<?php echo esc_attr($national_code); ?>" class="regular-text" disabled />
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'display_national_code_in_admin_user_profile');
add_action('edit_user_profile', 'display_national_code_in_admin_user_profile');

















