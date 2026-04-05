import assert from "node:assert/strict";
import test from "node:test";

import {
  buildPaymentPayload,
  buildQrcodePayload,
  buildSecurityPayload,
  createSettingsSections,
  hydrateSettingsSections
} from "../src/views/system/settings/sectionState.ts";

test("settings sections hydrate backend payload and emit independent save payloads", () => {
  const sections = createSettingsSections();

  hydrateSettingsSections(sections, {
    user: "admin",
    notifyUrl: "https://merchant.example/notify",
    returnUrl: "https://merchant.example/return",
    key: "sign-key",
    monitorKey: "monitor-sign-key",
    notify_ssl_verify: "0",
    close: "15",
    payQf: "1",
    wxpay: "weixin://pay",
    zfbpay: "alipay://pay"
  });

  assert.equal(sections.security.user, "admin");
  assert.equal(sections.security.newPassword, "");
  assert.equal(sections.payment.notifyUrl, "https://merchant.example/notify");
  assert.equal(sections.payment.monitorKey, "monitor-sign-key");
  assert.equal(sections.payment.notifySslVerify, "0");
  assert.equal(sections.qrcode.wxpay, "weixin://pay");

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
    monitorKey: "monitor-sign-key",
    notify_ssl_verify: "0",
    close: "15",
    payQf: "1"
  });
  assert.deepEqual(buildQrcodePayload(sections.qrcode), {
    wxpay: "weixin://pay",
    zfbpay: "alipay://pay"
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
