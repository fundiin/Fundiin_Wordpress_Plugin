# Security

## CVE-2026-81649 and distribution scope

[WPScan's advisory](https://wpscan.com/vulnerability/28cec871-1f17-48c7-b836-db705fc5cffa/)
describes missing authorization and stored XSS in Fundiin cho WooCommerce through
3.4.0. This GitHub repository previously had a 2.0.7 plugin header and a v2.0.8 tag;
its route set differs from the advisory description. Version 2.0.9 hardens the
code present here. It is not a claim of an independently verified CVE fix for the
WordPress.org package, and a GitHub release does not update WordPress.org installs.

## Authentication boundaries

- `/wp-json/merchant/{merchant_id}/api/...` exports require WordPress authentication
  and `manage_woocommerce`. A shared RSA signature provides no authorization.
  Use HTTPS and a dedicated authorized account with an Application Password
  (WordPress 5.6+), or an authenticated admin REST session with a valid REST nonce.
- The payment notification endpoint is intentionally reachable by Fundiin without
  a WordPress login. It requires an HMAC-SHA256 signature using the store's nonempty
  secret and an exact merchant match. Both raw-body and legacy JSON-normalized
  signatures are supported. Query/body parameters cannot override signed fields.
- An order can change only when its gateway, persisted `_fundiin_reference_id`,
  amount and currency match. Persisted references bind callbacks to the latest
  payment attempt.
- Concurrent notifications for the same order are serialized using per-order
  locks and post-lock status re-verification.
- Browser redirects never confirm payment. Only verified server notifications do.
- Settings writes and refunds require WooCommerce management permission. This
  repository does not expose settings or order-status mutation REST routes.

## Verification and rollout

Run `php tests/security.php`. The standalone suite uses WordPress/WooCommerce test
doubles to exercise authorization, tampered callbacks, malformed data, underpayment,
cross-gateway events, retry handling, stored XSS, TLS options, logging, checkout and
refund requests. CI also checks syntax on PHP 7.4, 8.2 and 8.5.

This is not a full WordPress/WooCommerce integration test or an independent
penetration test. Before production rollout, verify checkout, guest checkout,
pay-for-order, physical/virtual fulfillment, notifications and full refunds against
Fundiin sandbox with your supported WordPress/WooCommerce versions. Concurrent
callbacks are not serialized by this plugin; sequential retries are guarded by
order status and WooCommerce's payment/stock handling.

Older versions wrote merchant secrets and customer payloads to logs. After
upgrading, rotate credentials that may have been exposed and remove old copies of
those logs from the server and any accessible backups. New logging is opt-in and
contains payment event identifiers only.
