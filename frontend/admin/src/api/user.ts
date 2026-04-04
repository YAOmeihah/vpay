import { http } from "@/utils/http";
import { adminLogin, getAdminProfile } from "./admin/auth";

export type UserResult = {
  success: boolean;
  data: {
    avatar: string;
    username: string;
    nickname: string;
    roles: string[];
    permissions: string[];
  };
};

export const getLogin = (data?: { user: string; pass: string }) => {
  return adminLogin(data).then(async loginRes => {
    if (loginRes.code !== 1) {
      return { success: false, data: null } as any;
    }
    const profile = await getAdminProfile();
    return {
      success: profile.code === 1,
      data: profile.data
    };
  });
};
