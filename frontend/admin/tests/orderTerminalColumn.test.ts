import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("orders list shows terminal ownership using terminal snapshot and terminal code", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/orders/index.vue"),
    "utf8"
  );

  assert.match(source, /label="所属终端"/);
  assert.match(source, /terminal_snapshot/);
  assert.match(source, /terminal_code/);
  assert.match(source, /return name \|\| code \|\| "-"/);
});
