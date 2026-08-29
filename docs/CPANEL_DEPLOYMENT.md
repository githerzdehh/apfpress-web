# APF Press cPanel deployment

This runbook deploys Laravel 13 to the existing `apfpress` cPanel account. The Git checkout stays outside the web root at `/home/apfpress/repositories/apfpress-web`; only the contents of Laravel's `public/` directory are published to `/home/apfpress/public_html`.

The current CentOS 7/cPanel 110 host is a temporary bridge. Complete its migration to a supported operating system before January 1, 2027.

The lockfile pins PHP 8.3-compatible Laravel 13 dependencies, and CI verifies the application against MySQL 5.7. Never run `composer update` on the server; deployments use `composer install` from the reviewed lockfile.

## 1. Install PHP 8.3 alongside existing versions

Run as `root`. Do not change the server-wide PHP default or any unrelated virtual host.

```bash
yum install -y ea-php83 ea-php83-php-bcmath ea-php83-php-cli ea-php83-php-common ea-php83-php-curl ea-php83-php-fpm ea-php83-php-intl ea-php83-php-mbstring ea-php83-php-mysqlnd ea-php83-php-opcache ea-php83-php-process ea-php83-php-xml ea-php83-php-zip
```

Verify the runtime and required extensions:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php -r 'echo PHP_VERSION, PHP_EOL; foreach (["bcmath","ctype","curl","dom","fileinfo","filter","hash","intl","mbstring","openssl","pcre","pdo","pdo_mysql","session","tokenizer","xml","zip"] as $e) echo $e.": ".(extension_loaded($e) ? "yes" : "NO").PHP_EOL;'
```

## 2. Prepare GitHub and cPanel Git

1. Revoke the previously exposed personal access token in GitHub.
2. As `apfpress`, generate a dedicated Ed25519 key, add its public half to the private GitHub repository as a read-only deploy key, and verify GitHub's host key through cPanel's **SSH Access / Manage SSH Keys** workflow.
3. In GitHub branch protection, require the `Laravel 13 / PHP 8.3 / MySQL 5.7` check before merging to `main`.
4. In **cPanel > Files > Git Version Control**, clone the `main` branch to `/home/apfpress/repositories/apfpress-web`.
5. Create `/home/apfpress/repositories/apfpress-web/.deploy-target` containing `staging`. The file is ignored by Git and an absent file also defaults safely to staging.

Do not clone the application into `public_html` and do not store GitHub tokens in clone URLs.

## 3. Create staging

1. Create `staging.apfpress.com` with document root `/home/apfpress/repositories/apfpress-web/public`.
2. Assign only the staging virtual host to PHP 8.3-FPM and issue AutoSSL.
3. Protect staging with cPanel Directory Privacy except while payment providers need to reach sandbox webhooks.
4. Create separate staging and production MySQL databases and least-privileged users. Do not reuse the WordPress database.
5. Copy `.env.example` to `.env`, configure staging values, generate `APP_KEY`, and set mode `600`. Required security values include `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`.
6. Use **Update from Remote** and **Deploy HEAD Commit**. The deployment script installs locked Composer dependencies, migrates, links storage, caches Laravel, and leaves `public_html` untouched while the target is `staging`.
7. Run the first staging seed while WordPress media URLs remain available:

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan db:seed --force
```

Install the two jobs in `deploy/cpanel/crontab.example` through cPanel after staging is healthy.

## 4. Production cutover

1. Complete sandbox orders, email checks, uploads, private downloads, policy review, shipping, tax, stock, and catalogue acceptance.
2. Disable staging access. Change the server-only `.env` to the production database and `https://apfpress.com`, generate the permanent production key, migrate, and seed while legacy WordPress media is still reachable.
3. Remove `APF_OWNER_PASSWORD` from `.env` after the owner is created, set `SESSION_SECURE_COOKIE=true`, then run `artisan optimize` with PHP 8.3.
4. Confirm the WordPress file and database backups are stored outside `public_html`. Move the live WordPress files into the retained legacy archive; do not delete the archive or its database for 30 days.
5. Leave `.well-known/` and `cgi-bin/` in place, then explicitly arm the production public root:

```bash
printf '%s\n' APF_PRESS_PRODUCTION > /home/apfpress/public_html/.apfpress-deploy-root
```

6. Set `apfpress.com` to PHP 8.3-FPM, change `.deploy-target` to `production`, and run **Deploy HEAD Commit**. The marker, target, production URL, production environment, MySQL credentials, secure cookies, disabled debug mode, generated key, and removed seed password are all required before the script will sync public files.
7. Verify `https://apfpress.com/up`, public pages, admin access, email, storage, and sandbox webhooks before entering live Stripe or PayPal credentials.

The deployment script now checks `/up` and `/api/v1/catalog` after bringing Laravel back online. Before enabling live payment credentials, also run the rendered Playwright suite against production with at least three workers and require two consecutive runs with no HTTP 5xx responses:

```bash
APFPRESS_BASE_URL=https://apfpress.com npx playwright test tests/browser/public-site.spec.ts --workers=3
```

If a concurrent smoke run reports intermittent 500 responses while sequential requests pass, stop the release and inspect Laravel, PHP-FPM, Apache, and MySQL logs for the same timestamp. In particular, verify the MySQL user's connection allowance has headroom above the PHP-FPM worker count plus the queue worker and scheduler, and confirm the database-backed session and cache tables are available.

## 5. Rollback and operations

If launch verification fails, leave the Laravel application in maintenance, restore the archived WordPress files to `public_html`, reassign `apfpress.com` to PHP 8.0, and keep using the untouched WordPress database. The Laravel production database remains separate.

Back up the Laravel database, `.env`, `storage/app/public`, and `storage/app/private`. Monitor failed jobs, application logs, webhooks, AutoSSL, and disk usage. GitHub is not a backup for customer data or uploaded files.
