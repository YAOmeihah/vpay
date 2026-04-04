# Legacy Admin Removal Design

## Summary

This project currently ships two parallel admin frontends:

- The current Vue admin console built from `frontend/admin` and deployed to `public/console`
- The legacy static admin pages under `public/admin`

The goal of this change is to fully retire the legacy admin frontend and make the Vue admin console the only supported admin surface. There are no compatibility consumers, bookmarks, automation flows, or deployment requirements that still depend on the legacy pages or their menu endpoints.

This design intentionally treats the work as a product cutover, not just a file deletion. The cutover removes old admin pages, removes their backend entrypoints, unifies admin navigation around `/console/`, and cleans up residual template and migration-era artifacts that would otherwise continue to mislead future maintenance.

## Goals

- Make `/console/` the only supported admin entrypoint
- Remove legacy admin HTML pages and their dead dependencies
- Remove backend routes and controllers that only exist to support the legacy admin UI
- Keep the current Vue admin console fully functional for system settings, monitor settings, QR code management, and order management
- Leave payment pages and merchant-facing flows untouched

## Non-Goals

- Rebuild the payment page in `public/payPage`
- Redesign merchant-facing or public landing pages beyond entrypoint cleanup
- Introduce backend role or menu systems for the Vue admin console
- Perform unrelated refactors in payment controllers or services

## Current State

### Active admin stack

The active admin implementation is the Vue console:

- Source: `frontend/admin`
- Build target: `public/console`
- Runtime config: `public/console/platform-config.json`
- Backend API contract: `/login`, `/admin/index/*`, `/enQrcode`

This stack already covers the core legacy admin feature set:

- Dashboard stats
- System settings
- Monitor settings
- QR code add/list flows for WeChat and Alipay
- Order list, delete, and manual repair flows

### Legacy stack

The legacy stack consists of static pages under `public/admin`, plus a backend menu endpoint that still returns links to those pages. The endpoint is implemented in `app/controller/admin/Menu.php` and exposed through `/getMenu`.

This stack is now redundant and misleading for three reasons:

1. The Vue admin console is already the intended replacement.
2. The old pages contain outdated assumptions such as references to `qr-code/test.php`, which is no longer present in the repository.
3. Keeping both admin surfaces increases maintenance cost and makes it unclear which code path is authoritative.

## Options Considered

### Option 1: Hard cutover to the Vue admin console

Remove the legacy admin pages and their backend menu contract, then make `/console/` the only admin entrypoint.

Pros:

- Cleanest architecture
- Lowest long-term maintenance cost
- Eliminates dead code and misleading routes
- Makes future admin work unambiguous

Cons:

- Requires one-time verification that every required admin task already exists in the Vue console
- Forces immediate handling of any remaining documentation gaps such as old API help links

### Option 2: Soft-deprecate the legacy pages

Stop linking to the old pages but keep files and endpoints in the repository.

Pros:

- Lower short-term deletion risk

Cons:

- Leaves dead code in place
- Preserves confusion around the real admin entrypoint
- Does not reduce maintenance burden meaningfully

### Option 3: Redirect legacy pages to `/console/`

Keep the old URLs but convert them into redirects.

Pros:

- Useful if external bookmarks or integrations exist

Cons:

- Adds migration complexity without solving repository bloat
- Not justified because there are no compatibility consumers

## Decision

Adopt Option 1.

The repository should fully remove the legacy admin UI and its backend glue. The Vue admin console becomes the only supported admin frontend, with `/console/` as the sole admin entrypoint.

## Functional Design

### 1. Admin entrypoint unification

The project should expose only one admin path for human operators:

- Primary admin entry: `/console/`

The public root page should no longer act as an alternate admin login experience.

For this migration, `/` should redirect directly to `/console/`, because the current root page primarily exists as a legacy admin login wrapper and there is no compatibility requirement to preserve it as a separate landing page.

### 2. Legacy admin removal

Delete the legacy admin pages under `public/admin`, including the old QR management, settings, monitor, and order list pages.

Also remove any legacy-only static dependencies that become unreferenced after deletion. This cleanup must be based on repository-wide reference checks so that payment pages and unrelated public assets are not removed accidentally.

### 3. Backend contract cleanup

Remove backend code that only serves the legacy admin UI:

- The `/getMenu` route
- `app/controller/admin/Menu.php`

Retain backend endpoints used by the Vue console:

- `/login`
- `/admin/index/*`
- `/enQrcode`

This preserves the current Vue admin login, profile bootstrap, QR preview, settings, and order operations.

### 4. Documentation and admin help cleanup

The legacy menu currently links to `api.html`. Once the legacy stack is removed, the project must stop presenting that page as part of the admin experience.

Because the user requested a full shutdown of the old admin experience and did not require preserving legacy help pages, the design assumes:

- `api.html` is no longer treated as an admin navigation target
- Any API reference that still matters should live in repository docs, not in the retired legacy menu system

This keeps the admin console focused on operations and avoids carrying forward a single orphaned legacy page just to preserve a menu link.

### 5. Vue admin polish as part of the cutover

A small amount of cleanup should be done alongside the cutover so the remaining admin surface looks intentional:

- Remove the default prefilled login credentials from the Vue login form
- Replace obvious template-brand remnants such as the `pure-admin-thin` title in the built console shell
- Keep internal helper naming cleanup out of scope for this cutover unless it is required by the file changes

The first two items are in scope because they affect the only surviving admin UX. Internal helper renames are deliberately out of scope so the migration stays focused on the admin cutover.

## Data Flow and Architecture Impact

No payment or merchant runtime data model changes are required.

The architecture after cutover becomes:

- Public and merchant-facing pages remain static/ThinkPHP-driven as they are today
- Payment page remains under `public/payPage`
- Admin frontend is only the Vue console deployed to `public/console`
- Admin backend is only the ThinkPHP JSON API consumed by the Vue console

This removes the previous split where two different admin clients consumed overlapping backend capabilities.

## Error Handling

The migration should fail closed rather than leaving broken legacy links behind.

- If a route or controller only exists for the legacy admin UI, remove it completely.
- If a public page still links to the old admin UI, update that page in the same change.
- If any Vue admin feature is found to be missing during verification, stop the removal and implement the missing piece before deleting legacy files.

## Verification Plan

Verification should be manual plus targeted static checks.

### Static checks

- Search the repository for references to `public/admin`, `/getMenu`, old legacy page names, and legacy menu URLs
- Confirm no remaining app code links to deleted admin pages
- Confirm no surviving file still references missing legacy helpers such as `qr-code/test.php`

### Runtime checks

- Open `/console/`
- Log in through the Vue admin console
- Load dashboard stats
- Open system settings and verify load/save still works
- Open monitor settings and verify QR/config rendering still works
- Upload and decode a QR code in the Vue admin flow
- View QR code list and delete an item
- Open order list, filter, inspect detail, delete an order, and run manual repair on a testable order
- Verify `/enQrcode` still renders QR previews used by the Vue console

### Entry checks

- Open `/`
- Confirm it now routes operators to `/console/`
- Confirm there is no remaining navigation path to the retired legacy admin pages

## Risks and Mitigations

### Risk: hidden links to deleted pages

Mitigation:

- Run repository-wide string searches before and after deletion
- Update root entrypoints in the same change

### Risk: missing admin feature in the Vue console

Mitigation:

- Verify settings, monitor, QR, and order workflows before deleting old files
- Treat any discovered functional gap as a blocker to removal

### Risk: deleting shared static assets

Mitigation:

- Remove assets only after confirming they are legacy-only
- Keep public payment assets and shared libraries unless reference scans show they are safe to remove

## Implementation Outline

1. Change `/` to redirect directly to `/console/`.
2. Delete `/getMenu` and `app/controller/admin/Menu.php`.
3. Delete `public/admin/*.html`.
4. Remove legacy-only assets proven to be unused.
5. Clean the Vue admin login defaults and branding leftovers.
6. Run repository-wide reference scans and manual admin verification.

## Success Criteria

- There is exactly one supported admin frontend: the Vue console under `/console/`
- No application code references `public/admin/*.html` or `/getMenu`
- Legacy menu/controller code is gone
- Root entrypoints no longer send operators into the retired admin UI
- The Vue admin console remains fully functional for the existing operational workflows
