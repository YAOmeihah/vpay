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

export const generateRsaKeys = () =>
  http.request<{
    code: number;
    msg: string;
    data: { private_key: string; public_key: string };
  }>("post", "/admin/index/generateRsaKeys");
