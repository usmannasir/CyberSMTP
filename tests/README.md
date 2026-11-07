Run the standalone regression suite against the PHPMailer bundled with a local WordPress checkout:

```sh
WORDPRESS_ROOT=/path/to/wordpress php tests/mail-headers.php
```

The harness builds real MIME messages and recipient envelopes. It replaces PHPMailer's transport with an in-memory capture, captures WordPress database inserts, and stubs CyberMail HTTP responses. It does not load a WordPress database, contact SMTP servers, or send email. All addresses, credentials and records are synthetic.
