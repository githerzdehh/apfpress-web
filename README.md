# APF Press platform

APF Press is a Laravel 13, MySQL 8.4, Vue 3, and Tailwind CSS publishing and commerce platform. It replaces the WordPress/WooCommerce runtime with a fast server-rendered public site, a small JSON API, and a role-based Vue administration workspace.

The public pages are rendered by Laravel for reliable indexing, metadata, and first-page speed. Vue is intentionally limited to the cart, checkout quote, responsive navigation, and administration screens. Catalogue, customer, order, inventory, and download data live in the local MySQL database; the live website is not queried during normal requests.

## Branch and deployment policy

- `dev` is the active development and review branch.
- `main` is the production/deployment branch. Merge tested work into `main`; do not develop directly on it.
- GitHub Actions builds the frontend and tests Laravel 13 on PHP 8.3 and MySQL 5.7 for pushes and pull requests targeting `dev` or `main`.
- cPanel Git deployment reads [.cpanel.yml](.cpanel.yml). On the APF Press server the private application checkout lives outside `public_html`; a guarded deployment publishes only Laravel's `public/` files.
- Production frontend assets in `public/build/` are committed because the target cPanel environment does not require Node.js.

This is proprietary client software. Secrets, private manuscripts, and digital editions are excluded from Git.

## Stack

- PHP 8.3+ (the provided container uses PHP 8.4)
- Laravel 13
- MySQL 8.4 LTS
- Blade SSR and Vue 3 + TypeScript islands
- Tailwind CSS 4 with a custom accessible editorial design system
- Stripe Checkout and PayPal Orders/Capture
- Private Laravel storage for manuscript submissions and digital editions
- SMTP mail, with Mailpit supplied locally

## Local setup with Docker

Docker Desktop or a running Docker Engine is required.

Use `docker compose` with current Docker Desktop/Compose v2. On older Linux installations that only provide Compose v1, replace it with `docker-compose` in the commands below.

On WSL installations without systemd, start Docker Engine directly when `docker info` reports that no daemon is available:

```bash
sudo nohup dockerd --host=unix:///var/run/docker.sock >/tmp/dockerd.log 2>&1 &
```

After the one-time `sudo usermod -aG docker "$USER"` command, run `newgrp docker` (or open a new WSL terminal) before using Docker without `sudo`.

The quickest setup automatically detects either Compose version:

```bash
bash scripts/local-bootstrap.sh
```

For normal daily restarts after the first setup, start the complete frontend, backend, database, and local mail service together:

```bash
docker-compose up -d
docker-compose ps
```

Use `docker compose` instead when the Compose v2 plugin is installed. MySQL should report `healthy`; Laravel is served on port `8080` and Vite on port `5174`.

Then run the complete local verification suite with:

```bash
bash scripts/local-check.sh
```

The equivalent manual setup is shown below for troubleshooting.

```bash
cp .env.example .env
docker compose build app
docker compose run --rm app composer install
docker compose run --rm vite npm ci
docker compose up -d mysql mailpit
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate --seed
docker compose run --rm app php artisan storage:link
docker compose up
```

Open:

- Website: `http://localhost:8080`
- Vite development server: `http://localhost:5174`
- Mailpit: `http://localhost:8025`

Port `5174` can be changed by setting `VITE_DEV_PORT` in `.env` if it is already in use.

The non-production seed owner is `owner@apfpress.test` with password `ChangeMe!12345`. Change it immediately. Production seeding creates no owner unless `APF_OWNER_EMAIL` and `APF_OWNER_PASSWORD` are explicitly configured.

To build production assets and run checks:

```bash
docker compose run --rm vite npm run build
docker compose run --rm vite npm test
docker compose run --rm app php artisan test
```

For a rendered browser check at desktop, tablet, and mobile sizes, install Playwright's local Chromium build once and run:

```bash
npx playwright install chromium
npm run test:browser
```

The browser suite verifies that CSS and fonts load through the local CSP/CORS boundary, checks responsive navigation and cart keyboard behaviour, catches horizontal overflow and failed local requests, walks the public catalogue/content routes, and saves review screenshots under `test-results/`.

The consolidated verification suite passes the Vue type/build checks, Vitest, Laravel feature tests, route discovery, the health endpoint, and the live catalogue API. Laravel tests are forced onto in-memory SQLite and do not migrate or truncate the local MySQL catalogue.

If legacy Compose v1 reports `KeyError: 'ContainerConfig'` after a service definition changes, recreate only the disposable application containers:

```bash
docker-compose stop app vite
docker-compose rm -f app vite
docker-compose up -d app vite
```

This leaves the named MySQL volume intact.

## Database model

The catalogue does not overload a single products table. Its core relationships are:

```text
catalog_items (book, product, service)
├── contributors via catalog_item_contributors (author/editor/etc.)
├── categories and collections
├── media_assets (cover/gallery/social)
├── book_details or service_details
└── offerings (print, e-book, product, service package)
    ├── book_editions (format, ISBN-10/13, publication date, pages, dimensions)
    ├── product_variants
    ├── inventories + inventory_movements
    └── digital_assets (private, versioned files)

carts → cart_items → offerings
orders → order_items → payments/refunds
                       └── digital_entitlements → download_events
```

The remaining schemas cover pages/posts, editorial board members, contact inquiries, manuscript submissions, revisions, redirects, shipping zones/rules, tax nexus rules, integration settings, import batches, and audit logs.

Prices are integer minor units (cents), currency is CAD, and all totals are recalculated from database values on the server. Launch shipping zones are Canada and the United States. Tax rules are deliberately empty/disabled until APF Press has jurisdiction-specific advice; staff can manage the rules without a code change.

## WooCommerce migration

The repository contains a normalized snapshot of the 33 publicly available WooCommerce catalogue records at [database/data/woocommerce-products.json](database/data/woocommerce-products.json). `CatalogSeeder` imports that snapshot into MySQL. It is not a runtime API dependency.

`APF_IMPORT_DOWNLOAD_MEDIA=true` is the recommended default and copies cover binaries into local public storage during the first catalogue import. The source URL remains in MySQL for provenance; after a successful copy, customer pages use the local asset and no longer depend on WordPress media. Set it to `false` only when running an intentionally offline import, in which case the verified source URL remains as a temporary fallback.

For a newer export, staff can open `/admin`, choose **Woo import**, upload a WooCommerce Store API JSON response or standard product CSV, review the preview and warnings, and then commit it. The converter:

- upserts on the stable WooCommerce source ID;
- maps print/e-book formats, prices, categories, authors, stock data, and source URLs;
- creates an internal `APF-WOO-xxxxx` SKU when the source has none;
- records flags for missing author, ISBN, publication date, price, and exact stock count;
- keeps imported e-books inquiry-only until a private PDF/EPUB is uploaded;
- can copy cover images from the APF Press domain, with host, MIME, redirect, size, and timeout checks.

The standalone normalization helper is also available:

```bash
node scripts/normalize-woocommerce-export.mjs input.json output.json
```

No legacy WordPress customer passwords or orders are imported. Customers create clean accounts on the new platform.

## Administration

Roles are intentionally separated:

- `owner`: all catalogue, order, payment, shipping, and tax settings;
- `editor`: catalogue editing, metadata remediation, covers, private digital files, and imports;
- `fulfillment`: paid order review and fulfilment updates;
- `customer`: account, orders, and active downloads.

Payment credentials entered in the admin workspace use Laravel's encrypted database cast and are never returned to the browser. Environment variables can be used before an integration record is created. A saved disabled integration remains disabled even when credentials exist.

## Payments and digital access

Both gateways use server-created orders and idempotent provider identifiers. Stripe completion is authoritative through a signed webhook. PayPal captures on the signed-in return route and also verifies webhook events against PayPal's verification API. Amount and currency are compared to the local payment before an order is finalized.

Inventory is reserved before redirecting to payment, converted into a sale on successful payment, and released on cancellation. Payment events are deduplicated. A successful digital order receives an entitlement tied to a private asset and order item. Downloads require authentication plus a short-lived signed URL, are audited, and stop when the configured access period expires or the entitlement is revoked.

Configure provider callbacks as:

- Stripe: `https://apfpress.com/payments/webhooks/stripe`
- PayPal: `https://apfpress.com/payments/webhooks/paypal`

Use sandbox credentials and provider CLI/test tools before enabling live mode.

## cPanel production checklist

The complete, server-specific walkthrough is in [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md). The current CentOS 7/cPanel 110 host uses patched EasyApache PHP 8.3 as a temporary bridge and must move to a supported operating system before January 1, 2027.

1. Install EasyApache PHP 8.3 alongside the existing versions with `bcmath`, `curl`, `intl`, `mbstring`, `mysqlnd`, `opcache`, `process`, `xml`, and `zip`; do not change other virtual hosts.
2. Create separate staging and production MySQL databases with least-privileged application users.
3. Clone the private `main` branch through cPanel Git to `/home/apfpress/repositories/apfpress-web`; use a read-only GitHub deploy key, not a personal token.
4. Create `.env` from `.env.example`; set `APP_ENV=production`, `APP_DEBUG=false`, the canonical HTTPS `APP_URL`, MySQL credentials, SMTP, and a new `APP_KEY`.
5. On the first deployment only, run `php artisan db:seed --force` after migration to create the content and catalogue snapshot. With `APF_IMPORT_DOWNLOAD_MEDIA=true`, cover files are copied locally and their original URLs are retained only as provenance.
6. Ensure `storage/` and `bootstrap/cache/` are writable by the PHP account. Keep `storage/app/private` outside public access.
7. Install the scheduler and guarded queue-worker cron entries from `deploy/cpanel/crontab.example`.
8. Configure Stripe and PayPal webhooks, then run sandbox orders for print and digital editions.
9. Review and publish the privacy, terms, and refund policy drafts. They intentionally return 404 until approved.
10. Review imported metadata flags, exact stock, ISBNs, publication dates, covers, descriptions, tax nexus, and shipping rates before launch.
11. Require the GitHub `Laravel 13 / PHP 8.3 / MySQL 5.7` check on `main`, merge the release only after it passes, then let cPanel run the guarded deployment. Production publishing requires an explicit target and public-root marker after WordPress is archived.

## API surface

Public read endpoints:

- `GET /api/v1/catalog`
- `GET /api/v1/catalog/{slug}`

Session/CSRF-protected cart and checkout endpoints are under `/api/cart` and `/checkout`. Staff endpoints live under `/admin/api` and require an authenticated, verified account with the relevant role. Detailed validation errors use JSON for all API requests.

## Quality and security notes

- Every public page has a canonical URL, description, Open Graph metadata, responsive layout, keyboard focus states, semantic headings, and reduced-motion handling.
- Book pages emit Schema.org `Book`/`Offer` JSON-LD; the home page emits organization data; `/sitemap.xml` is database-driven.
- CSP, frame, MIME-sniffing, referrer, and permissions headers are applied centrally.
- Passwords are hashed, email verification is required for checkout, login and form actions are rate-limited, CSRF protects state changes, and admin routes enforce roles on the server.
- Source maps, secrets, private files, runtime caches, and dependencies are excluded from deployment source. Security comes from server-side boundaries and least privilege—not brittle code obfuscation.

Current automated checks cover import mapping and metadata flags, SSR catalogue pages, draft-page protection, database-priced carts, admin shipping/tax quotes, inventory finalization, and expiring digital entitlements.
