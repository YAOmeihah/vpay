import assert from "node:assert/strict";
import test from "node:test";

import {
  addPaymentLabHistoryEntry,
  buildPaymentLabCurl,
  buildPaymentLabRequestPayload,
  validatePaymentLabForm
} from "../src/views/payment-lab/paymentLabState.ts";

test("validatePaymentLabForm reports field-level errors", () => {
  assert.deepEqual(
    validatePaymentLabForm({
      type: 1,
      price: "0",
      signType: "MD5",
      payId: "",
      param: "",
      notifyUrl: "ftp://example.test/notify",
      returnUrl: "bad-url"
    }),
    {
      price: "请输入大于 0 的测试金额",
      payId: "请输入商户订单号",
      notifyUrl: "异步回调地址必须以 http:// 或 https:// 开头",
      returnUrl: "同步跳转地址必须以 http:// 或 https:// 开头"
    }
  );
});

test("buildPaymentLabRequestPayload trims optional fields for submission and copy", () => {
  assert.deepEqual(
    buildPaymentLabRequestPayload({
      type: 2,
      price: " 10.01 ",
      signType: "HMAC_SHA256",
      payId: " TEST-1001 ",
      param: " smoke ",
      notifyUrl: " ",
      returnUrl: " https://merchant.example/return "
    }),
    {
      type: 2,
      price: "10.01",
      signType: "HMAC_SHA256",
      payId: "TEST-1001",
      param: "smoke",
      returnUrl: "https://merchant.example/return"
    }
  );
});

test("buildPaymentLabCurl produces a reproducible admin api request", () => {
  const curl = buildPaymentLabCurl(
    {
      type: 1,
      price: "0.10",
      signType: "MD5",
      payId: "TEST-1001"
    },
    "http://vpay.test"
  );

  assert.match(curl, /curl -X POST "http:\/\/vpay\.test\/admin\/index\/createPaymentTestOrder"/);
  assert.match(curl, /"payId": "TEST-1001"/);
});

test("addPaymentLabHistoryEntry keeps the newest local records first", () => {
  const entries = addPaymentLabHistoryEntry(
    [
      { payId: "old-1", orderId: "cloud-1", price: "0.10", payPageUrl: "/a", createdAt: 1 },
      { payId: "old-2", orderId: "cloud-2", price: "0.20", payPageUrl: "/b", createdAt: 2 }
    ],
    { payId: "new", orderId: "cloud-3", price: "0.30", payPageUrl: "/c", createdAt: 3 },
    2
  );

  assert.deepEqual(entries.map(item => item.payId), ["new", "old-1"]);
});

