# Dockerizing CFOs Tools — A Step-by-Step Guide for Beginners

> **Project profile (detected):** Laravel 12 + Inertia + Vue 3 (Vite), PHP 8.4, MySQL,
> database-driven queues with 3 upload-processing jobs. Currently deployed on cPanel
> via `deploy.sh` + supervisor.

---

## Step 0 — The mental model (read this first)

A Docker "app" is not one box. For Laravel, best practice is **one image, several containers**:

| Container | What it does | Why separate |
|---|---|---|
| `app` (php-fpm) | Runs your PHP code | The core |
| `web` (nginx) | Serves `public/`, forwards `.php` to php-fpm | Web server ≠ PHP runtime |
| `queue` | `php artisan queue:work` | Replaces your supervisor setup |
| `scheduler` | `php artisan schedule:work` | Replaces cron (optional — no scheduled tasks yet) |
| `mysql` | Database | Swappable for managed RDS later |

`app`, `queue`, and `scheduler` all run **the exact same image** — just a different start
command. That's the key insight beginners miss: you build one image, run it three ways.

**Vite is a build-time tool, not a runtime one.** Your Vue/Tailwind assets get compiled into
`public/build/` during the image build, then Node is thrown away. No Node in the final image.

---

## Step 1 — Inventory what your app actually needs

Before writing anything, list the requirements. For this project:

- **PHP 8.4** (`composer.json` says `^8.2`, the server runs 8.4 — match production)
- **PHP extensions**: `pdo_mysql` (MySQL), `zip` + `gd` (maatwebsite/excel writes xlsx),
  `bcmath` (math-php / financial calcs), `intl`, `opcache` (performance), `exif`
- **Composer** (PHP deps)
- **Node 20+** (Vite 7 build)
- **Writable dirs**: `storage/`, `bootstrap/cache/`
- **A queue worker** (`ProcessSalesUpload`, `ProcessExpenseUpload`, `ProcessExportSalesUpload`)

> Why this matters: every extension you *don't* need is attack surface and image size.
> Every one you forget is a 500 error at 2 AM.

---

## Step 2 — Create `.dockerignore` (do this before the Dockerfile)

At the project root, a file named `.dockerignore`:

```gitignore
.git
node_modules
vendor
storage/logs/*
storage/framework/cache/*
storage/framework/sessions/*
storage/framework/views/*
public/build
public/storage
.env
.env.*
*.log
tests
.phpunit.result.cache
docs
resources.zip
README.md
deploy.sh
```

**What it does:** when you run `docker build`, Docker first copies the whole folder
("build context") to the build engine. Your `node_modules` + `vendor` are hundreds of MB.
This file excludes them.

**Why it's critical here:**

1. **Speed** — your context drops from ~500MB to a few MB.
2. **Correctness** — `vendor/` and `node_modules/` get installed *inside* the image for the
   right platform. Copying your Linux-host copies in would mask mistakes and break on other machines.
3. **Security** — `.env` contains your real DB password, AWS secret, and mail password.
   **It must never be baked into an image.** Images get pushed to registries; anyone who
   pulls it can run `docker history` and read layers.

---

## Step 3 — Write the `Dockerfile` (multi-stage)

Create `Dockerfile` at the project root.

### Stage 1 — PHP dependencies

```dockerfile
# syntax=docker/dockerfile:1

FROM composer:2 AS vendor

WORKDIR /app

# Copy ONLY the dependency manifests first.
COPY composer.json composer.lock ./

# Install without running Laravel's post-install scripts
# (artisan isn't copied yet, so they'd fail).
RUN composer install \
      --no-dev \
      --no-scripts \
      --no-autoloader \
      --prefer-dist \
      --no-interaction

# Now bring in the app code and finish the autoloader.
COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative
```

**Beginner explanation of the trick above:** Docker caches each instruction as a *layer*.
If a file an instruction depends on hasn't changed, Docker reuses the cached layer. By
copying `composer.json`/`composer.lock` alone before the rest of the code, the slow
`composer install` only re-runs when your dependencies actually change — not every time you
edit a Blade file. This turns a 2-minute rebuild into 5 seconds.

`--no-dev` drops PHPUnit, Faker, Pint, Sail — you don't ship test tooling to production.

### Stage 2 — Frontend assets (Vue + Tailwind via Vite)

```dockerfile
FROM node:22-alpine AS assets

WORKDIR /app

# Same caching trick: lockfile first.
COPY package.json package-lock.json ./
RUN npm ci

# Vite needs the source files and the PHP routes
# (Ziggy + laravel-vite-plugin read from them).
COPY . .
RUN npm run build
```

`npm ci` (not `npm install`) installs *exactly* what `package-lock.json` says — reproducible
builds. `npm install` may quietly update versions.

The output lands in `public/build/`. That's all we'll carry forward — Node itself is discarded.

### Stage 3 — The actual runtime image

```dockerfile
FROM php:8.4-fpm-alpine AS runtime

# System libs needed to COMPILE the PHP extensions
RUN apk add --no-cache \
      libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev \
      icu-dev oniguruma-dev \
      $PHPIZE_DEPS \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
      pdo_mysql bcmath zip gd intl exif opcache \
 && apk del $PHPIZE_DEPS \
 && rm -rf /tmp/*

WORKDIR /var/www/html

# Production PHP settings
COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-app.ini

# Copy the built application from the earlier stages.
COPY --chown=www-data:www-data --from=vendor /app                /var/www/html
COPY --chown=www-data:www-data --from=assets /app/public/build   /var/www/html/public/build

# Laravel must be able to write to exactly these two places.
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Never run as root.
USER www-data

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
```

**Key points for a beginner:**

- **`alpine`** = a tiny Linux base (~5MB vs ~80MB). Smaller image, faster pulls, fewer CVEs.
- **`apk del $PHPIZE_DEPS` in the same `RUN`** — compilers are needed to build extensions but
  not to run them. Deleting them *in the same instruction* means they never land in a layer.
  Deleting them in a separate `RUN` would not shrink the image at all (layers are append-only).
- **`USER www-data`** — if someone exploits your app, they get an unprivileged user, not root.
  This is the single highest-value security line in the file.
- **Only two writable dirs.** Everything else being read-only is a feature.

---

## Step 4 — The supporting config files

Create a `docker/` folder with three files.

### `docker/php/php.ini`

```ini
memory_limit = 512M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120

opcache.enable = 1
opcache.memory_consumption = 256
opcache.max_accelerated_files = 20000
opcache.validate_timestamps = 0
```

`validate_timestamps=0` tells PHP "the code never changes, don't stat files on every request"
— a large speedup, and safe because in Docker a code change means a new image. The upload
limits matter for you specifically: your app ingests sales/expense spreadsheets.

### `docker/nginx/default.conf`

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    client_max_body_size 64M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Note `root .../public` — **only the `public/` folder is exposed**. Your `.env`, `app/`, and
`vendor/` are unreachable over HTTP. `fastcgi_pass app:9000` works because Docker Compose
gives every service a DNS name equal to its service name.

### `docker/entrypoint.sh`

```bash
#!/bin/sh
set -e

# Wait for the database to accept connections.
until php -r "new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));" 2>/dev/null; do
  echo "Waiting for database..."
  sleep 2
done

# Cache config/routes/views at BOOT, not at build time.
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
```

**Why caching happens here and not in the Dockerfile:** `config:cache` freezes your
environment variables into a PHP file. If you ran it during `docker build`, the image would be
locked to one environment's settings — you could never reuse it for staging and production.
At container boot the real env vars are present, so the cache is correct.

`exec "$@"` hands control to whatever `CMD` was given (`php-fpm`, or `queue:work`), keeping it
as PID 1 so Docker's stop signals reach it properly.

---

## Step 5 — `docker-compose.yml`

```yaml
services:
  app:
    build:
      context: .
      target: runtime
    env_file: .env.docker
    volumes:
      - storage:/var/www/html/storage
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped

  web:
    image: nginx:alpine
    ports:
      - "8080:80"
    volumes:
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - app
    restart: unless-stopped

  queue:
    build:
      context: .
      target: runtime
    env_file: .env.docker
    command: php artisan queue:work --tries=3 --timeout=300 --max-jobs=1000
    volumes:
      - storage:/var/www/html/storage
    depends_on:
      mysql:
        condition: service_healthy
    restart: unless-stopped

  mysql:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: cfostools
      MYSQL_USER: cfostools
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - dbdata:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      retries: 10
    restart: unless-stopped

volumes:
  storage:
  dbdata:
```

**Concepts to absorb:**

- **`queue` reuses the same build** — identical code, different `command`. This replaces your
  `supervisorctl restart queue-worker:*`. Docker's `restart: unless-stopped` *is* your supervisor.
- **`--max-jobs=1000`** makes the worker exit periodically so Docker restarts it fresh. PHP
  workers leak memory over time; this is the idiomatic fix. (It also means new code is picked
  up — no more `queue:restart`.)
- **Named volumes** (`storage`, `dbdata`) persist data across container rebuilds. Without
  `dbdata`, `docker compose down` would erase your database. Without `storage`, user uploads
  would vanish on every deploy.
- **`healthcheck` + `condition: service_healthy`** — `depends_on` alone only waits for the
  container to *start*, not for MySQL to be *ready*. This is the #1 "it works on the second
  try" bug.
- **`:ro`** on the nginx config mounts it read-only.

---

## Step 6 — Handle environment variables safely

Create `.env.docker` (and add it to `.gitignore`, next to your existing `.env` entry):

```env
APP_NAME="CFOs Tools"
APP_ENV=production
APP_KEY=base64:...your key...
APP_DEBUG=false
APP_URL=https://cfostools.evoqas.com

DB_CONNECTION=mysql
DB_HOST=mysql          # ← the service name, NOT 127.0.0.1
DB_PORT=3306
DB_DATABASE=cfostools
DB_USERNAME=cfostools
DB_PASSWORD=...

QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
LOG_CHANNEL=stderr     # ← send logs to Docker, not to a file
```

Three changes from your current `.env` that beginners always trip on:

1. **`DB_HOST=mysql`, not `127.0.0.1`.** Inside a container, `127.0.0.1` means *that container
   itself*. Containers reach each other by service name.
2. **`APP_ENV=production` and `APP_DEBUG=false`.** Your current `.env` has `APP_ENV=local`
   with `APP_DEBUG=true` while pointing at the live domain — in Docker, `debug=true` would
   expose stack traces containing your DB credentials.
3. **`LOG_CHANNEL=stderr`.** Containers are ephemeral; a log file inside one disappears when
   it's replaced. Write to stdout/stderr and let `docker compose logs` (or your log platform)
   collect it.

---

## Step 7 — Build and run

```bash
# Build the images (first time: several minutes)
docker compose build

# Start everything in the background
docker compose up -d

# Run migrations once, inside the app container
docker compose exec app php artisan migrate --force

# Create the public/storage symlink
docker compose exec app php artisan storage:link

# Watch what's happening
docker compose logs -f app
docker compose logs -f queue
```

Open <http://localhost:8080>.

Everyday commands:

```bash
docker compose ps                    # what's running
docker compose exec app sh           # shell inside the container
docker compose restart queue         # restart the worker
docker compose down                  # stop (volumes/data survive)
docker compose down -v               # stop AND DELETE all data ⚠️
```

---

## Step 8 — Your deploy flow becomes

Replacing `deploy.sh`:

```bash
docker compose build
docker compose up -d
docker compose exec app php artisan migrate --force
```

That's it. No `artisan down`, no `chmod -R 775`, no `optimize:clear`, no
`supervisorctl restart`. The image *is* the artifact — build it once, run the identical bytes
in staging and production. Rollback becomes "run the previous image tag."

---

## Pitfalls specific to *this* project

1. **`node_modules` has both React and Vue.** `react`/`react-dom` are in `dependencies`
   (probably pulled in by `@univerjs/presets`). Harmless, but it inflates the build stage —
   worth confirming what actually needs React before optimizing further.
2. **`maatwebsite/excel` needs `zip` and `gd`.** Omit either and spreadsheet export fails
   silently at runtime, not build time. They're in the extension list above.
3. **Your 3 upload jobs can be slow.** Hence `--timeout=300` on the worker and the
   120s/64MB PHP limits. Tune to your largest real file.
4. **`storage/` is a volume, but `bootstrap/cache` is not.** That's deliberate — cache should
   be rebuilt per-image, not persisted.
5. **`laravel/sail` is already in `require-dev`.** Sail is a *development* convenience wrapper
   around Docker; it is not a production setup. Don't confuse the two — what's above is the
   production-grade path. You can still run `./vendor/bin/sail up` locally for a quick dev
   environment.
6. **Your `.env` currently holds live secrets and is git-ignored.** Keep it that way. For
   production, prefer injecting env vars from your host's secret manager over shipping a
   `.env.docker` file.

---

## Alternative worth knowing

[FrankenPHP](https://frankenphp.dev) (`FROM dunglas/frankenphp`) merges nginx + php-fpm into
one container and has an official Laravel integration. Fewer moving parts, but a smaller
community. The nginx + php-fpm split above is still the most widely documented — better for
learning and for finding answers when something breaks.
