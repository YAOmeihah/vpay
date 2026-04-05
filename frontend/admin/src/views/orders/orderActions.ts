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
