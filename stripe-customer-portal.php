<?php
/**
 * Plugin Name: Stripe Customer Portal Integration
 * Description: Allow merchants to connect Stripe and provide a customer login endpoint for the Stripe Customer Portal.
 * Version: 1.2
 * Author: Your Name
 * License: GPLv3
 * Text Domain: stripe-portal
 */

namespace StripeCustomerPortal;

require_once plugin_dir_path(__FILE__) . 'lib/stripe-php/init.php';  // Ensure Stripe SDK is included

class Plugin {
    public function __construct() {
        // Register settings and menus
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        
        // Add endpoint and handle customer portal
        add_action('init', [$this, 'add_customer_portal_endpoint']);
        add_action('template_redirect', [$this, 'handle_customer_portal']);

        // Enqueue styles for the form
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
    }

    /**
     * Add the settings page for API key entry.
     */
    public function add_settings_page() {
        add_options_page(
            __('Stripe Customer Portal Settings', 'stripe-portal'),
            __('Stripe Portal', 'stripe-portal'),
            'manage_options',
            'stripe-portal-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register the API key setting.
     */
    public function register_settings() {
        register_setting('stripe_portal_settings_group', 'stripe_api_key', [
            'sanitize_callback' => [$this, 'sanitize_secret_key'],
        ]);
    }

    /**
     * Sanitize the secret key before saving.
     */
    public function sanitize_secret_key($input) {
        return sanitize_text_field($input);
    }

    /**
     * Render the settings page for entering the Stripe API key.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('Stripe Customer Portal Settings', 'stripe-portal'); ?></h1>
            <p><?php _e('Provide your Stripe Secret Key below and save. After saving, it will be hidden for security.', 'stripe-portal'); ?></p>
            <form method="post" action="options.php">
                <?php 
                settings_fields('stripe_portal_settings_group');
                do_settings_sections('stripe_portal_settings_group');
                ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Stripe Secret Key', 'stripe-portal'); ?></th>
                        <td>
                            <?php
                            $secret_key = get_option('stripe_api_key');
                            $masked_key = $secret_key ? str_repeat('●', strlen($secret_key)) : '';
                            ?>
                            <input type="password" name="stripe_api_key" value="<?php echo esc_attr($masked_key); ?>" />
                            <p class="description"><?php _e('Your Stripe Secret Key. After saving, it will be hidden.', 'stripe-portal'); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
            <h2><?php _e('Instructions', 'stripe-portal'); ?></h2>
            <p><?php _e('Your customer portal endpoint is available at:', 'stripe-portal'); ?></p>
            <p><code><?php echo esc_url(home_url('/customer-portal/')); ?></code></p>
            <p><?php _e('Make sure to resave your permalinks after activating this plugin by going to', 'stripe-portal'); ?> 
                <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>"><?php _e('Permalinks Settings', 'stripe-portal'); ?></a>.
            </p>
        </div>
        <?php
    }

    /**
     * Add the custom endpoint for the customer portal login page.
     */
    public function add_customer_portal_endpoint() {
        add_rewrite_rule('customer-portal/?$', 'index.php?stripe_customer_portal=1', 'top');
        add_rewrite_tag('%stripe_customer_portal%', '([^&]+)');
    }

    /**
     * Handle the customer portal by processing the email form and redirecting to the Stripe Customer Portal.
     */
    public function handle_customer_portal() {
        global $wp_query;

        if (isset($wp_query->query_vars['stripe_customer_portal'])) {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
                $email = sanitize_email($_POST['email']);

                if (is_email($email)) {
                    $this->process_customer_portal_login($email);
                } else {
                    wp_die(__('Invalid email address.', 'stripe-portal'));
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
        <div class="stripe-portal-container">
            <form method="post" action="">
                <label for="email"><?php _e('Enter your email address:', 'stripe-portal'); ?></label>
                <input type="email" name="email" id="email" required class="stripe-portal-input" />
                <input type="submit" value="<?php _e('Continue to Stripe Portal', 'stripe-portal'); ?>" class="stripe-portal-submit" />
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
            wp_die(__('Error: ', 'stripe-portal') . $e->getMessage());
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
                'return_url' => home_url(),
            ]);

            wp_redirect($session->url);
            exit;
        } catch (Exception $e) {
            wp_die(__('Error redirecting to Stripe Customer Portal: ', 'stripe-portal') . $e->getMessage());
        }
    }

    /**
     * Enqueue custom styles for the customer portal form.
     */
    public function enqueue_styles() {
        wp_enqueue_style('stripe-portal-styles', plugin_dir_url(__FILE__) . 'styles.css');
    }
}

// Initialize the plugin
new Plugin();
