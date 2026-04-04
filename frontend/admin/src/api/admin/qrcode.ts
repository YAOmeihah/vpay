import { http } from "@/utils/http";

export const getPayQrcodes = (params: {
  type: 1 | 2;
  page: number;
  limit: number;
}) =>
  http.request<{ code: number; msg: string; data: any[]; count: number }>(
    "get",
    "/admin/index/getPayQrcodes",
    { params }
  );

export const addPayQrcode = (data: {
  type: 1 | 2;
  pay_url: string;
  price: string;
}) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/addPayQrcode",
    { data }
  );

export const deletePayQrcode = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/delPayQrcode",
    { data }
  );
