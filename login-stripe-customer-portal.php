<?php
/**
 * Plugin Name: Login for Stripe Customer Portal
 * Description: Allow merchants to connect Stripe and provide a customer login endpoint for the Stripe Customer Portal.
 * Version: 1.0.1
 * Author: Gaucho Plugins
 * License: GPLv3
 * Text Domain: login-stripe-customer-portal
 */

namespace LSCP;

if ( ! function_exists( 'lscp_fs' ) ) {
    // Create a helper function for easy SDK access.
    function lscp_fs() {
        global $lscp_fs;

        if ( ! isset( $lscp_fs ) ) {
            // Include Freemius SDK.
            require_once dirname(__FILE__) . '/freemius/start.php';

            $lscp_fs = fs_dynamic_init( array(
                'id'                  => '16814',
                'slug'                => 'login-stripe-customer-portal',
                'type'                => 'plugin',
                'public_key'          => 'pk_816f55d4825ad20415edb31060db5',
                'is_premium'          => false,
                'has_addons'          => false,
                'has_paid_plans'      => false,
                'menu'                => array(
                    'slug'           => 'login-stripe-customer-portal',
                    'account'        => false,
                ),
            ) );
        }

        return $lscp_fs;
    }

    // Init Freemius.
    lscp_fs();
    // Signal that SDK was initiated.
    do_action( 'lscp_fs_loaded' );
}

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
     * Add the settings page for API key, redirect URL, endpoint slug, and allow validation setting.
     */
    public function add_settings_page() {
        add_menu_page(
            __('Embed Stripe Customer Portal', 'login-stripe-customer-portal'),
            __('Stripe Portal', 'login-stripe-customer-portal'),
            'manage_options',
            'login-stripe-customer-portal',
            [$this, 'render_settings_page'],
            'dashicons-businessperson',  // Custom icon for the menu item
            100  // This sets the position of the menu item at the bottom
        );
    }

    /**
     * Register the API key, redirect URL, endpoint slug, and validation settings.
     */
    public function register_settings() {
        register_setting('lscp_settings_group', 'lscp_stripe_api_key', [
            'sanitize_callback' => [$this, 'sanitize_secret_key'],
        ]);
        register_setting('lscp_settings_group', 'lscp_stripe_redirect_url', [
            'sanitize_callback' => [$this, 'sanitize_redirect_url'],
        ]);
        register_setting('lscp_settings_group', 'lscp_stripe_endpoint_slug', [
            'sanitize_callback' => 'sanitize_title',
        ]);
        register_setting('lscp_settings_group', 'lscp_stripe_validate_existing_customers', [
            'sanitize_callback' => [$this, 'sanitize_checkbox'],
        ]);
    }

    /**
     * Sanitize the secret key before saving.
     */
    public function sanitize_secret_key($input) {
        // Get the current saved API key
        $current_api_key = get_option('lscp_stripe_api_key');
    
        // If the input is masked (i.e., dots or empty), return the currently saved key
        if (empty($input) || strpos($input, '●') !== false) {
            return $current_api_key;
        }
    
        // If there's a new API key entered, save it
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
     * Sanitize checkbox values.
     */
    public function sanitize_checkbox($input) {
        return $input === '1' ? '1' : '0';
    }

    /**
     * Render the settings page for entering the Stripe API key, redirect URL, and endpoint slug.
     */
    public function render_settings_page() {
        $slug = get_option('lscp_stripe_endpoint_slug', 'customer-portal');
        $customer_portal_url = home_url('/' . $slug . '/');
        $validate_existing_customers = get_option('lscp_stripe_validate_existing_customers', '0');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Login for Stripe Customer Portal Settings', 'login-stripe-customer-portal'); ?></h1>
            <p><?php esc_html_e('Provide your Stripe Secret Key, Redirect URL, and Customer Portal Endpoint Slug below. After saving, the Secret Key will be hidden for security.', 'login-stripe-customer-portal'); ?></p>
            <form method="post" action="options.php">
                <?php 
                settings_fields('lscp_settings_group');
                do_settings_sections('lscp_settings_group');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Stripe Secret Key', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <?php
                            $secret_key = get_option('lscp_stripe_api_key');
                            $masked_key = $secret_key ? str_repeat('●', strlen($secret_key)) : '';
                            ?>
                            <input type="password" name="lscp_stripe_api_key" value="<?php echo esc_attr($masked_key); ?>" />
                            <p class="description"><?php esc_html_e('Your Stripe Secret Key. After saving, it will be hidden.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Redirect URL', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <input type="url" name="lscp_stripe_api_keylscp_stripe_redirect_url" value="<?php echo esc_url(get_option('lscp_stripe_api_keylscp_stripe_redirect_url', $customer_portal_url)); ?>" />
                            <p class="description"><?php esc_html_e('The URL to redirect the user back to after they exit the Stripe portal. Default is the customer portal page.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Customer Portal Slug', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <input type="text" name="lscp_stripe_endpoint_slug" value="<?php echo esc_attr($slug); ?>" />
                            <p class="description"><?php esc_html_e('Customize the slug for the customer portal page. Leave empty to disable the page.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Only allow existing Stripe customers to login', 'login-stripe-customer-portal'); ?></th>
                        <td>
                            <input type="checkbox" name="lscp_stripe_validate_existing_customers" value="1" <?php checked('1', $validate_existing_customers); ?> />
                            <p class="description"><?php esc_html_e('If checked, only existing Stripe customers can log in to the portal.', 'login-stripe-customer-portal'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <!-- Add the instructional strings here -->
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
        $slug = get_option('lscp_stripe_endpoint_slug', 'customer-portal');
        if (!empty($slug)) {
            add_rewrite_rule($slug . '/?$', 'index.php?lscp_stripe_customer_portal=1', 'top');
            add_rewrite_tag('%lscp_stripe_customer_portal%', '([^&]+)');
        }
    }

    /**
     * Handle the customer portal by processing the email form and sending the email with the login link.
     */
    public function handle_customer_portal() {
        $slug = get_option('lscp_stripe_endpoint_slug', 'customer-portal');
        if (empty($slug)) {
            return; // Disable the customer portal page if the slug is empty
        }
    
        global $wp_query;
    
        if (isset($wp_query->query_vars['lscp_stripe_customer_portal'])) {
    
            // Check if request method is POST and verify nonce
            if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
                // Unsanitize and sanitize the nonce
                $nonce = isset($_POST['lscp_stripe_portal_nonce']) ? sanitize_text_field(wp_unslash($_POST['lscp_stripe_portal_nonce'])) : '';
    
                // Verify nonce
                if (!wp_verify_nonce($nonce, 'lscp_stripe_portal_login_action')) {
                    wp_die(esc_html__('Security check failed', 'login-stripe-customer-portal'));
                }
    
                // Process form data after nonce verification
                if (isset($_POST['email'])) {
                    $email = sanitize_email(wp_unslash($_POST['email']));
                    $message = 'If your email address is registered, you should receive an email with a login link.';
    
                    // Check if validation for existing customers is enabled
                    $validate_existing_customers = get_option('lscp_stripe_validate_existing_customers', '0');
                    $customer_exists = $this->check_if_customer_exists($email);
    
                    if ($validate_existing_customers === '1' && !$customer_exists) {
                        // If the customer does not exist and validation is required, do not send the email
                        wp_die(esc_html($message), esc_html__('Login Message', 'login-stripe-customer-portal'));
                    }
    
                    // Send the login email if validation is not required or if the customer exists
                    if (is_email($email)) {
                        $this->send_login_email($email);
                    }
    
                    // Always display this message after form submission
                    wp_die(esc_html($message), esc_html__('Login Message', 'login-stripe-customer-portal'));
                }
            } elseif (isset($_GET['token'])) {
                // If a token is present in the URL, process login
                $token = sanitize_text_field(wp_unslash($_GET['token']));
                $email = get_transient('lscp_stripe_login_token_' . md5($token));
    
                if ($email) {
                    delete_transient('lscp_stripe_login_token_' . md5($token));
                    $this->process_customer_portal_login($email);
                } else {
                    wp_die(esc_html__('Invalid or expired token.', 'login-stripe-customer-portal'));
                }
            } else {
                $this->render_email_form();
            }
    
            exit;
        }
    }    

    /**
     * Send the email containing the login link with a token.
     *
     * @param string $email The email address to send the login link to.
     * @return bool True if the email was sent, false otherwise.
     */
    public function send_login_email($email) {
        $validate_existing_customers = get_option('lscp_stripe_validate_existing_customers', '0');
        
        // Check if the validation is enabled and if the customer exists
        if ($validate_existing_customers === '1' && !$this->check_if_customer_exists($email)) {
            return false;
        }
    
        // Generate a unique token and store it in the database (e.g., as a transient with expiration)
        $token = wp_generate_password(20, false);
        set_transient('lscp_stripe_login_token_' . md5($token), $email, 3600); // Token expires after 1 hour
    
        // Generate login link with token
        $login_url = add_query_arg([
            'token' => $token,
        ], home_url('/customer-portal'));
    
        // Send email
        $subject = __('Login to Stripe Customer Portal', 'login-stripe-customer-portal');
        $message = sprintf(
            __('Click the following link to log in to the Stripe Customer Portal: <a href="%s">%s</a>', 'login-stripe-customer-portal'),
            esc_url($login_url),
            esc_url($login_url)
        );
        $headers = ['Content-Type: text/html; charset=UTF-8'];
    
        return wp_mail($email, $subject, $message, $headers);
    }    

    /**
     * Show the message after the email is sent or the form is submitted.
     */
    public function show_login_message() {
        ?>
        <div style="text-align: center; padding: 50px;">
            <p><?php esc_html_e('If your email address is registered, you should receive an email with a login link shortly.', 'login-stripe-customer-portal'); ?></p>
        </div>
        <?php
    }

    /**
     * Check if the customer exists in Stripe.
     *
     * @param string $email The email address to check.
     * @return bool True if the customer exists, false otherwise.
     */
    public function check_if_customer_exists($email) {
        \LSCP\Stripe\Stripe::setApiKey(get_option('lscp_stripe_api_key'));

        try {
            $customers = \LSCP\Stripe\Customer::all([
                'email' => $email,
                'limit' => 1,
            ]);
            return count($customers->data) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Display the email form for customers to enter their email.
     */
    public function render_email_form() {
        ?>
        <div style="display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4;">
            <form method="post" action="" style="background-color: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1); max-width: 400px; width: 100%; text-align: center;">
                <?php wp_nonce_field('lscp_stripe_portal_login_action', 'lscp_stripe_portal_nonce'); ?>
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
        \LSCP\Stripe\Stripe::setApiKey(get_option('lscp_stripe_api_key'));
    
        try {
            // Search for the customer by email
            $customers = \LSCP\Stripe\Customer::all([
                'email' => $email,
                'limit' => 1,
            ]);
    
            if (count($customers->data) > 0) {
                $customer_id = $customers->data[0]->id;
            } else {
                $customer = \LSCP\Stripe\Customer::create([
                    'email' => $email,
                ]);
                $customer_id = $customer->id;
            }
    
            // Redirect to Stripe Customer Portal
            $this->redirect_to_stripe_customer_portal($customer_id);
    
        } catch (\Exception $e) {
            error_log('Stripe Error: ' . $e->getMessage());
            wp_die(esc_html__('Error: ', 'login-stripe-customer-portal') . esc_html($e->getMessage()));
        }
    }    

    /**
     * Redirect to Stripe Customer Portal with the customer ID.
     *
     * @param string $customer_id The Stripe customer ID.
     */
    public function redirect_to_stripe_customer_portal($customer_id) {
        \LSCP\Stripe\Stripe::setApiKey(get_option('lscp_stripe_api_key'));
    
        try {
            // Ensure the return URL is properly set
            $return_url = get_option('lscp_stripe_redirect_url', home_url('/' . get_option('lscp_stripe_endpoint_slug', 'customer-portal')));
            if (empty($return_url)) {
                $return_url = home_url(); // Fallback to home URL if the return URL is not set
            }
    
            $session = \LSCP\Stripe\BillingPortal\Session::create([
                'customer' => $customer_id,
                'return_url' => esc_url($return_url),
            ]);
    
            wp_redirect(esc_url_raw($session->url));
            exit;
        } catch (\Exception $e) {
            wp_die(esc_html__('Error redirecting to Stripe Customer Portal: ', 'login-stripe-customer-portal') . esc_html($e->getMessage()));
        }
    }    
}

// Initialize the plugin
new Plugin();