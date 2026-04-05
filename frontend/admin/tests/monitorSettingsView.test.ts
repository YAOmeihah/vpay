import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("monitor settings view builds config QR from monitorKey", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/system/monitor/index.vue"),
    "utf8"
  );

  assert.match(source, /settings\.value\.monitorKey/);
  assert.doesNotMatch(source, /buildMonitorConfigUrl\(location\.host,\s*settings\.value\.key\)/);
  assert.doesNotMatch(source, /必须以 https:\/\/ 开头/);
});
