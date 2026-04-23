import assert from "node:assert/strict";
import test from "node:test";

import { buildMonitorConfigUrl } from "../src/utils/adminLegacy.ts";

test("buildMonitorConfigUrl includes terminal code and monitor key for multi-terminal binding", () => {
  assert.equal(
    buildMonitorConfigUrl("pay.example.com", "term-a", "abc123"),
    "pay.example.com/monitor-bind?terminalCode=term-a&monitorKey=abc123"
  );
  assert.equal(
    buildMonitorConfigUrl("http://pay.example.com/", "term-a", "abc123"),
    "http://pay.example.com/monitor-bind?terminalCode=term-a&monitorKey=abc123"
  );
  assert.equal(
    buildMonitorConfigUrl("https://pay.example.com", "term-a", "abc 123"),
    "https://pay.example.com/monitor-bind?terminalCode=term-a&monitorKey=abc%20123"
  );
  assert.equal(buildMonitorConfigUrl("https://pay.example.com", "", "abc123"), "");
  assert.equal(buildMonitorConfigUrl("https://pay.example.com", "term-a", ""), "");
});
