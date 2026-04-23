export type MonitorStatusType = "success" | "warning" | "danger";

type DashboardStats = {
  todayOrder: number;
  todaySuccessOrder: number;
  todayCloseOrder: number;
  todayMoney: number | string;
  countOrder: number;
  countMoney: number | string;
};

export function mapDashboardStats(payload?: Record<string, any>): DashboardStats {
  return {
    todayOrder: Number(payload?.todayOrder ?? 0),
    todaySuccessOrder: Number(payload?.todaySuccessOrder ?? 0),
    todayCloseOrder: Number(payload?.todayCloseOrder ?? 0),
    todayMoney: payload?.todayMoney ?? 0,
    countOrder: Number(payload?.countOrder ?? 0),
    countMoney: payload?.countMoney ?? 0
  };
}

export function normalizePagedList<T = Record<string, any>>(payload?: {
  data?: unknown;
  count?: unknown;
}): { items: T[]; total: number } {
  const items = Array.isArray(payload?.data) ? (payload?.data as T[]) : [];
  const rawTotal = Number(payload?.count);
  const total = Number.isFinite(rawTotal) && rawTotal >= 0 ? rawTotal : items.length;

  return { items, total };
}

export function formatUnixTimestamp(value?: string | number | null): string {
  const timestamp = Number(value ?? 0);
  if (!timestamp) return "无";

  const date = new Date(timestamp * 1000);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, "0");
  const day = String(date.getDate()).padStart(2, "0");
  const hour = String(date.getHours()).padStart(2, "0");
  const minute = String(date.getMinutes()).padStart(2, "0");
  const second = String(date.getSeconds()).padStart(2, "0");

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
