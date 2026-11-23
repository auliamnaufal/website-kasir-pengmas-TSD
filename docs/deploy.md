# Deploy

Target: Domainesia Nimbus Plus (cPanel, jailed SSH, MariaDB). Works the same on
any cPanel host with SSH; VPS differences are in the last section.

Deployed commit: `main`. Build assets are not in git (`public/build` is ignored),
so they are built locally and uploaded.

## 0. Server prerequisites

- PHP 8.2+ selectable in cPanel (`PHP Selector` / MultiPHP).
- PHP extensions: `pdo_mysql`, `mbstring`, `dom`, `fileinfo`, `gd`, `zip`, `bcmath`, `openssl`, `tokenizer`, `session`.
  - `dom` + `mbstring` are needed by `barryvdh/laravel-dompdf` (invoice PDFs).
  - `gd` is needed by `milon/barcode` (`DNS1D`) on product pages.
- One MariaDB database + user (cPanel > MySQL Databases).
- SSH access and cron jobs enabled on the plan.

## 1. Upload the code

```bash
# on your machine, from the repo root
rsync -avz --delete \
  --exclude=.git --exclude=node_modules --exclude=vendor \
  --exclude=.env --exclude=tests --exclude=.phpunit.cache \
  ./ user@host:~/apps/kasir/
```

Keep the app one level above `public_html`. `vendor/` is not uploaded: build it on
the server in step 3.

## 2. Environment file

```bash
cd ~/apps/kasir
cp .env.example .env
php artisan key:generate
```

Fill in `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `APP_URL` (https), and
`MAIL_*` if invoices are emailed. `APP_ENV=production` and `APP_DEBUG=false`
are already set in `.env.example`.

`APP_TIMEZONE=Asia/Jakarta` keeps the 16 -> 15 cutoff (`Piutang::periodFor`) on
WIB. Changing it after data exists shifts period boundaries.

SQLite is not a deployment option here: the session, cache and queue drivers all
write to the same file and shared hosting has no reliable locking story.

## 3. Dependencies and database

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
```

Seed only the master data, not the demo set:

```bash
php artisan db:seed --class=JabatanConfigSeeder --force
```

`DatabaseSeeder` is not used on production: it also seeds demo orders and the two
default accounts below.

If you do seed `UserSeeder`, change both passwords immediately. It creates
`admin@example.com` / `karyawan@example.com` with password `password`.

`JabatanConfigSeeder` ships `max_potongan_persen = 50` as a placeholder until the
client confirms the real cap.

## 4. Web root

Point the domain's document root at `~/apps/kasir/public`:

- cPanel > Domains > (your domain) > Document Root, or
- add `public` as an addon-domain document root, or
- as a last resort, symlink `public_html` contents to `public/`.

If you must serve from `public_html` directly, move the contents of `public/`
there and edit `index.php` so `require __DIR__.'/../vendor/autoload.php'` points
to the app directory. Prefer the document-root change: the alternative breaks
`storage:link`.

## 5. Frontend assets

`public/build` is gitignored, so build locally and upload it:

```bash
npm ci && npm run build
rsync -avz public/build user@host:~/apps/kasir/public/
```

`public/assets` (theme fonts, CSS, chart.js) is tracked and arrives with step 1.

## 6. Permissions

```bash
chmod -R ug+rwX storage bootstrap/cache
```

## 7. Caches

```bash
php artisan optimize
```

Run it again on every deploy, after `composer install` and migrations.

## 8. Cron

The only scheduled task is `simpanan:generate-wajib`, monthly on the 16th at
01:00 (`routes/console.php`). `schedule:run` only fires a task when the minute
matches, so a 15-minute cron will usually skip it. Call the command directly:

```
0 1 16 * *  cd /home/USER/apps/kasir && /usr/local/bin/php artisan simpanan:generate-wajib >> /home/USER/apps/kasir/storage/logs/cron.log 2>&1
```

Use the PHP binary reported by `php -v` over SSH; cPanel hosts often expose
`ea-php82` instead of `php` in cron.

No queue worker is needed: nothing dispatches jobs, and `QUEUE_CONNECTION=database`
is only a placeholder.

## 9. Backups

The plan includes weekly remote backups. Add a database dump, because a code-only
restore loses transactions:

```
15 2 * * *  mysqldump -u USER -pPASS DBNAME | gzip > /home/USER/backups/kasir-$(date +\%F).sql.gz
```

Copy that directory off-host periodically. Verify a restore once: `gunzip < dump.sql.gz | mysql -u USER -p DBNAME`.

## 10. Post-deploy checks

1. Log in as admin; open the dashboard.
2. One cashier transaction with each payment method (`cash`, `qris`, `potong_gaji`).
3. Download a kredit and a pinjaman invoice PDF (exercises dompdf).
4. Open a product page (exercises `DNS1D`).
5. `php artisan simpanan:generate-wajib 2026-11` once by hand, confirm rows, then rerun to confirm it skips duplicates.
6. Confirm `APP_DEBUG=false`: hit a bogus route and check no stack trace is shown.

## 11. Deployed instance (2026-11-23)

What is actually live, versus the generic steps above:

| Item | Value |
|---|---|
| URL | `https://kasir.kopkartirtasuci.com` |
| cPanel user | `kopkart1` (plan `nimbus_plus`, IP `202.155.137.50`) |
| App path | `/home/kopkart1/apps/kasir` |
| Docroot | `/home/kopkart1/apps/kasir/public` |
| Git deployment | cPanel Git Version Control, id `25422544`, branch `main` |
| PHP | 8.3.37, handler `lsapi`, CLI `/usr/local/bin/php` |
| Database | `kopkart1_kasir` / user `kopkart1_kasir`, `ALL PRIVILEGES` |
| Upload limits | `upload_max_filesize=2M`, `post_max_size=8M`, `memory_limit=128M` |

### No SSH on this account

Ports 22/2222/2022/22222 are filtered, so the SSH steps in §1–§7 are replaced by:

1. **Code**: `git_deploy_add` + `git_deploy_deploy` clone/pull straight into
   `/home/kopkart1/apps/kasir`; the subdomain docroot is then repointed at
   `apps/kasir/public` (cPanel UAPI requires a *relative* path there).
   A deploy only does git pull — dependencies and caches are a separate step.
2. **Composer / artisan**: a one-off PHP file under `public/`, guarded by a random
   token, executed over HTTPS, then permanently deleted:

```php
<?php
if (!hash_equals('<random-token>', (string)($_GET['t'] ?? ''))) { http_response_code(403); exit; }
set_time_limit(0);
$base = dirname(__DIR__);
$env  = 'HOME=/home/kopkart1 COMPOSER_HOME=/home/kopkart1/.composer ';
$cmds = [
  $env . 'php -d memory_limit=768M composer.phar install --no-dev --no-interaction --prefer-dist --optimize-autoloader',
  'php artisan migrate --force',
  'php artisan db:seed --class=JabatanConfigSeeder --force',
  'php artisan storage:link',
  'chmod -R ug+rwX storage bootstrap/cache',
  'php artisan optimize',
];
foreach ($cmds as $c) {
  echo "\n### $c\n";
  $out = []; $rc = 0;
  exec('cd ' . escapeshellarg($base) . ' && ' . $c . ' 2>&1', $out, $rc);
  echo implode("\n", array_slice($out, -45)), "\nexit=$rc\n";
  if ($rc !== 0) break;
}
```

`HOME` is mandatory — Composer aborts without it. While that file exists it is a
remote command executor: delete it as soon as the run finishes.

3. **Frontend assets**: `public/build` is zipped locally, uploaded (≤ 2 MB),
   extracted server-side, then the zip is deleted.

### Cron as deployed

```
0 1 16 * *   cd /home/kopkart1/apps/kasir && /usr/local/bin/php artisan simpanan:generate-wajib >> /home/kopkart1/apps/kasir/storage/logs/cron.log 2>&1
15 2 * * *   mysqldump -u kopkart1_kasir -p<PASS> kopkart1_kasir 2>/dev/null | gzip > /home/kopkart1/backups/kasir-$(date +\%F).sql.gz
```

`simpanan:generate-wajib` was verified by hand for `2026-11`: 1 row written, rerun
wrote 0. Barcode (`DNS1D`, GD) and invoice PDF (dompdf) both render on the server.
Cron uses the `MAILTO` variable in the crontab — set it (`set_cron_email`) or output
stays silent on failure.

## VPS variant

Same steps, plus:

- Nginx root `.../public`, `fastcgi_pass` to PHP-FPM 8.2.
- `php artisan optimize` and `php-fpm` reload after each deploy.
- Supervisor or systemd timers replace the cPanel cron; a queue worker is still unnecessary.
- `mysqldump` cron and instance snapshots cover backups.
- Store `.env` outside the web root (it already is) and set `fastcgi_param` carefully so `.env` is never served.
