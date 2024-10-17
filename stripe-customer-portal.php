<?php
/**
 * Plugin Name: Login for Stripe Customer Portal
 * Description: Allow merchants to connect Stripe and provide a customer login endpoint for the Stripe Customer Portal.
 * Version: 1.0
 * Author: Gaucho Plugins
 * License: GPLv3
 * Text Domain: login-stripe-customer-portal
 */

namespace LoginStripeCustomerPortal;

// Ensure Stripe SDK is included
require_once plugin_dir_path(__FILE__) . 'lib/stripe-php/init.php';  // Adjust path to where you placed the SDK 

class Plugin {
    public function __construct() {
        // Register settings and menus
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add endpoint and handle customer portal
        add_action('init', [$this, 'add_customer_portal_endpoint']);
        add_action('template_redirect', [$this, 'handle_customer_portal']);
    }

    /**
     * Add the settings page for API key, redirect URL, and endpoint slug.
     */
    public function add_settings_page() {
        add_menu_page(
            __('Embed Stripe Customer Portal', 'login-stripe-customer-portal'),
            __('Stripe Portal', 'login-stripe-customer-portal'),
            'manage_options',
            'stripe-portal-settings',
            [$this, 'render_settings_page'],
            'dashicons-businessperson',  // Custom icon for the menu item
            100  // This sets the position of the menu item at the bottom
        );
    }

    /**
     * Register the API key, redirect URL, and endpoint slug settings.
     */
    public function register_settings() {
        register_setting('stripe_portal_settings_group', 'stripe_api_key', [
            'sanitize_callback' => [$this, 'sanitize_secret_key'],
        ]);
        register_setting('stripe_portal_settings_group', 'stripe_redirect_url', [
            'sanitize_callback' => [$this, 'sanitize_redirect_url'],
        ]);
        register_setting('stripe_portal_settings_group', 'stripe_endpoint_slug', [
            'sanitize_callback' => 'sanitize_title',
        ]);
    }

    /**
     * Sanitize the secret key before saving.
     */
    public function sanitize_secret_key($input) {
        return sanitize_text_field($input);
    }

    /**
     * Sanitize the redirect URL before saving.
     */
    public function sanitize_redirect_url($input) {
        if (!empty($input)) {
            return esc_url_raw($input);
        }
        return '';
    }

    /**
     * Render the settings page for entering the Stripe API key, redirect URL, and endpoint slug.
     */
    public function render_settings_page() {
        $slug = get_option('stripe_endpoint_slug', 'customer-portal');
        $customer_portal_url = home_url('/' . $slug . '/');

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Embed Stripe Customer Portal Settings', 'login-stripe-customer-portal'); ?></h1>
            <p><?php esc_html_e('Provide your Stripe Secret Key, Redirect URL, and Customer Portal Endpoint Slug below. After saving, the Secret Key will be hidden for security.', 'login-stripe-customer-portal'); ?></p>
            <form method="post" action="options.php">
                <?php 
                settings_fields('stripe_portal_settings_group');
                do_settings_sections('stripe_portal_settings_group');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Stripe Secret Key', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <?php
                            $secret_key = get_option('stripe_api_key');
                            $masked_key = $secret_key ? str_repeat('●', strlen($secret_key)) : '';
                            ?>
                            <input type="password" name="stripe_api_key" value="<?php echo esc_attr($masked_key); ?>" />
                            <p class="description"><?php esc_html_e('Your Stripe Secret Key. After saving, it will be hidden.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect URL', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <input type="url" name="stripe_redirect_url" value="<?php echo esc_url(get_option('stripe_redirect_url', $customer_portal_url)); ?>" />
                            <p class="description"><?php esc_html_e('The URL to redirect the user back to after they exit the Stripe portal. Default is the customer portal page.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Customer Portal Slug', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <input type="text" name="stripe_endpoint_slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Customize the slug for the customer portal page. Leave empty to disable the page.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <?php if (!empty($slug)) : ?>
                <h2><?php esc_html_e('Customer Portal URL', 'login-stripe-customer-portal'); ?></h2>
                <p><?php esc_html_e('Your customer portal is available at:', 'login-stripe-customer-portal'); ?></p>
                <p><a href="<?php echo esc_url($customer_portal_url); ?>" target="_blank">
                    <?php echo esc_url($customer_portal_url); ?>
                </a></p>
            <?php else : ?>
                <p><strong><?php esc_html_e('Customer Portal is disabled. Please set a slug to enable it.', 'login-stripe-customer-portal'); ?></strong></p>
            <?php endif; ?>

            <h2><?php esc_html_e('Permalink Settings', 'login-stripe-customer-portal'); ?></h2>
            <p><?php esc_html_e('Make sure to resave your permalinks after making changes to the customer portal slug by going to:', 'login-stripe-customer-portal'); ?>
            <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>" target="_blank"><?php esc_html_e('Permalinks Settings', 'login-stripe-customer-portal'); ?></a>.
            </p>
        </div>
        <?php
    }

    /**
     * Add the custom endpoint for the customer portal login page.
     */
    public function add_customer_portal_endpoint() {
        $slug = get_option('stripe_endpoint_slug', 'customer-portal');
        if (!empty($slug)) {
            add_rewrite_rule($slug . '/?$', 'index.php?stripe_customer_portal=1', 'top');
            add_rewrite_tag('%stripe_customer_portal%', '([^&]+)');
        }
    }

    /**
     * Handle the customer portal by processing the email form and redirecting to the Stripe Customer Portal.
     */
    public function handle_customer_portal() {
        $slug = get_option('stripe_endpoint_slug', 'customer-portal');
        if (empty($slug)) {
            return; // Disable the customer portal page if the slug is empty
        }
    
        global $wp_query;
    
        if (isset($wp_query->query_vars['stripe_customer_portal'])) {
    
            // Check if request method is POST and verify nonce
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Verify nonce
                if (!isset($_POST['stripe_portal_nonce']) || !wp_verify_nonce($_POST['stripe_portal_nonce'], 'stripe_portal_login_action')) {
                    wp_die(esc_html__('Security check failed', 'login-stripe-customer-portal'));
                }
    
                // Process form data after nonce verification
                if (isset($_POST['email'])) {
                    $email = sanitize_email(wp_unslash($_POST['email']));
    
                    if (is_email($email)) {
                        $this->process_customer_portal_login($email);
                    } else {
                        wp_die(esc_html__('Invalid email address.', 'login-stripe-customer-portal'));
                    }
                }
            } else {
                $this->render_email_form();
            }
    
            exit;
        }
    }    

    /**
     * Display the email form for customers to enter their email.
     */
    public function render_email_form() {
        ?>
        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4;">
            <form method="post" action="" style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); max-width: 400px; width: 100%; text-align: center;">
                <?php wp_nonce_field('stripe_portal_login_action', 'stripe_portal_nonce'); ?>
                <label for="email" style="display: block; margin-bottom: 10px; font-weight: bold;"><?php esc_html_e('Enter your email address:', 'login-stripe-customer-portal'); ?></label>
                <input type="email" name="email" id="email" required style="width: 100%; padding: 10px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ccc;" />
                <input type="submit" value="<?php esc_html_e('Continue to Stripe Portal', 'login-stripe-customer-portal'); ?>" style="background-color: #0073aa; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; font-size: 16px;" />
            </form>
        </div>
        <?php
    }    

    /**
     * Process the form submission and redirect to the Stripe Customer Portal.
     *
     * @param string $email The email address submitted by the customer.
     */
    public function process_customer_portal_login($email) {
        \Stripe\Stripe::setApiKey(get_option('stripe_api_key'));

        try {
            // Search for the customer by email
            $customers = \Stripe\Customer::all([
                'email' => $email,
                'limit' => 1,
            ]);

            if (count($customers->data) > 0) {
                $customer_id = $customers->data[0]->id;
            } else {
                $customer = \Stripe\Customer::create([
                    'email' => $email,
                ]);
                $customer_id = $customer->id;
            }

            // Redirect to Stripe Customer Portal
            $this->redirect_to_stripe_customer_portal($customer_id);

        } catch (Exception $e) {
            wp_die(esc_html__('Error: ', 'login-stripe-customer-portal') . esc_html($e->getMessage()));
        }
    }

    /**
     * Redirect to Stripe Customer Portal with the customer ID.
     *
     * @param string $customer_id The Stripe customer ID.
     */
    public function redirect_to_stripe_customer_portal($customer_id) {
        \Stripe\Stripe::setApiKey(get_option('stripe_api_key'));

        try {
            $session = \Stripe\BillingPortal\Session::create([
                'customer' => $customer_id,
                'return_url' => esc_url(get_option('stripe_redirect_url', home_url('/' . get_option('stripe_endpoint_slug', 'customer-portal')))),
            ]);

            wp_redirect(esc_url_raw($session->url));
            exit;
        } catch (Exception $e) {
            wp_die(esc_html__('Error redirecting to Stripe Customer Portal: ', 'login-stripe-customer-portal') . esc_html($e->getMessage()));
        }
    }
}

// Initialize the plugin
new Plugin();
