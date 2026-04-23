import { http } from "@/utils/http";

export type ChannelPayload = {
  id?: number;
  terminalId: number;
  type: 1 | 2;
  channelName: string;
  status?: string;
  payUrl?: string;
  priority?: number;
};

export const getTerminalChannels = (params: { terminalId: number }) =>
  http.request<{ code: number; msg: string; data: any[] }>(
    "get",
    "/admin/index/getTerminalChannels",
    { params }
  );

export const saveTerminalChannel = (data: ChannelPayload) =>
  http.request<{ code: number; msg: string; data: any }>(
    "post",
    "/admin/index/saveTerminalChannel",
    { data }
  );

export const toggleTerminalChannel = (data: { id: number }) =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/toggleTerminalChannel",
    { data }
  );
