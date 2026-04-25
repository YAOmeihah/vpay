export type UpdateStatus =
  | "update_available"
  | "up_to_date"
  | "check_failed"
  | "ahead"
  | string;

export type PreflightCheck = {
  label: string;
  ok: boolean;
  message: string;
};

export type PreflightState = {
  checks: PreflightCheck[];
  ok: boolean;
};

export function canStartUpdate(
  status: UpdateStatus,
  preflightOk: boolean,
  updating: boolean
): boolean {
  return status === "update_available" && preflightOk && !updating;
}

export function updateBadgeType(
  status: UpdateStatus
): "success" | "warning" | "danger" | "info" {
  if (status === "up_to_date") return "success";
  if (status === "update_available") return "warning";
  if (status === "check_failed") return "danger";
  return "info";
}

export function normalizePreflightChecks(input: unknown): PreflightCheck[] {
  if (!Array.isArray(input)) return [];

  return input.map(item => {
    const row = item as Partial<PreflightCheck>;

    return {
      label: String(row.label ?? ""),
      ok: row.ok === true,
      message: String(row.message ?? "")
    };
  });
}

export function clearedPreflightState(): PreflightState {
  return {
    checks: [],
    ok: false
  };
}
