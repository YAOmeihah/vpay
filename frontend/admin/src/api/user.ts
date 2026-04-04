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

const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));

export const getLogin = (data?: { user: string; pass: string }) => {
  return adminLogin(data).then(async loginRes => {
    if (loginRes.code !== 1) {
      return { success: false, data: null } as any;
    }

    for (let attempt = 0; attempt < 5; attempt++) {
      const profile = await getAdminProfile();
      if (profile.code === 1) {
        return {
          success: true,
          data: profile.data
        };
      }

      await delay(150);
    }

    return { success: false, data: null } as any;
  });
};
