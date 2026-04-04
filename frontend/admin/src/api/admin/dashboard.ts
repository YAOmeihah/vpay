import { http } from "@/utils/http";

export const getDashboardStats = () =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/getMain"
  );
