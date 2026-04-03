# Task 3 Design: Cache Adapters and Canonical Order Payload

## Goal
Introduce thin, instantiable cache adapters and a single canonical order payload factory that preserves the legacy key order and value shapes. No behavior changes and no consumer refactors in this task.

## Scope
In scope:
- `OrderCache` adapter wrapping `CacheService::cacheOrder/getOrder/deleteOrder`.
- `DashboardStatsCache` adapter wrapping `CacheService::cacheStats('dashboard') / getStats('dashboard') / deleteStats('dashboard')`.
- `OrderPayloadFactory` that builds the canonical payload with keys in this exact order:
  `payId, orderId, payType, price, reallyPrice, payUrl, isAuto, state, timeOut, date`.
- Tests covering both adapters and the payload factory.

Out of scope:
- Refactoring controllers or services to use adapters or the payload factory.
- Any additional interfaces or base classes.
- New cache keys, value shapes, or behavior changes.

## Architecture
New classes will be added under:
- `app/service/cache/OrderCache.php`
- `app/service/cache/DashboardStatsCache.php`
- `app/service/order/OrderPayloadFactory.php`

All three are instantiable classes with instance methods. Each cache adapter delegates to the existing `CacheService` to preserve key naming, TTLs, and payload shapes.

## Data Flow
- `OrderPayloadFactory` accepts explicit input parameters and returns an array with the legacy key order and values unchanged.
- Cache adapters are thin pass-throughs; they accept inputs and forward them to `CacheService` without transforming payloads or keys.

## Error Handling
No new error handling. Adapters rely on `CacheService` behavior, which already swallows cache errors and returns null/false.

## Testing
Add `tests/CacheAndPayloadAdaptersTest.php` with two tests:
1. `test_order_payload_factory_preserves_legacy_key_order_and_values`
   - Asserts key order and values for the canonical payload.
2. `test_cache_adapters_keep_existing_cache_keys_and_payloads`
   - Asserts adapter methods call through to `CacheService` by validating stored payloads and retrieval/deletion behavior.

Run focused PHPUnit test before and after implementation as required by the workflow.

## Rollout Notes
No runtime behavior change expected. This creates a seam for future consumer migration.
