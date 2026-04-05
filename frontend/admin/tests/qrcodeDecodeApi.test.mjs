import assert from "node:assert/strict";
import test from "node:test";
import fs from "node:fs";
import path from "node:path";

const source = fs.readFileSync(
  path.resolve(import.meta.dirname, "../src/api/admin/qrcode.ts"),
  "utf8"
);

test("decodeQrcodeImage skips unauthorized auto logout", () => {
  const decodeCallMatch = source.match(
    /decodeQrcodeImage[\s\S]*?http\.request<\{[\s\S]*?\}\>\([\s\S]*?skipUnauthorizedLogout:\s*true[\s\S]*?\)/
  );

  assert.ok(
    decodeCallMatch,
    "decodeQrcodeImage should opt out of the global unauthorized auto logout"
  );
});
