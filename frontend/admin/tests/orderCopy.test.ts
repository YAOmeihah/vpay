import assert from "node:assert/strict";
import test from "node:test";

import {
  buildOrderAmountCopyText,
  resolveOrderCopyText
} from "../src/views/orders/orderCopy.ts";

test("resolveOrderCopyText returns common order fields as trimmed copy text", () => {
  const row = {
    order_id: " cloud-2001 ",
    pay_id: " merchant-1001 ",
    notify_url: " https://merchant.example/notify "
  };

  assert.equal(resolveOrderCopyText(row, "orderId"), "cloud-2001");
  assert.equal(resolveOrderCopyText(row, "payId"), "merchant-1001");
  assert.equal(
    resolveOrderCopyText(row, "notifyUrl"),
    "https://merchant.example/notify"
  );
});

test("buildOrderAmountCopyText keeps both order and actual amount when they differ", () => {
  assert.equal(
    buildOrderAmountCopyText({ price: "10.00", really_price: "10.01" }),
    "订单金额 10.00 / 实际金额 10.01"
  );

  assert.equal(
    buildOrderAmountCopyText({ price: "10.00", really_price: "10.00" }),
    "10.00"
  );
});

