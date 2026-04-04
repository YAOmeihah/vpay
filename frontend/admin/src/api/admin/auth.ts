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
  };
};

export const adminLogin = (data: { user: string; pass: string }) =>
  http.request<{ code: number; msg: string; data: null }>("post", "/login", {
    data
  }, {
    skipUnauthorizedLogout: true
  });

export const getAdminProfile = () =>
  http.request<AdminProfile>("get", "/admin/index/profile");

export const adminLogout = () =>
  http.request<{ code: number; msg: string; data: null }>(
    "post",
    "/admin/index/logout",
    {},
    {
      skipUnauthorizedLogout: true
    }
  );
