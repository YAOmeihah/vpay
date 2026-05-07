import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

function readSource(path: string): string {
  return readFileSync(resolve(path), "utf8");
}

test("shared admin mobile components exist and use Element Plus primitives", () => {
  const mobileIndex = readSource(
    "frontend/admin/src/components/admin/mobile/index.ts"
  );
  const listSource = readSource(
    "frontend/admin/src/components/admin/mobile/AdminMobileList.vue"
  );
  const cardSource = readSource(
    "frontend/admin/src/components/admin/mobile/AdminMobileRecordCard.vue"
  );
  const actionsSource = readSource(
    "frontend/admin/src/components/admin/mobile/AdminMobileActions.vue"
  );
  const drawerSource = readSource(
    "frontend/admin/src/components/admin/mobile/AdminFilterDrawer.vue"
  );

  assert.match(mobileIndex, /AdminMobileList/);
  assert.match(mobileIndex, /AdminMobileRecordCard/);
  assert.match(mobileIndex, /AdminMobileField/);
  assert.match(mobileIndex, /AdminMobileActions/);
  assert.match(mobileIndex, /AdminFilterDrawer/);
  assert.match(listSource, /el-empty/);
  assert.match(cardSource, /admin-mobile-record-card/);
  assert.match(actionsSource, /el-dropdown/);
  assert.match(drawerSource, /el-drawer/);
});

test("high-frequency admin pages expose mobile card and filter patterns", () => {
  const orders = readSource("frontend/admin/src/views/orders/index.vue");
  const terminals = readSource(
    "frontend/admin/src/views/system/terminals/index.vue"
  );
  const qrList = readSource("frontend/admin/src/components/admin/QrList.vue");
  const qrBatch = readSource(
    "frontend/admin/src/components/admin/QrBatchUploader.vue"
  );
  const dashboard = readSource("frontend/admin/src/views/dashboard/index.vue");

  assert.match(orders, /AdminFilterDrawer/);
  assert.match(orders, /AdminMobileRecordCard/);
  assert.match(orders, /orders-mobile-list/);
  assert.match(orders, /orders-desktop-table/);
  assert.match(terminals, /AdminMobileRecordCard/);
  assert.match(terminals, /terminal-mobile-list/);
  assert.match(terminals, /terminal-desktop-table/);
  assert.match(qrList, /AdminMobileRecordCard/);
  assert.match(qrList, /qr-mobile-list/);
  assert.match(qrBatch, /AdminMobileRecordCard/);
  assert.match(qrBatch, /qr-upload-mobile-list/);
  assert.match(dashboard, /dashboard-mobile-system/);
});

test("mobile baseline styles do not target the payment test page", () => {
  const globalStyle = readSource("frontend/admin/src/style/element-plus.scss");
  const paymentTest = readSource(
    "frontend/admin/src/views/system/payment-test/index.vue"
  );
  const paymentLab = readSource(
    "frontend/admin/src/views/payment-lab/index.vue"
  );

  assert.match(globalStyle, /admin-mobile-only/);
  assert.match(globalStyle, /admin-desktop-only/);
  assert.doesNotMatch(globalStyle, /payment-test/);
  assert.doesNotMatch(paymentTest, /AdminMobile/);
  assert.doesNotMatch(paymentLab, /AdminMobile/);
});

test("mobile baseline removes PureAdmin route gutters from card pages", () => {
  const globalStyle = readSource("frontend/admin/src/style/element-plus.scss");

  assert.match(globalStyle, /\.main-content\.p-4/);
  assert.match(globalStyle, /\.main-content\.p-4[\s\S]*?margin:\s*0;/);
});
