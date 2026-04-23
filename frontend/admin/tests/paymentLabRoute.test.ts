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

test("payment lab page submits through admin api and redirects into the original pay page flow", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/payment-lab/index.vue"),
    "utf8"
  );

  assert.match(source, /VPay Payment Lab/);
  assert.match(source, /createPaymentTestOrder/);
  assert.match(source, /payPageUrl/);
  assert.match(source, /window\.location\.href/);
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
