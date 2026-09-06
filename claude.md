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
- The release version lives in `package.json` ("version"). Optional override: `npm run build -- --version=x.y.z`.
- All manifests (`administrator/components/com_userreminder/userreminder.xml`,
  `components/com_userreminder/com_userreminder.xml`, `plugins/task/userreminder/userreminder.xml`,
  `pkg_userreminder.xml`, `media/joomla.asset.json`) carry `##VERSION##` placeholders — the build
  substitutes them in the staged copies. Bump the version ONLY in `package.json`.
- What it does:
  1. Stages `administrator/components/com_userreminder` (→ `admin/`), `components/com_userreminder` (→ `site/`),
     `plugins/task/userreminder` (→ `plg_task_userreminder/`), root manifest + `script.php` at the zip root,
     `media/` at the zip root, plus `pkg_userreminder.xml` + `pkg_script.php`.
  2. Minifies the custom admin JS (`dashboard.js`, `optoutusers-select.js`, `userreminder-sidebar.js`)
     with Vite (IIFE, same filenames). Vendored libs (e.g. `chart.umd.min.js`) are copied verbatim.
  3. Replaces `##VERSION##` in all staged XML/JSON files.
  4. Zips inner extension zips, then `releases/pkg_userreminder_v<version>.zip`, and cleans the staging dir.
- Output: `releases/pkg_userreminder_v<version>.zip` (never commit it).

# E2E testing (Docker: Joomla 4 / 5 / 6)
- Testbed: `.testbed/docker-compose.yml` (project name `urtest`) — J4.4+MySQL on :8811, J5+MySQL on :8812,
  J6.1.3+MariaDB on :8813, Mailpit SMTP catcher on :8025 (UI/API) / :1025 (SMTP), releases bound at `/pkg`.
- Harness: `.testbed/e2e/` — run with `php .testbed/e2e/run.php <fresh|upgrade|all> <j4|j5|j6|all>` from the repo root.
  - `fresh` — clean Joomla, install pkg 6.0.0, seed 6 users (pending activation / never logged in /
    inactive / opted-out / too young / cycle complete), force the `userreminder.run` task due, run
    `scheduler:run --all`, assert: correct 3 users recorded in `#__userreminder` + logged + exactly
    3 emails in Mailpit, skipped users untouched, schema state correct.
  - `upgrade` — clean Joomla, install the real 5.2.3 zip, seed old-schema data (MyISAM rows, optout,
    legacy log with raw language keys), update to 6.0.0 on top, assert migration (schemas=6.0.0,
    InnoDB/utf8mb4, PK + indexes, sch dropped, data preserved, log translated, legacy system plugin
    disabled, new mail templates registered) and the same fresh-send checks.
- Mailpit UI: http://localhost:8025
- Note: `factory()`-style CLI boots must mirror `cli/joomla.php` (see `.testbed/e2e/_boot.php`).

# Versioning / release checklist
- Current release line starts at **6.0.0** (the full MVC rebuild). The last pre-rebuild release was 5.2.3.
- Bump the release version ONLY in `package.json` — every manifest carries a `##VERSION##` placeholder
  that `npm run build` substitutes.
- Run `php .testbed/e2e/run.php all all` before releasing (fresh + upgrade on J4/5/6).

# SQL updates
- Schema updates live in `administrator/components/com_userreminder/sql/updates/mysql/<version>.sql` — one file per release, named exactly after the manifest version.
- `sql/install.mysql.utf8.sql` records the current release version in `#__schemas` so fresh installs never run update files.
- Old 4.x update files were deleted: customers coming from 5.2.3 have **no** `#__schemas` row, so every surviving update file would run — each one must be written against the 5.2.3 schema (MyISAM, `UNIQUE KEY userid`) and be idempotent.
- Index/PK changes are NOT put in the update SQL — MySQL aborts on duplicate index names and `DROP INDEX IF EXISTS` is MariaDB-only. Do them in `administrator/components/com_userreminder/script.php::migrateSchema6()` style: check `information_schema.STATISTICS` first, wrap every statement in try/catch.
- Run the portability audit before every release that touches schema:
  `php C:\Users\amine\.claude\skills\auditing-joomla-sql-portability\scripts\audit-sql-portability.php C:\wamp64\www\UserReminder\administrator\components\com_userreminder`
- Test update SQL on BOTH engines (WAMP: MariaDB on 3306, MySQL on 3308) with the sqlprobe skill script.
