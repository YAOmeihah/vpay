import { http } from "@/utils/http";

export type AdminProfile = {
  code: number;
  msg: string;
  data: {
    avatar: string;
    username: string;
    nickname: string;
    roles: string[];
    permissions: string[];
    csrfToken?: string;
  };
};

type AdminLogin = {
  code: number;
  msg: string;
  data: {
    csrfToken?: string;
  } | null;
};

export const adminLogin = (data: { user: string; pass: string }) =>
  http.request<AdminLogin>(
    "post",
    "/login",
    {
      data
    },
    {
      skipUnauthorizedLogout: true
    }
  );

export const getAdminProfile = () =>
  http.request<AdminProfile>(
    "get",
    "/admin/index/profile",
    {},
    {
      skipUnauthorizedLogout: true
    }
  );

export const adminLogout = () =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/logout",
    {},
    {
      skipUnauthorizedLogout: true
    }
  );
