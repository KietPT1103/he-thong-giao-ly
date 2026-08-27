# Spec: Device-bound QR attendance

## Objective

Allow a child to activate one personal phone after signing in once, then use the
phone's native camera to scan teacher attendance QR codes without an account
session. The device credential grants attendance check-in only; it must never
authenticate account, profile, or other child APIs.

## Tech Stack

- Laravel 12, session/Sanctum authentication, PostgreSQL production database.
- Vue 3, TypeScript, Vue Router, Axios, Ant Design Vue, and `qrcode.vue`.
- An opaque random credential stored in an encrypted, `HttpOnly`, same-site
  cookie; only its SHA-256 hash is stored in `child_devices`.

## API Contract

- `GET /api/child-device`
  - Requires an authenticated child account with `check-in-attendance-qr`.
  - Returns `{ is_active, is_current_device, activated_at, expires_at, last_used_at }`.
- `POST /api/child-device`
  - Requires the same authenticated child account and the sensitive-action
    throttle.
  - Rotates the child's single device credential and sets the cookie.
  - Never returns the raw credential.
- `DELETE /api/child-device`
  - Revokes the child's credential and expires the cookie.
- `POST /api/attendance/qr/check-in`
  - Does not require an account session.
  - Requires the device credential middleware and the existing QR scan throttle.
  - Preserves existing success payloads and QR/business error semantics.
  - Missing, invalid, or revoked credentials return HTTP 401 with code
    `DEVICE_ACTIVATION_REQUIRED`.

## User Flow

1. The child signs in once and opens `/child/my-qr`.
2. The child activates the current phone. Any previous phone credential becomes
   invalid immediately.
3. The teacher creates QR exactly as today, while the QR content becomes a
   same-origin `/attendance/scan?token=...` URL.
4. The child scans with the phone's native camera. The public scan page submits
   automatically with the device cookie.
5. The server validates credential, account/child status, permission, class,
   token signature, expiry, and duplicate attendance before recording.
6. Clearing browser data, using private mode, changing browser, or rotating the
   credential requires activation again.

## Project Structure

- `database/migrations`, `app/Models`: device persistence.
- `app/Services`, `app/Http/Middleware`: credential lifecycle and route-scoped
  authentication.
- `app/Http/Controllers/Api`, `routes/api.php`: activation and check-in APIs.
- `resources/js/api`, `resources/js/views`, `resources/js/router`: activation,
  public deep-link check-in, and QR URL rendering.
- `tests/Feature/QrAttendanceTest.php`: integration and abuse-case coverage.

## Code Style

Follow the existing Laravel/Vue conventions and keep the credential boundary
explicit:

```php
$device = $credentials->resolve($request->cookie($credentials->cookieName()));
abort_unless($device, 401);
```

Raw credentials are never logged, serialized, or stored in plaintext.

## Testing Strategy

- Feature tests with `RefreshDatabase` for activation, cookie issuance,
  check-in without a login session, revocation, invalid credential, wrong class,
  expiry, and duplicate submission.
- Existing QR feature suite must remain green.
- `vue-tsc`, production build, and browser checks at desktop/mobile widths.

## Commands

```text
Focused backend: php artisan test tests/Feature/QrAttendanceTest.php
Full backend:    php artisan test
PHP style:       vendor/bin/pint --test
Type check:      npm run type-check
Build:           npm run build
```

## Boundaries

- Always: validate token input, hash device secrets, use secure cookie flags,
  preserve rate limiting and unique attendance records, audit activation/revoke.
- Ask first: changing teacher workflow, adding device fingerprinting/location,
  allowing multiple active devices, or changing the 15-minute late threshold.
- Never: expose raw device tokens, place PII in the QR URL, use localStorage for
  credentials, or let device authentication access non-attendance APIs.

## Success Criteria

- A bound phone can check in after logout without an account session.
- An unbound, revoked, wrong-child, wrong-class, expired, or duplicate request is
  handled predictably and cannot create an unauthorized attendance record.
- The teacher creation UI remains unchanged apart from QR payload content.
- The public result page clearly shows processing, success, duplicate,
  activation-required, and error states on mobile.

## Open Questions

None for this increment. One active personal phone per child is the approved
policy.
