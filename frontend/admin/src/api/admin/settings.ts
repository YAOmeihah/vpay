import { http } from "@/utils/http";

export const getSettings = () =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/getSettings"
  );

export const saveSettings = (data: Record<string, string>) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/saveSetting",
    { data }
  );

export const generateMaintenanceToken = () =>
  http.request<{ code: number; msg: string; data: { token: string } }>(
    "post",
    "/admin/index/generateMaintenanceToken"
  );

export const testMaintenanceNotification = () =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/testMaintenanceNotification"
  );
