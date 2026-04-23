import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("admin http interceptor only logs out on explicit unauthorized responses", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/utils/http/index.ts"),
    "utf8"
  );

  assert.match(source, /response\.status\s*===\s*401/);
  assert.match(source, /responseCode\s*===\s*40101/);
  assert.match(source, /isUnauthorized/);
  assert.doesNotMatch(source, /code\s*===\s*-1/);
});
