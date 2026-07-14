-- Track G-Q2 — Advanced SQL mode: one-time restricted-role provisioning.
--
-- Run as the postgres superuser on the cluster hosting cms_saas_platform
-- (and cms_saas_platform_test, since roles are cluster-global). Until this
-- runs, SQL mode is disabled: views are built but not queryable, and the
-- runtime security tests skip.
--
--   sudo -u postgres psql -f docs/sql-mode-role-setup.sql
--
-- Idempotent — safe to re-run.

DO $$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'cms_sql_guest') THEN
    -- NOLOGIN: reachable only via SET LOCAL ROLE from the app connection,
    -- never a direct login. No inherited privileges.
    CREATE ROLE cms_sql_guest NOLOGIN NOINHERIT;
  END IF;
END
$$;

-- The app role must be a member of the guest role to SET LOCAL ROLE to it.
-- Replace cms_saas if your DB_USERNAME differs.
GRANT cms_sql_guest TO cms_saas;

-- The guest role sees NOTHING by default: strip any ambient access to the
-- real schema. Scoped-view schemas (cq_*) are granted per-site by
-- ScopedViewManager::rebuildSite / `php artisan collections:rebuild-views`.
REVOKE ALL ON SCHEMA public FROM cms_sql_guest;
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM cms_sql_guest;
ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM cms_sql_guest;

-- After running this, rebuild the per-site views + grants:
--   php artisan collections:rebuild-views
