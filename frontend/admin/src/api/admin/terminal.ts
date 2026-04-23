import { http } from "@/utils/http";

export type TerminalPayload = {
  id?: number;
  terminalCode: string;
  terminalName: string;
  status?: string;
  online_state?: string;
  monitorKey?: string;
};

export const getTerminals = (params?: Record<string, any>) =>
  http.request<{
    code: number;
    msg: string;
    data: { data: any[]; count: number; page: number; limit: number };
  }>("get", "/admin/index/getTerminals", { params });

export const saveTerminal = (data: TerminalPayload) =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/saveTerminal",
    { data }
  );

export const toggleTerminal = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/toggleTerminal",
    { data }
  );

export const resetTerminalKey = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: { monitorKey: string } }>(
    "post",
    "/admin/index/resetTerminalKey",
    { data }
  );
