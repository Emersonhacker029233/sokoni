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
- **Talking point**: Google/Apple buttons are real, working native flows, currently shown disabled with a tooltip — they light up the moment their blocked credentials (BLOCKERS.md) are supplied, no code changes needed. Facebook sign-in was removed entirely (not just disabled) after it was found hanging app startup on at least one real Android 16 device.
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
