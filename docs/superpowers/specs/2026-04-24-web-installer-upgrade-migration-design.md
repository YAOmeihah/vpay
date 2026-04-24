# Web Installer, Upgrade, and Migration Design

**Date:** 2026-04-24  
**Project:** VPay  
**Status:** Draft approved in conversation, written for implementation planning

## Goal

Add a web-based lifecycle console that can:

- perform first-time installation on low-permission PHP hosting,
- support controlled database upgrades through versioned migrations,
- keep administrator credentials stable across upgrades,
- close the current unsafe initialization gaps around default admin credentials and empty signing keys.

The feature must work without shell access and must fail safely when file writes or migrations cannot complete.

## Chosen Product Decisions

The design below reflects the choices made during discussion:

- Delivery model: web installer only
- Scope: first-time install, upgrade, and migration
- Upgrade trigger: detect version mismatch, show upgrade wizard, require manual confirmation
- File writes: attempt automatic writes first, fall back to manual copy instructions when needed
- Migration source: versioned incremental migration files
- Install endpoint exposure: only available when install is explicitly enabled and the system is either not installed or pending upgrade
- Hosting target: compatible with common PHP hosting, BaoTa, LNMP, VPS, and Docker, but designed around low-permission environments
- Admin credentials: must be set during first install
- Upgrade behavior for admin account: preserve existing admin credentials by default

## Non-Goals

The first version should not attempt to solve everything:

- no automatic database rollback for failed upgrades,
- no heavy SPA installer frontend,
- no full operations console for repair/maintenance beyond install and upgrade recovery,
- no shell-dependent execution flow,
- no hidden auto-upgrade execution.

## Why This Feature Is Needed

The current project behaves like a manual deployment package:

- schema import is done externally through `vmq.sql`,
- the default admin account comes from bootstrap SQL,
- the merchant signing key starts empty and is only generated when settings are later read,
- missing tables or missing config produce runtime failures rather than installation guidance.

That creates two concrete bootstrap risks:

1. a freshly imported system can expose a known admin credential until the operator changes it,
2. a freshly imported system can enter service while the merchant signing key is still empty.

The installer must guarantee a safe initialized state before the system is considered installed.

## High-Level Architecture

The lifecycle feature should be isolated from the business controllers and split into four major units.

### 1. Install Entry Guard

Responsibilities:

- decide whether `/install` is available,
- classify system state as `not_installed`, `installed`, `upgrade_required`, `locked`, or `recovery_required`,
- prevent regular business/admin routes from running before install or while a blocking upgrade is pending.

This keeps install state decisions out of merchant, monitor, and admin business controllers.

### 2. Install Wizard

Responsibilities:

- collect and validate database settings,
- collect admin username and password on first install,
- run environment checks,
- write `.env` when possible,
- import bootstrap schema,
- initialize secure configuration and install markers.

This is the only component allowed to mark the system as installed.

### 3. Migration Engine

Responsibilities:

- read current database schema version,
- compare with target application version,
- scan incremental migration files,
- execute them in order,
- record execution results and stop immediately on failure.

This engine is reused by both upgrade flow and recovery flow.

### 4. Install State Repository

Responsibilities:

- persist file-based install lock and last-error snapshots,
- read and write database-backed install/version metadata,
- give the rest of the system one consistent view of lifecycle state.

## State Model

The lifecycle system should use two layers of state.

### File-Based State

Location:

- `runtime/install/enable.flag`
- `runtime/install/lock.json`
- `runtime/install/last-error.json`

Purpose:

- works before the database is ready,
- controls whether `/install` may be accessed,
- survives partial failures during first install,
- prevents concurrent install/upgrade execution.

### Database-Backed State

Persisted in the existing `setting` table under dedicated keys:

- `install_status`
- `install_time`
- `app_version`
- `schema_version`
- `install_guid`
- `upgrade_locked_at`

Purpose:

- tracks lifecycle state after the database is available,
- lets the application compare current schema version to target code version,
- avoids introducing another general-purpose config table.

### Migration Audit Table

Create a dedicated table such as `system_migration_log` with fields:

- `id`
- `migration_key`
- `from_version`
- `to_version`
- `status`
- `started_at`
- `finished_at`
- `error_message`
- `checksum`

Purpose:

- provides an auditable execution trail,
- supports interrupted upgrade recovery,
- prevents ambiguous “was this script already run?” states.

## Install Endpoint Exposure

`/install` must not behave like a permanently public setup tool.

Recommended access rule:

- `/install` is available only if `runtime/install/enable.flag` exists,
- and the lifecycle state is either `not_installed`, `upgrade_required`, `locked`, or `recovery_required`.

If the system is already installed and current:

- `/install` returns `404`, or
- redirects to the site root without exposing lifecycle detail.

This prevents the installer from becoming a standing attack surface.

## First-Time Install Flow

### Step 1: Entry and Environment Check

The user accesses `/install`.

The installer:

- verifies install is enabled,
- verifies system is not already installed,
- runs environment checks:
  - PHP version
  - `PDO`
  - `pdo_mysql`
  - `curl`
  - `json`
  - `mbstring`
  - writable `runtime/`
  - writable `.env` target location, if possible

Any blocking failure stops the flow before configuration is accepted.

### Step 2: Database and Admin Form

The installer presents a form for:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`
- admin username
- admin password
- confirm password

The administrator account is mandatory on first install.

### Step 3: Connection Test

The installer tests the submitted database configuration with a temporary connection.

This must not depend on the current `.env`, because `.env` may still be absent or invalid.

### Step 4: Confirm and Lock

Before executing install steps, the installer:

- writes `runtime/install/lock.json`,
- stores action type `install`,
- stores target version,
- stores current step name and timestamp.

### Step 5: Persist Config

The installer attempts to write `.env`.

If `.env` is writable:

- write the new values automatically.

If `.env` is not writable:

- display the exact content for manual copy,
- allow the operator to continue only after explicit confirmation,
- do not mark installation complete until the application can later validate effective config.

### Step 6: Bootstrap Schema

The installer imports the bootstrap schema using `vmq.sql` as the baseline schema source.

Important rule:

- bootstrap SQL may create base tables and baseline settings,
- but its default admin and empty signing key values must be treated only as placeholders and must be overwritten during install finalization.

### Step 7: Secure Bootstrap Data

The installer must then enforce secure initialization:

- write `setting.user` from the submitted admin username,
- write `setting.pass` from `password_hash(...)`,
- generate a random merchant signing key and write `setting.key`,
- ensure missing defaults such as `notify_ssl_verify=1` are set,
- write `schema_version`,
- write `app_version`,
- write `install_status=installed`,
- write `install_time`,
- generate and write `install_guid`.

The system must never be considered installed while admin credentials or the signing key remain unset.

### Step 8: Cleanup and Finish

On success:

- remove `enable.flag`,
- remove `lock.json`,
- clear `last-error.json`,
- show a completion page with:
  - login entry,
  - recommendation to back up the current database,
  - recommendation to remove any temporary deployment exposure.

## Upgrade Flow

### Trigger

When the system is installed and the stored `schema_version` is behind `config('app.ver')`, lifecycle state becomes `upgrade_required`.

### Access

`/install` opens in upgrade mode when install is enabled and a pending upgrade is detected.

### Pre-Upgrade Screen

The screen shows:

- current schema version,
- target app version,
- pending migration files,
- explicit backup reminder,
- admin account summary in read-only form,
- confirmation button.

### Admin Preservation Rule

Normal upgrade flow must preserve the current admin username and password.

Upgrade must not silently reset:

- `setting.user`
- `setting.pass`

### Broken Admin Recovery Rule

If upgrade detects that admin credentials are missing or invalid:

- do not auto-overwrite them,
- switch to a repair step,
- require explicit operator confirmation before repairing the admin config.

### Execution

On confirmation:

- write install lock with action `upgrade`,
- scan migration files between current and target versions,
- execute each file in order,
- record each file in `system_migration_log`,
- stop immediately on the first failure.

### Finalization

If all migrations succeed:

- update `schema_version`,
- update `app_version`,
- clear upgrade lock,
- remove `enable.flag`,
- return to normal application state.

## Migration File Strategy

Use versioned incremental migration files instead of replaying the full bootstrap SQL.

Recommended structure:

```text
database/
  migrations/
    2.0.1/
      001-add-install-meta.sql
      002-fill-notify-ssl-verify.sql
    2.1.0/
      001-add-system-migration-log.sql
      002-backfill-schema-version.sql
```

Execution rules:

1. read current `schema_version`,
2. read target version from `config('app.ver')`,
3. collect version directories between current and target,
4. execute files inside each version directory in filename order,
5. log each executed file,
6. only advance `schema_version` after the whole version directory succeeds.

Migration file guidelines:

- each file should have one clear purpose,
- prefer idempotent operations when possible,
- avoid mixing schema changes and large data repair in the same file,
- filename should clearly describe intent.

## Failure Handling and Recovery

The system should aim for safe recovery, not fake automatic rollback.

### Why No Automatic Rollback

Automatic rollback is risky here because:

- MySQL DDL is not always safely reversible,
- partial schema mutations can already be committed,
- web-hosted environments are a poor fit for complex transactional recovery.

### Failure Strategy

On any failed install or migration step:

- stop immediately,
- write step/error details into `runtime/install/last-error.json`,
- if relevant, write failure into `system_migration_log`,
- keep lifecycle state in `locked` or `recovery_required`,
- show a recovery page with:
  - failed step,
  - exact error message,
  - retry action,
  - manual remediation notes.

### Retry Strategy

The operator should be able to:

- fix environment issues,
- revisit `/install`,
- retry from recovery mode.

This means migration scripts should be written to tolerate safe retries whenever possible.

### Backup Policy

The upgrade flow should require the operator to explicitly acknowledge database backup readiness before execution starts.

## Security Design

### 1. No Default Admin Trust

The installer must not trust admin credentials from `vmq.sql`.

Even if bootstrap SQL inserts:

- `setting.user`
- `setting.pass`

the install flow must overwrite them with submitted administrator credentials before completion.

### 2. No Empty Signing Key State

The installer must generate the merchant signing key during install finalization.

The system must not become `installed` while `setting.key` is empty.

### 3. Controlled Install Exposure

The install route is only open when:

- enable flag exists,
- and lifecycle state requires installation or upgrade.

### 4. Upgrade Confirmation

Upgrade execution requires a manual confirmation step.

The first version should not allow anonymous background upgrade execution.

### 5. Concurrency Lock

Install and upgrade actions must hold a lifecycle lock so repeated clicks or refreshes cannot run the same process twice.

## UI Structure

The first version should use server-rendered pages with light JavaScript, not a full SPA.

Suggested screens:

1. entry/status page
2. environment check page
3. database + admin form page
4. execution confirmation page
5. progress page
6. success/recovery page

Benefits:

- simpler deployment,
- lower complexity,
- fewer frontend build requirements,
- better compatibility with low-permission hosting.

## Integration Points with Current Codebase

### Routing

Add a dedicated route file:

- `route/install.php`

Example endpoints:

- `/install`
- `/install/check`
- `/install/run`
- `/install/recover`

### Middleware

Add a lifecycle gate middleware for normal application routes.

Responsibilities:

- block admin and business entry before installation,
- block sensitive routes while an upgrade is pending,
- keep lifecycle logic out of business controllers.

### Configuration Layer

Existing config access through `Setting` and config repository classes should remain.

However, install state must be resolved before the app enters code paths that assume:

- the database exists,
- the `setting` table exists,
- required config rows already exist.

### Version Source

Keep application target version in:

- `config/app.php` via `ver`

Keep current database version in:

- `setting.schema_version`

## Proposed File Layout

```text
app/
  controller/
    install/
      Wizard.php
  service/
    install/
      InstallStateService.php
      InstallGuardService.php
      InstallStepService.php
      EnvWriter.php
      DatabaseBootstrapService.php
      MigrationScanner.php
      MigrationRunner.php
      MigrationLogService.php
      AdminBootstrapService.php
  middleware/
    EnsureSystemInstalled.php

route/
  install.php

view/
  install/
    entry.php
    check.php
    form.php
    confirm.php
    progress.php
    success.php
    recover.php

database/
  migrations/
    2.0.1/
      001-add-install-meta.sql

runtime/
  install/
    enable.flag
    lock.json
    last-error.json
```

## Responsibilities by Component

### `Wizard.php`

- lifecycle page entry,
- form submission handling,
- confirmation and progress transitions.

### `InstallStateService.php`

- reads file state,
- reads DB lifecycle state when available,
- returns one normalized lifecycle status.

### `InstallGuardService.php`

- decides whether `/install` is accessible,
- decides whether the main app should be blocked for install/upgrade reasons.

### `EnvWriter.php`

- writes `.env` when possible,
- builds manual-copy fallback text when direct write fails.

### `DatabaseBootstrapService.php`

- runs baseline schema import from `vmq.sql`,
- creates migration audit table when needed.

### `AdminBootstrapService.php`

- initializes admin username/password,
- generates merchant signing key,
- enforces secure bootstrap state.

### `MigrationScanner.php`

- finds migration directories and files between two versions.

### `MigrationRunner.php`

- executes migration files in order,
- records success/failure,
- updates version state on successful boundaries.

### `MigrationLogService.php`

- writes and queries `system_migration_log`.

### `EnsureSystemInstalled.php`

- blocks normal routes before install,
- blocks normal routes when upgrade is required.

## Acceptance Criteria

The feature is considered complete when all of the following are true:

1. a fresh deployment can be fully installed through the browser without shell access,
2. first install requires operator-supplied admin username and password,
3. first install always generates a non-empty merchant signing key,
4. the system never becomes installed while default admin bootstrap values remain in effect,
5. the system can detect pending upgrades from version mismatch,
6. the browser can display and execute ordered migration files after manual confirmation,
7. failed install or upgrade can be resumed from a recovery page,
8. `/install` is not exposed when the system is fully installed and current,
9. admin credentials are preserved across normal upgrades.

## Out of Scope for First Implementation

- automatic DB rollback,
- online downgrade support,
- automatic upgrade scheduling,
- anonymous upgrade execution,
- multi-admin lifecycle workflows,
- plugin-style migration marketplace,
- deep repair console beyond recovery retry flow.
