<?php
/**
 * Plugin Name: VAT Assist for WooCommerce
 * Plugin URI: https://github.com/wpcorner/vat-assist-for-woocommerce
 * Description: WooCommerce VAT checker and validator.
 * Version: 1.0.9
 * Author: Patrick Lumumba
 * Author URI: https://wpcorner.co/author/patrick-l/
 * WC requires at least: 4.0
 * WC tested up to: 9.1.2
 * License: GNU General Public License v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */



/**
 * Enqueue main JavaScript to automatically check VAT on checkout page
 */
function wcva_enqueue_scripts() {
    wp_enqueue_script('wcva-main', plugins_url('/assets/init.js', __FILE__), [], '1.0.7', true);
    wp_localize_script('wcva-main', 'wcva_ajax_var', [
        'ajaxurl' => admin_url('admin-ajax.php')
    ]);
}

add_action('wp_enqueue_scripts', 'wcva_enqueue_scripts');



/**
 * Add new VAT field to checkout screen
 * 
 * Adds a new VAT text field to the checkout screen
 * 
 * @param  array $fields Intial fields
 * @return array $fields
 */
function wcva_override_checkout_fields($fields) {
    $class = ['form-row-wide'];

    if ((string) get_option('wcva_vatcheck') !== '') {
        $class[] = 'require-vatcheck-validation';
    }

    $fields['billing']['wcva_vat'] = [
        'label' => __('VAT', 'woocommerce'),
        'placeholder' => _x('VAT', 'placeholder', 'woocommerce'),
        'required' => true,
        'class' => $class,
        'clear' => true
    ];

    return $fields;
}

add_filter('woocommerce_checkout_fields' , 'wcva_override_checkout_fields');



/**
 * Add new VAT section to order summary screen
 * 
 * Adds a new VAT section to the order summary screen
 * 
 * @param  object $order
 * @return string
 */
function wcva_custom_checkout_field_order_meta_keys($order) {
    echo '<p><strong>VAT:</strong>' . $order->order_custom_fields['_wcva_vat'][0] . '</p>';
}

add_action('woocommerce_admin_order_data_after_billing_address',  'wcva_custom_checkout_field_order_meta_keys');



/**
 * Validate VAT number
 * 
 * Helper AJAX function for connecting to VATCheck.eu and requesting VAT validation
 * 
 * @return string
 */
function wcva_vat_validate() {
    $wcvaVatcheck = get_option('wcva_vatcheck');

    $vat = sanitize_text_field($_POST['vat']);
    $vat = preg_replace('/\s+/', '', $vat);

    $response = wp_remote_post('https://api.vatcheck.eu/v1/vies.essentials.verify', [
        'body' => [
            'vatnumber' => $vat,
            'language' => 'en'
        ],
        'headers' => [
            'Authorization' => 'Bearer ' . $wcvaVatcheck,
        ],
    ]);

    echo $response['body'];

    wp_die();
}
add_action('wp_ajax_wcva_vat_validate', 'wcva_vat_validate');
add_action('wp_ajax_nopriv_wcva_vat_validate', 'wcva_vat_validate');



/**
 * Override order button HTML to disable it
 * 
 * Overrides the button HTML (via filter) in order to disable it on page load
 * 
 * @param  string $button_html
 * @return string
 */
function wcva_custom_button_html($button_html) {
    $button_html = '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="Place order" data-value="Place order" disabled="">Place order</button>';

    return $button_html;
}

if ((int) get_option('wcva_require_vat') === 1) {
    add_filter('woocommerce_order_button_html', 'wcva_custom_button_html');
}



/**
 * Add settings page
 */
function wcva_menu_links() {
    add_submenu_page('woocommerce', 'VAT Assist', 'VAT Assist', 'manage_options', 'wcva', 'wcva_build_admin_page'); 
}

add_action('admin_menu', 'wcva_menu_links');

function wcva_build_admin_page() {
    $tab = (filter_has_var(INPUT_GET, 'tab')) ? filter_input(INPUT_GET, 'tab') : 'dashboard';
    $section = 'admin.php?page=wcva&amp;tab='; ?>

    <div class="wrap">
        <h1>WooCommerce VAT Assist</h1>

        <h2 class="nav-tab-wrapper">
            <a href="<?php echo $section; ?>dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>">Dashboard</a>
            <a href="<?php echo $section; ?>help" class="nav-tab <?php echo $tab === 'help' ? 'nav-tab-active' : ''; ?>">Help</a>
        </h2>

        <?php if ($tab === 'dashboard') { ?>
            <h2>General Settings</h2>
            <p>Configure your VAT check or leave all options blank for no validation.</p>

            <?php
            if (isset($_POST['save_licence_settings'])) {
                $wcvaRequireVat = ((int) $_POST['wcva_require_vat'] !== 0) ? ((int) $_POST['wcva_require_vat']) : 0;
                $wcvaVatcheck = (sanitize_text_field($_POST['wcva_vatcheck']) !== '') ? sanitize_text_field($_POST['wcva_vatcheck']) : '';

                update_option('wcva_require_vat', $wcvaRequireVat);
                update_option('wcva_vatcheck', $wcvaVatcheck);

                echo '<div class="updated notice is-dismissible"><p>Changes saved successfully!</p></div>';
            }
            ?>

            <form method="post">
                <table class="form-table">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="wcva_require_vat">Require VAT<br><small>(Optional)</small></label></th>
                            <td>
                                <p>
                                    <input type="checkbox" id="wcva_require_vat" name="wcva_require_vat" value="1" <?php echo ((int) get_option('wcva_require_vat') === 1) ? 'checked' : ''; ?>>
                                    <label for="wcva_require_vat">Require VAT field to be present (valid or not)</label>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="wcva_vatcheck">Vatcheck.eu API Key<br><small>(Optional)</small></label></th>
                            <td>
                                <input type="text" name="wcva_vatcheck" value="<?php echo get_option('wcva_vatcheck'); ?>" class="regular-text">
                                <br><small>Validate VAT numbers. Get a Vatcheck.eu API key <a href="https://developer.vatcheck.eu/?version=latest" rel="external noopener">here</a>.</small>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><input type="submit" name="save_licence_settings" class="button button-primary" value="Save Changes"></th>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
            </form>

            <hr>
            <p>&copy;<?php echo date('Y'); ?> <a href="https://wpcorner.co/" rel="external"><strong>WPCorner.co</strong></a> &middot; <small>Code wrangling since 2005</small></p>
        <?php } else if ($tab === 'help') { ?>
            <h2>Help</h2>

            <p>This simple feature plugin adds a new VAT field to the checkout section and, optionally, checks for any entered value.</p>
            <p>It also, optionally, integrates with a third-party service, VATCheck.eu, for VAT number validation.</p>

            <hr>
            <p>&copy;<?php echo date('Y'); ?> <a href="https://wpcorner.co/" rel="external"><strong>WPCorner.co</strong></a> &middot; Code wrangling since 2005</p>
        <?php } ?>
    </div>
	<?php
}
