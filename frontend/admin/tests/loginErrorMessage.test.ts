import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("login failure keeps and displays backend error message", () => {
  const apiSource = readFileSync(
    resolve("frontend/admin/src/api/user.ts"),
    "utf8"
  );
  const viewSource = readFileSync(
    resolve("frontend/admin/src/views/login/index.vue"),
    "utf8"
  );

  assert.match(apiSource, /msg:\s*loginRes\.msg/);
  assert.match(viewSource, /message\(\s*res\.msg\s*\|\|\s*"登录失败"/);
});
