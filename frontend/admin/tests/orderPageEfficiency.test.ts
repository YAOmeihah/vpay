import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { resolve } from "node:path";

test("orders list exposes advanced filters and copy actions", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/views/orders/index.vue"),
    "utf8"
  );

  assert.match(source, /filters\.keyword/);
  assert.match(source, /filters\.amount/);
  assert.match(source, /filters\.dateRange/);
  assert.match(source, /filters\.terminalId/);
  assert.match(source, /filters\.channelId/);
  assert.match(source, /buildOrderQueryParams/);
  assert.match(source, /copyOrderField\(row, ['"]payId['"]/);
  assert.match(source, /copyOrderField\(row, ['"]orderId['"]/);
  assert.match(source, /copyOrderField\(row, ['"]amount['"]/);
});

test("order detail dialog exposes copyable notify url and amount", () => {
  const source = readFileSync(
    resolve("frontend/admin/src/components/admin/OrderDetailDialog.vue"),
    "utf8"
  );

  assert.match(source, /notify_url/);
  assert.match(source, /\$emit\(['"]copy['"], order, ['"]notifyUrl['"]\)/);
  assert.match(source, /\$emit\(['"]copy['"], order, ['"]amount['"]\)/);
});
