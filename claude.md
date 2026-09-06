# Localhost access
http://localhost/userreminder/administrator/
username: admin
pass: system123zz@@


# Browser testing
- always use Playwright mcp
- make sure playwright mcp working and functional

# Building releases (Vite)
- Command: `npm run build` (repo root; runs `build.mjs`, powered by the Vite JS API + archiver).
- No phing (`build.xml`) and no PowerShell build (`build.ps1`) anymore — both were removed. Do not reintroduce them.
- Optional override: `npm run build -- --version=x.y.z`; otherwise the version is read from
  `administrator/components/com_userreminder/userreminder.xml`.
- What it does:
  1. Stages `administrator/components/com_userreminder` (→ `admin/`), `components/com_userreminder` (→ `site/`),
     `plugins/task/userreminder` (→ `plg_task_userreminder/`), root manifest + `script.php` at the zip root,
     `media/` at the zip root, plus `pkg_userreminder.xml` + `pkg_script.php`.
  2. Minifies the custom admin JS (`dashboard.js`, `optoutusers-select.js`, `userreminder-sidebar.js`)
     with Vite (IIFE, same filenames). Vendored libs (e.g. `chart.umd.min.js`) are copied verbatim.
  3. Replaces `##VERSION##` in all staged XMLs (pkg manifest uses the placeholder; component manifests are hardcoded).
  4. Zips inner extension zips, then `releases/pkg_userreminder_v<version>.zip`, and cleans the staging dir.
- Output: `releases/pkg_userreminder_v<version>.zip` (never commit it).

# Versioning / release checklist
- Current release line starts at **6.0.0** (the full MVC rebuild). The last pre-rebuild release was 5.2.3.
- Bump `<version>` in all three manifests on every release:
  - `administrator/components/com_userreminder/userreminder.xml`
  - `components/com_userreminder/com_userreminder.xml`
  - `plugins/task/userreminder/userreminder.xml`
- Also bump `"version"` in `administrator/components/com_userreminder/media/joomla.asset.json`.

# SQL updates
- Schema updates live in `administrator/components/com_userreminder/sql/updates/mysql/<version>.sql` — one file per release, named exactly after the manifest version.
- `sql/install.mysql.utf8.sql` records the current release version in `#__schemas` so fresh installs never run update files.
- Old 4.x update files were deleted: customers coming from 5.2.3 have **no** `#__schemas` row, so every surviving update file would run — each one must be written against the 5.2.3 schema (MyISAM, `UNIQUE KEY userid`) and be idempotent.
- Index/PK changes are NOT put in the update SQL — MySQL aborts on duplicate index names and `DROP INDEX IF EXISTS` is MariaDB-only. Do them in `administrator/components/com_userreminder/script.php::migrateSchema6()` style: check `information_schema.STATISTICS` first, wrap every statement in try/catch.
- Run the portability audit before every release that touches schema:
  `php C:\Users\amine\.claude\skills\auditing-joomla-sql-portability\scripts\audit-sql-portability.php C:\wamp64\www\UserReminder\administrator\components\com_userreminder`
- Test update SQL on BOTH engines (WAMP: MariaDB on 3306, MySQL on 3308) with the sqlprobe skill script.
