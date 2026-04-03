# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development commands

- Install PHP dependencies: `composer install`
- Run the local ThinkPHP server: `php think run`
- Run the local server on a custom host/port: `php think run --host 0.0.0.0 --port 8000`
- List registered routes: `php think route:list`
- Clear framework caches: `php think clear`
- Manage application cache: `php think cache:manage clear|stats|warmup`

## Testing and linting

- There is no repository-level PHPUnit/Pest config, package.json, or lint script checked in.
- Do not assume automated test or lint commands exist until you verify them.

## Architecture overview

This is a ThinkPHP 8 payment application with a small number of large controller entrypoints and a thin model/service layer.

- `route/app.php` is the main HTTP route table. Both merchant-facing payment APIs and admin APIs are declared here rather than being split across many route files.
- `app/controller/Index.php` contains the public payment workflow: merchant auth/login, order creation, order lookup, payment status polling, monitor heartbeat, payment push callbacks, and order cleanup.
- `app/controller/Admin.php` contains the admin backend API: dashboard stats, system settings, QR code management, order management, manual order补单-style actions, and QR code rendering.
- `app/model/PayOrder.php`, `app/model/PayQrcode.php`, `app/model/TmpPrice.php`, and `app/model/Setting.php` are the core persistence models. The payment flow depends on understanding these models together rather than in isolation.
- `app/service/CacheService.php` is the cache abstraction for settings, orders, dashboard stats, and QR-code-related data.
- `app/middleware.php` registers global middleware. `app/middleware/Security.php` applies request rate limiting and security headers to all requests.

## Payment flow notes

The main business flow is centered in `app/controller/Index.php`:

- `createOrder` validates merchant input, verifies the signature, reserves a unique payable amount via `TmpPrice`, selects a QR/payment URL, creates a `PayOrder`, and caches order data.
- `appPush` is the monitor callback path that matches an incoming payment to an open order, marks it paid, and sends the merchant notify callback.
- `getOrder`, `checkOrder`, `closeOrder`, and `closeEndOrder` handle client polling and order lifecycle cleanup.

When changing payment behavior, read `route/app.php`, `app/controller/Index.php`, `app/model/PayOrder.php`, `app/model/TmpPrice.php`, and `app/service/CacheService.php` together.

## Runtime assumptions

- The app requires PHP 8+, ThinkPHP 8, Think ORM, and `endroid/qr-code` as defined in `composer.json`.
- Database connection settings come from environment variables in `.example.env` / `config/database.php`.
- Cache is configured to use Redis by default in `config/cache.php`. Some admin/cache stats paths expect Redis to be available.
- Session initialization is global middleware, and session storage is file-based by default in `config/session.php`.

## Frontend/static assets

- Public payment page assets live under `public/payPage/`.
- There are repo-local Chinese notes describing recent payment-page UX changes: `支付检查提示功能说明.md` and `微信支付指引功能优化说明.md`. Check them before changing payment-page behavior or layout.

## Working conventions for this repo

- Prefer reading whole request flows instead of isolated helper methods; the important behavior is concentrated in a few large controllers.
- Verify any cache or settings change against both controller behavior and `CacheService`, because settings and dashboard data are cached explicitly.
- Before changing admin authentication/session behavior, inspect both controller auth checks and global middleware/session config.