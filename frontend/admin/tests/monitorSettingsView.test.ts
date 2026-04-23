import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const testDir = dirname(fileURLToPath(import.meta.url));

test("monitor settings view builds config QR from monitorKey", () => {
  const source = readFileSync(
    resolve(testDir, "../src/views/system/monitor/index.vue"),
    "utf8"
  );

  assert.match(source, /getTerminals/);
  assert.match(source, /buildMonitorOverviewCards/);
  assert.match(source, /监控总览/);
  assert.doesNotMatch(source, /getSettings/);
  assert.doesNotMatch(source, /逐个绑定；此页面保留默认终端的旧版绑定方式/);
  assert.doesNotMatch(source, /settings\.value\.monitorKey/);
});
