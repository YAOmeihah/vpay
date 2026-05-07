import type {
  PaymentLabCreatePayload,
  PaymentLabResult
} from "@/api/admin/paymentLab";

export type PaymentLabForm = {
  type: number;
  price: string;
  signType: "MD5" | "HMAC_SHA256";
  payId: string;
  param?: string;
  notifyUrl?: string;
  returnUrl?: string;
};

export type PaymentLabFieldErrors = Partial<Record<keyof PaymentLabForm, string>>;

export type PaymentLabHistoryEntry = {
  payId: string;
  orderId: string;
  price: string;
  payPageUrl: string;
  createdAt: number;
};

function trimValue(value: unknown): string {
  return String(value ?? "").trim();
}

function isHttpUrl(value: string): boolean {
  return /^https?:\/\//i.test(value);
}

export function validatePaymentLabForm(
  form: PaymentLabForm
): PaymentLabFieldErrors {
  const errors: PaymentLabFieldErrors = {};
  const price = trimValue(form.price);
  const payId = trimValue(form.payId);
  const notifyUrl = trimValue(form.notifyUrl);
  const returnUrl = trimValue(form.returnUrl);

  if (!/^[0-9]+(?:\.[0-9]{1,2})?$/.test(price) || Number(price) <= 0) {
    errors.price = "请输入大于 0 的测试金额";
  }
  if (payId === "") {
    errors.payId = "请输入商户订单号";
  }
  if (notifyUrl !== "" && !isHttpUrl(notifyUrl)) {
    errors.notifyUrl = "异步回调地址必须以 http:// 或 https:// 开头";
  }
  if (returnUrl !== "" && !isHttpUrl(returnUrl)) {
    errors.returnUrl = "同步跳转地址必须以 http:// 或 https:// 开头";
  }

  return errors;
}

export function buildPaymentLabRequestPayload(
  form: PaymentLabForm
): PaymentLabCreatePayload {
  const payload: PaymentLabCreatePayload = {
    type: form.type,
    price: trimValue(form.price),
    signType: form.signType
  };
  const payId = trimValue(form.payId);
  const param = trimValue(form.param);
  const notifyUrl = trimValue(form.notifyUrl);
  const returnUrl = trimValue(form.returnUrl);

  if (payId !== "") payload.payId = payId;
  if (param !== "") payload.param = param;
  if (notifyUrl !== "") payload.notifyUrl = notifyUrl;
  if (returnUrl !== "") payload.returnUrl = returnUrl;

  return payload;
}

export function buildPaymentLabCurl(
  payload: PaymentLabCreatePayload,
  origin: string
): string {
  const baseUrl = origin.replace(/\/+$/, "");
  const body = JSON.stringify(payload, null, 2);
  return [
    `curl -X POST "${baseUrl}/admin/index/createPaymentTestOrder"`,
    '  -H "Content-Type: application/json"',
    `  --data '${body}'`
  ].join(" \\\n");
}

export function buildPaymentLabHistoryEntry(
  result: PaymentLabResult
): PaymentLabHistoryEntry | null {
  const payPageUrl = trimValue(result.payPageUrl);
  if (!result.order || payPageUrl === "") return null;

  return {
    payId: trimValue(result.order.payId),
    orderId: trimValue(result.order.orderId),
    price: trimValue(result.order.reallyPrice || result.order.price),
    payPageUrl,
    createdAt: Date.now()
  };
}

export function addPaymentLabHistoryEntry(
  entries: PaymentLabHistoryEntry[],
  entry: PaymentLabHistoryEntry,
  limit = 5
): PaymentLabHistoryEntry[] {
  return [entry, ...entries].slice(0, limit);
}

