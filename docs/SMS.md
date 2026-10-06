# SMS delivery (phone OTP)

## App Review bypass — one fixed-code demo account

Apple App Review rejected an earlier submission because the reviewer has no
Tanzanian phone number and can't receive a real OTP. `PhoneOtpService`
(`api/app/Services/Otp/PhoneOtpService.php`) has a narrow bypass for exactly
this: when `REVIEW_ACCOUNT_PHONE` and `REVIEW_ACCOUNT_CODE` are both set in
`.env`, requesting an OTP for that **exact** phone number sends no SMS at
all and starts no real one-time code — `verifyCode()` for that number
accepts only the configured fixed code, which (unlike a real code) isn't
consumed after one use, since the reviewer needs to sign in repeatedly
across a review session. Every other phone number is completely
unaffected — the real random-code-plus-SMS path runs exactly as before.

```
REVIEW_ACCOUNT_PHONE=
REVIEW_ACCOUNT_CODE=
```

**Both must be set for the bypass to exist at all** — `isReviewAccount()`
requires non-empty values for both before anything is compared, so a
production `.env` that never sets these (every environment except the one
designated review account) never takes this branch. Leave both blank
locally and in CI.

Every use — a request, a correct verify, or a wrong-code attempt — is
logged to the `sms` channel (`Log::channel('sms')->info(...)`, same
channel every gateway uses), so the bypass being exercised is visible even
though no real send happens. The existing `otp`/`otp-verify` rate limiters
(`AppServiceProvider`, phone-keyed, 3 per 15 minutes) apply to this number
exactly like any other — nothing here runs outside them.

**Do not use a real user's phone number for this.** It's meant for one
designated App Store Connect demo account only — see `docs/DEMO.md` for
what to put in Review Notes.

Sokoni's phone sign-in sends a 6-digit OTP through the `SmsGateway` interface
(`api/app/Services/Sms/SmsGateway.php`). The active implementation is picked
by `SMS_DRIVER` in `.env` (`AppServiceProvider::register()`), not by which
credentials happen to be set:

- `SMS_DRIVER=kibonet` — `KibonetSmsGateway`, Sokoni's **active** SMS
  provider (swapped in from Textify, 2026-10).
- `SMS_DRIVER=textify` — `TextifySmsGateway`, kept as a selectable
  alternative. This was the active provider until the client switched to
  Kibonet — left in place in case it's ever useful again, not because it's
  expected to be used.
- `SMS_DRIVER=africastalking` — `AfricasTalkingSmsGateway`, also kept as a
  selectable alternative. An earlier active provider, before Textify.
- `SMS_DRIVER=beem` — `BeemSmsGateway`, also kept as a selectable
  alternative. This has never been the client's actual provider — an
  earlier version of this doc assumed it would be, before the client's real
  account was confirmed (first as Africa's Talking, then Textify, now
  Kibonet).
- Anything else, including `SMS_DRIVER` left unset — `LogSmsGateway`, which
  writes the code to the log instead of sending it. This is what local dev
  and CI always use.

No code change is needed to switch drivers — set `SMS_DRIVER` and the
credentials it needs, nothing else.

## Kibonet — the active provider

**Docs**: https://sms.kibonet.co.tz — the vendor's own documentation does
not state a request/response shape as explicitly as Textify's does; the
contract below reflects what the vendor confirmed directly, not a published
spec, so `KibonetSmsGateway` is deliberately defensive about response shape
(see below).

```
SMS_DRIVER=kibonet
KIBONET_API_KEY=
KIBONET_API_SECRET=
KIBONET_SENDER_ID=SOKONI
KIBONET_ENDPOINT=https://sms.kibonet.co.tz/api/v1/vendor/message/send
KIBONET_NUMBER_FORMAT=255
KIBONET_DELIVERY_REPORT_URL=https://sokoni.co.tz/sms/delivery-callback
```

### API contract

`KibonetSmsGateway` posts a JSON body to `KIBONET_ENDPOINT`, authenticated
via two plain headers (not Bearer, not Basic Auth):

```
Content-Type: application/json
api_key: <KIBONET_API_KEY>
api_secret: <KIBONET_API_SECRET>

{
  "senderId": "SOKONI",
  "messageType": "text",
  "message": "Your Sokoni code: 482913. Do not share it.",
  "contacts": "255754123456",
  "deliveryReportUrl": "https://sokoni.co.tz/sms/delivery-callback"
}
```

**`contacts` is a comma-separated string, not a JSON array.** Every current
caller (`sendOtp`, and the bulk-SMS admin tool's own per-recipient loop in
`ProcessSmsBlasts`) only ever sends one number per call, but the gateway's
internal `send()` method takes a list and joins it, so a future caller that
wants one call per batch doesn't need a second code path.

**`deliveryReportUrl` is omitted entirely when `KIBONET_DELIVERY_REPORT_URL`
is unset** — rather than send a dead callback URL — and the field is a
plain config value specifically so it can point somewhere else (staging, a
different domain) without a code change.

**Numbers — format undocumented by the vendor.** Kibonet's own
documentation describes only "recipient phone numbers," with no stated
shape. The app stores E.164 (`+255XXXXXXXXX`); `KIBONET_NUMBER_FORMAT`
(default `255`, no leading `+`) is a config value rather than a hardcoded
conversion specifically because this is a guess at the common convention
among Tanzanian SMS panels, not a confirmed contract — if it turns out
wrong, it changes without a redeploy. Both the original and converted
number are always logged to the `sms` channel so a mis-format is visible
immediately.

**No documented response body — only an explicit failure value is treated
as a failure, on any HTTP status.** Unlike Textify (which requires an
explicit `success` field and treats its absence as malformed), Kibonet's
docs show no response body at all, so `KibonetSmsGateway` reads a 2xx with
no recognisable `success`/`status` field as success — that's the expected
shape, not a malformed one. Specifically:

- Any non-2xx HTTP status is always a failure, regardless of body.
- A 2xx with `"success": false`, or `"status"` equal to `failed`/
  `failure`/`error` (case-insensitive), is a failure even though the HTTP
  call itself succeeded.
- A 2xx with no such field, or an empty body, is success.

Every failure throws a `RuntimeException` carrying the vendor's own
`message`/`error` field when present, never a generic string.

### Delivery reports

Kibonet POSTs a delivery report to `KIBONET_DELIVERY_REPORT_URL` out of
band, after a send. `SmsDeliveryCallbackController`
(`app/Http/Controllers/Web/SmsDeliveryCallbackController.php`, routed at
`POST /sms/delivery-callback` in `routes/web.php`) logs the entire payload
verbatim to the `sms` channel — Kibonet doesn't document this payload's
shape either, so nothing is parsed into named fields that might not match;
this is diagnostics, not a status update anything else in the app currently
reads. The route is outside CSRF protection
(`bootstrap/app.php`'s `validateCsrfTokens(except: [...])`) and
unauthenticated, since Kibonet — not a browser with a Sokoni session — is
the caller.

## Textify Africa — kept as a selectable alternative

**Docs**: https://docs.textify.africa

```
SMS_DRIVER=textify
TEXTIFY_API_KEY=            # issued by Textify, already prefixed txf_ — paste as-is
TEXTIFY_SENDER_NAME=Textify
TEXTIFY_ENDPOINT=https://portal.textify.africa/api/v1/messages
```

### API contract

`TextifySmsGateway` posts a JSON body to `TEXTIFY_ENDPOINT`, authenticated
via a bearer token (the API key already carries its own `txf_` prefix — the
gateway does not add one):

```
Authorization: Bearer txf_xxxxxxxxxxxxxxxx
Content-Type: application/json

{
  "sender_name": "Sokoni",
  "is_scheduled": false,
  "messages": [
    { "receiver": "0754123456", "content": "Your Sokoni code: 482913. Do not share it." }
  ]
}
```

**Numbers are local format, not E.164.** Textify's documented example uses
`0712345678`, so the app's stored `+255XXXXXXXXX` is converted at this
boundary — `+255` stripped, `0` prefixed — immediately before the request,
and both the original and converted number are always logged to the `sms`
channel so a mis-format is visible immediately rather than discovered from
a support ticket, whether or not the send itself succeeds.

**`success: false` is a failure regardless of HTTP status.** A success
response looks like:

```json
{ "success": true, "status_code": 200 }
```

A failure — which can arrive on a 401, a 400, or even a 200 — looks like:

```json
{ "success": false, "status_code": 401, "message": "invalid api key", "error": "invalid_token" }
```

`TextifySmsGateway` reads `success` from the body, not the HTTP status
code, and throws a `RuntimeException` carrying both `message` and `error`
whenever it isn't `true`.

**`invalid_token` is handled explicitly, not as just another failure
string.** It means the API key is wrong or has been revoked, which stops
every subsequent send until someone notices — logged at **critical** level
(every other failure logs at `error`) specifically so it's unmissable, then
still throws the same way any other failure does.

A response with no `success` field at all (an unexpected shape, a proxy
error page, truncated JSON) is treated as malformed and also throws,
logged at `error` — a missing field is never silently read as `false`, or
worse, ignored.

## Logging — the `sms` channel

Every request and response, on every driver, goes to a dedicated `sms` log
channel (`storage/logs/sms.log`, `config/logging.php`, daily rotation) —
separate from `laravel.log` on purpose, so a delivery problem is
diagnosable by opening one small, obviously-named file in cPanel's File
Manager, with no shell access needed:

- One `info` line per request (destination, sender/sender name).
- One `info` line per response (HTTP status, raw body).
- One `error` line for any failed send.
- One `critical` line for a failure mode that stops *every* send until
  someone notices — Textify's `invalid_token`, Africa's Talking's
  `InsufficientBalance`. Kibonet has no equivalent documented failure
  string, so an auth/balance failure there currently logs at the routine
  `error` level — revisit if the vendor documents one.

## Message text

Both languages, deliberately short (a single SMS segment):

- English: `Your Sokoni code: 482913. Do not share it.`
- Swahili: `Namba yako ya Sokoni: 482913. Usimpe mtu yeyote.`

Every gateway picks between them from the same `$locale` parameter
(`SmsGateway::sendOtp(string $phone, string $code, string $locale = 'en')`).
The **website's** OTP flow (`OtpAuthController`) already has a reliable
locale for every request via `SetWebLocale` (the site's own EN/SW toggle),
so it passes `app()->getLocale()` through and both languages are reachable
today. The **mobile API** (`POST /auth/otp/request`) has no equivalent
locale-detection middleware — it accepts an optional `locale` field
(`en`/`sw`) in the request body and defaults to English when the field is
absent. **The Flutter client does not currently send this field**, so
mobile OTPs are English-only until the app is updated to pass
`Localizations.localeOf(context).languageCode` on that call — a small,
contained client change, not done as part of any backend-only round so far.

## Rate limiting

`POST /auth/otp/request` (and `POST /auth/check-phone`, which shares the
same limiter despite never sending an SMS itself) is throttled to **3
requests per phone number per 15 minutes** (`AppServiceProvider`'s `otp`
RateLimiter, keyed on the `phone` request field, not the caller's IP) — a
4th request for the same number within the window gets a 429. This protects
both the phone (against being bombed with codes) and, with a real paid
gateway wired up, the client's SMS credit — true of every driver above,
not just the currently-active one.

## Africa's Talking — kept as a selectable alternative

**Client account**: dashboard at
https://account.africastalking.com/apps/gladxtazoy, app name `skyfar`,
username `skyfar`, sender ID `skyfar`.

```
SMS_DRIVER=africastalking
AT_USERNAME=skyfar
AT_API_KEY=                 # from the dashboard above — Settings → API Key
AT_SENDER_ID=skyfar
AT_SANDBOX=false
```

Set `AT_SANDBOX=true` to send through Africa's Talking's sandbox endpoint
instead of the live one — useful for an end-to-end check that doesn't spend
real credit or reach a real handset.

### API contract

`AfricasTalkingSmsGateway` posts form-encoded data to:

- Live: `https://api.africastalking.com/version1/messaging`
- Sandbox (`AT_SANDBOX=true`): `https://api.sandbox.africastalking.com/version1/messaging`

Authenticated via an `apiKey` header (not Basic Auth, and not part of the
form body):

```
apiKey: <AT_API_KEY>

username=skyfar&to=%2B255754123456&message=Your+Sokoni+code%3A+482913.+Do+not+share+it.&from=skyfar
```

`to` is the full E.164 number with the leading `+` — the app already stores
phone numbers this way, but the gateway re-adds a missing `+` defensively
at the boundary regardless, since this is the last point before an external
API call where a mistake would be expensive to trace.

**HTTP 201 does not mean delivered.** A 201 only means Africa's Talking
accepted the request for processing. The real per-recipient outcome is
inside the response body:

```json
{
  "SMSMessageData": {
    "Message": "Sent to 1/1 Total Cost: TZS 32.0000",
    "Recipients": [
      {
        "statusCode": 101,
        "number": "+255754123456",
        "status": "Success",
        "cost": "TZS 32.0000",
        "messageId": "ATXid_xxx"
      }
    ]
  }
}
```

Anything other than `"status": "Success"` for a recipient is treated as a
failure and throws a `RuntimeException` — the message/gateway never
swallows a rejected send.

**Insufficient balance is handled explicitly, not just as another failure
status.** Africa's Talking reports it as `"status": "InsufficientBalance"`
(`statusCode` 405) — this is prepaid SMS, and running out of balance is the
single most common way sending silently stops working in production. When
this happens, `AfricasTalkingSmsGateway` logs at **critical** level (not the
routine `error` level every other failure gets) specifically so it's easy
to grep for and impossible to miss, then still throws the same way any
other failure does.

## Beem Africa — kept as a selectable alternative

```
SMS_DRIVER=beem
BEEM_SMS_API_KEY=...
BEEM_SMS_SECRET_KEY=...
BEEM_SMS_SENDER_ID=SOKONI
```

Beem is a Tanzania-based aggregator with direct routes to all three local
carriers — a reasonable choice in the abstract, but **not what this
client's account actually uses**. `BeemSmsGateway` posts to
`https://apisms.beem.africa/v1/send` with HTTP Basic Auth (API key as
username, secret key as password); a successful response includes
`"successful": true`, anything else is treated as a send failure.

## Testing without sending real SMS

- **Local dev / automated tests**: leave `SMS_DRIVER` unset. `LogSmsGateway`
  writes `[MOCK SMS] OTP for {phone} ({locale}): {code}` to the log, and
  every feature test reads the code straight out of
  `Cache::get("otp:{$phone}")` rather than depending on which gateway is
  bound (see `tests/Feature/Auth/PhoneOtpTest.php`).
- **Gateway unit tests**: `tests/Unit/Services/Sms/KibonetSmsGatewayTest.php`,
  `TextifySmsGatewayTest.php`, `AfricasTalkingSmsGatewayTest.php` and
  `BeemSmsGatewayTest.php` use `Http::fake()` to assert the request shape
  and every failure path for each gateway — no network call, no real
  account, for any of the four. Kibonet's cover: the expected
  `api_key`/`api_secret` headers and JSON body shape, the comma-joined
  `contacts` field, the Swahili message, the configurable number-format
  conversion, `deliveryReportUrl` included when configured and omitted
  entirely when not, a non-2xx response treated as a failure, an explicit
  `success: false` or `status: failed` treated as a failure even on a 200,
  and — unlike Textify — a 2xx with *no* recognisable success/status field
  at all treated as success, since Kibonet's own docs show no response
  body. Textify's cover: a successful send with the expected JSON shape and
  local-format number conversion, the Swahili message, `success: false`
  treated as a failure regardless of HTTP status, `invalid_token` logged
  at `critical` and named explicitly in the thrown message, and a
  malformed response (no `success` field, or a non-JSON body) also
  throwing rather than being silently read as success.
- **Driver-level feature tests**: `tests/Feature/Auth/PhoneOtpKibonetDriverTest.php`
  exercises the real `/api/auth/otp/request` route with `SMS_DRIVER=kibonet`
  and `Http::fake()` — confirming the route actually reaches Kibonet with
  the right shape, that an auth failure from Kibonet surfaces as a server
  error rather than a silent 200, and that the rate limiter (below) still
  applies when Kibonet is the active driver.
  `tests/Feature/Web/SmsDeliveryCallbackTest.php` confirms the delivery-report
  route accepts a POST without a CSRF token and logs the payload verbatim.
  `tests/Feature/Auth/ReviewAccountOtpBypassTest.php` covers the App Review
  bypass above: no SMS/cached code for the review number, the fixed code
  signing it in and being reusable (unlike a real code), a wrong code for
  that same number still rejected, the fixed code *not* working for any
  other number, every use logged, the bypass genuinely not existing when
  either env value is blank, and the rate limiter still applying to the
  review number like any other.
- **Binding test**: `tests/Unit/Services/Sms/SmsGatewayBindingTest.php` locks
  in that an unset or unrecognised `SMS_DRIVER` always resolves to
  `LogSmsGateway`, and that each of `kibonet`/`textify`/`africastalking`/
  `beem` resolves to its own gateway class, so a misconfigured environment
  can never silently start sending real SMS through the wrong provider.
- **Rate limit**: `PhoneOtpTest`'s rate-limit tests confirm the 4th request
  for the same number within 15 minutes is rejected with 429, and that the
  limit is scoped per phone number, not global — this is enforced above the
  gateway layer, so it applies identically no matter which driver is active;
  `PhoneOtpKibonetDriverTest` confirms the same thing specifically with
  Kibonet as the active driver.
- **Staging with a real Africa's Talking account**: set `AT_SANDBOX=true` —
  Africa's Talking's sandbox mode accepts real-looking requests without
  delivering to a real handset or spending live credit. Neither Kibonet's
  nor Textify's API has an equivalent sandbox flag documented; test against
  either with a real, low-balance account instead.
