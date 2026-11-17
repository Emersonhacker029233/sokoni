# Building Sokoni 1.0 (9): iOS on the Mac, Android bundle for Play

> **For the production release, follow [RELEASE.md](RELEASE.md)**: step 9 there has Oswald's Mac steps. This file adds the Android bundle.

Branch `fix/ugc-moderation`, version `1.0.0+9`. **Deploy the production API first** ([RELEASE.md](RELEASE.md)). Release builds talk to `https://api.sokoni.co.tz/api` with no flag needed, and this build needs the new `/api/blocks` endpoint.

## iOS 1.0 (9) on the Mac

### 1. Get the branch onto the Mac

The branch is not pushed. Pick one way:

- **You push it** from the PC, then on the Mac:
  ```bash
  cd ~/sokoni && git fetch origin && git checkout fix/ugc-moderation
  ```
- **Without pushing.** On the PC:
  ```powershell
  git -C D:\Projects\sokoni-ugc bundle create D:\sokoni-1.0.9.bundle fix/ugc-moderation
  ```
  Copy `sokoni-1.0.9.bundle` to the Mac, then:
  ```bash
  cd ~/sokoni
  git fetch ~/Downloads/sokoni-1.0.9.bundle fix/ugc-moderation:fix/ugc-moderation
  git checkout fix/ugc-moderation
  ```

Then:
```bash
git log -1 --oneline        # the newest moderation commit
grep '^version' app/pubspec.yaml   # version: 1.0.0+9
```

`app/ios/Runner/GoogleService-Info.plist` is not in git. Use the same file build 7 used, already in place on the Mac (`ls app/ios/Runner/GoogleService-Info.plist`).

### 2. Build

Use the same Flutter and Xcode as build 7.

```bash
cd ~/sokoni/app
flutter clean
flutter pub get
cd ios && pod install && cd ..
flutter build ipa --release
```

**Run it exactly like this, with no `--dart-define` at all.** That is how build 7 was built. With neither `GOOGLE_SERVER_CLIENT_ID` nor `APPLE_SERVICE_ID` defined, `SokoniSocialAuthConfig` leaves Google and Apple sign-in out entirely, so they stay hidden. A test locks this in (`test/features/auth/auth_entry_sheet_test.dart`).

Output:

- `build/ios/ipa/*.ipa`
- `build/ios/archive/Runner.xcarchive`

### 3. Upload

Either:
- Open **Transporter**, drag in `build/ios/ipa/*.ipa`, then **Deliver**.
- Or `open build/ios/archive/Runner.xcarchive` → Xcode Organizer → **Distribute App** → App Store Connect → Upload.

### 4. Check on the iPad before submitting (TestFlight)

1. Install 1.0 (9) from TestFlight.
2. Profile → **Sign in**. The sheet opens on **Sokoni Terms of Use** (Zero tolerance / Report and block / 24 hours) with one **Agree and continue** button.
3. After Agree: only **Sign in** and **Create an account**. **No Google or Apple button.**
4. Sign in with `783 029 233` / `Review2026`. It goes straight in, with no code.
5. Run through [RECORDING_SCRIPT.md](RECORDING_SCRIPT.md) once before recording for real.

### 5. Submit

1. App Store Connect → Sokoni → iOS App → version **1.0** → **Build** → choose **1.0 (9)**.
2. **App Review Information → Notes**: keep the sign-in text from `docs/DEMO.md`, then add the moderation note below.
3. Reply in **Resolution Center** to the Guideline 1.2 message with the same note, and attach the screen recording.
4. **Submit for Review.**

Suggested note:

> Guideline 1.2 — what changed in 1.0 (9):
> 1. Terms (EULA): before any sign-in or account creation, the app shows the Sokoni Terms of Use with one "Agree and continue" button. The terms state zero tolerance for objectionable content and abusive users (full text: Profile → Settings → Terms of Use, clause 2; also https://sokoni.co.tz/terms). Existing accounts must agree to the new version once.
> 2. Flag content: every listing, shop, feed post, offer, video, update, comment, review and chat has a "⋯" button with "Report…" (reasons include offensive or abusive, harassment or hate, sexual or violent content). Chat messages can also be reported by pressing and holding.
> 3. Block users: the same "⋯" menu has "Block…". Their content disappears from the blocker's feed, search, comments, reviews, chats and notifications immediately, neither person can message the other, and our moderation team is notified of every block. Blocked accounts can be managed in Settings → Blocked accounts.
> 4. Our moderators are emailed and notified in the admin panel for every report and block, and a daily summary flags anything older than 20 hours. We act on every report within 24 hours by removing the content and banning the user who posted it, which removes all of their content.
> Demo account: 783 029 233 / Review2026 (no verification code needed).

## Android: bundle for Play

On the Windows PC. The worktree has no signing files, so copy them in once. They're gitignored and never committed.

```powershell
Copy-Item D:\Projects\sokoni\app\android\key.properties, D:\Projects\sokoni\app\android\sokoni-release.jks D:\Projects\sokoni-ugc\app\android\
cd D:\Projects\sokoni-ugc\app
flutter clean
flutter pub get
flutter build appbundle --release --flavor prod `
  --dart-define=GOOGLE_SERVER_CLIENT_ID=825257290618-pis0jcmn11bo5n3vpi5q0nchagrlhbuq.apps.googleusercontent.com `
  --dart-define=APPLE_SERVICE_ID=tz.co.sokoni.signin
```

- **Output:** `build\app\outputs\bundle\prodRelease\app-prod-release.aab`, versionName `1.0.0`, versionCode `9`. Upload it in Play Console → Production (or a testing track) → Create new release.
- **The two `--dart-define`s** are the documented Android release flags (`docs/DEPLOY.md`), which show Google sign-in on Android. To make Android look exactly like iOS 1.0 (9), with no Google or Apple button, drop both lines.
- **Version code clash.** If Play already has versionCode 9 or higher, raise the `+9` in `app/pubspec.yaml` before building. iOS and Android don't need the same build number.
