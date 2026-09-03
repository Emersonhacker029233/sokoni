# SMS delivery (phone OTP)

Sokoni's phone sign-in sends a 6-digit OTP through the `SmsGateway` interface
(`api/app/Services/Sms/SmsGateway.php`). The active implementation is picked
by `SMS_DRIVER` in `.env` (`AppServiceProvider::register()`), not by which
credentials happen to be set:

- `SMS_DRIVER=textify` — `TextifySmsGateway`, Sokoni's **active** SMS
  provider.
- `SMS_DRIVER=africastalking` — `AfricasTalkingSmsGateway`, kept as a
  selectable alternative. This was the active provider until the client
  switched to Textify — left in place in case it's ever useful again, not
  because it's expected to be used.
- `SMS_DRIVER=beem` — `BeemSmsGateway`, also kept as a selectable
  alternative. This has never been the client's actual provider — an
  earlier version of this doc assumed it would be, before the client's real
  account was confirmed (first as Africa's Talking, now as Textify).
- Anything else, including `SMS_DRIVER` left unset — `LogSmsGateway`, which
  writes the code to the log instead of sending it. This is what local dev
  and CI always use.

No code change is needed to switch drivers — set `SMS_DRIVER` and the
credentials it needs, nothing else.

## Textify Africa — the active provider

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
  `InsufficientBalance`.

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
- **Gateway unit tests**: `tests/Unit/Services/Sms/TextifySmsGatewayTest.php`,
  `AfricasTalkingSmsGatewayTest.php` and `BeemSmsGatewayTest.php` use
  `Http::fake()` to assert the request shape and every failure path for
  each gateway — no network call, no real account, for any of the three.
  Textify's cover: a successful send with the expected JSON shape and
  local-format number conversion, the Swahili message, `success: false`
  treated as a failure regardless of HTTP status, `invalid_token` logged
  at `critical` and named explicitly in the thrown message, and a
  malformed response (no `success` field, or a non-JSON body) also
  throwing rather than being silently read as success.
- **Binding test**: `tests/Unit/Services/Sms/SmsGatewayBindingTest.php` locks
  in that an unset or unrecognised `SMS_DRIVER` always resolves to
  `LogSmsGateway`, and that each of `textify`/`africastalking`/`beem`
  resolves to its own gateway class, so a misconfigured environment can
  never silently start sending real SMS through the wrong provider.
- **Rate limit**: `PhoneOtpTest`'s rate-limit tests confirm the 4th request
  for the same number within 15 minutes is rejected with 429, and that the
  limit is scoped per phone number, not global — this is enforced above the
  gateway layer, so it applies identically no matter which driver is active.
- **Staging with a real Africa's Talking account**: set `AT_SANDBOX=true` —
  Africa's Talking's sandbox mode accepts real-looking requests without
  delivering to a real handset or spending live credit. Textify's API has
  no equivalent sandbox flag documented; test against it with a real,
  low-balance account instead.
