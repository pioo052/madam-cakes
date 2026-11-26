# MADAM CAKES — Docker deployment

The repository ships a two-container stack:

| Container | Image | Purpose |
|---|---|---|
| `app` | built from `Dockerfile` (`php:8.4-apache`) | Apache + PHP 8.4, the whole application |
| `db` | `mariadb:10.11` | database, same major version verified on the development machine |

The application still runs exactly as documented in `README.md`; Docker does not add a
framework, a build step or simulated data.

---

## 1. First deployment

Requirements: Docker Engine 24+ with the Compose plugin, and a host with ports 8080
(or your chosen port) reachable.

```bash
cp .env.example .env
# edit .env: set DB_PASS, DB_ROOT_PASS and APP_URL (real domain in production)
docker compose up -d --build
```

Then:

1. Open `http://<host>:8080/admin/setup.php` and create the first administrator.
   The page disables itself once an administrator exists.
2. Sign in at `admin/login.php` and enter the studio's real data — business identity,
   contact details, WhatsApp number, categories, products and prices, delivery zones,
   coupons and gallery content (README §5).
3. Place one real test order and walk it through the admin workflow.

On the first start only, `database/schema.sql` and `database/seed.sql` are imported into
the empty database volume. The seed creates categories, cake options and settings — never
products, prices or customers.

`docker compose ps` should show both containers `healthy`. If the app container restarts,
its entrypoint waits up to 120 seconds for MariaDB and then exits, so inspect
`docker compose logs app` and `docker compose logs db`.

---

## 2. Configuration

`config/config.php` reads these environment variables, so no credentials are written
into the image or the repository:

| Variable | Default in compose | Meaning |
|---|---|---|
| `APP_PORT` | `8080` | host port published for Apache |
| `APP_URL` | `http://localhost:8080` | public base URL used in links, sessions and redirects |
| `APP_ENV` | `production` | `production` hides PHP errors and logs them to `logs/error.log` |
| `APP_HTTPS` | `0` | set `1` when TLS is terminated in front of the container (marks the session cookie Secure) |
| `DB_NAME` | `madam_cakes_db` | database name |
| `DB_USER` | `madam` | application database user |
| `DB_PASS` | — (required) | application database password |
| `DB_ROOT_PASS` | — (required) | MariaDB root password, used only for administration |

`MADAM_DB_*` names inside the container are set by compose from the `DB_*` values above.
A local XAMPP installation is unaffected: without these variables the previous defaults
apply, and `config/config.local.php` still takes precedence when present.

### Changing the database password later

MariaDB applies `DB_PASS` only when it initialises an empty data volume. After that,
changing `.env` alone leaves the app unable to connect. Rotate it inside the database:

```bash
docker compose exec db mariadb -u root -p"$DB_ROOT_PASS" \
  -e "ALTER USER 'madam'@'%' IDENTIFIED BY 'new-password';"
```

then update `DB_PASS` in `.env` and run `docker compose up -d app`.

---

## 3. Persistence

| Volume | Mounted at | Contents |
|---|---|---|
| `madam-cakes_db_data` | `/var/lib/mysql` | MariaDB data files |
| `madam-cakes_uploads` | `assets/uploads` | owner-uploaded images |
| `madam-cakes_logs` | `logs` | PHP error log |

The application code itself lives in the image; only these three volumes survive
`docker compose down`. Never run `docker compose down -v` unless you intend to delete the
database and every uploaded image.

### Backups

```bash
# database
docker compose exec -T db sh -c 'mariadb-dump -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' > madam-cakes-$(date +%F).sql

# uploads
docker run --rm -v madam-cakes_uploads:/data -v "$PWD":/backup alpine \
  tar czf /backup/uploads-$(date +%F).tar.gz -C /data .
```

Restore the database with `docker compose exec -T db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < backup.sql`,
and uploads by extracting the archive into the `uploads` volume.

---

## 4. HTTPS and exposure

The container serves plain HTTP. In production put a TLS-terminating reverse proxy
(Caddy, nginx, Traefik, or the hosting platform's load balancer) in front of it and:

1. set `APP_URL=https://your-domain` and `APP_HTTPS=1` in `.env`;
2. publish the app port on loopback only, e.g. `127.0.0.1:8080:80` in the `ports` entry,
   so only the proxy can reach Apache;
3. keep the database unpublished — it has no `ports` entry and is reachable only from
   the `app` container.

The application's `.htaccess` files (denying `config/`, `database/`, `includes/`, `logs/`
and `tools/`, and blocking script execution in `assets/uploads/`) are enforced because
`docker/apache.conf` sets `AllowOverride All`. Do not remove that line.

---

## 5. Updating and operating

```bash
git pull
docker compose build app
docker compose up -d
```

The database volume is untouched by a rebuild; uploads and logs stay in their volumes.

Useful commands:

| Task | Command |
|---|---|
| Follow application logs | `docker compose logs -f app` |
| Follow database logs | `docker compose logs -f db` |
| Application error log | `docker compose exec app tail -n 100 logs/error.log` |
| Open a database shell | `docker compose exec db mariadb -u root -p"$DB_ROOT_PASS" "$DB_NAME"` |
| Restart after an `.env` change | `docker compose up -d` |
| Stop (keeps data) | `docker compose down` |

### Running the end-to-end suite against the container

The test script needs the CLI and HTTP on the same origin, both of which exist inside
the app container:

```bash
docker compose exec -e MADAM_URL=http://127.0.0.1 app php tools/e2e_test.php
```

It creates and then removes temporary records, and it refuses to run if an administrator
already exists in some checks — run it before entering the studio's real data, or expect
the owner account to be left untouched.

### Schema migrations

`schema.sql` already contains the current schema (including the `migrate_v2.sql`
additions), so fresh deployments need nothing else. An existing database created from an
older schema is upgraded with:

```bash
docker compose exec -T db sh -c 'mariadb -u root -p"$MARIADB_ROOT_PASSWORD" "$MARIADB_DATABASE"' < database/migrate_v2.sql
```

Never import `schema.sql` over a live database: it drops every table.

---

## 6. Deliberate limits

* No TLS, no mail server and no payment gateway are added by the containers — the
  application verifies payments manually and stores notifications, as designed.
* One MariaDB instance and one app instance; horizontal scaling would need shared
  session storage and shared uploads, which this release does not use.
* Uploaded images are stored on the `uploads` volume, not in object storage.
