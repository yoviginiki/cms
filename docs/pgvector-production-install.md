# pgvector — production install runbook

**Audience:** the person running this by hand, possibly at 1 AM. Every command is
copy-paste. Nothing here is executed automatically — you run each step yourself.

**What this does:** installs the PostgreSQL `vector` extension on the **production**
database so Sumi (RAG) can eventually use a real ANN index instead of scanning every
row and computing cosine in PHP. This runbook installs *only the extension*. It does
**not** migrate any data, add any column, or change any application code — those are a
separate, later session (see `pgvector-handoff.md`).

---

## TL;DR — the two things you actually care about

- **Restart required?** ❌ **No.** `CREATE EXTENSION vector` does not touch
  `shared_preload_libraries`. Installing the apt package does not restart PostgreSQL.
- **Downtime / app broken at any point?** ❌ **No.** The extension is purely additive.
  Nothing in the current application code references the `vector` type yet, so the
  running app neither sees nor needs this change until a future migration uses it.

If you only remember one thing: this is safe to run on a live system.

---

## Target

| | |
|---|---|
| Database | `cms_saas_platform` (production — from `.env`, `APP_ENV=production`) |
| Host / port | `127.0.0.1:5432` |
| App DB role | `cms_saas` (NOT a superuser — this is exactly why the extension must be installed by an admin) |
| PostgreSQL | 16.x |
| OS | Ubuntu 24.04 |
| Extension version installed on dev | `vector` 0.6.0 |

> The app role `cms_saas` **cannot** run `CREATE EXTENSION` (`must be superuser`).
> That is the entire reason this runbook exists. You need a superuser: locally that is
> the `postgres` OS user via peer auth (`sudo -u postgres psql`).

---

## Step 0 — Pre-flight checks (read-only, change nothing)

Run these first. They tell you whether the package is present and whether the extension
is already installed. **None of these modify anything.**

```bash
# 0a. Is the pgvector package present on the production DB host?
dpkg -l | grep -i pgvector
```
**Expect:** a line like `ii  postgresql-16-pgvector  0.6.0-1 ...`.
If you get **no output**, the package is missing → do **Step 1**. Otherwise skip to Step 2.

```bash
# 0b. Does the server offer the extension, and is it already installed on the prod DB?
sudo -u postgres psql -d cms_saas_platform -c \
  "SELECT name, default_version, installed_version
     FROM pg_available_extensions WHERE name = 'vector';"
```
**Expect:** one row. `default_version` = `0.6.0`. If `installed_version` is **already
set** (e.g. `0.6.0`), the extension is **already installed** → you are done, skip to
Step 3 to confirm and stop. If `installed_version` is **empty**, continue to Step 2.

If Step 0b returns **zero rows**, the package is not visible to this server → Step 1.

---

## Step 1 — Install the OS package (ONLY if Step 0 showed it missing)

Skip this entirely if `dpkg -l | grep pgvector` already showed the package.

```bash
sudo apt-get update
sudo apt-get install -y postgresql-16-pgvector
```
- **What it does:** drops the `vector` extension files into the PostgreSQL 16 extension
  directory. It does **not** restart PostgreSQL and does **not** load anything into a
  running database yet.
- **Version note:** the `16` must match the server's **major** version. Confirm with
  `psql -V` or `SELECT version();` if unsure. On PG 15 the package is
  `postgresql-15-pgvector`, etc.
- **Expect:** apt reports the package installed. Re-run `dpkg -l | grep pgvector` to
  confirm.

No restart is needed after this. Proceed to Step 2.

---

## Step 2 — Create the extension on the production database

This is the one privileged action. It uses the superuser connection **only** for this
single statement.

```bash
sudo -u postgres psql -d cms_saas_platform -c "CREATE EXTENSION IF NOT EXISTS vector;"
```
- **What it does:** registers the `vector` type, its operators (`<=>`, `<->`, `<#>`),
  and index access methods **inside the `cms_saas_platform` database**. `CREATE EXTENSION`
  is per-database — this affects only this one DB, nothing else on the instance.
- **Expect output:** `CREATE EXTENSION` (or, if it already existed, the command is a
  no-op thanks to `IF NOT EXISTS`).
- **`IF NOT EXISTS`** makes this safe to run twice — re-running never errors or duplicates.

**Do not grant the app role anything.** pgvector needs no extra privileges for the app
user after install (verified on dev). If any instruction anywhere tells you to `GRANT`
something to `cms_saas` to make vectors work — stop, that is not needed.

---

## Step 3 — Verify it worked

```bash
# 3a. Extension registered? (superuser view)
sudo -u postgres psql -d cms_saas_platform -c \
  "SELECT extname, extversion FROM pg_extension WHERE extname = 'vector';"
```
**Expect:** one row — `vector | 0.6.0`.

```bash
# 3b. Usable by the APPLICATION role (the real test). Run as cms_saas, not postgres.
#     You will be prompted for the cms_saas password (it is in .env as DB_PASSWORD).
PGPASSWORD='<DB_PASSWORD from .env>' psql -h 127.0.0.1 -p 5432 -U cms_saas -d cms_saas_platform -c \
  "SELECT '[1,2,3]'::vector AS cast_ok,
          '[1,2,3]'::vector <=> '[1,2,4]'::vector AS cosine_distance;"
```
**Expect:** `cast_ok = [1,2,3]` and `cosine_distance ≈ 0.0085…`.

If **3b succeeds as the `cms_saas` role**, the install is complete and correct: the
application user can use the type without any further grants. **You are done.**

---

## If something goes wrong

| Symptom | Meaning | Action |
|---|---|---|
| Step 0b returns 0 rows | Server can't see the extension files | Do Step 1 (install package), then retry Step 0b |
| Step 2: `could not open extension control file … vector.control` | Package not installed / wrong PG major | Confirm `psql -V`, install matching `postgresql-<major>-pgvector`, retry |
| Step 2: `permission denied to create extension "vector" … must be superuser` | You are not connected as a superuser | Use `sudo -u postgres psql`, not the `cms_saas` role |
| Step 3b: `type "vector" does not exist` when run as `cms_saas` | Extension created in the wrong database | Re-check you targeted `-d cms_saas_platform` in Step 2 |

**Rollback** (only if you must undo — not expected to be necessary):
```bash
sudo -u postgres psql -d cms_saas_platform -c "DROP EXTENSION IF EXISTS vector;"
```
This is safe **only** as long as no table has a `vector` column yet. After the future
data migration adds a `vector(N)` column, dropping the extension would fail (dependency)
— which is correct. At the point of this runbook, nothing depends on it, so rollback is
clean.

---

## After the runbook

- Nothing else to do in this window. The app keeps running on the existing jsonb +
  PHP-cosine path exactly as before — the extension is installed but unused.
- The actual switch to a `vector(N)` column + ANN index is a **separate later session**
  with normal (non-superuser) privileges. See `pgvector-handoff.md` for that plan and,
  importantly, for the check that confirms the real embedding **dimension** in the
  production `rag_chunks` rows before any column is added.
