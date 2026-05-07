import assert from "node:assert/strict";
import test from "node:test";

import {
  buildOrderQueryParams,
  createDefaultOrderFilters
} from "../src/views/orders/orderFilters.ts";

test("buildOrderQueryParams includes trimmed advanced filters for the backend", () => {
  const filters = createDefaultOrderFilters();
  filters.page = 3;
  filters.type = "1";
  filters.state = "0";
  filters.keyword = "  merchant-1001  ";
  filters.amount = " 10.01 ";
  filters.dateRange = [
    new Date("2026-01-02T03:04:05.000Z"),
    new Date("2026-01-03T04:05:06.000Z")
  ];
  filters.terminalId = " 7 ";
  filters.channelId = "17";

  assert.deepEqual(buildOrderQueryParams(filters, 15), {
    page: 3,
    limit: 15,
    type: "1",
    state: "0",
    keyword: "merchant-1001",
    amount: "10.01",
    createStart: 1767323045,
    createEnd: 1767413106,
    terminalId: 7,
    channelId: 17
  });
});

test("buildOrderQueryParams omits empty or invalid optional filters", () => {
  const filters = createDefaultOrderFilters();
  filters.keyword = " ";
  filters.amount = "abc";
  filters.dateRange = ["bad-start", "bad-end"];
  filters.terminalId = "0";
  filters.channelId = "-1";

  assert.deepEqual(buildOrderQueryParams(filters, 15), {
    page: 1,
    limit: 15
  });
});

