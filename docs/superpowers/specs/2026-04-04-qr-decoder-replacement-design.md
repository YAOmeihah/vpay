# QR Decoder Replacement Design

## Goal

Replace the legacy `QrReader + public/qr-code/lib` backend QR decoder with a lighter maintained Composer dependency that improves compatibility and keeps the existing admin upload flow unchanged.

## Current Context

- The admin QR upload endpoint is [`app/controller/Admin.php`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/app/controller/Admin.php), method `decodeQrcode()`.
- The frontend already uploads image blobs and expects the same endpoint and response shape.
- The current decoder is a legacy bundled library under `public/qr-code/lib`, which has already required PHP 8 compatibility patches and still has poor decode behavior for payment collection QR screenshots.
- The current logout regression was caused by QR decode failures sharing the same `-1` code path as unauthorized responses. That behavior is being separated.

## Options Considered

### Option A: Keep the bundled legacy decoder

- Lowest migration effort.
- Keeps the unmaintained library and weak decode behavior.
- Does not justify continued patching.

### Option B: Replace with `chillerlan/php-qrcode` (recommended)

- Composer-managed, maintained, PHP-native, and light enough for this project.
- Suitable for "upload one payment QR code image and decode its content" without introducing external services.
- Keeps deployment simple while materially improving maintainability over the bundled decoder.

### Option C: Move to `zxing-cpp` or an external decode service

- Highest decode strength.
- Heavier operational and deployment cost than this project needs for a simple admin-side QR decode action.

## Recommended Design

Use `chillerlan/php-qrcode` as the new backend decode library and keep the existing `/admin/index/decodeQrcode` API contract.

### Backend

- Add `chillerlan/php-qrcode` to Composer dependencies.
- Update `decodeQrcode()` to decode the uploaded image blob using the new library instead of loading `public/qr-code/lib/QrReader.php`.
- Keep successful responses as `code = 1`.
- Keep QR decode failures as business failures, not unauthorized failures.

### Frontend

- Keep the current upload flow unchanged.
- Continue treating decode failures as recoverable UI errors.
- Ensure the QR decode request explicitly opts out of unauthorized auto logout.

### Compatibility

- Do not change the admin route, request payload, or success payload shape.
- Defer deleting `public/qr-code/lib` until the new decoder is fully verified in this repo and on production-like samples.

## Error Handling

- Empty image payload: keep explicit validation failure.
- Decode returns no text: return a business failure message such as `二维码识别失败`.
- Decoder exceptions: catch and return the same business failure shape.
- No logout side effect should occur for decode failures.

## Testing

- Add a PHP regression test that asserts QR decode failures use a business error code instead of the unauthorized code path.
- Add a frontend regression test that asserts the QR decode request includes `skipUnauthorizedLogout: true`.
- After replacement, add a focused backend test around the new decoder integration if the library can be exercised deterministically from a fixture image.

## Scope Boundaries

- In scope: QR decode library replacement for admin-side collection code parsing.
- Out of scope: QR generation changes, monitor app scanning changes, merchant payment flow changes, and broad auth/error-code redesign.
