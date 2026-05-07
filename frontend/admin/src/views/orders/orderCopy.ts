export type OrderCopyField = "orderId" | "payId" | "notifyUrl" | "amount";

type OrderLike = Record<string, unknown>;

function trimValue(value: unknown): string {
  return String(value ?? "").trim();
}

export function buildOrderAmountCopyText(row: OrderLike): string {
  const price = trimValue(row.price);
  const reallyPrice = trimValue(row.really_price);

  if (price !== "" && reallyPrice !== "" && price !== reallyPrice) {
    return `订单金额 ${price} / 实际金额 ${reallyPrice}`;
  }

  return reallyPrice || price;
}

export function resolveOrderCopyText(
  row: OrderLike,
  field: OrderCopyField
): string {
  if (field === "orderId") return trimValue(row.order_id);
  if (field === "payId") return trimValue(row.pay_id);
  if (field === "notifyUrl") return trimValue(row.notify_url);
  return buildOrderAmountCopyText(row);
}

