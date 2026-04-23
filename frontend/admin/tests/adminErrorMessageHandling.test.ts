import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("admin http interceptor lifts backend msg or message from failed responses", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(source, /response\?\.data/);
  assert.match(source, /\.msg/);
  assert.match(source, /\.message/);
  assert.match(source, /errorMessage/);
});
