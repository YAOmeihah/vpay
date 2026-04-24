import { adminLogin, getAdminProfile } from "./admin/auth";

type LoginProfile = {
  avatar: string;
  username: string;
  nickname: string;
  roles: string[];
  permissions: string[];
};

export type UserResult =
  | {
      success: true;
      msg?: string;
      data: LoginProfile;
    }
  | {
      success: false;
      msg?: string;
      data: null;
    };

const delay = (ms: number) => new Promise(resolve => setTimeout(resolve, ms));

export const getLogin = (data?: {
  user: string;
  pass: string;
}): Promise<UserResult> => {
  return adminLogin(data).then(async loginRes => {
    if (loginRes.code !== 1) {
      return { success: false, msg: loginRes.msg, data: null };
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

    return { success: false, msg: "登录状态确认失败", data: null };
  });
};
