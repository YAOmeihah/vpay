import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";
import test from "node:test";

import { generateSettingsKey } from "../src/views/system/settings/keyGenerator.ts";

test("generateSettingsKey returns a 32-character hex string", () => {
  const generated = generateSettingsKey();

  assert.match(generated, /^[a-f0-9]{32}$/);
});

test("generateSettingsKey produces different values across calls", () => {
  const first = generateSettingsKey();
  const second = generateSettingsKey();

  assert.notEqual(first, second);
});

test("payment config card no longer exposes a global monitor-key generator", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/system/settings/components/PaymentConfigCard.vue"),
    "utf8"
  );

  assert.doesNotMatch(source, /generateSettingsKey/);
  assert.doesNotMatch(source, /handleGenerateMonitorKey/);
  assert.doesNotMatch(source, /自动生成/);
});
