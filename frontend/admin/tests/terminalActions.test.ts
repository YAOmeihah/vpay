import assert from "node:assert/strict";
import test from "node:test";

import {
  buildDeleteTerminalConfirmMessage,
  buildResetTerminalKeyConfirmMessage
} from "../src/views/system/terminals/terminalActions.ts";

test("terminal delete copy identifies terminal and destructive impact", () => {
  assert.equal(
    buildDeleteTerminalConfirmMessage({
      id: 3,
      terminal_name: "主监控",
      terminal_code: "main-monitor"
    }),
    "确认删除终端（主监控 / main-monitor）？删除后会同时清理该终端下的支付通道配置；若存在未支付订单，后端会阻止删除。"
  );
});

test("terminal reset key copy identifies monitor binding impact", () => {
  assert.equal(
    buildResetTerminalKeyConfirmMessage({
      id: 3,
      terminal_code: "main-monitor"
    }),
    "确认重置终端（main-monitor）的监控密钥？重置后，已有监控绑定链接或二维码需要重新配置。"
  );
});
