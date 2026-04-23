import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import { buildPaymentSlots } from "../src/views/system/terminals/paymentSlotState.ts";

const testDir = dirname(fileURLToPath(import.meta.url));

test("buildPaymentSlots always returns wechat and alipay slots", () => {
  const slots = buildPaymentSlots(
    [
      {
        id: 11,
        terminal_id: 9,
        type: 1,
        channel_name: "设备微信",
        status: "enabled",
        pay_url: "wxp://slot-a",
        exists: true
      }
    ],
    9
  );

  assert.equal(slots.length, 2);
  assert.deepEqual(
    slots.map(slot => ({
      type: slot.type,
      label: slot.slotLabel,
      exists: slot.exists
    })),
    [
      { type: 1, label: "微信", exists: true },
      { type: 2, label: "支付宝", exists: false }
    ]
  );
  assert.equal(slots[0].channelName, "设备微信");
  assert.equal(slots[1].terminalId, 9);
  assert.equal(slots[1].channelName, "支付宝收款");
  assert.equal(slots[1].payUrl, "");
  assert.equal("priority" in slots[0], false);
});

test("terminal detail view no longer exposes free-form channel creation UI", () => {
  const source = readFileSync(
    resolve(testDir, "../src/views/system/terminals/detail.vue"),
    "utf8"
  );

  assert.doesNotMatch(source, /新增通道/);
  assert.doesNotMatch(source, /<el-select v-model=\"form\\.type\"/);
  assert.doesNotMatch(source, /优先级/);
  assert.doesNotMatch(source, /form\\.priority/);
  assert.match(source, /固定维护微信和支付宝两个支付配置/);
  assert.match(source, /buildPaymentSlots/);
  assert.match(source, /TerminalMonitorCard/);
  assert.match(source, /getTerminalDetail/);
});

test("terminal management view edits dispatch priority on the terminal itself", () => {
  const source = readFileSync(
    resolve(testDir, "../src/views/system/terminals/index.vue"),
    "utf8"
  );

  assert.match(source, /dispatchPriority/);
  assert.match(source, /分配顺序/);
});

test("terminal management view exposes guarded terminal deletion", () => {
  const viewSource = readFileSync(
    resolve(testDir, "../src/views/system/terminals/index.vue"),
    "utf8"
  );
  const apiSource = readFileSync(
    resolve(testDir, "../src/api/admin/terminal.ts"),
    "utf8"
  );

  assert.match(apiSource, /deleteTerminal/);
  assert.match(apiSource, /\/admin\/index\/deleteTerminal/);
  assert.match(viewSource, /deleteTerminal/);
  assert.match(viewSource, /handleDelete/);
  assert.match(viewSource, /确认删除该终端/);
  assert.match(viewSource, /type="danger"/);
});
