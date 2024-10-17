=== Login for Stripe Customer Portal ===
Contributors: gauchoplugins
Tags: stripe, customer portal, login, api
Stable tag: 1.0.1
Requires at least: 5.0
Tested up to: 6.7
Requires PHP: 7.0
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Create a customer login page for the Stripe Customer Portal directly from your WordPress site.

== Description ==

The **Login for Stripe Customer Portal** plugin simplifies the process for Stripe merchants to integrate the Stripe Customer Portal into their WordPress website. By offering a customizable login endpoint, this plugin makes it easy for customers to log in and access their Stripe billing information securely.

Upon entering their email address, the platform checks your Stripe account for existing customers, and then generates a secure login link for the customer to login, which is only valid for 1 hour.  

The Customer Portal is still hosted on Stripe, but the Login page is on your domain, giving a bit more control over your branding and experience. 

### Key Features:
* **Stripe API Integration**: Allows WordPress site admins to connect their Stripe account via API and provide customer access to the Stripe Customer Portal.
* **Customizable Endpoint**: Admins can define a custom slug for the customer portal login page (e.g., `yourwebsite.com/customer-portal/`).
* **Secure Authentication**: Users enter their email and are sent a secure login link to get access to the Stripe Customer Portal. 
* **Redirect URL**: After logging out of the portal, customers are redirected back to a specified URL, which can be customized in the plugin settings.

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

Yes, the plugin allows you to customize the endpoint URL for the login page. You can define this under the settings.

== Screenshots ==

1. Settings page where you configure your Stripe API key, redirect URL, and customer portal slug.
2. The customer login page where users enter their email.
3. Confirmation message after submitting the email form.

== Changelog ==

= 1.0.1 =
* Added email login link functionality.




