import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("payment lab has a backend launcher and a standalone full-screen route", () => {
  const systemRoutes = readFileSync(
    resolve("frontend/admin/src/router/modules/system.ts"),
    "utf8"
  );
  const remainingRoutes = readFileSync(
    resolve("frontend/admin/src/router/modules/remaining.ts"),
    "utf8"
  );

  assert.match(systemRoutes, /\/system\/payment-test/);
  assert.match(systemRoutes, /PaymentLabLauncher/);
  assert.match(remainingRoutes, /\/payment-lab/);
  assert.match(remainingRoutes, /views\/payment-lab\/index\.vue/);
  assert.match(remainingRoutes, /showLink:\s*false/);
});

test("payment lab page submits through admin api and keeps debug actions before opening pay page", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/payment-lab/index.vue"),
    "utf8"
  );

  assert.match(source, /VPay Payment Lab/);
  assert.match(source, /createPaymentTestOrder/);
  assert.match(source, /payPageUrl/);
  assert.match(source, /openPayPage/);
  assert.match(source, /复制请求参数/);
  assert.match(source, /复制 curl/);
  assert.match(source, /最近测试记录/);
  assert.match(source, /field-error/);
  assert.match(source, /signType:\s*"MD5"/);
  assert.match(source, /HMAC_SHA256/);
  assert.match(source, /signature-switch/);
  assert.doesNotMatch(
    source,
    /async function submitOrder\(\)[\s\S]*window\.location\.href\s*=\s*payPageUrl/
  );
  assert.doesNotMatch(source, /getPaymentTestOrder/);
  assert.doesNotMatch(source, /getPaymentTestCallback/);
  assert.doesNotMatch(source, /\/enQrcode\?url=/);
  assert.doesNotMatch(source, /二维码与支付地址/);
  assert.doesNotMatch(source, /回调捕获/);
  assert.doesNotMatch(source, /<el-/);
  assert.doesNotMatch(source, /<\/el-/);
});

test("payment lab launcher uses scoped visual styles for a readable launch button", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/system/payment-test/index.vue"),
    "utf8"
  );

  assert.match(source, /class="payment-lab-launcher"/);
  assert.match(source, /class="launch-button"/);
  assert.match(source, /\.launch-button/);
  assert.match(source, /color:\s*#04111d/);
  assert.doesNotMatch(source, /launcher-meter/);
  assert.doesNotMatch(source, />LAB</);
  assert.doesNotMatch(source, />READY</);
});

test("payment lab launcher card has no shadow and tighter mobile gutters", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/system/payment-test/index.vue"),
    "utf8"
  );

  assert.match(
    source,
    /:global\(:root\)[\s\S]*--payment-lab-card-shadow:\s*none;/
  );
  assert.match(
    source,
    /:global\(html\.dark\)[\s\S]*--payment-lab-card-shadow:\s*none;/
  );
  assert.match(
    source,
    /:global\(:root\)[\s\S]*--payment-lab-launcher-bg:\s*#eef2f7;/
  );
  assert.match(
    source,
    /:global\(html\.dark\)[\s\S]*--payment-lab-launcher-bg:\s*#020617;/
  );
  assert.match(
    source,
    /@media \(width <= 860px\)[\s\S]*\.payment-lab-launcher\s*{[\s\S]*margin-inline:\s*-12px;[\s\S]*padding:\s*16px 24px;/
  );
});

test("payment lab launcher includes dark mode overrides", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/system/payment-test/index.vue"),
    "utf8"
  );

  assert.match(source, /--payment-lab-launcher-bg/);
  assert.match(source, /--payment-lab-card-bg/);
  assert.match(source, /--payment-lab-title-color/);
  assert.match(source, /--payment-lab-copy-color/);
  assert.match(source, /:global\(:root\)/);
  assert.match(source, /:global\(html\.dark\)/);
});

test("payment lab api types include selected sign type", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/api/admin/paymentLab.ts"),
    "utf8"
  );

  assert.match(source, /signType\?:\s*"MD5"\s*\|\s*"HMAC_SHA256"/);
  assert.match(source, /signatureValid:\s*boolean/);
});
