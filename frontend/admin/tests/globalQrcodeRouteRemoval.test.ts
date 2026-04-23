import assert from "node:assert/strict";
import test from "node:test";
import { existsSync, readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const testDir = dirname(fileURLToPath(import.meta.url));

test("global qrcode menu route is removed in favor of terminal-scoped payment config", () => {
  assert.equal(
    existsSync(resolve(testDir, "../src/router/modules/qrcode.ts")),
    false
  );
  assert.equal(existsSync(resolve(testDir, "../src/views/qrcode")), false);

  const terminalDetail = readFileSync(
    resolve(testDir, "../src/views/system/terminals/detail.vue"),
    "utf8"
  );

  assert.match(terminalDetail, /QrBatchUploader/);
  assert.match(terminalDetail, /QrList/);
  assert.match(terminalDetail, /channel-id/);
});
