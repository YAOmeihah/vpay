export type MonitorStatusType = "success" | "warning" | "danger";
export type DashboardStatusType = MonitorStatusType | "info";

export type DashboardStats = {
  todayOrder: number;
  todaySuccessOrder: number;
  todayCloseOrder: number;
  todayMoney: number | string;
  countOrder: number;
  countMoney: number | string;
};

export type DashboardOperationSummary = {
  successRate: string;
  successPercentage: number;
  unfinishedOrderCount: number;
  statusText: string;
  statusType: DashboardStatusType;
  progressStatus?: "success" | "warning" | "exception";
  actionText: string;
};

const LEGACY_TIMEZONE_OFFSET_MS = 8 * 60 * 60 * 1000;

export function mapDashboardStats(
  payload?: Record<string, any>
): DashboardStats {
  return {
    todayOrder: Number(payload?.todayOrder ?? 0),
    todaySuccessOrder: Number(payload?.todaySuccessOrder ?? 0),
    todayCloseOrder: Number(payload?.todayCloseOrder ?? 0),
    todayMoney: payload?.todayMoney ?? 0,
    countOrder: Number(payload?.countOrder ?? 0),
    countMoney: payload?.countMoney ?? 0
  };
}

function toNonNegativeNumber(value: unknown): number {
  const numberValue = Number(value ?? 0);
  if (!Number.isFinite(numberValue) || numberValue < 0) return 0;
  return numberValue;
}

function formatRate(value: number): string {
  if (value === 0) return "0%";
  return `${value.toFixed(1).replace(/\.0$/, "")}%`;
}

export function buildDashboardOperationSummary(
  stats: DashboardStats
): DashboardOperationSummary {
  const todayOrder = toNonNegativeNumber(stats.todayOrder);
  const todaySuccessOrder = Math.min(
    toNonNegativeNumber(stats.todaySuccessOrder),
    todayOrder
  );
  const todayCloseOrder = toNonNegativeNumber(stats.todayCloseOrder);
  const unfinishedOrderCount = Math.max(todayOrder - todaySuccessOrder, 0);
  const successPercentage =
    todayOrder === 0
      ? 0
      : Math.round((todaySuccessOrder / todayOrder) * 1000) / 10;

  if (todayOrder === 0) {
    return {
      successRate: "0%",
      successPercentage: 0,
      unfinishedOrderCount: 0,
      statusText: "等待订单",
      statusType: "info",
      progressStatus: undefined,
      actionText: "今日暂无订单，关注终端在线状态即可。"
    };
  }

  if (unfinishedOrderCount === 0) {
    return {
      successRate: "100%",
      successPercentage: 100,
      unfinishedOrderCount,
      statusText: "运行平稳",
      statusType: "success",
      progressStatus: "success",
      actionText: "今日订单全部成功，继续关注收入变化。"
    };
  }

  const statusType = todayCloseOrder > 0 ? "danger" : "warning";

  return {
    successRate: formatRate(successPercentage),
    successPercentage,
    unfinishedOrderCount,
    statusText: "需关注",
    statusType,
    progressStatus: statusType === "danger" ? "exception" : "warning",
    actionText: `今日有 ${unfinishedOrderCount} 笔订单未成功，请优先排查。`
  };
}

export function normalizePagedList<T = Record<string, any>>(payload?: {
  data?: unknown;
  count?: unknown;
}): { items: T[]; total: number } {
  const items = Array.isArray(payload?.data) ? (payload?.data as T[]) : [];
  const rawTotal = Number(payload?.count);
  const total =
    Number.isFinite(rawTotal) && rawTotal >= 0 ? rawTotal : items.length;

  return { items, total };
}

export function formatUnixTimestamp(value?: string | number | null): string {
  const timestamp = Number(value ?? 0);
  if (!timestamp) return "无";

  const date = new Date(timestamp * 1000 + LEGACY_TIMEZONE_OFFSET_MS);
  const year = date.getUTCFullYear();
  const month = String(date.getUTCMonth() + 1).padStart(2, "0");
  const day = String(date.getUTCDate()).padStart(2, "0");
  const hour = String(date.getUTCHours()).padStart(2, "0");
  const minute = String(date.getUTCMinutes()).padStart(2, "0");
  const second = String(date.getUTCSeconds()).padStart(2, "0");

  return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
}

export function getMonitorStatus(jkstate?: string | number | null): {
  text: string;
  type: MonitorStatusType;
} {
  const state = Number(jkstate ?? -1);

  if (state === -1) {
    return { text: "监控端未绑定，请您扫码绑定", type: "warning" };
  }
  if (state === 0) {
    return {
      text: "监控端已掉线，请您检查App是否正常运行",
      type: "danger"
    };
  }
  if (state === 1) {
    return { text: "运行正常", type: "success" };
  }

  return { text: "未知状态", type: "warning" };
}

export function buildMonitorConfigUrl(
  base: string,
  terminalCode?: string | null,
  key?: string | null
): string {
  const normalizedBase = String(base ?? "").replace(/\/+$/, "");
  const normalizedTerminalCode = String(terminalCode ?? "").trim();
  const normalizedKey = String(key ?? "").trim();

  if (!normalizedBase || !normalizedTerminalCode || !normalizedKey) return "";
  return `${normalizedBase}/monitor-bind?terminalCode=${encodeURIComponent(
    normalizedTerminalCode
  )}&monitorKey=${encodeURIComponent(normalizedKey)}`;
}

export function buildQrcodePreviewUrl(url?: string | null): string {
  const raw = String(url ?? "").trim();
  if (!raw) return "";
  return `/enQrcode?url=${encodeURIComponent(raw)}`;
}

export function generateMd5LikeKey(): string {
  const chars = "abcdefghijklmnopqrstuvwxyz0123456789";
  let key = "";

  for (let i = 0; i < 32; i += 1) {
    key += chars.charAt(Math.floor(Math.random() * chars.length));
  }

  return key;
}

export function isValidMoneyInput(value?: string | number | null): boolean {
  return /^[0-9]+(?:\.[0-9]+)?$/.test(String(value ?? "").trim());
}
