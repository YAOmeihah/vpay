import { http } from "@/utils/http";

export type UpdateAsset = {
  name: string;
  download_url: string;
  size: number;
};

export type UpdateCheckResponse = {
  status: string;
  message: string;
  current_version?: string;
  latest_version?: string;
  tag_name?: string;
  release_url?: string;
  published_at?: string;
  body?: string;
  assets?: Record<string, UpdateAsset>;
};

export type UpdateApiResponse<T> = {
  code: number;
  msg: string;
  data: T;
};

export type UpdatePreflightResponse = {
  can_update: boolean;
  checks: Array<{ label: string; ok: boolean; message?: string }>;
};

export const checkUpdate = () =>
  http.request<UpdateApiResponse<UpdateCheckResponse>>(
    "get",
    "/admin/index/checkUpdate"
  );

export const preflightUpdate = (release: UpdateCheckResponse) =>
  http.request<UpdateApiResponse<UpdatePreflightResponse>>(
    "post",
    "/admin/index/preflightUpdate",
    { data: { release } }
  );

export const startUpdate = (release: UpdateCheckResponse) =>
  http.request<UpdateApiResponse<Record<string, unknown>>>(
    "post",
    "/admin/index/startUpdate",
    { data: { release } }
  );

export const getUpdateStatus = () =>
  http.request<UpdateApiResponse<Record<string, unknown>>>(
    "get",
    "/admin/index/getUpdateStatus"
  );

export const getUpdateRecovery = () =>
  http.request<UpdateApiResponse<Record<string, unknown>>>(
    "get",
    "/admin/index/getUpdateRecovery"
  );
