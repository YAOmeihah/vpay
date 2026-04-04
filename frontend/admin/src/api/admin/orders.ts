import { http } from "@/utils/http";

export const getOrders = (params: {
  page: number;
  limit: number;
  type?: string;
  state?: string;
}) =>
  http.request<{ code: number; msg: string; data: any[]; count: number }>(
    "get",
    "/admin/index/getOrders",
    { params }
  );

export const deleteOrder = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/delOrder",
    { data }
  );

export const repairOrder = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: string | null }>(
    "post",
    "/admin/index/setBd",
    { data }
  );

export const deleteExpiredOrders = () =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/delGqOrder"
  );

export const deleteOldOrders = () =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/delLastOrder"
  );
