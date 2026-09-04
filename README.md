# Kafeel (كَفِيلْ) — E-commerce Store

[![Build and publish Docker image](https://github.com/TechZeeLand/Kafeel/actions/workflows/docker-publish.yml/badge.svg)](https://github.com/TechZeeLand/Kafeel/actions/workflows/docker-publish.yml)
[![PHP lint](https://github.com/TechZeeLand/Kafeel/actions/workflows/php-lint.yml/badge.svg)](https://github.com/TechZeeLand/Kafeel/actions/workflows/php-lint.yml)
[![License: AGPL v3](https://img.shields.io/badge/License-AGPL%20v3-blue.svg)](LICENSE)

A self-hosted online store for EDC gear, bags, leather goods, and
customized/personalized pieces. Plain PHP + MariaDB backend, vanilla
HTML/CSS/JS frontend, no framework — deploys as a standalone Docker Compose
stack on any Docker host (a Debian/Ubuntu server, Portainer, Unraid, etc.).

## What's included

- **Storefront:** homepage, category pages, product detail pages with an
  image gallery, optional color/size variants, full-text search, cart,
  wishlist/favorites, checkout (cash-on-delivery only), order confirmation,
  order tracking with a real status timeline, and a downloadable PDF
  invoice per order.
- **Customer accounts:** register/login with email verification, profile +
  password management, saved addresses, order history.
- **Admin portal** at `/admin`: dashboard with revenue/low-stock stats,
  product CRUD with image + gallery uploads and per-product variants
  (color/size, price adjustment, stock), category CRUD, order management
  with status updates that log a timestamped history and email the
  customer, customer list with enable/disable, and a theme settings page
  for live primary/secondary color changes plus optional seasonal effects
  (snow / falling leaves / rain).
- **Email:** PHPMailer-backed transactional email (falls back to PHP's
  `mail()` if no SMTP is configured) for the contact form, email
  verification, and order status updates.
- **Security basics:** password hashing (bcrypt via `password_hash`),
  CSRF tokens on every form and AJAX call, prepared statements everywhere,
  session hardening, uploaded files validated by MIME type and served from
  a directory with PHP execution disabled.
- Fully responsive layout (mobile nav drawer, responsive grids) styled with
  a custom, non-templated design system — the "field ledger" theme
  (ink-navy/brass/paper tones, monospace type, index-card product tiles).
  See `src/assets/css/style.css`.

## Stack

A fully independent Docker Compose stack — it does not share a database,
network, or reverse proxy with anything else on the host:

- **`web`** — one container with **nginx baked in** alongside PHP 8.3-FPM
  (run together via supervisord). Nginx serves static assets directly and
  proxies `.php` requests to PHP-FPM over a unix socket. This is the single
  container/port you expose. Built automatically by CI and published to
  GitHub Container Registry — see [Continuous integration](#continuous-integration).
- **`db`** — its own **MariaDB 11.4** instance, with data on a dedicated
  named volume (`kafeel_db_data`), isolated from any other project.
- **`phpmyadmin`** — a bundled phpMyAdmin UI pointed at `db`, on its own
  port, for easy database inspection/edits.

All three share a private `kafeel_net` bridge network and nothing else.

## Continuous integration

`.github/workflows/docker-publish.yml` builds `docker/php/Dockerfile` and
pushes it to **GitHub Container Registry** on every push to `main` and on
version tags:

| Trigger | Image tag |
|---|---|
| Push to `main` | `ghcr.io/techzeeland/kafeel:latest` |
| Tag `v1.2.3` | `ghcr.io/techzeeland/kafeel:v1.2.3`, `:1.2`, `:1` |
| Any push | `ghcr.io/techzeeland/kafeel:<git-sha>` (for pinning/rollback) |

`.github/workflows/php-lint.yml` syntax-checks every PHP file on every push
and pull request against `main`.

Because the image is a real, public package, **`docker-compose.yml` needs
no build step and no local filesystem context** — it just references
`image: ghcr.io/techzeeland/kafeel:latest`. That's what makes it safe to
paste directly into Portainer's web editor, or run anywhere with plain
`docker compose up`, with no "unable to prepare context" or "pull access
denied" surprises.

## Quick start

1. Create the project folder and a writable uploads folder:
   ```bash
   mkdir -p /Sites/Kafeel/uploads/products
   chown -R 33:33 /Sites/Kafeel/uploads   # uid 33 = www-data inside the container
   ```
2. Get the files there — either clone the repo:
   ```bash
   git clone https://github.com/TechZeeLand/Kafeel.git /Sites/Kafeel
   ```
   or download/extract a release archive into `/Sites/Kafeel`.
3. Copy `.env.example` to `.env` and adjust values (DB credentials, site
   name, currency, etc.).
4. Deploy — pick one:
   - **Portainer (web editor):** Stacks → Add stack → name it `kafeel` →
     Web editor → paste the contents of `docker-compose.yml` → Deploy. Env
     vars are optional in Portainer's UI since every one already has a
     working default (`${VAR:-default}`).
   - **Portainer (Repository):** Stacks → Add stack → Repository → point at
     `https://github.com/TechZeeLand/Kafeel`, branch `main`, compose path
     `docker-compose.yml`. Portainer will pull updates on redeploy.
   - **CLI:**
     ```bash
     cd /Sites/Kafeel && docker compose up -d
     ```
5. First boot runs `sql/schema.sql` automatically (via MariaDB's
   `docker-entrypoint-initdb.d`), creating all tables plus sample
   categories/products so the site isn't empty on first look.

> **Using a folder other than `/Sites/Kafeel`?** `docker-compose.yml` binds
> the `uploads` folder and `sql/schema.sql` using that absolute path on
> purpose (see the comment at the top of the file for why). Update those
> two lines to match your actual path, or switch them to relative paths
> (`./uploads`, `./sql/schema.sql`) if you're deploying via Portainer's
> Repository method or plain CLI, where relative paths resolve correctly.

### Access

- **Storefront:** `http://your-server-ip:1023` (or whatever `WEB_PORT` you set)
- **Admin portal:** `http://your-server-ip:1023/admin`
  - **Username:** `admin`
  - **Password:** `ChangeMe123!`
- **phpMyAdmin:** `http://your-server-ip:1024` (or whatever `PMA_PORT` you
  set) — logs in automatically as the `admin` database user.

The same `admin` / `ChangeMe123!` credentials are used for: the database
user (`DB_USER`/`DB_PASS`), phpMyAdmin login, and the store admin account.
The database **root** password defaults to the same value (`DB_ROOT_PASS`)
— **change all of these in `.env` before going live.**

### Changing the admin password later

There's no in-app UI for this yet, so generate a new bcrypt hash and update
it directly:

```bash
docker compose exec web php -r "echo password_hash('YourNewPassword!', PASSWORD_DEFAULT), PHP_EOL;"
docker compose exec db mariadb -u root -p"$DB_ROOT_PASS" "$DB_NAME" \
  -e "UPDATE admins SET password_hash='PASTE_HASH_HERE' WHERE username='admin';"
```

### Applying schema migrations to an existing database

New database changes ship as numbered SQL files in `sql/migrations/`. Fresh
installs get everything via `sql/schema.sql` automatically; if you already
have a running database, apply any new migration files by hand, in order:

```bash
docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASS" "$DB_NAME" < sql/migrations/001_phase1.sql
docker compose exec -T db mariadb -u root -p"$DB_ROOT_PASS" "$DB_NAME" < sql/migrations/002_phase2.sql
```

(Or paste each file's contents into phpMyAdmin's SQL tab, in order.)

`002_phase2.sql` adds product dimensions/color/variants, order status
history, the suburbs shipping zone, email verification columns, and the
`settings` table used by the admin theme page — all backward compatible,
existing orders/products/users are backfilled sensibly (existing accounts
are marked verified so nobody gets locked out).

### Outbound email (SMTP)

The contact form, email verification, and order status notifications are
all sent via [PHPMailer](https://github.com/PHPMailer/PHPMailer). Set
`SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, and `SMTP_SECURE`
(`tls`, `ssl`, or blank) in `.env` to point at a real mail provider (Gmail
app password, Zoho, SendGrid SMTP, your registrar's mail server, etc.).
Leaving `SMTP_HOST` blank falls back to PHP's built-in `mail()` — fine for
a quick local test, but most hosts won't actually deliver without real SMTP
credentials, and messages will silently fail (logged to the PHP error log,
never crashes the request).

## Local development (live-reload)

`docker-compose.override.yml` is picked up automatically by `docker compose`
whenever it's run from this folder — no flags needed. It bind-mounts your
live `src/` tree over the container's code and switches on OPcache
timestamp checking (`docker/php/opcache-dev.ini`, already baked into the
image), so **editing a file and refreshing the browser is all it takes** —
no rebuild, no restart.

Because the whole `/var/www/html` is bind-mounted from `./src`, the
image's baked-in Composer dependencies (PHPMailer, mPDF — see
[`composer.json`](composer.json)) get shadowed too. Run
[`./vendor-install.sh`](vendor-install.sh) once (and again whenever
`composer.json` changes) to populate a local `./vendor` that the override
file mounts back on top:

```bash
git clone https://github.com/TechZeeLand/Kafeel.git
cd Kafeel
cp .env.example .env
mkdir -p uploads/products
./vendor-install.sh

# Build a local image tagged to match docker-compose.yml's `image:` field,
# so `docker compose up` uses it instead of trying to pull from GHCR.
# docker-compose.yml has no `build:` section on purpose (see the comment
# at the top of that file), so this is a plain `docker build`.
./build.sh

docker compose up -d
```

You only need to re-run `./build.sh` (then `docker compose up -d
--force-recreate web`) after changing the `Dockerfile`, nginx config,
supervisor config, or PHP extensions/ini — not for everyday `src/` edits.

Full contributor workflow, code style, and PR checklist: see
[CONTRIBUTING.md](CONTRIBUTING.md).

### Migrating from an old "Khasais" deployment

If you previously had this stack running under the old `Khasais` name (e.g.
in `/Sites/Khasais` or `/opt/stacks/khasais`), tear that stack down first so
its containers, network, and volumes don't collide with the renamed ones:

```bash
cd /Sites/Khasais           # or wherever the old stack folder was
docker compose down         # stops & removes khasais_web / khasais_db / khasais_phpmyadmin + khasais_net

# Optional: only if you don't need the old data (a fresh deploy re-seeds
# sample products/categories anyway). Skip this if you want to keep it.
docker volume rm khasais_db_data khasais_uploads_data
```

Then follow [Quick start](#quick-start) above. Because every container,
network, and volume name changed from `khasais_*` to `kafeel_*`, this is
treated as a brand-new stack — it will not reuse the old `khasais_db_data`
volume, so a fresh database is created and seeded from `sql/schema.sql`.

## Project layout

```
Kafeel/
├── .github/
│   ├── workflows/
│   │   ├── docker-publish.yml  # builds & pushes the image to GHCR
│   │   └── php-lint.yml        # syntax-checks PHP on push/PR
│   ├── ISSUE_TEMPLATE/
│   └── PULL_REQUEST_TEMPLATE.md
├── docker-compose.yml          # production: GHCR image, no build step
├── docker-compose.override.yml # local dev: live-reload bind mount (auto-merged)
├── build.sh                    # local dev/test build, tagged to match GHCR
├── .env.example                # copy to .env
├── docker/
│   ├── php/
│   │   ├── Dockerfile          # PHP 8.3-FPM + nginx + supervisor, one image
│   │   ├── uploads.ini         # upload size limits
│   │   └── opcache-dev.ini     # live-reload: revalidate PHP files every request
│   ├── nginx/default.conf      # nginx site config (proxies *.php to php-fpm)
│   └── supervisor/supervisord.conf   # runs nginx + php-fpm together
├── sql/schema.sql              # tables + seed data (auto-run on first boot)
├── uploads/products/            # uploaded product photos — bind-mounted into
│                                 # the container, so files land right here on
│                                 # the host (back these up like any real data)
├── LICENSE                      # AGPL-3.0
├── CONTRIBUTING.md
├── CODE_OF_CONDUCT.md
└── src/                         # the actual web root (bind-mounted for live reload)
    ├── includes/                # config, db, auth, shared helpers/templates
    ├── assets/{css,js,img}      # styles, JS, icons/placeholders
    ├── api/                     # AJAX endpoints (cart, favorites)
    ├── admin/                   # admin portal
    └── *.php                    # storefront pages
```

## Running behind a domain / reverse proxy

If you're putting this behind Nginx Proxy Manager, Caddy, or Traefik:

1. Point the proxy at `http://<server-ip>:1023` (or `WEB_PORT`), or put
   `web` on the proxy's Docker network and drop the published port.
2. Set `SITE_URL` in `.env` to your public URL once you have one (currently
   used for reference only — all internal links are relative).
3. Terminate TLS at the proxy; consider adding `secure` to the session
   cookie in `src/includes/config.php` once you're serving over HTTPS only.
4. You generally do **not** need to expose the `phpmyadmin` port publicly —
   either drop its port mapping or keep it reachable only over your home
   network/VPN.

## Extending

- **Payments:** only Cash on Delivery and "bank transfer" (manual) are wired
  up. To add a card gateway (Stripe, SSLCommerz, bKash, etc.), add an
  integration in `checkout.php` — the `orders` table already has a
  `payment_method` column to extend.
- **Email:** the contact form and order confirmation currently don't send
  email (no SMTP server assumed). Wire up PHPMailer or a transactional
  email API if you want notifications.
- **Extra services:** it's a normal Docker Compose file, so you can add
  another service (e.g. a scheduled inventory report) and point it at the
  same `db` service on the `kafeel_net` network.

## Security notes before going live

- Change the default `admin` / `ChangeMe123!` credentials (admin login, DB
  user, and DB root password) before exposing this publicly.
- Set `APP_DEBUG=0` in production (it already defaults to that) so PHP
  errors aren't shown to visitors.
- `uploads/products` is a real host folder (already configured, bind-mounted)
  so photos survive rebuilds — include it in your backups.
- Back up the `db_data` volume regularly:
  `docker compose exec db mariadb-dump -u root -p"$DB_ROOT_PASS" "$DB_NAME" > backup.sql`
- Found a security issue? Please report it privately rather than opening a
  public issue — see [CONTRIBUTING.md](CONTRIBUTING.md#reporting-security-issues).

## Contributing

Bug reports, features, and PRs are welcome — see
[CONTRIBUTING.md](CONTRIBUTING.md) for the dev setup, code style, and PR
checklist. Please also read the
[Code of Conduct](CODE_OF_CONDUCT.md).

## License

Kafeel is licensed under the [GNU AGPL v3.0](LICENSE). In short: you're
free to use, modify, and self-host it, but if you run a modified version
as a network service, you must make your modified source available to its
users under the same license.
