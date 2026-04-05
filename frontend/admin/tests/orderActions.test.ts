import assert from "node:assert/strict";
import test from "node:test";

import { resolveRepairAction } from "../src/views/orders/orderActions.ts";

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
