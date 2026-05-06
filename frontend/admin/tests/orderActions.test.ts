import assert from "node:assert/strict";
import test from "node:test";

import {
  buildDeleteExpiredOrdersConfirmMessage,
  buildDeleteOldOrdersConfirmMessage,
  buildDeleteOrderConfirmMessage,
  buildRepairConfirmMessage,
  resolveRepairAction
} from "../src/views/orders/orderActions.ts";

test("resolveRepairAction returns repair copy for unpaid orders", () => {
  assert.deepEqual(resolveRepairAction(0), {
    label: "补单",
    successMessage: "补单成功",
    failureMessage: "补单失败",
    confirmMessage: "确认对该订单执行补单？",
    notifyErrorMessage: "补单失败，异步通知返回错误，是否查看通知返回数据？"
  });
});

test("resolveRepairAction returns renotify copy for paid and notify-failed orders", () => {
  const expected = {
    label: "重新通知",
    successMessage: "重新通知成功",
    failureMessage: "重新通知失败",
    confirmMessage: "确认重新通知该订单？",
    notifyErrorMessage: "重新通知失败，异步通知返回错误，是否查看通知返回数据？"
  };

  assert.deepEqual(resolveRepairAction(1), expected);
  assert.deepEqual(resolveRepairAction(2), expected);
});

test("resolveRepairAction hides the action for expired orders", () => {
  assert.equal(resolveRepairAction(-1), null);
});

test("order destructive copy identifies concrete order targets", () => {
  assert.equal(
    buildDeleteOrderConfirmMessage({
      id: 8,
      pay_id: "merchant-1001",
      order_id: "cloud-2001"
    }),
    "确认删除订单（商户订单号 merchant-1001）？删除后不可恢复。"
  );

  assert.equal(
    buildDeleteOrderConfirmMessage({ id: 9, order_id: "cloud-2002" }),
    "确认删除订单（云端订单号 cloud-2002）？删除后不可恢复。"
  );
});

test("order bulk destructive copy states irreversible scope", () => {
  assert.equal(
    buildDeleteExpiredOrdersConfirmMessage(),
    "确认删除所有过期订单？删除后不可恢复。"
  );
  assert.equal(
    buildDeleteOldOrdersConfirmMessage(),
    "确认删除七天前的订单？删除后不可恢复。"
  );
});

test("order repair copy identifies external notification impact", () => {
  const action = resolveRepairAction(2);

  assert.equal(
    buildRepairConfirmMessage(action, { pay_id: "merchant-1001" }),
    "确认对订单（商户订单号 merchant-1001）执行重新通知？该操作会重新触发异步通知。"
  );
});
