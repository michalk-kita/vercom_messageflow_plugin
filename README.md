# Vercom Messageflow Plugin

A WordPress plugin that replaces the default `wp_mail()` transport with the [MessageFlow](https://messageflow.com/) API, so all outgoing emails are sent through MessageFlow's transactional email service.

Built as a recruitment task for Vercom.

## Features

- **Drop-in replacement** for `wp_mail()` — no changes needed in themes or other plugins
- **Settings page** in the WordPress admin panel: Settings > Vercom Messageflow Plugin
- **One-click test email** directly from the settings page
- **Graceful fallback** — when the plugin is deactivated or not configured, WordPress reverts to default email behavior
- **Full wp_mail compatibility** — supports HTML/plain text, CC, BCC, Reply-To, custom headers, and attachments
- **Security** — nonce verification, capability checks, input sanitization, output escaping

## Requirements

- WordPress 5.7+
- PHP 7.4+
- MessageFlow account with API keys ([register here](https://app.messageflow.com/register))
- **Authorized sender domain** — your domain must be verified in the MessageFlow panel with DNS records (SPF, DKIM, DMARC). Without this, the API will reject all emails.

## Installation

1. Download the plugin as a ZIP or clone this repository
2. Upload the `vercom_messageflow_plugin` folder to `/wp-content/plugins/`
3. Activate the plugin: Plugins > Installed Plugins
4. Go to **Settings > Vercom Messageflow Plugin** and enter your API credentials

## Configuration

### 1. Domain Authorization (Required)

Before sending any emails, you must authorize your sender domain in the MessageFlow panel:

1. Log in to the [MessageFlow panel](https://app.messageflow.com)
2. Go to **E-mail > Bezpieczenstwo nadawcy > Autoryzacja nadawcy > Autoryzacja domen**
3. Add your domain and configure the required DNS records:
   - **SPF** — authorizes MessageFlow servers to send on behalf of your domain
   - **DKIM** — adds a digital signature for email authenticity
   - **DMARC** — defines the policy for handling unauthenticated emails
4. Wait for DNS propagation (may take up to 48 hours)

Without domain authorization, the MessageFlow API will reject emails with a 400 error.

### 2. API Keys

1. Log in to the [MessageFlow panel](https://app.messageflow.com)
2. Go to **Konto > Ustawienia > API**
3. Click **Nowy klucz API** and save:
   - **Authorization Token** (128-character string)
   - **Application Key**

### 3. SMTP Account

1. In the MessageFlow panel, go to **E-mail > E-mail API > Ustawienia > Konta SMTP**
2. Copy the SMTP account name (e.g. `1.yourdomain.smtp`)

### 4. Plugin Settings

1. In WordPress, go to **Settings > Vercom Messageflow Plugin**
2. Enter the Authorization Token, Application Key, and SMTP Account
3. Optionally set a Default From Email and Default From Name
4. Click **Save Settings**
5. Use the **Send Test Email** button to verify everything works

## How It Works

The plugin uses the `pre_wp_mail` filter (available since WP 5.7) to intercept all `wp_mail()` calls:

1. WordPress (core, plugin, or theme) calls `wp_mail()`
2. The `pre_wp_mail` filter intercepts the call before PHPMailer is initialized
3. Email arguments (to, subject, message, headers, attachments) are transformed into the MessageFlow API format
4. A `POST https://api.messageflow.com/v2.1/email` request is sent with `Authorization` and `Application-Key` headers
5. The result (success/error) is returned to the caller

PHPMailer is never loaded. Deactivating the plugin immediately restores the default WordPress email behavior.

## Plugin Structure

```
vercom_messageflow_plugin/
├── vercom_messageflow_plugin.php          # Main plugin file (bootstrap)
├── uninstall.php                          # Cleanup on plugin deletion
├── includes/
│   ├── class-vercom-plugin.php            # Orchestrator (composition root)
│   ├── class-vercom-api-client.php        # HTTP client for MessageFlow API
│   ├── class-vercom-email-handler.php     # wp_mail() interceptor
│   └── class-vercom-admin.php             # Settings page + AJAX test email
├── admin/
│   ├── views/settings-page.php            # Settings page template
│   ├── css/vercom-admin.css               # Admin styles
│   └── js/vercom-admin.js                 # AJAX test email script
└── README.md
```

## MessageFlow API

- **Endpoint:** `POST https://api.messageflow.com/v2.1/email`
- **Authentication:** `Authorization` + `Application-Key` headers
- **Documentation:** [dev.messageflow.com](https://dev.messageflow.com)

## Author

Michał Kita
