# Sokoni Client Demo Script

~15 minutes, two devices (or one device + one emulator) so the buyer→seller→buyer loop is visible live. Both devices need the release APK installed and pointed at the same API (see [DEPLOY.md](DEPLOY.md) for the `--dart-define=API_BASE_URL=...` build flag, or run both against a local API for a fully offline demo).

Seed the API first (`php artisan migrate:fresh --seed`) so the feed isn't empty — this gives 10 categories, 20 sellers (14 verified), 120 products, and a realistic order/review history to browse before the live portion starts.

## 1. Cold open (Device A)

- Launch the app from a killed state. Point out: the logo scale-and-settle splash (skippable, 900ms cap), then a settled home feed — no loading spinner left hanging, shimmer skeletons if the network is genuinely slow.
- **Talking point**: "Near You" defaults to a 5km radius around the device's real location; if location is denied, it falls back to a Dar es Salaam district picker instead of a blank screen.

## 2. Discovery

- Tap a category chip — the selection pill slides, the feed cross-fades rather than flashing to a spinner.
- Open the filter sheet, change the radius — feed re-queries live.
- Toggle List ⇄ Map — real seller pin positions (not approximated), tap a pin to open the seller quick-view sheet.
- Pull to refresh — point out the custom Sokoni-bag-mark indicator, not the stock platform spinner.
- Search a product name — debounced, and the search history is there on a second visit to the tab.

## 3. Product & shop

- Tap a product card — the cover image flies into the detail page (hero transition, corner radius animating too), not a hard cut.
- Swipe the media carousel if the product has more than one photo/video; if a video is in the mix, it autoplays muted once >60% visible, tap to unmute.
- Tap the seller name to open the shop profile — products / media grid / reviews tabs, review distribution bar, verified badge if applicable.
- Favourite the product (heart icon) — works only if signed in; if not, it prompts sign-in rather than silently failing.
- Tap "Message seller" — opens (or resumes) a chat thread with this product pinned at the top.

## 4. Sign in (Device A, if not already)

- From Profile, sign in with phone OTP — enter a number, the code is logged server-side (`storage/logs/laravel.log`) rather than SMS-sent (BLOCKERS.md item — no SMS gateway configured yet). Read the code from the log, or from an admin's terminal, and enter it.
- **Talking point**: Google/Apple buttons are real, working native flows — currently hidden entirely (not shown disabled) until their blocked credentials (BLOCKERS.md) are supplied, per Apple App Review's own Guideline 2.1(a) finding that a disabled button with a tooltip still reads as "unresponsive" on a touch device. They appear automatically the moment the credentials land, no code changes needed. Facebook sign-in was removed entirely (not just disabled) after it was found hanging app startup on at least one real Android 16 device.
- First-ever sign-in shows the Terms & Privacy acceptance screen — can't be dismissed without accepting, matching CLAUDE.md's acceptance-recording requirement.

## 5. Become a seller (Device A)

- Profile → "Start selling" → the 4-step wizard:
  1. **Business**: shop name, handle (`@handle`, validated live), category, WhatsApp number.
  2. **Location**: drag the map pin — address/region/district reverse-geocode automatically, editable.
  3. **Identity**: NIDA number (20-digit format check) + a photo (camera or gallery, compressed client-side).
  4. **Licence**: business/trading licence, image or PDF, 5MB cap.
- Completion shows the full-screen drawn-checkmark success animation, then the My Shop tab shows a "Pending verification" badge.
- **Talking point**: the seller can add products immediately, even while pending — they just don't show up in public search until an admin verifies the account. Demonstrate this now: add a product with 2–3 photos and (optionally) a short video clip.

## 6. Verify the seller (Filament admin, `https://api.sokoni.co.tz/admin` or local equivalent)

- Sign in as `admin@sokoni.co.tz`.
- Trust & Safety → Seller Profiles → the new seller is at the top of the queue (pending sorts first). Open it — NIDA photo, licence file link, and a Google Maps link to the pinned location, side by side.
- Click **Verify**. Point out the dashboard stats widget's "Pending verifications" count drop by one.
- Back in the app (pull to refresh, or re-open the shop profile), the seller's product is now live in public search.

## 7. Order it (Device B)

- On the second device, browse to that same product (search by name, or via the shop's `@handle`).
- Add to cart (bounce-badged cart icon, top-right of Home).
- Checkout: pickup or delivery, cash-on-delivery/pay-on-pickup (v1 payment methods — CLAUDE.md scopes a mobile-money gateway out for later), place order.
- Success checkmark animation, then a straight hop into the order's auto-opened chat thread with the seller.

## 8. Seller side (Device A)

- **Talking point**: a push notification would have landed here the moment the order was placed — currently logged rather than delivered (BLOCKERS.md item 1, no Firebase project yet); show the log line as proof the notification fired correctly.
- Orders tab (now showing a "Shop orders" sub-tab, since this account is a seller) → open the new order → timeline shows `pending`.
- Reply in the chat thread — Device B sees it arrive within 5 seconds (polling, no WebSocket server — by design for shared hosting).
- Advance the order: Accepted → Ready → Completed, one tap each. Device B's order detail updates on its own next poll/refresh.

## 9. Review it (Device B)

- Once the order shows `completed`, a "Leave a review" button appears on the order detail screen — star rating + optional comment.
- Submit it. Back on Device A's shop profile (or the Filament admin), the review is visible with the seller's ability to reply once.

## 10. Trust & safety (either device)

- Tap the flag/report icon on any product, shop, or (long-press) chat message — reason picker, submits to the moderation queue.
- In the admin, Trust & Safety → Reports → show the pending report, then **Uphold**/**Dismiss**, or a direct **Hide** / **Warn** / **Suspend** / **Ban** action on the underlying content/user.

## 11. Language switch

- Settings (or the system locale) → switch to Kiswahili. Everything re-renders in Swahili immediately — no restart needed, no English strings left over. Re-run steps 2–3 briefly to show the feed, product detail, and checkout flow all fully localized.

## 12. Offline resilience (optional, if time allows)

- Turn on airplane mode on Device A mid-browse. The feed keeps showing its last-loaded results under a "showing saved results" banner rather than going blank. Turn airplane mode back off — the banner clears on the next successful fetch.

---

**If something in the above doesn't work live**: every mocked external call (SMS, NIDA live verification, payment gateway, push delivery) is a deliberate, documented stub behind a real interface — see `BLOCKERS.md` for exactly which four credentials unlock which pieces, and `DECISIONS.md` for the reasoning behind every other implementation choice made along the way.

## App Store Connect Review Notes

Apple's own App Review rejected earlier submissions because the reviewer has no Tanzanian phone number and can't receive a real OTP (`docs/SMS.md`'s "App Review bypass" section), and then (build 1.0 (7), Guideline 2.1) because the demo credentials were typed into the **username/password** screen — the app's default sign-in — while only the OTP route knew about the review account.

The review account now works on **both** routes, configured entirely in the production `.env` (never for a real user's number):

| `.env` key | Route | Effect |
| --- | --- | --- |
| `REVIEW_ACCOUNT_PHONE` | both | The one designated demo account, E.164 (`+255783029233`). |
| `REVIEW_ACCOUNT_PASSWORD` | username/password (default screen) | This password signs straight in — no two-factor, no new-device code, no set-a-password prompt (`App\Services\Auth\ReviewAccount`). The phone may be typed as `+255783029233`, `0783029233`, `783 029 233`, or the account's username. |
| `REVIEW_ACCOUNT_CODE` | "Sign in with phone number instead" | This fixed code is accepted instead of an SMS (`PhoneOtpService`). |

Each key is independent; a blank `REVIEW_ACCOUNT_PASSWORD` or `REVIEW_ACCOUNT_CODE` switches that route's bypass off. The demo user must already exist with that phone (signing in once through the OTP bypass creates it). Give it a username and stored password, and accept Terms so the Terms screen doesn't appear after sign-in:

```bash
php artisan tinker --execute="\$u = App\Models\User::where('phone', '+255783029233')->firstOrFail(); \$u->forceFill(['username' => \$u->username ?? 'appreview', 'password' => 'Review2026', 'two_factor_enabled' => false, 'terms_accepted_at' => \$u->terms_accepted_at ?? now(), 'terms_version' => \$u->terms_version ?? '1.0'])->save(); echo json_encode(\$u->only('id', 'phone', 'username', 'two_factor_enabled', 'terms_accepted_at', 'banned_at'));"
```

Fill in App Store Connect's **App Review Information** section like this:

- **Sign-in required**: Yes
- **Username**: `+255783029233`
- **Password**: the value of `REVIEW_ACCOUNT_PASSWORD` (currently `Review2026`)
- **Notes**:
  > Enter the username and password above on the first sign-in screen and tap "Sign in" — no verification code is needed for this account. Alternatively, tap "Sign in with phone number instead", enter 783 029 233, tap "Send code", and enter `<REVIEW_ACCOUNT_CODE>` (no real SMS is sent for this one review account).

Browsing the product feed, shop profiles, and search all work fully without signing in at all — sign-in is only required to place an order, start a chat, or favourite/follow something (Guideline 5.1.1(i), confirmed compliant — `api/routes/api.php`'s own public-routes comment documents this design intent directly). If the reviewer doesn't want to sign in at all, point them to the feed and product/shop pages as the way to exercise the app's core browsing functionality without the demo account.
