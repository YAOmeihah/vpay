import assert from "node:assert/strict";
import test from "node:test";

import route from "../src/router/modules/orders.ts";

test("orders parent route redirects to the list page", () => {
  assert.equal(route.path, "/orders");
  assert.equal(route.redirect, "/orders/index");
  assert.equal(route.children?.[0]?.path, "/orders/index");
});
