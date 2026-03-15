# CyberSMTP

The official CyberPanel email plugin for WordPress. Send emails reliably via **CyberMail**, Amazon SES, SendGrid, Mailgun, Brevo, or any SMTP server.

---

## Features

- **CyberMail Integration** — Free email delivery with delivery tracking, bounce analytics, and domain health monitoring. Recommended for CyberPanel users.
- **Multi-provider support** — Amazon SES, SendGrid, Mailgun, Brevo (Sendinblue), and generic SMTP
- **Modern dashboard** — Email activity charts, stats cards, quick actions
- **Email logs** — Paginated logs with search, date filtering, status badges, email preview, and one-click resend
- **Deliverability tools** — SPF, DKIM, DMARC, and MX record checker built-in
- **Test email** — Send test emails from a dedicated tab
- **CyberPanel auto-detection** — Detects CyberPanel servers and offers one-click CyberMail setup
- **Zero bloat** — No Composer dependencies. Uses WordPress HTTP API for all provider integrations.
- **Secure** — Credentials encrypted with WordPress salts. Nonce verification on all forms.

---

## Installation

1. Upload to `/wp-content/plugins/cybersmtp/` or install via WordPress plugins screen.
2. Activate the plugin.
3. Go to **CyberSMTP** in your admin menu.
4. Choose **CyberMail** (recommended) or configure your preferred provider.

---

## CyberMail (Recommended)

CyberMail is a free email delivery service by CyberPanel with features not available from third-party providers:

- Real-time delivery status tracking
- Bounce analytics
- Domain health monitoring
- Auto-DKIM configuration
- Built-in spam protection

Get your free API key at [platform.cyberpersons.com/email](https://platform.cyberpersons.com/email/).

---

## Providers

| Provider | Modes | Auth |
|----------|-------|------|
| CyberMail | API, SMTP | API Key (`sk_live_`) |
| Amazon SES | API (SigV4), SMTP | Access Key + Secret |
| SendGrid | API, SMTP | API Key |
| Mailgun | API, SMTP | API Key + Domain |
| Brevo | API, SMTP | API Key |
| Generic SMTP | SMTP | Username + Password |

---

## FAQ

**Q: Does this work with WooCommerce, Contact Form 7, Gravity Forms, etc.?**
A: Yes. CyberSMTP replaces `wp_mail()`, so all plugins using it will work automatically.

**Q: Do I need Composer or any PHP dependencies?**
A: No. CyberSMTP uses the WordPress HTTP API for all API-based providers. Zero external dependencies.

**Q: Is my data secure?**
A: Yes. API keys and passwords are encrypted using WordPress salts before storage.

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

Developed by [CyberPanel](https://cyberpanel.net/).
