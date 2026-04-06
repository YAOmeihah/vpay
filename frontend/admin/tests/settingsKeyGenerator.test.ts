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

test("payment config card exposes an auto-generate action for monitor key", () => {
  const source = readFileSync(
    resolve("src/views/system/settings/components/PaymentConfigCard.vue"),
    "utf8"
  );

  assert.match(source, /generateSettingsKey/);
  assert.match(source, /handleGenerateMonitorKey/);
  assert.match(source, /自动生成/);
});
