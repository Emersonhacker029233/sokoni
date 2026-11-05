# Production API deployment: moderation release (pairs with iOS 1.0 (9))

> **Superseded by [RELEASE.md](RELEASE.md).** Use RELEASE.md for the production deploy. It uploads the migrations first and runs them before the code goes live, so there is no window where new code meets the old database. This file uploads everything first. It is kept only for its SSH variants.

For whoever updates `api.sokoni.co.tz` on cPanel (`/home/sokoftsn/sokoni-api`). The package comes from branch `fix/ugc-moderation`, which is `main` plus the moderation work. It contains nothing from `feature/delivery`.

**Deploy before** building, testing or recording iOS 1.0 (9). The new app calls `/api/blocks` and sends `terms_version` at sign-in.

**Safe for the apps already out there.** Every database change is additive, and no response loses a field the live apps read. Builds 1.0 (7) and earlier, and the live Android builds, keep working. What changes for them:

- They stop seeing content from banned users and from moderator-hidden shops, messages, showcases and updates.
- Public review lists no longer include reviewers' phone, email or username.
- Website users are asked once to accept Terms 1.1.

## 1. Back up (both, before anything else)

1. **Database.** cPanel → phpMyAdmin → select the Sokoni database → **Export** → *Custom* → all tables, format SQL, *Add DROP TABLE* ticked → **Go**. Keep the `.sql` file. With SSH:
   ```bash
   mysqldump --single-transaction -u <db_user> -p <db_name> > ~/sokoni-before-moderation-$(date +%F).sql
   ```
2. **Code.** File Manager → `/home/sokoftsn` → right-click `sokoni-api` → **Compress** → zip → download it. Also download `/home/sokoftsn/sokoni-api/.env` on its own.

## 2. Build the update package (on the Windows PC)

```powershell
cd D:\Projects\sokoni-ugc
$out = "$env:TEMP\sokoni-api-moderation"
if (Test-Path $out) { Remove-Item -Recurse -Force $out }
git archive --format=tar -o "$out.tar" fix/ugc-moderation:api
New-Item -ItemType Directory $out | Out-Null
tar -xf "$out.tar" -C $out
composer install --working-dir $out --no-dev --optimize-autoloader --no-interaction --no-scripts
Remove-Item -Recurse -Force "$out\tests", "$out\public\.htaccess", "$out\public\.user.ini" -ErrorAction SilentlyContinue
"commit: $(git rev-parse fix/ugc-moderation)" | Set-Content "$out\BUILD.txt"
tar -a -c -f D:\Projects\sokoni-api-moderation.zip -C $out *
```

- **Committed code only.** `git archive` takes what is committed on the branch, so no local `.env`, logs or caches get in.
- **Linux-safe paths.** `tar -a` writes a zip with forward slashes. PowerShell's `Compress-Archive` writes backslashes, which break on Linux.
- **The server keeps its own** `public/.htaccess` and `public/.user.ini`.

## 3. Upload

1. File Manager → `/home/sokoftsn/sokoni-api` → **Upload** `sokoni-api-moderation.zip` → **Extract** into the same folder → confirm overwrite.
2. Open `BUILD.txt` and check that the commit matches `git rev-parse fix/ugc-moderation`.
3. Delete the zip from the server.
4. Never delete `.env`, `storage/` or the public uploads folder.

## 4. `.env`

Add this (comma-separate more than one inbox):

```
MODERATION_ALERT_EMAIL=moderation@sokoni.co.tz
```

If it's unset, alerts go to `SOKONI_SUPPORT_EMAIL`. Mail goes out through the existing mailer, so `MAIL_MAILER=smtp` and the `MAIL_*` mailbox settings must already work. If mail fails, the report is still saved and the admin bell still rings, and the failure is logged in `storage/logs/mail-*.log`.

## 5. Migrations: five new, all additive, in this order

Laravel runs them in this order on its own:

| # | Migration | What it adds | Index size (utf8mb4) |
|---|---|---|---|
| 1 | `2026_11_04_100000_create_user_blocks_table` | table `user_blocks` | unique (blocker_id, blocked_id) 16 B; blocked_id 8 B |
| 2 | `2026_11_04_100100_add_is_hidden_to_moderated_content` | `is_hidden` boolean, default 0, on `seller_profiles`, `messages`, `showcases`, `updates` | none |
| 3 | `2026_11_04_100200_add_actor_id_to_app_notifications_table` | nullable `actor_id` on `app_notifications` | 8 B |
| 4 | `2026_11_04_100300_add_moderation_timestamps_to_reports_table` | nullable `alerted_at`, `resolved_at` on `reports` | none |
| 5 | `2026_11_04_100400_create_notifications_table` | table `notifications` (the admin bell) | PK 144 B; (notifiable_type 191, notifiable_id) 774 B |

**Key size limit.** Every index key is under MyISAM's 1000-byte limit. Indexed strings are capped at 191 characters; Laravel's stock `notifications` table, at 1028 bytes, would fail on MyISAM.

**MyISAM and foreign keys.** On MyISAM, MySQL accepts and ignores foreign keys, which is why `actor_id` also gets a plain index.

**No existing column changes** type, default or meaning.

**With SSH** (preferred):
```bash
cd /home/sokoftsn/sokoni-api
php artisan migrate:status | tail -6      # the five above show "Pending", in this order
php artisan migrate --force               # runs them as one batch
```

**Without SSH**, use a one-shot cron job:

1. cPanel → Cron Jobs → add a job for *Once Per Minute*:
   ```
   cd /home/sokoftsn/sokoni-api && [ ! -f storage/app/migrated-moderation ] && /usr/local/bin/php artisan migrate --force >> storage/logs/setup.log 2>&1 && touch storage/app/migrated-moderation
   ```
2. After two minutes, `storage/logs/setup.log` lists the five migrations as `DONE`.
3. **Delete the cron job.**

**Without SSH or cron**, run this in phpMyAdmin → SQL. It's exactly what the migrations run, produced from them with Laravel's pretend mode:

```sql
CREATE TABLE `user_blocks` (`id` bigint unsigned not null auto_increment primary key, `blocker_id` bigint unsigned not null, `blocked_id` bigint unsigned not null, `reason` varchar(50) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
ALTER TABLE `user_blocks` ADD CONSTRAINT `user_blocks_blocker_id_foreign` FOREIGN KEY (`blocker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_blocks` ADD CONSTRAINT `user_blocks_blocked_id_foreign` FOREIGN KEY (`blocked_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
ALTER TABLE `user_blocks` ADD UNIQUE `user_blocks_blocker_id_blocked_id_unique`(`blocker_id`, `blocked_id`);
ALTER TABLE `user_blocks` ADD INDEX `user_blocks_blocked_id_index`(`blocked_id`);

ALTER TABLE `seller_profiles` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `messages` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `showcases` ADD `is_hidden` tinyint(1) not null default '0';
ALTER TABLE `updates` ADD `is_hidden` tinyint(1) not null default '0';

ALTER TABLE `app_notifications` ADD `actor_id` bigint unsigned null;
ALTER TABLE `app_notifications` ADD CONSTRAINT `app_notifications_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
ALTER TABLE `app_notifications` ADD INDEX `app_notifications_actor_id_index`(`actor_id`);

ALTER TABLE `reports` ADD `alerted_at` timestamp null;
ALTER TABLE `reports` ADD `resolved_at` timestamp null;

CREATE TABLE `notifications` (`id` char(36) not null, `type` varchar(191) not null, `notifiable_type` varchar(191) not null, `notifiable_id` bigint unsigned not null, `data` text not null, `read_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
ALTER TABLE `notifications` ADD INDEX `notifications_notifiable_type_notifiable_id_index`(`notifiable_type`, `notifiable_id`);

SET @batch = (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);
INSERT INTO migrations (migration, batch) VALUES
  ('2026_11_04_100000_create_user_blocks_table', @batch),
  ('2026_11_04_100100_add_is_hidden_to_moderated_content', @batch),
  ('2026_11_04_100200_add_actor_id_to_app_notifications_table', @batch),
  ('2026_11_04_100300_add_moderation_timestamps_to_reports_table', @batch),
  ('2026_11_04_100400_create_notifications_table', @batch);
```

## 6. Clear caches

With SSH:
```bash
php artisan optimize:clear
php artisan config:cache && php artisan route:cache
```

Without SSH:

1. File Manager → `bootstrap/cache` → delete everything except `.gitignore`, including the `filament` folder if it's there.
2. `storage/framework/views` → delete every `.php` file.

The Filament folder matters: a stale Filament cache hides new admin features (the bell and the new report actions), exactly as it once hid the Drivers screen on staging.

If a page still looks old after five minutes: cPanel → MultiPHP Manager → switch `api.sokoni.co.tz` to another PHP version and back. That resets OPcache.

## 7. Scheduler

The daily summary needs the scheduler cron from `docs/DEPLOY.md` step 9. Check it's there:

```
* * * * * /usr/local/bin/php /home/sokoftsn/sokoni-api/artisan schedule:run >> /dev/null 2>&1
```

With SSH, `php artisan schedule:list` should show `reports:waiting-summary` at `0 5 * * *`, which is 08:00 in Dar es Salaam.

## 8. Check it works

1. `curl -s -o /dev/null -w "%{http_code}\n" https://api.sokoni.co.tz/api/blocks` → `401`. The route exists and needs a sign-in.
2. `curl -s https://api.sokoni.co.tz/api/sellers/<any-handle>/reviews | grep -c '"phone"'` → `0`.
3. `https://sokoni.co.tz/terms` → "Terms of Use (EULA) — version 1.1" with clause 2 "Zero tolerance…".
4. **Report alert.** Sign in to the admin panel. In the app (any test account, not the review account), report a product. Within a minute:
   - an email arrives at `MODERATION_ALERT_EMAIL`
   - the admin bell shows "New report"
   - Trust & Safety → Reports lists it with a green "23h left" badge, and its page shows the product's text and photos

   **Dismiss** it afterwards.
5. With SSH: `php artisan reports:waiting-summary` → "No report has waited 20 hours." (or it sends the summary).
6. **Review account.** It still signs in from an older build: its server-side login is unchanged. In iOS 1.0 (9) it signs in on the first screen after the one Agree tap, and its `terms_version` becomes `1.1`.

## 9. Rollback

**Code.** Re-extract the backup zip from step 1 over `/home/sokoftsn/sokoni-api`, keeping the current `.env`, then clear caches (step 6).

**Database: normally leave it.** The five changes are additive, and the old code ignores the new tables and columns. The one side effect is that items hidden with the new tools (shops, messages, showcases, updates) become visible again to the old code, which doesn't know `is_hidden` on those tables.

**Database, to fully undo:**

With SSH, if these five are still the newest batch:
```bash
php artisan migrate:rollback --force   # rolls back the last batch: the five, newest first
```

Or by hand in phpMyAdmin, newest first:

```sql
DROP TABLE IF EXISTS `notifications`;
ALTER TABLE `reports` DROP COLUMN `alerted_at`, DROP COLUMN `resolved_at`;
ALTER TABLE `app_notifications` DROP FOREIGN KEY `app_notifications_actor_id_foreign`;  -- InnoDB only; skip this line on MyISAM
ALTER TABLE `app_notifications` DROP INDEX `app_notifications_actor_id_index`, DROP COLUMN `actor_id`;
ALTER TABLE `seller_profiles` DROP COLUMN `is_hidden`;
ALTER TABLE `messages` DROP COLUMN `is_hidden`;
ALTER TABLE `showcases` DROP COLUMN `is_hidden`;
ALTER TABLE `updates` DROP COLUMN `is_hidden`;
DROP TABLE IF EXISTS `user_blocks`;
DELETE FROM migrations WHERE migration IN (
  '2026_11_04_100000_create_user_blocks_table',
  '2026_11_04_100100_add_is_hidden_to_moderated_content',
  '2026_11_04_100200_add_actor_id_to_app_notifications_table',
  '2026_11_04_100300_add_moderation_timestamps_to_reports_table',
  '2026_11_04_100400_create_notifications_table'
);
```

Dropping `user_blocks` loses every block users made, and dropping `is_hidden` un-hides what moderators hid. Only do this if the release is abandoned.

**Last resort.** Re-import the step-1 `.sql` export. This also discards every order, message and sign-up since the backup.

## Staging

`staging-api.sokoni.co.tz` currently runs `feature/delivery`. Deploying this branch there would remove the delivery screens until `feature/delivery` is redeployed.

**Merging into `feature/delivery` later.** The migrations don't clash: these five are dated after the delivery ones and touch different columns and tables. A test merge (`git merge-tree`) does show small text conflicts in 9 files, where both branches added lines at the end of the same place:

- `ConversationController.php`
- `AppServiceProvider.php`
- `routes/console.php`
- both ARB files
- `user.dart` and its generated files
- `pubspec.yaml` (version)

Keep both sides in each, then regenerate with `dart run build_runner build` and `flutter gen-l10n`.
