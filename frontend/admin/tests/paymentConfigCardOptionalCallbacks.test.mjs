import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";

const testDir = dirname(fileURLToPath(import.meta.url));

test("payment callback defaults are optional in the settings form", () => {
  const source = readFileSync(
    resolve(testDir, "../src/views/system/settings/components/PaymentConfigCard.vue"),
    "utf8"
  );

  assert.doesNotMatch(source, /notifyUrl:\s*\[\{\s*required:\s*true/);
  assert.doesNotMatch(source, /returnUrl:\s*\[\{\s*required:\s*true/);
  assert.match(source, /订单未传 notifyUrl 时使用/);
  assert.match(source, /订单未传 returnUrl 时使用/);
});
