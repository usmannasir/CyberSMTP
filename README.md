# CyberSMTP

A modern, multi-provider SMTP plugin for WordPress. Supports Amazon SES, SendGrid, Mailgun, Brevo (Sendinblue), and generic SMTP. Includes logs, analytics, tooltips, and a beautiful UI.

---

## Features

- **Multi-provider support:** Amazon SES, SendGrid, Mailgun, Brevo (Sendinblue), and generic SMTP
- **Modern UI:** Clean, intuitive admin interface with SVG provider icons
- **Email logs & analytics:** Track sent and failed emails, view delivery stats
- **Test email:** Send test emails from a dedicated tab
- **Tooltips:** Helpful tooltips for all important fields
- **Admin notice:** Prompts you to configure SMTP after installation
- **Security:** Credentials are stored securely and never shared
- **Easy setup:** Get started in minutes

---

## Installation

1. Upload the plugin files to the `/wp-content/plugins/cybersmtp` directory, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to **CyberSMTP** in your WordPress admin menu to configure your email provider and settings.

---

## Usage

- Select your email provider from the grid.
- Enter your SMTP/API credentials and sender details.
- Save your settings.
- Use the **Test Email** tab to verify your configuration.
- View sent/failed emails in the **Logs** tab.

---

## FAQ

**Q: Will this work with WooCommerce, Contact Form 7, Gravity Forms, etc?**  
A: Yes! CyberSMTP replaces the default WordPress mailer, so all plugins that use `wp_mail()` will work.

**Q: Is my email data secure?**  
A: Yes. Credentials are stored in your WordPress database and only sent to your chosen provider.

**Q: What providers are supported?**  
A: Amazon SES, SendGrid, Mailgun, Brevo (Sendinblue), and any generic SMTP server.

**Q: Can I see email logs?**  
A: Yes, CyberSMTP includes a logs tab for sent and failed emails.

---

## Contributing

Pull requests are welcome! For major changes, please open an issue first to discuss what you would like to change.

---

## License

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html)

---

## Credits

Developed by [CyberPanel](https://cyberpanel.net/). 