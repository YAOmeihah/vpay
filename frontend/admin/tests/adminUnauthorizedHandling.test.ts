import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("admin http interceptor only logs out on explicit unauthorized responses", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(source, /response\.status\s*===\s*401/);
  assert.match(source, /responseCode\s*===\s*40101/);
  assert.match(source, /isUnauthorized/);
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
  const source = readFileSync(resolve("frontend/admin/src/api/user.ts"), "utf8");

  assert.match(source, /lastProfileError/);
  assert.match(source, /catch\s*\(error\)/);
  assert.match(source, /登录状态确认失败/);
  assert.match(source, /COOKIE_SECURE/);
});

test("global unauthorized logout shows a visible message", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(source, /import \{ message \} from "@\/utils\/message"/);
  assert.match(source, /notifyUnauthorized/);
  assert.match(source, /登录已失效，请重新登录/);
});
