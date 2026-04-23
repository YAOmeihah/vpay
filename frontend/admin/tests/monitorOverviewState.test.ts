import assert from "node:assert/strict";
import test from "node:test";
import { existsSync, readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const testDir = dirname(fileURLToPath(import.meta.url));
const helperPath = resolve(
  testDir,
  "../src/views/system/monitor/overviewState.ts"
);

test("monitor overview state helper exists for multi-terminal monitor cards", () => {
  assert.equal(existsSync(helperPath), true);

  const source = readFileSync(helperPath, "utf8");

  assert.match(source, /buildMonitorOverviewCards/);
  assert.match(source, /buildMonitorConfigUrl/);
  assert.match(source, /buildMonitorConfigUrl\(host,\s*item\.terminal_code,\s*item\.monitor_key\)/);
  assert.match(source, /buildQrcodePreviewUrl/);
  assert.match(source, /formatUnixTimestamp/);
});

test("monitor overview and terminal detail share the same terminal monitor card component", () => {
  const componentPath = resolve(
    testDir,
    "../src/components/admin/TerminalMonitorCard.vue"
  );

  assert.equal(existsSync(componentPath), true);

  const componentSource = readFileSync(componentPath, "utf8");
  const overviewSource = readFileSync(
    resolve(testDir, "../src/views/system/monitor/index.vue"),
    "utf8"
  );
  const detailSource = readFileSync(
    resolve(testDir, "../src/views/system/terminals/detail.vue"),
    "utf8"
  );

  assert.match(componentSource, /配置二维码/);
  assert.match(componentSource, /支付配置/);
  assert.match(overviewSource, /TerminalMonitorCard/);
  assert.match(detailSource, /TerminalMonitorCard/);
});
