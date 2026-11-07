## 2.0.1

- Fix From, Reply-To, Cc and Bcc handling for SMTP and CyberMail SMTP fallback. Bcc recipients enter the envelope without appearing in visible message headers.
- Preserve display names, quoted commas, charset and content type; ignore blank header lines and report useful send errors.
- Restore WordPress mail filters/actions and retain original headers in logs for resend.
- Preserve CyberMail API's existing sender and recipient payload fields. Header support in other API providers remains limited; this release does not claim full WordPress header compatibility for every API provider.
