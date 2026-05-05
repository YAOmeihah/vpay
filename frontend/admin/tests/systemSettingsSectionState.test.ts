import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import {
  buildMaintenancePayload,
  buildPaymentPayload,
  buildSecurityPayload,
  createSettingsSections,
  hydrateSettingsSections
} from "../src/views/system/settings/sectionState.ts";

const testDir = dirname(fileURLToPath(import.meta.url));

test("settings sections hydrate backend payload and emit independent save payloads", () => {
  const sections = createSettingsSections();

  hydrateSettingsSections(sections, {
    user: "admin",
    notifyUrl: "https://merchant.example/notify",
    returnUrl: "https://merchant.example/return",
    key: "sign-key",
    notify_ssl_verify: "0",
    close: "15",
    payQf: "1",
    allocationStrategy: "round_robin",
    maintenance_enabled: "1",
    maintenance_token: "token",
    maintenance_allowed_ips: "127.0.0.1",
    maintenance_task_terminal_offline_check: "1",
    maintenance_task_expired_order_cleanup: "0",
    maintenance_last_run_at: "1770000000",
    maintenance_last_run_result: '{"status":"ok"}',
    notify_telegram_enabled: "1",
    notify_telegram_bot_token: "bot",
    notify_telegram_chat_id: "chat",
    notify_event_terminal_offline: "1",
    notify_event_terminal_recovered: "0",
    notify_event_expired_order_cleanup: "1",
    notify_event_maintenance_exception: "0"
  });

  assert.equal(sections.security.user, "admin");
  assert.equal(sections.security.newPassword, "");
  assert.equal(sections.payment.notifyUrl, "https://merchant.example/notify");
  assert.equal(sections.payment.notifySslVerify, "0");
  assert.equal(sections.payment.allocationStrategy, "round_robin");
  assert.equal(sections.maintenance.enabled, "1");
  assert.equal(sections.maintenance.token, "token");
  assert.equal(sections.maintenance.allowedIps, "127.0.0.1");
  assert.equal(sections.maintenance.expiredOrderCleanupTask, "0");
  assert.equal(sections.maintenance.telegramEnabled, "1");
  assert.equal(sections.maintenance.notifyTerminalRecovered, "0");
  assert.equal("monitorKey" in sections.payment, false);
  assert.equal("qrcode" in sections, false);

  sections.security.newPassword = "next-pass";
  sections.security.confirmPassword = "next-pass";

  assert.deepEqual(buildSecurityPayload(sections.security), {
    user: "admin",
    pass: "next-pass"
  });
  assert.deepEqual(buildPaymentPayload(sections.payment), {
    notifyUrl: "https://merchant.example/notify",
    returnUrl: "https://merchant.example/return",
    key: "sign-key",
    notify_ssl_verify: "0",
    close: "15",
    payQf: "1",
    allocationStrategy: "round_robin"
  });
  assert.deepEqual(buildMaintenancePayload(sections.maintenance), {
    maintenance_enabled: "1",
    maintenance_token: "token",
    maintenance_allowed_ips: "127.0.0.1",
    maintenance_task_terminal_offline_check: "1",
    maintenance_task_expired_order_cleanup: "0",
    notify_telegram_enabled: "1",
    notify_telegram_bot_token: "bot",
    notify_telegram_chat_id: "chat",
    notify_event_terminal_offline: "1",
    notify_event_terminal_recovered: "0",
    notify_event_expired_order_cleanup: "1",
    notify_event_maintenance_exception: "0"
  });
});

test("hydrateSettingsSections resets password fields after reload", () => {
  const sections = createSettingsSections();

  sections.security.newPassword = "stale-pass";
  sections.security.confirmPassword = "stale-pass";

  hydrateSettingsSections(sections, {
    user: "admin-next"
  });

  assert.equal(sections.security.user, "admin-next");
  assert.equal(sections.security.newPassword, "");
  assert.equal(sections.security.confirmPassword, "");
});

test("system settings page no longer renders single-terminal monitor key or default qrcode cards", () => {
  const source = readFileSync(
    resolve(testDir, "../src/views/system/settings/index.vue"),
    "utf8"
  );

  assert.doesNotMatch(source, /QrcodeCard/);
  assert.doesNotMatch(source, /wxpay|zfbpay/);
  assert.doesNotMatch(source, /monitorKey/);
  assert.doesNotMatch(source, /默认终端继承的旧版收款码/);
  assert.match(source, /MaintenanceCard/);
  assert.match(source, /buildMaintenancePayload/);
});

test("maintenance card shows cron integration request hints", () => {
  const source = readFileSync(
    resolve(
      testDir,
      "../src/views/system/settings/components/MaintenanceCard.vue"
    ),
    "utf8"
  );

  assert.match(source, /POST \/maintenance\/run/);
  assert.match(source, /X-Maintenance-Token/);
  assert.match(source, /curl/);
});

test("maintenance integration hints use theme-aware Element Plus components", () => {
  const source = readFileSync(
    resolve(
      testDir,
      "../src/views/system/settings/components/MaintenanceCard.vue"
    ),
    "utf8"
  );

  assert.match(source, /<el-descriptions/);
  assert.doesNotMatch(source, /bg-gray-50|border-gray-200/);
});
