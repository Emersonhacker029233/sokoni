# User-generated content and moderation audit (Apple Guideline 1.2)

Audit of branch `fix/ugc-moderation`, cut from `main` at `b4b42a4`, the code of Sokoni iOS 1.0 (7), version `1.0.0+7`. Nothing from `feature/delivery` is on this branch. No code is changed yet.

Apple rejected build 7 under Guideline 1.2 and asks for five things:

| # | Apple requirement | Today | Verdict |
|---|---|---|---|
| 1 | Users agree to terms (EULA) **before** registering or logging in, and the terms say there is zero tolerance for objectionable content and abusive users | Only "Create account" shows terms before the account exists. Every sign-in route (password, phone code, forgot password, Google/Apple) shows terms **after** sign-in, and only to an account that never accepted. The wording has no zero-tolerance statement. | **Missing** |
| 2 | A way to flag objectionable content | Products and shops have a flag icon. Chat messages have a hidden long-press. Comments, reviews, showcases, updates, offers and user profiles have no report action in the app. The reasons are business-only, with no "offensive/abusive" option. | **Partial** |
| 3 | A way to block abusive users that notifies the developer and instantly removes the blocked user's content from the blocker's feed | Nothing. No table, endpoint or button. | **Missing** |
| 4 | The developer acts on reports within 24 hours: removes the content and ejects the user | There is an admin queue with hide/warn/suspend/ban. Admins get no alert of a new report. Nothing marks a report as late. Admins cannot see the reported content. Messages, shops, showcases and updates cannot be hidden. A ban stops sign-in but leaves all the user's content public. | **Partial** |
| 5 | Screen recording on a device: terms, flag, block | Needs 1–3 first. | — |

## 1. User-generated content, item by item

"Report" means a user can flag it in the app. "Block author" and "Hidden from blocker" are **No** everywhere, because blocking does not exist.

| Content | Created by | Who sees it | Report: API / app | Block author | Hidden from blocker | Admin can remove it |
|---|---|---|---|---|---|---|
| **Product**: title, description EN/SW, price, vehicle details | Seller | Everyone, guests included, once the shop is verified and the product is active and not hidden | Yes / Yes, flag icon on product page | No | No | Yes: Products → Hide/Delete, and "Hide content" on its report |
| **Product photos and videos** (up to 8) | Seller | Same as product | Through the product report | No | No | Yes: Product media → Delete, or hide the whole product |
| **Shop profile**: name, @handle, logo, bio EN/SW, WhatsApp, address, hours | Seller | Everyone. `GET /sellers/{handle}` returns any shop, even pending or rejected | Yes / Yes, flag icon on shop page | No | No | Partly. "Reject" unpublishes the shop's products, showcases, updates and offers. The shop page, logo and bio stay public, and there is no way to remove only the logo or bio. A shop report has no "Hide content" (no `is_hidden` column). |
| **Product comments and replies** | Any signed-in user (buyer or seller) | Everyone, guests included (`GET /products/{id}/comments` is public) | **Yes / No**. The API accepts `comment`, but the comment sheet has no report action. | No | No | Yes: Comments → Hide |
| **Reviews**: stars, text, and the seller's one reply | Buyer with a completed order, plus the seller's reply | Everyone, guests included, on the shop page | **No / No** | No | No | Yes: Reviews → Hide (hides the review and its reply) |
| **Showcases**: video and caption | Seller | Everyone, in the feed and on the shop page | **No / No** | No | No | Yes: Showcases → Remove (deletes) |
| **Updates**, 24-hour stories: image or video and caption | Seller | Everyone. Followers also get a notification containing the caption. | **No / No** | No | No | Yes: Updates → Remove (deletes) |
| **Offers**: a discount on the seller's own product, no free text | Seller | Everyone. Followers are notified. | **No / No**. Only through the product page. | No | No | Yes: Offers → Remove |
| **Chat messages**: text and image attachments | Buyer and seller | The two people in the conversation. The recipient's "New message" notification carries the text. | Yes / **Hidden**: only a long-press on the other person's bubble, with no visual hint | No | No | **No.** There is no admin message screen, and messages cannot be hidden. The report view shows only "Message #123", not the text. |
| **User profile**: display name, avatar | Every user | Name on comments, reviews (public) and chat (other party). Avatar on comments. | **No / No**. There is no `user` report type. | No | No | Name: Users → Edit. Avatar: no remove tool. |
| Notifications that carry user text (message body, update caption) | Generated from the items above | The recipient | n/a | No | No | Only by removing the source item |

These are not user-generated, and no change is needed: banners and categories (admin-made), order details (private to the two parties), and the onboarding identity data (never shown).

**The website** (`sokoni.co.tz`) shows the same products, shops and reviews. It has its own report form and chat. It is outside this iOS fix, except where it reads the same model scopes (see the plan).

## 2. Terms: where they are shown and accepted

| Path | Terms shown before sign-in or account creation? | How acceptance is recorded |
|---|---|---|
| **Create account** (phone, details, password), 4 steps | **Yes.** Step 3 has an explicit checkbox before step 4 creates the account. | `terms_version` sent with `POST /auth/register` |
| **Sign in with username/phone + password** (the default sign-in) | **No.** After sign-in, and only if the account has never accepted any version. | `POST /auth/terms/accept` |
| **Phone + code (OTP)**, which also *creates* an account for an unknown number | **No.** The account is created first and terms are shown afterwards. | Same |
| **Forgot password → reset** (signs in) | **No**, afterwards | Same |
| **Google / Apple** (hidden on iOS in build 7: built without the two `--dart-define`s) | **No.** The account is created first. | Same |
| **Already signed in, app reopened** | Never checked | — |
| Website login | After sign-in, on `/auth/terms` | Server |

All routes funnel through one entry, `showAuthEntrySheet` ([auth_entry_sheet.dart](../../app/lib/features/auth/presentation/auth_entry_sheet.dart)). The after-sign-in check is in [post_sign_in.dart](../../app/lib/features/auth/presentation/post_sign_in.dart) and [legal_gate.dart](../../app/lib/features/legal/presentation/legal_gate.dart).

Weak points:

- **Version-blind check.** The check is `terms_accepted = terms_accepted_at != null`, so new wording never asks anyone again.
- **The server enforces nothing.** `terms_accepted_at` gates no endpoint.
- **Killing the app escapes the gate.** Closing the app on the acceptance screen leaves a signed-in session that never accepted, and the next launch doesn't check.

**Exact wording today.** The acceptance screen (EN / SW):

> **Before you continue.** Please review our Terms of Service and Privacy Policy. You need to accept them to use Sokoni.
> ☐ I have read and agree to the Terms of Service and Privacy Policy.
>
> **Kabla ya kuendelea.** Tafadhali soma Masharti yetu ya Huduma na Sera ya Faragha. Unahitaji kuvikubali ili kutumia Sokoni.
> ☐ Nimesoma na ninakubaliana na Masharti ya Huduma na Sera ya Faragha.

The full Terms, version `1.0`, are in-app ([terms_screen.dart](../../app/lib/features/legal/presentation/terms_screen.dart)) and on the website (`resources/views/web/pages/terms.blade.php`). The closest clauses are:

> 6. Prohibited conduct — You may not: list prohibited or illegal goods; impersonate another person or business; harass, threaten, or abuse other users; submit fraudulent reviews, reports, or verification documents; or attempt to circumvent our moderation or verification systems.
>
> 7. Content moderation — We may hide, warn, suspend, or ban accounts that violate these Terms … Content reported and upheld three or more times is automatically hidden pending review.

Missing from the Terms:

- **No zero-tolerance statement.** The words "zero tolerance" and "objectionable content" don't appear.
- **No blocking or 24-hour commitment.** Nothing mentions blocking or review within 24 hours.
- **Clause 7 points the wrong way.** "Three upheld reports" reads as if objectionable content can stay up until it is reported three times.

## 3. Reports: table, API and admin screen

**Table `reports`:** `reporter_id`, `reportable_type`/`reportable_id` (polymorphic), `reason` (free text, max 255), `note`, `status` (`pending|upheld|dismissed`), `resolved_by`, `resolution`, timestamps.

**API:** `POST /api/reports`, signed-in users only. Types: `product`, `shop`, `message`, `comment`.

**App reasons** (sent as translated labels, so admins may see Swahili text):
- Not business content
- Prohibited item
- Misleading listing
- Spam

None of these covers offensive, abusive, sexual, violent or hateful content. A guest who taps a reason gets the server's raw "Unauthenticated." message instead of the sign-in sheet.

**Admin, Trust & Safety → Reports:**
- **List columns:** type, ID, reporter, reason, note, status, date. It's sorted by status only, and the type filter is missing Comment.
- **Badge and dashboard:** the menu badge counts pending reports. The dashboard has a "Pending reports" stat and a "Moderation queue" widget showing the 10 oldest, with a "waiting since" column.
- **Actions per report:**
  - Uphold / Dismiss
  - **Hide content**, which only works for product and comment, the reportable types with `is_hidden`
  - **Warn** (sends a notification)
  - **Suspend** for N days, or **Ban**. Both set `banned_at`, revoke every token and notify the user.
- **Auto-hide:** after 3 *upheld* reports (configurable in Settings), the content is hidden automatically.
- **Detail page:** shows type + ID, reporter, reason, note and status. **It does not show the reported content**, so an admin has to hunt for it, and for a message there is no admin screen to find it on.

**Is the admin told about a new report? No.**
- There's no email, no admin-panel notification and no push. The only signals are the passive badge and dashboard.
- Mail infrastructure exists: `SafeMail`, cPanel SMTP in production, `SOKONI_SUPPORT_EMAIL`. Push is still a mock (`LogPushNotifier`), which writes only to the in-app inbox.

**Nothing marks a report as late.** No highlight, filter, stat or reminder exists for reports pending more than 24 hours.

## 4. Ban, hide and remove: what they actually do

- **Ban / suspend** (Users screen or Reports screen):
  - Sets `banned_at`, `banned_until` and `ban_reason`, revokes all sessions, and blocks every sign-in route (app and web).
  - **The user's existing content stays public:** products, shop page, showcases, updates, offers, comments and reviews. So a ban "ejects" the person but not their content.
- **Reject shop** (Seller profiles): hides products, showcases, updates and offers, because they all require a verified shop. The shop page, comments and reviews stay.
- **Per-type hide or remove:**
  - Products, comments, reviews: Hide/Unhide.
  - Showcases, updates, offers, product media: Remove (permanent delete).
  - Messages, shop logo/bio, user avatar: nothing.

## 5. Other findings (outside Guideline 1.2, worth fixing now)

1. **Privacy leak in public reviews.** `GET /api/sellers/{handle}/reviews` is public. It embeds each reviewer's full `UserResource`: **phone, email, username** and notification settings. Anyone can collect reviewers' phone numbers from any shop. Build 7 reads that object and needs only `id`, `name`, `is_seller` and `terms_accepted` (the rest are nullable). The fix can drop phone, email and username without breaking the live app. The same full object also goes to the seller in chat and orders, which is arguably needed there, so I'd leave those.
2. **The report sheet has no "Other" option with a note field**, though the API accepts a `note`.

## 6. Plan for step 2 (after your approval)

Everything is on `fix/ugc-moderation`, nothing is pushed, and the version becomes `1.0.0+9`. Google/Apple stay hidden on iOS exactly as in build 7: same code, and the iOS build passes no `GOOGLE_SERVER_CLIENT_ID`/`APPLE_SERVICE_ID`. A test locks that in. Every new string goes into both ARB files.

### 6.1 Terms gate (requirement 1)

- **A "Terms of Use" step inside `showAuthEntrySheet`**, shown before any sign-in or create-account option. Every route above passes through it. It has:
  - the zero-tolerance summary (below)
  - links to the full Terms and Privacy Policy
  - an unticked checkbox and an "Agree and continue" button
- **It appears until this device has agreed to the current version.** The agreement is then sent with the sign-in or registration request and stored on the account (`terms_accepted_at`, `terms_version`). Create-account step 3 shows the same new wording.
- **Terms version becomes `1.1`.** The check after sign-in and at app start compares versions, so existing accounts (including the review account) accept 1.1 once. Closing the app no longer escapes it.
- **Full Terms (app and website) get a new "Zero tolerance" clause** covering:
  - objectionable content and abusive users
  - report and block
  - review within 24 hours
  - removal of the content and of the user who posted it

  Clause 7's "three reports" line is reworded so it no longer suggests content waits for three reports.
- **Proposed summary text (EN; the SW draft is for your review):**
  > **Zero tolerance.** Sokoni does not allow objectionable content or abusive users. Offensive, sexual, violent, hateful, harassing or illegal content is not allowed anywhere: products, photos, videos, shop profiles, comments, reviews or messages.
  > **Report and block.** You can report any content and block any user. Blocked users disappear from your feeds, search, chats and notifications.
  > **24 hours.** We review every report within 24 hours, remove content that breaks these rules and remove the user who posted it.
  > ☐ I have read and agree to the Terms of Use (EULA) and the Privacy Policy.
  >
  > **Hatuvumilii kabisa.** Sokoni hairuhusu maudhui yasiyofaa wala watumiaji wanaonyanyasa wengine. Maudhui ya matusi, ngono, vurugu, chuki, unyanyasaji au yaliyo kinyume cha sheria hayaruhusiwi popote: bidhaa, picha, video, wasifu wa duka, maoni, tathmini au ujumbe.
  > **Ripoti na zuia.** Unaweza kuripoti maudhui yoyote na kumzuia mtumiaji yeyote. Watumiaji uliowazuia hawataonekana tena kwenye mipasho, utafutaji, mazungumzo na arifa zako.
  > **Saa 24.** Tunakagua kila ripoti ndani ya saa 24, tunaondoa maudhui yanayovunja sheria hizi na kumwondoa mtumiaji aliyeyaweka.
  > ☐ Nimesoma na ninakubali Masharti ya Matumizi (EULA) na Sera ya Faragha.

### 6.2 Report everywhere (requirement 2)

- **New API report types:** `review`, `showcase`, `update`, `offer` and `user` (profile name and avatar). The existing four stay.
- **One "⋯" menu holding Report + Block**, in every place that shows someone else's content:
  - feed cards (product, showcase, offer, update)
  - product page, shop page
  - each comment, each review
  - chat thread app bar, plus a visible "Report message" on long-press
  - showcase viewer, story viewer
- **New reasons** (sent as stable codes, shown translated):
  - Offensive or abusive
  - Harassment or hate
  - Sexual or violent content
  - Scam or fraud
  - Prohibited item
  - Not business content
  - Spam
  - Something else (with a note)
- **Guests** get the sign-in sheet (with the terms step) instead of an error.

### 6.3 Block users (requirement 3)

- **New `user_blocks` table** (`blocker_id`, `blocked_id`, `reason`, timestamps, unique pair). Blocking a shop blocks its owner.
- **API:** `GET /blocks`, `POST /blocks` (by `user_id` or `seller_id`), `DELETE /blocks/{user}`.
- **Server-side filtering for the blocker, on every list:**
  - feed, search and the sponsored slot
  - products: list and detail; a direct link shows a "You blocked this shop" state with Unblock
  - sellers: list and shop page
  - comments, reviews, showcases, updates, offers
  - conversations and messages
  - notifications and the following list
- **The app also filters at once**, from a local blocked set that also cleans the offline feed cache. The content disappears the moment Block is tapped, with no reload.
- **Contact stops both ways.** Neither person can message the other or start a new conversation or order between them. The blocked person is not told.
- **Blocking also unfollows** the blocked shop.
- **Admins are alerted.** Each block creates a report (type `user`, reason "Blocked by user") in the same queue, with the same alert as any report.
- **Settings → Blocked accounts** lists them, with Unblock.

### 6.4 Admin: act within 24 hours (requirement 4)

- **Alert on every new report and block:**
  - an email to `SOKONI_MODERATION_EMAIL` (new `.env` key, falls back to `SOKONI_SUPPORT_EMAIL`), sent through `SafeMail` so a mail failure never fails the user's report
  - a bell notification in the admin panel (Filament database notifications)
- **24-hour clock on the Reports list:**
  - pending reports sorted oldest first
  - a "Due" column: hours left, amber under 6 h, **red "Overdue"** after 24 h
  - an "Overdue" filter
  - an "Overdue reports" dashboard stat, and the same colours in the dashboard queue widget
- **Reminder email:** a scheduled command sends one when a report has waited 12 hours. It needs the scheduler cron.
- **Report page shows the content itself:** text, photos, video thumbnail, the author, the author's earlier reports, and a link to the item.
- **Hide works on every type.** `is_hidden` is added to seller profiles, messages, showcases and updates, so "Hide content" works everywhere. Messages also get a read-only admin view through the report.
- **One-click "Remove content and ban user"**:
  - hides the item
  - bans the author permanently and revokes their sessions
  - resolves the report as upheld
  - logs it and notifies the user
- **A ban now also hides everything the user posted:** products, shop, showcases, updates, offers, comments and reviews, through ban-aware visibility scopes. Unbanning restores it. The same scopes also clean the website.
- **"Remove logo" and "Clear bio"** actions on shop reports, and "Remove avatar" on user reports.

### 6.5 Also in step 2

- **Privacy fix (§5.1):** public reviews and comments carry only `id`, `name`, `avatar`, `is_seller` and `terms_accepted`, so build 7 still parses them.
- `UserResource` gains `terms_version`.

### 6.6 Database: additive migrations only

The live API serves the builds under review. Every change only adds:

| Migration | Adds |
|---|---|
| `create_user_blocks_table` | New table |
| `add_is_hidden_to_moderated_content` | `is_hidden` boolean, default false, on `seller_profiles`, `messages`, `showcases`, `updates` |
| `add_actor_id_to_app_notifications_table` | Nullable `actor_id`, so notifications from a blocked user can be filtered |
| `add_reminded_at_to_reports_table` | Nullable timestamp |
| `create_notifications_table` | Laravel's standard table, for the admin-panel bell |

There are no renames, drops or type changes, and no existing response loses a field that build 7 needs. Old apps keep working. They just stop receiving banned or hidden content.

### 6.7 Tests

**API feature tests:**
- every new report type
- block / unblock / list
- each filtered list hiding a blocked user's content, for the blocker only
- messaging and ordering refused between blocked pairs
- a block creating an admin report
- the email and admin notification on a new report
- overdue state and reminder
- a ban hiding all of a user's content
- terms version recorded at sign-in
- the reviews privacy fix
- build 7's user fields still present

**Flutter tests:**
- the terms step blocks every sign-in route until agreed
- the version check after sign-in
- the report sheet reasons
- Block removing a shop's cards from the feed immediately
- the blocked accounts screen
- Google/Apple hidden with no `--dart-define`

### 6.8 Then

- `flutter analyze`, `flutter test`, `php artisan test`
- Version `1.0.0+9`
- The exact Mac steps for the iOS build, and a staging → production API checklist (backup, upload, run the 5 migrations, `.env` keys, scheduler cron, clear caches, verify) in `docs/moderation/`
- A script for the screen recording Apple asked for

## 7. Decisions to confirm

Each has my recommendation. Say "approved" to take them all, or change any.

1. **Blocking stops contact both ways** (chat and new orders), not just hiding content. *Recommended:* yes. It's what users expect from "block", and Apple asks for blocking *abusive* users.
2. **A ban hides all the user's content site-wide** until unbanned. *Recommended:* yes. This is the "eject the user" in Apple's requirement 4.
3. **Terms version 1.1 makes every existing account accept once** at their next app start or sign-in, including the App Review account. *Recommended:* yes. It also puts the agreement screen in the recording naturally.
4. **The reviews privacy fix ships in this build.** *Recommended:* yes. It's small, compatible with build 7, and it is a real leak of phone numbers.
5. **Website scope.** The website gets the new Terms text, and the ban-aware scopes apply there automatically. It gets no block UI in this round. *Recommended:* as stated.
