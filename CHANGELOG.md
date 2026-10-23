# Changelog

## 2.0.9 — 2026-10-23

### Security

- Replace the shared RSA trust mechanism on merchant REST exports with WordPress
  authentication, the `manage_woocommerce` capability and an exact merchant match.
  Merchant identity and resource IDs are read from route parameters.
- Validate query direction, status, dates, pagination and maximum page size before
  building SQL or WooCommerce queries. Remove order access keys from exports.
- Require a configured merchant secret and a constant-time HMAC check on payment
  notifications. Check reference, gateway, total and currency before changing an
  order. New checkouts persist their payment reference before calling Fundiin.
- Treat browser returns as navigation only. Ignore pending notifications and late
  or repeated events for terminal orders. Let WooCommerce handle stock and the
  processing/completed transition.
- Encode merchant identifiers and escape script URLs. Stop rendering the legacy
  arbitrary-script setting and encode displayed prices as numeric JSON values.
- Verify TLS for checkout and refunds, disable outbound HTTP redirects, validate
  payment redirect destinations under `fundiin.vn`, and validate upstream replies.
- Remove credential, signature, request-body and response-body logging. Logging
  defaults to off and uses the WooCommerce logger rather than ad-hoc public files.
- Require management permission when saving settings or issuing refunds. Remove
  the global, invalid CORS header.

### Corrections and compatibility

- Use the order total instead of the current cart total for payment requests.
- Fix double-encoded refund HMACs, missing customer lookup and order formatter calls.
- Set plugin and stable-tag versions consistently to 2.0.9; declare PHP 7.4+.
- Add standalone security regression tests and a PHP 7.4/8.2/8.5 CI matrix.

### Upgrade

Merchant export clients must migrate to WordPress authentication before upgrading.
Use an authorized WordPress account and, on WordPress 5.6+, an Application Password
over HTTPS. The old shared RSA credential is no longer accepted. Exports default
to 50 records and accept `limit` from 1 to 100 plus `offset`; `limit: -1` is rejected.
Existing payment notifications without a locally stored reference remain accepted
only after signature, reference-format, gateway, amount and currency validation.

These changes harden this GitHub codebase. They do not establish that the separate
WordPress.org distribution has been updated or that CVE-2026-81649 has been independently
verified as resolved. See [SECURITY.md](SECURITY.md).
