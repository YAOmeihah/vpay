export interface RepairAction {
  label: string;
  confirmMessage: string;
  successMessage: string;
  failureMessage: string;
  notifyErrorMessage: string;
}

const REPAIR_ACTION: RepairAction = {
  label: "补单",
  confirmMessage: "确认对该订单执行补单？",
  successMessage: "补单成功",
  failureMessage: "补单失败",
  notifyErrorMessage: "补单失败，异步通知返回错误，是否查看通知返回数据？"
};

const RENOTIFY_ACTION: RepairAction = {
  label: "重新通知",
  confirmMessage: "确认重新通知该订单？",
  successMessage: "重新通知成功",
  failureMessage: "重新通知失败",
  notifyErrorMessage: "重新通知失败，异步通知返回错误，是否查看通知返回数据？"
};

export function resolveRepairAction(state: number): RepairAction | null {
  switch (state) {
    case 0:
      return REPAIR_ACTION;
    case 1:
    case 2:
      return RENOTIFY_ACTION;
    default:
      return null;
  }
}

type OrderLike = {
  id?: string | number;
  pay_id?: string | number;
  order_id?: string | number;
};

function formatOrderIdentity(row: OrderLike): string {
  const payId = String(row.pay_id ?? "").trim();
  const orderId = String(row.order_id ?? "").trim();
  const id = String(row.id ?? "").trim();

  if (payId !== "") {
    return `商户订单号 ${payId}`;
  }

  if (orderId !== "") {
    return `云端订单号 ${orderId}`;
  }

  return id !== "" ? `ID ${id}` : "当前订单";
}

export function buildDeleteOrderConfirmMessage(row: OrderLike): string {
  return `确认删除订单（${formatOrderIdentity(row)}）？删除后不可恢复。`;
}

export function buildDeleteExpiredOrdersConfirmMessage(): string {
  return "确认删除所有过期订单？删除后不可恢复。";
}

export function buildDeleteOldOrdersConfirmMessage(): string {
  return "确认删除七天前的订单？删除后不可恢复。";
}

export function buildRepairConfirmMessage(
  action: RepairAction | null,
  row: OrderLike
): string {
  if (!action) {
    return "";
  }

  const impact =
    action.label === "重新通知"
      ? "该操作会重新触发异步通知。"
      : "该操作会尝试修复订单支付状态。";

  return `确认对订单（${formatOrderIdentity(row)}）执行${action.label}？${impact}`;
}
