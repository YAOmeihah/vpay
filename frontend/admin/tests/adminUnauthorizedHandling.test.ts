import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("admin http interceptor only logs out on explicit unauthorized responses", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(
    source,
    /isAdminUnauthorized\(response\.status,\s*responseCode\)/
  );
  assert.match(
    source,
    /isAdminUnauthorized\(\$error\.response\?\.status,\s*unauthorizedCode\)/
  );
  assert.match(source, /hasStoredAdminUser\(\)/);
  assert.match(source, /notifyAdminSessionExpired\(\)/);
  assert.doesNotMatch(source, /code\s*===\s*-1/);
});

test("admin profile probes do not trigger a second logout request", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/api/admin/auth.ts"),
    "utf8"
  );

  assert.match(
    source,
    /getAdminProfile = \(\) =>\s+http\.request<AdminProfile>\(\s*"get",\s*"\/admin\/index\/profile",\s*\{\},\s*\{\s*skipUnauthorizedLogout:\s*true/
  );
});

test("login profile confirmation handles unauthorized responses as login failure", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/api/user.ts"),
    "utf8"
  );

  assert.match(source, /lastProfileError/);
  assert.match(source, /catch\s*\(error\)/);
  assert.match(source, /登录状态确认失败/);
  assert.match(source, /COOKIE_SECURE/);
});

test("global unauthorized logout shows a visible message only for stored sessions", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(
    source,
    /import \{[\s\S]*notifyAdminSessionExpired[\s\S]*\} from "@\/utils\/adminUnauthorized"/
  );
  assert.match(source, /const hadStoredAdminUser = hasStoredAdminUser\(\)/);
  assert.match(
    source,
    /if \(hadStoredAdminUser\) \{\s*notifyAdminSessionExpired\(\);\s*\}/
  );
  assert.doesNotMatch(source, /登录已失效，请重新登录/);
});

test("admin unauthorized helper centralizes code, message, and stored session checks", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/adminUnauthorized.ts"),
    "utf8"
  );

  assert.match(source, /ADMIN_UNAUTHORIZED_CODE\s*=\s*40101/);
  assert.match(
    source,
    /SESSION_EXPIRED_MESSAGE\s*=\s*"登录已过期，请重新登录"/
  );
  assert.match(source, /function isAdminUnauthorized/);
  assert.match(source, /status\s*===\s*401/);
  assert.match(source, /Number\(code\)\s*===\s*ADMIN_UNAUTHORIZED_CODE/);
  assert.match(source, /function hasStoredAdminUser/);
  assert.match(source, /storageLocal\(\)\.getItem(?:<[^)]*>)?\(userKey\)/);
  assert.match(source, /function notifyAdminSessionExpired/);
  assert.match(source, /unauthorizedMessageVisible/);
  assert.match(source, /grouping:\s*true/);
});

test("user store exposes local-only admin session cleanup", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/store/modules/user.ts"),
    "utf8"
  );

  assert.match(source, /clearAdminSession\(\)/);
  assert.match(source, /storageLocal\(\)\.removeItem\(userKey\)/);
  assert.match(source, /Cookies\.remove\(multipleTabsKey\)/);
  assert.match(source, /setAdminCsrfToken\(null\)/);
  assert.match(source, /resetRouter\(\)/);
  assert.match(
    source,
    /async logOut\(options: \{ remote\?: boolean \} = \{\}\)/
  );
  assert.match(source, /options\.remote !== false/);
  assert.match(source, /await adminLogout\(\)\.catch\(\(\) => undefined\)/);
  assert.match(source, /this\.clearAdminSession\(\)/);
  assert.match(source, /router\.push\("\/login"\)/);
});

test("global unauthorized handling skips redundant remote logout", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(source, /useUserStoreHook\(\)\.logOut\(\{ remote: false \}\)/);
});

test("router profile probe silently redirects first visits and notifies stored-session expiry", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/router/index.ts"),
    "utf8"
  );

  assert.match(source, /isAdminUnauthorized/);
  assert.match(source, /notifyAdminSessionExpired/);
  assert.match(source, /useUserStoreHook/);
  assert.match(source, /const hadStoredAdminUser = Boolean\(userInfo\)/);
  assert.match(source, /useUserStoreHook\(\)\.clearAdminSession\(\)/);
  assert.match(source, /next\(\{ path: "\/login" \}\)/);
});
