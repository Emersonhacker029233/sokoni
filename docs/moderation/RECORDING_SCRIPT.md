# Screen recording for Apple (Guideline 1.2): terms, report, block

Apple asked for a recording from a physical device showing the terms agreement, the flag mechanism and the block mechanism. This script takes about 3 minutes on an iPad with Sokoni 1.0 (9) from TestFlight, against the production API after [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md).

## Before you record

1. **Install and set the language.** Install 1.0 (9) from TestFlight. In the app, set Profile → Settings → Language → English.
2. **Start signed out, from a fresh launch.** If Sokoni is signed in: Profile → Settings → Sign out. Then swipe Sokoni away in the app switcher, so the next launch is a fresh session.
3. **Pick the shop to block.** Find a verified shop with listings in the feed that isn't the review account's own. Note its name, here "Shop X". Ideally pick one that also has a comment or review, so its disappearance is easy to show.
4. **Turn on recording.** Control Centre → long-press **Screen Recording** → microphone off. Turn on Do Not Disturb so no notifications appear.

## Recording

Speak nothing, or add short captions later. Pause about two seconds on each screen so the reviewer can read it.

### Part 1: terms before sign-in (about 60 s)

1. Start the recording, then open **Sokoni**. The home feed loads as a guest (browsing needs no account).
2. Tap **Profile** → **Sign in**.
3. The sheet shows **Sokoni Terms of Use**, with no sign-in options yet. Hold on the three points: **Zero tolerance**, **Report and block**, **24 hours**.
4. Tap **Read the full Terms of Use**. Scroll slowly to clause **2. Zero tolerance for objectionable content and abusive users** and hold. Tap back.
5. Tap **Agree and continue**. Only now do **Sign in** and **Create an account** appear.
6. Tap **Sign in**. Enter `783 029 233` and password `Review2026` → **Sign in**. You're in, with no code step.

### Part 2: flag objectionable content (about 45 s)

7. On **Home**, on a Shop X listing card, tap **⋯** (top right of the card). The menu shows **Report this listing** and **Block Shop X**.
8. Tap **Report this listing**. Hold on the reasons: **Offensive or abusive**, **Harassment or hate**, **Sexual or violent content**, … and "Our team reviews every report within 24 hours."
9. Tap **Offensive or abusive**. The message "Thanks — we'll take a look." appears.
10. Optional, to show reporting is everywhere: open a listing's **comments** → **⋯** on a comment → **Report this comment** → pick a reason. Or open a chat and press and hold the other person's message to report it.

### Part 3: block an abusive user (about 45 s)

11. Back on **Home**, scroll so Shop X's card is on screen. Tap **⋯** → **Block Shop X**.
12. The dialog reads: "You won't see their shop, products, posts, comments, reviews or messages… Our team is notified and reviews it within 24 hours." Tap **Block**.
13. **Shop X's card disappears from the feed at once.** "Shop X is blocked." shows. Scroll a little to show no Shop X content remains.
14. Tap **Search**, type Shop X's product name. It doesn't appear.
15. Tap **Profile** → **Settings** → **Blocked accounts**. Shop X is listed with **Unblock**.
16. Stop the recording (red status pill → Stop). It's saved to Photos.

## After recording

1. **Restore the review account's view.** In the app: Settings → Blocked accounts → **Unblock** Shop X, so the reviewer sees the full feed.
2. **Clear the test reports.** Admin panel → Trust & Safety → Reports:
   - Optionally, open the "User blocked" report and the listing report to confirm the email alert and the admin bell both arrived.
   - Then **Dismiss** both, so the shop isn't acted on for a test report.
3. **Trim and send.** Trim the start and end in Photos if needed. Attach the video to your Resolution Center reply in App Store Connect, with the note in [BUILD_1.0.9.md](BUILD_1.0.9.md) §5.

## If something looks wrong while rehearsing

| You see | Cause | Fix |
|---|---|---|
| Google or Apple buttons after Agree | The iOS build was made with `--dart-define`s | Rebuild with plain `flutter build ipa --release` |
| "Our Terms have changed" right after sign-in | The production API is not updated yet (it ignores `terms_version`) | Deploy the API first ([DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)) |
| Block shows an error | Same: `/api/blocks` is missing | Deploy the API first |
| No "⋯" on a card | That card is the signed-in account's own shop | Use another shop's card |
