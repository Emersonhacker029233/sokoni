# SMS delivery (phone OTP)

Sokoni's phone sign-in sends a 6-digit OTP through the `SmsGateway` interface
(`api/app/Services/Sms/SmsGateway.php`). Two implementations exist:

- `LogSmsGateway` — writes the code to the Laravel log instead of sending it.
  Used automatically whenever Beem credentials aren't configured (local dev,
  CI, this environment).
- `BeemSmsGateway` — sends a real SMS through
  [Beem Africa](https://beem.africa)'s send-SMS API. Bound automatically once
  both `BEEM_SMS_API_KEY` and `BEEM_SMS_SECRET_KEY` are set in `.env` — see
  `AppServiceProvider::register()`.

No code change is needed to switch between them; the binding is
credential-driven.

## Why Beem Africa

Beem is a Tanzania-based aggregator with direct routes to all three local
carriers (Vodacom, Tigo/Mixx, Airtel), which matters for OTP latency — a
generic international SMS aggregator often routes Tanzanian traffic through
a slower international hop. Pricing is quoted in TZS and billed locally, and
the send API is a single JSON POST with no SDK dependency.

If Beem ever becomes unavailable, `SmsGateway` is the seam to swap in a
different provider (e.g. Africa's Talking, Twilio) — implement the interface,
add its config block, and change the `AppServiceProvider` binding condition.
No other code depends on which gateway is active.

## What a production account needs

1. **Sign up** at https://beem.africa and complete their KYC/business
   verification — this is what a real "credentials" blocker looks like for
   this integration; it isn't a same-day API key.
2. **Register a sender ID.** `BEEM_SMS_SENDER_ID` (`source_addr` in the API
   body) must be pre-approved by Beem — an arbitrary string is rejected in
   production, even though Beem's sandbox accepts the default `INFO` sender
   for testing. Request something recognisable, e.g. `SOKONI`, subject to
   Beem's length/character rules (typically ≤11 alphanumeric characters).
3. **Fund the account.** Beem SMS is prepaid; OTP sends fail once the
   balance is exhausted — `BeemSmsGateway::sendOtp()` throws a
   `RuntimeException` in that case (surfaced to the caller as a 500 from
   `POST /auth/otp/request`), it does not fail silently.
4. **Get the API key and secret key** from the Beem dashboard (Settings →
   API Keys) and set them in the production `.env`:
   ```
   BEEM_SMS_API_KEY=...
   BEEM_SMS_SECRET_KEY=...
   BEEM_SMS_SENDER_ID=SOKONI
   ```

## API contract

`BeemSmsGateway` posts to `https://apisms.beem.africa/v1/send` with HTTP
Basic Auth (API key as username, secret key as password):

```json
{
  "source_addr": "SOKONI",
  "encoding": 0,
  "message": "Your Sokoni verification code is 482913. It expires in 5 minutes.",
  "recipients": [{"recipient_id": 1, "dest_addr": "255754123456"}]
}
```

`dest_addr` is the E.164 phone number with the leading `+` stripped — Beem
expects a bare MSISDN. A successful response includes `"successful": true`;
anything else (HTTP failure, `successful: false`, or a non-2xx code) is
treated as a send failure and throws.

## Testing without sending real SMS

- **Local dev / automated tests**: leave `BEEM_SMS_API_KEY` /
  `BEEM_SMS_SECRET_KEY` unset. `LogSmsGateway` writes
  `[MOCK SMS] OTP for {phone}: {code}` to the log, and every feature test
  reads the code straight out of `Cache::get("otp:{$phone}")` rather than
  depending on which gateway is bound (see
  `tests/Feature/Auth/PhoneOtpTest.php`).
- **Gateway unit test**: `tests/Unit/Services/Sms/BeemSmsGatewayTest.php`
  uses `Http::fake()` to assert the request shape and the failure path,
  without a network call or a real Beem account.
- **Staging with a real account**: Beem's sandbox mode (ask their support to
  enable it on your account) accepts the default `INFO` sender ID and does
  not deliver to real handsets, useful for an end-to-end check before a
  sender ID is approved.
