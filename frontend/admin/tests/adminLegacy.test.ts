import assert from "node:assert/strict";
import test from "node:test";

import {
  buildDashboardOperationSummary,
  buildMonitorConfigUrl,
  formatUnixTimestamp,
  getMonitorStatus,
  mapDashboardStats,
  normalizePagedList
} from "../src/utils/adminLegacy.ts";

test("mapDashboardStats preserves legacy backend field names", () => {
  const mapped = mapDashboardStats({
    todayOrder: 12,
    todaySuccessOrder: 8,
    todayCloseOrder: 3,
    todayMoney: 66.5,
    countOrder: 99,
    countMoney: 188.8
  });

  assert.deepEqual(mapped, {
    todayOrder: 12,
    todaySuccessOrder: 8,
    todayCloseOrder: 3,
    todayMoney: 66.5,
    countOrder: 99,
    countMoney: 188.8
  });
});

test("buildDashboardOperationSummary highlights today's order health", () => {
  assert.deepEqual(
    buildDashboardOperationSummary({
      todayOrder: 12,
      todaySuccessOrder: 8,
      todayCloseOrder: 3,
      todayMoney: 66.5,
      countOrder: 99,
      countMoney: 188.8
    }),
    {
      successRate: "66.7%",
      successPercentage: 66.7,
      unfinishedOrderCount: 4,
      statusText: "需关注",
      statusType: "danger",
      progressStatus: "exception",
      actionText: "今日有 4 笔订单未成功，请优先排查。"
    }
  );

  assert.deepEqual(
    buildDashboardOperationSummary(mapDashboardStats()),
    {
      successRate: "0%",
      successPercentage: 0,
      unfinishedOrderCount: 0,
      statusText: "等待订单",
      statusType: "info",
      progressStatus: undefined,
      actionText: "今日暂无订单，关注终端在线状态即可。"
    }
  );
});

test("formatUnixTimestamp renders legacy-style timestamps and zero as 无", () => {
  assert.equal(formatUnixTimestamp(0), "无");
  assert.equal(formatUnixTimestamp("0"), "无");
  assert.equal(formatUnixTimestamp(1712217906), "2024-04-04 16:05:06");
});

test("getMonitorStatus matches legacy monitor copy", () => {
  assert.deepEqual(getMonitorStatus(-1), {
    text: "监控端未绑定，请您扫码绑定",
    type: "warning"
  });
  assert.deepEqual(getMonitorStatus(0), {
    text: "监控端已掉线，请您检查App是否正常运行",
    type: "danger"
  });
  assert.deepEqual(getMonitorStatus(1), {
    text: "运行正常",
    type: "success"
  });
});

test("buildMonitorConfigUrl mirrors multi-terminal binding payload composition", () => {
  assert.equal(
    buildMonitorConfigUrl("https://pay.example.com", "term-a", "abc123"),
    "https://pay.example.com/monitor-bind?terminalCode=term-a&monitorKey=abc123"
  );
  assert.equal(buildMonitorConfigUrl("https://pay.example.com/", "term-a", ""), "");
});

test("normalizePagedList keeps legacy list payloads usable when data is empty or count is missing", () => {
  assert.deepEqual(normalizePagedList({ data: [{ id: 1 }, { id: 2 }], count: "2" }), {
    items: [{ id: 1 }, { id: 2 }],
    total: 2
  });
  assert.deepEqual(normalizePagedList({ data: [] }), {
    items: [],
    total: 0
  });
  assert.deepEqual(normalizePagedList({ data: null, count: null }), {
    items: [],
    total: 0
  });
});
