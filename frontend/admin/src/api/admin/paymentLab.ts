import { http } from "@/utils/http";

export type PaymentLabOrder = {
  payId: string;
  orderId: string;
  payType: number;
  price: string | number;
  reallyPrice: string | number;
  payUrl: string;
  isAuto: number;
  state: number;
  stateText: string;
  timeOut?: string | number;
  date?: number;
};

export type PaymentLabAssignment = {
  terminalId: number;
  channelId: number;
  terminalName: string;
  channelName: string;
  assignStatus: string;
  assignReason: string;
};

export type PaymentLabCallback = {
  kind: string;
  payId: string;
  orderId: string;
  payload: Record<string, string>;
  ip: string;
  receivedAt: number;
};

export type PaymentLabResult = {
  request?: Record<string, string | number>;
  order: PaymentLabOrder;
  assignment: PaymentLabAssignment;
  payPageUrl?: string;
  callback?: PaymentLabCallback | null;
  returnUrl?: string;
};

export type PaymentLabCreatePayload = {
  type: number;
  price: string;
  payId?: string;
  param?: string;
  notifyUrl?: string;
  returnUrl?: string;
};

export const createPaymentTestOrder = (data: PaymentLabCreatePayload) =>
  http.request<{ code: number; msg: string; data: PaymentLabResult }>(
    "post",
    "/admin/index/createPaymentTestOrder",
    { data }
  );

export const getPaymentTestOrder = (params: { orderId: string }) =>
  http.request<{ code: number; msg: string; data: PaymentLabResult }>(
    "get",
    "/admin/index/getPaymentTestOrder",
    { params }
  );

export const getPaymentTestCallback = (params: {
  orderId?: string;
  payId?: string;
}) =>
  http.request<{ code: number; msg: string; data: PaymentLabCallback | null }>(
    "get",
    "/admin/index/getPaymentTestCallback",
    { params }
  );
