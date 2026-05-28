=== Login for Stripe Customer Portal | Stripe Billing Login Page | Magic Link Customer Account ===
Contributors: gauchoplugins, brandonfire, freemius
Author URI: https://customerportalplugin.com/
Plugin URI: https://customerportalplugin.com/
Donate link: https://customerportalplugin.com/
Tags: stripe, portal, login, customer, account
Stable tag: 1.1.0
Requires at least: 5.0
Tested up to: 7.0
Requires PHP: 7.2
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Stripe Customer Portal login for WordPress — passwordless magic-link email, branded login page, [shortcode] embed, GDPR-ready.

== Description ==

**The fastest way to put a branded Stripe Customer Portal login page on your WordPress site — no passwords, no developer required.**

Customers want to update their card, change their plan, or download an invoice WITHOUT emailing support. Stripe's Customer Portal already does all of that — what's been missing is a clean, branded **login entry point on your own domain**.

**Login for Stripe Customer Portal** solves that in 60 seconds: paste your Stripe Secret Key, save, and a passwordless magic-link form is live on your site. Customers enter their email, click the link in their inbox, and land directly inside Stripe's hosted Customer Portal — already authenticated.

[https://customerportalplugin.com/](https://customerportalplugin.com/) — visit the website to see the plugin in action, browse the email template gallery, and view the PRO feature tour.

== ✅ FREE FEATURES ==

* **🔐 Stripe Customer Portal login** — connect your Stripe account, customers manage billing themselves.
* **✉️ Magic-link email authentication** — one-time link valid for 1 hour; no passwords to manage.
* **🧩 Shortcode embed** — drop `[login-stripe-customer-portal]` on any page; works multiple times on one page.
* **💬 Inline confirmation** — submissions stay on your page (new in 1.1.0 — no more blank `wp_die` screens).
* **🔗 Custom URL slug** — host the login at `yoursite.com/billing/`, `/account/`, or any path you like.
* **↩️ Custom return URL** — pick where customers land after logging out of Stripe's portal.
* **🎛️ Existing-customer gate** — optionally restrict access to email addresses that already have a Stripe customer record.
* **🛡️ Security hardened** — SHA-256 token hashing, per-email + per-IP rate limiting, CSRF nonces, no enumeration oracle.
* **🇪🇺 GDPR-compliant** — exporter + eraser registered with WordPress Privacy Tools out of the box.
* **🧰 WP-CLI commands** — `wp lscp purge-tokens / limiter-reset / send / config`.
* **🧹 Daily token cleanup** — WP-Cron sweep removes expired magic-link tokens automatically.
* **🧑‍💻 Developer extension surface** — 12+ filters and actions for customizing every stage of the flow.

📕 [Documentation](https://docs.customerportalplugin.com/) · 🆘 [Support forum](https://wordpress.org/support/plugin/login-stripe-customer-portal/) · 🌐 [Website](https://customerportalplugin.com/)

== 🚀 UNLOCK MORE WITH PRO ==

Need branded emails, a styled login form, role automation on Stripe events, or to run multiple Stripe accounts from one site? **[Upgrade to PRO](https://customerportalplugin.com/pricing/)** — every license tier includes every feature, including white-label.

[**👉 Compare PRO plans on the website**](https://customerportalplugin.com/pricing/)

= 🎨 Branded magic-link emails =

Replace the plain HTML email with one of **6 pre-built templates** (Minimal, Card, Bold, Stripe-like, Newsletter, Card-with-logo). Pick a brand color, drop in your logo URL, customize the subject / heading / CTA / footer — every change is shown in a **live preview iframe** inside the admin BEFORE you save. No "send a test email" loop required.

= 💅 Login-form styler =

Style the public login form to match your site — **6 form templates** (Minimal, Card, Inline, Full-width, Centered, Branded), brand color, custom heading / subheading / button label / email placeholder, all with the same live-preview iframe.

= 🔗 WP user ↔ Stripe customer bridge =

Pre-fill the magic-link form for logged-in WP users (one click instead of typing). On every successful redemption, link the Stripe customer id to the WP user as `_lscp_stripe_customer_id` user-meta — your other plugins and themes can read it. Optionally auto-create the WP user (with a configurable default role) the first time someone redeems.

= 🛒 WooCommerce / MemberPress / LearnDash integration =

Adds a configurable **"Manage Billing"** button to:

* The **WooCommerce My Account** dashboard (classic *and* block-based templates).
* The **MemberPress** account home.
* The **LearnDash** profile page (`[ld_profile]`).

One click sends the user to your branded portal login — pre-filled with their email if they're logged in.

= 🪝 Stripe webhooks → WP role automation =

> **PRO-only feature.** The webhook REST endpoint, signature verification, and idempotency cache all ship in the PRO build. Without PRO, Stripe events sent to your site are not processed by this plugin — subscription state will not auto-sync to WP roles. If you only need the public login form + portal redirect, FREE is enough.

A REST endpoint (`/wp-json/lscp/v1/webhook`) verifies the Stripe `Stripe-Signature` header (HMAC-SHA256 + 5-minute timestamp tolerance, constant-time compare) and **automates WordPress role changes** on the events that matter:

* `customer.subscription.created` / `.updated` → assign your configured "active" role.
* `customer.subscription.deleted` → remove the role (or assign a downgrade role).
* `invoice.payment_failed` → assign your "past due" role.
* `invoice.paid` → fire an extensible action (`lscp_pro_webhook_invoice_paid`).

7-day SHA-256-keyed idempotency cache means Stripe retries are safe — no double-firing.

= 🌐 Multi-Stripe-account routing =

Run **multiple Stripe accounts from one WordPress install**. Each account gets its own URL slug (`/billing-eu/`, `/billing-us/`), API key, validate-existing toggle, redirect URL, and From email. Requests are routed transparently via WordPress's `pre_option_*` filters — the FREE plugin code is unchanged.

= 🏷️ Agency white-label =

Replace "Powered by Gaucho Plugins" with your own brand name across every admin string. Hide the upgrade prompts entirely. **Included with every PRO tier** — no need to buy the most expensive plan.

= 💌 Priority email support =

PRO customers get a dedicated support inbox — typical reply within one business day.

= 💵 PRO licensing =

Every PRO tier unlocks every PRO feature, white-label included. Tiers differ only in how many sites a license covers.

[**👉 Compare plans on the website**](https://customerportalplugin.com/pricing/)

== ✅ PERFECT FOR ==

* **SaaS founders** using Stripe Billing who want customers to self-serve.
* **Membership sites** that need a branded billing portal.
* **WooCommerce stores** using Stripe Subscriptions (PRO adds the My Account button).
* **MemberPress / LearnDash sites** that want a one-click Manage Billing entry point.
* **Digital agencies** managing client portfolios with multiple Stripe accounts.

== 🤝 WORKS WITH ==

* **Stripe Billing** (subscriptions, invoices, customer portal) — required.
* **WooCommerce** — PRO integration adds the Manage Billing button to My Account.
* **MemberPress** — PRO integration adds the button to the account page.
* **LearnDash** — PRO integration adds the button to the profile page.
* Any WordPress theme — classic *or* block-based.
* Any caching plugin (the rewrite endpoint marks itself uncacheable).
* WP Mail SMTP, FluentSMTP, Brevo, SendGrid, Postmark, etc. (uses standard `wp_mail`).

== 📚 RESOURCES ==

* **Website:** [customerportalplugin.com](https://customerportalplugin.com/)
* **Documentation:** [docs.customerportalplugin.com](https://docs.customerportalplugin.com/)
* **Pricing & PRO upgrade:** [customerportalplugin.com/pricing](https://customerportalplugin.com/pricing/)
* **Changelog:** [customerportalplugin.com/changelog](https://customerportalplugin.com/changelog/)
* **Free support:** [WordPress.org support forum](https://wordpress.org/support/plugin/login-stripe-customer-portal/)
* **PRO support:** included with every PRO license

== 🧰 GAUCHO PLUGINS PORTFOLIO ==

* [**Payment Page**](https://wordpress.org/plugins/payment-page/) — Stripe payment forms in under 60 seconds.
* [**Split Pay**](https://wordpress.org/plugins/bsd-woo-stripe-connect-split-pay/) — Split WooCommerce payments across multiple connected Stripe accounts.
* [**Gyta Buyback**](https://wordpress.org/plugins/gyta-buyback/) — Trade-in / buyback for WooCommerce.
* [**China Payments**](https://wordpress.org/plugins/wp-stripe-global-payments/) — WeChat Pay + Alipay in WooCommerce.
* [**Speed in China**](https://wordpress.org/plugins/speed-in-china/) / [**Blocked in China**](https://wordpress.org/plugins/blocked-in-china/) — China-region site diagnostics.
* [**Version Info**](https://wordpress.org/plugins/version-info/) — WP, PHP, MySQL, web-server versions in the admin dashboard.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/` or install through the WordPress plugins screen.
2. Activate via the Plugins screen.
3. Navigate to **Stripe Portal** in the WordPress admin menu.
4. Paste your Stripe Secret API key, pick a slug for the login page (e.g. `customer-portal`), set a return URL, and save.
5. Visit `yourdomain.com/<your-slug>/` to see the login form — or drop `[login-stripe-customer-portal]` on any page.
6. (Recommended) Go to **Settings → Permalinks → Save** once after changing the slug.

Full setup guide: [docs.customerportalplugin.com](https://docs.customerportalplugin.com/)

== Frequently Asked Questions ==

= What problem does this plugin solve? =

Stripe's hosted Customer Portal is great, but the URL to access it is unique per customer and not shareable. This plugin puts a clean **public login page on your own domain** so customers can enter their email and get an instant magic-link to their portal session — without needing a password, your support team, or a custom Stripe API integration.

= Do I need PRO to use the plugin? =

No. The free version on WordPress.org has everything you need to put a working magic-link Stripe portal login on your site: shortcode embed, custom URL slug, GDPR tools, rate limiting, SHA-256-hashed tokens, WP-CLI commands. PRO adds branding, integrations, webhook automation, and multi-account routing.

= How do I get my Stripe Secret API key? =

Log into your Stripe Dashboard → Developers → API keys. Copy the Secret Key (starts with `sk_live_…` for live mode or `sk_test_…` for test mode). Paste it into **Stripe Portal → Settings**.

= Can I customize the login page styling? =

Yes — two ways:

* **Free:** Add custom CSS targeting `.lscp-portal-form`. The form HTML is fully filterable via the `lscp_form_template` filter for advanced theming.
* **PRO:** Use the built-in form styler — 6 templates, brand color pickers, custom heading/button label, live preview in admin. No CSS required.

= Can I brand the magic-link email? =

* **Free:** the email body is filterable via `lscp_email_html_body` (developer route).
* **PRO:** pick from 6 pre-built templates in the admin, set your brand color + logo + custom heading/CTA, see a live preview as you type.

= Does the plugin replace WooCommerce / MemberPress / LearnDash billing? =

No — it complements them. Your Stripe products, subscriptions, and billing logic stay in Stripe. With PRO, a **"Manage Billing"** button gets added to the WC / MP / LD account page so logged-in customers go to your branded portal login in one click.

= How does the magic link login actually work? =

1. Customer enters their email on your login form.
2. Plugin (rate-limited) issues a single-use token, stores its SHA-256 hash as a 1-hour transient, and emails the customer a link.
3. Customer clicks the link → token is verified, marked used, and the Stripe Customer Portal session is created.
4. Customer is redirected to Stripe's hosted portal — already authenticated.

The token never appears in your database in cleartext. The link expires after one hour or after a single use, whichever comes first.

= Can I run multiple Stripe accounts on one site? =

Yes, with PRO. The Multi-Account tab lets you add as many Stripe accounts as your license allows, each with its own URL slug, API key, redirect URL, and from email. The free version is single-account.

= Does the webhook listener handle Stripe retries safely? =

Yes. PRO's webhook endpoint dedupes by Stripe `event.id` with a 7-day SHA-256-keyed transient — a retried event returns `200 {"replayed": true}` without re-firing your role automation rules.

= Is the plugin GDPR compliant? =

Yes. The free version registers a Privacy Tools exporter and eraser so site owners can satisfy data subject access and erasure requests via **Tools → Export / Erase Personal Data**.

= Where can I find the changelog? =

Recent changes are below. The full changelog (every release) is at [customerportalplugin.com/changelog](https://customerportalplugin.com/changelog/).

= Where can I get support? =

* **Free:** [WordPress.org support forum](https://wordpress.org/support/plugin/login-stripe-customer-portal/).
* **PRO:** priority email support is included with every PRO license.

== External Services ==

This plugin connects to the following external services.

= Stripe (api.stripe.com) =
This plugin uses your Stripe Secret API key to authenticate customers and generate secure links to the Stripe Customer Portal. Customer email addresses are sent to Stripe when a user requests a login link. Stripe hosts the Customer Portal where customers manage billing information.

* [Stripe Terms of Service](https://stripe.com/legal/ssa)
* [Stripe Privacy Policy](https://stripe.com/privacy)

= Freemius (api.freemius.com, freemius.com) =
This plugin includes the Freemius SDK for license and update management. Data is sent to Freemius only when you opt in through the Freemius connect screen.

* [Freemius Terms of Service](https://freemius.com/terms/)
* [Freemius Privacy Policy](https://freemius.com/privacy/)

== Screenshots ==

1. Settings page — Stripe API key, redirect URL, customer portal slug.
2. Public login form — customer enters their email.
3. Confirmation message — inline, on the same page (1.1.0 UX fix).
4. Magic-link email — branded template preview (PRO).
5. Form-styler — brand color + heading + button (PRO).
6. Multi-Stripe-account — multiple accounts on one site, each at its own URL slug (PRO).
7. Webhook listener — assign WP roles on Stripe events (PRO).
8. WooCommerce integration — Manage Billing button on My Account (PRO).

== Changelog ==

Only the current version is shown here. The full release history is in `changelog.txt` (bundled with the plugin) and at [customerportalplugin.com/changelog](https://customerportalplugin.com/changelog/).

= 1.1.0 =
* New (FREE): Inline confirmation message — form submissions no longer land on a blank `wp_die` screen.
* New (FREE): `[lscp-message]` shortcode for rendering the success/error message on a custom page.
* New (FREE): Tabbed settings page; developer extension surface (12+ filters and actions).
* New (PRO): Branded magic-link emails — 6 templates with live preview.
* New (PRO): Login-form styler — 6 templates with live preview.
* New (PRO): WP user ↔ Stripe customer bridge (auto-link, pre-fill, optional auto-create).
* New (PRO): WooCommerce / MemberPress / LearnDash integrations (Manage Billing button).
* New (PRO): Stripe webhook listener with WP role automation (HMAC-verified, idempotent).
* New (PRO): Multi-Stripe-account routing — multiple accounts at different URL slugs.
* New (PRO): Agency white-label — included at every license tier.
