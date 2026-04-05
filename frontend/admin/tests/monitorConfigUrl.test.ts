import assert from "node:assert/strict";
import test from "node:test";

import { buildMonitorConfigUrl } from "../src/utils/adminLegacy.ts";

test("buildMonitorConfigUrl keeps the legacy host and key composition", () => {
  assert.equal(
    buildMonitorConfigUrl("pay.example.com", "abc123"),
    "pay.example.com/abc123"
  );
  assert.equal(
    buildMonitorConfigUrl("http://pay.example.com/", "abc123"),
    "http://pay.example.com/abc123"
  );
  assert.equal(
    buildMonitorConfigUrl("https://pay.example.com", "abc123"),
    "https://pay.example.com/abc123"
  );
});
