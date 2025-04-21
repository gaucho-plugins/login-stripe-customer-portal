=== Login for Stripe Customer Portal | Create an Account Login Page for the Stripe Customer Portal ===
Contributors: gauchoplugins, brandonfire, freemius
Author URI: https://gauchoplugins.com/
Plugin URI: https://gauchoplugins.com/
Donate link: https://gauchoplugins.com/
Tags: stripe, portal, login, customer, account
Stable tag: 1.0.3
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create a customer login page for the Stripe Customer Portal directly from your WordPress site.

== Description ==

The **Login for Stripe Customer Portal** plugin simplifies the process for Stripe businesses to integrate the Stripe Customer Portal into their WordPress website. By offering a customizable login endpoint or embeddable form, the plugin makes it easy for customers to log in and access and manage their Stripe billing information securely. 

Upon entering their email address, customers receive a secure login link, which is only valid for 1 hour. You can optionally restrict access only to your existing customers or allow new customers to register and add their information. 

The Customer Portal is still hosted on Stripe, but the Login page is on your website, giving you more control over professionally branding the experience customers have on your site. 

### Key Features:
* **Stripe API Integration**: Allows WordPress site admins to connect their Stripe account via API and provide customer access to the Stripe Customer Portal.
* **Customizable Endpoint**: Admins can define a custom slug for the customer portal login page (e.g., `yourwebsite.com/customer-portal/`).
* **Shortcode Embeddable Form**: Use the shortcode `[login-stripe-customer-portal]` to put the login form anywhere on your site. 
* **Secure Authentication**: Users enter their email and are sent a secure login link to get access to the Stripe Customer Portal. 
* **Redirect URL**: After logging out of the portal, customers are redirected back to a specified URL, which can be customized in the plugin settings.

### Third-Party Service Disclaimer
This plugin integrates with Stripe to provide the customer portal functionality. It uses Stripe's API to connect with and manage customer data securely. By using this plugin, you are consenting to the transmission of data to Stripe’s services.

Service Terms: [Stripe Terms of Use](https://stripe.com/legal/ssa)
Privacy Policy: [Stripe Privacy Policy](https://stripe.com/privacy)

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to "Stripe Portal" in the WordPress admin menu to configure the plugin settings.
4. Enter your Stripe Secret API Key, customize the endpoint slug, and set your desired redirect URL.

== Frequently Asked Questions ==

= What does this plugin do? =

This plugin allows you to provide a customer login page for Stripe’s Customer Portal directly from your WordPress site. It enables customers to access and manage their Stripe account details securely.

= How do I get my Stripe Secret API key? =

Log into your Stripe Dashboard, and under "Developers" > "API keys", you will find the option to copy your Secret API key.

= Can I customize the login page? =

Yes, the plugin allows you to customize the endpoint URL for the login page. You can define this under the settings. Styling settings are coming soon, but for now you can use custom CSS. 

== Screenshots ==

1. Settings page to configure Stripe API key, redirect URL, and customer portal slug
2. Login form example - users can enter email and generate a login link
3. Embeddable Stripe Customer Portal login form based on shortcode
4. Confirmation message after submitting the email form
5. Email including temporary login link for Stripe Customer Portal

== Changelog ==

= 1.0.3 =
* Updated Freemius SDK.

See our full changelog in our [documentation](https://gauchoplugins.gitbook.io/login-for-stripe-customer-portal-wordpress-plugin). 
