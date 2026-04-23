import {
  buildMonitorConfigUrl,
  buildQrcodePreviewUrl,
  formatUnixTimestamp
} from "@/utils/adminLegacy";

export type MonitorOverviewInput = {
  id?: number | string | null;
  terminal_code?: string | null;
  terminal_name?: string | null;
  dispatch_priority?: number | string | null;
  status?: string | null;
  online_state?: string | null;
  monitor_key?: string | null;
  last_heartbeat_at?: number | string | null;
  last_paid_at?: number | string | null;
};

export type MonitorOverviewCard = {
  id: number;
  terminalCode: string;
  terminalName: string;
  dispatchPriority: number;
  terminalStatus: "enabled" | "disabled";
  onlineState: "online" | "offline";
  statusText: string;
  statusType: "success" | "warning" | "danger" | "info";
  lastHeartbeatText: string;
  lastPaidText: string;
  configUrl: string;
  qrcodeUrl: string;
};

export function buildMonitorOverviewCards(
  terminals: MonitorOverviewInput[],
  host: string
): MonitorOverviewCard[] {
  return [...terminals]
    .sort((left, right) => {
      const priorityDiff =
        normalizePositiveInteger(left.dispatch_priority, 100) -
        normalizePositiveInteger(right.dispatch_priority, 100);

      if (priorityDiff !== 0) {
        return priorityDiff;
      }

      return normalizePositiveInteger(left.id, 0) - normalizePositiveInteger(right.id, 0);
    })
    .map(item => {
      const terminalStatus = item.status === "enabled" ? "enabled" : "disabled";
      const onlineState = item.online_state === "online" ? "online" : "offline";
      const configUrl = buildMonitorConfigUrl(host, item.terminal_code, item.monitor_key);

      return {
        id: normalizePositiveInteger(item.id, 0),
        terminalCode: String(item.terminal_code ?? "").trim(),
        terminalName:
          String(item.terminal_name ?? "").trim() ||
          `终端 #${normalizePositiveInteger(item.id, 0)}`,
        dispatchPriority: normalizePositiveInteger(item.dispatch_priority, 100),
        terminalStatus,
        onlineState,
        statusText: resolveStatusText(terminalStatus, onlineState, configUrl),
        statusType: resolveStatusType(terminalStatus, onlineState, configUrl),
        lastHeartbeatText: formatUnixTimestamp(item.last_heartbeat_at),
        lastPaidText: formatUnixTimestamp(item.last_paid_at),
        configUrl,
        qrcodeUrl: buildQrcodePreviewUrl(configUrl)
      };
    });
}

function normalizePositiveInteger(value: unknown, fallback: number): number {
  const normalized = Number(value);
  return Number.isFinite(normalized) && normalized >= 0 ? normalized : fallback;
}

function resolveStatusText(
  terminalStatus: "enabled" | "disabled",
  onlineState: "online" | "offline",
  configUrl: string
): string {
  if (terminalStatus === "disabled") {
    return "终端已停用";
  }

  if (!configUrl) {
    return "未生成绑定地址";
  }

  if (onlineState === "online") {
    return "运行正常";
  }

  return "监控离线";
}

function resolveStatusType(
  terminalStatus: "enabled" | "disabled",
  onlineState: "online" | "offline",
  configUrl: string
): "success" | "warning" | "danger" | "info" {
  if (terminalStatus === "disabled") {
    return "info";
  }

  if (!configUrl) {
    return "warning";
  }

  if (onlineState === "online") {
    return "success";
  }

  return "danger";
}
