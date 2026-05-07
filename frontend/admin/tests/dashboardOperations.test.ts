import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("dashboard exposes operational summary and retryable error feedback", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/dashboard/index.vue"),
    "utf8"
  );

  assert.match(source, /buildDashboardOperationSummary/);
  assert.match(source, /今日运营摘要/);
  assert.match(source, /loadError/);
  assert.match(source, /重新加载/);
});
