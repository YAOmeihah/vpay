import assert from "node:assert/strict";
import test from "node:test";

import {
  buildEpayPayload,
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
    close: "15",
    payQf: "1",
    wxpay: "weixin://pay",
    zfbpay: "alipay://pay",
    epay_enabled: "1",
    epay_pid: "10001",
    epay_name: "订单支付",
    epay_public_key: "PUBLIC-KEY"
  });

  assert.equal(sections.security.user, "admin");
  assert.equal(sections.security.newPassword, "");
  assert.equal(sections.payment.notifyUrl, "https://merchant.example/notify");
  assert.equal(sections.qrcode.wxpay, "weixin://pay");
  assert.equal(sections.epay.epay_public_key, "PUBLIC-KEY");

  sections.security.newPassword = "next-pass";
  sections.security.confirmPassword = "next-pass";
  sections.epay.epay_key = "";
  sections.epay.epay_private_key = "";

  assert.deepEqual(buildSecurityPayload(sections.security), {
    user: "admin",
    pass: "next-pass"
  });
  assert.deepEqual(buildPaymentPayload(sections.payment), {
    notifyUrl: "https://merchant.example/notify",
    returnUrl: "https://merchant.example/return",
    key: "sign-key",
    close: "15",
    payQf: "1"
  });
  assert.deepEqual(buildQrcodePayload(sections.qrcode), {
    wxpay: "weixin://pay",
    zfbpay: "alipay://pay"
  });
  assert.deepEqual(buildEpayPayload(sections.epay), {
    epay_enabled: "1",
    epay_pid: "10001",
    epay_name: "订单支付",
    epay_public_key: "PUBLIC-KEY"
  });
});

test("hydrateSettingsSections resets password and private secrets after reload", () => {
  const sections = createSettingsSections();

  sections.security.newPassword = "stale-pass";
  sections.security.confirmPassword = "stale-pass";
  sections.epay.epay_key = "stale-key";
  sections.epay.epay_private_key = "stale-private";

  hydrateSettingsSections(sections, {
    user: "admin-next",
    epay_public_key: "PUBLIC-KEY-NEXT"
  });

  assert.equal(sections.security.user, "admin-next");
  assert.equal(sections.security.newPassword, "");
  assert.equal(sections.security.confirmPassword, "");
  assert.equal(sections.epay.epay_key, "");
  assert.equal(sections.epay.epay_private_key, "");
  assert.equal(sections.epay.epay_public_key, "PUBLIC-KEY-NEXT");
});
