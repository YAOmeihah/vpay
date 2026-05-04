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

const resolveErrorMessage = (error: unknown): string => {
  if (error instanceof Error) {
    return error.message;
  }

  if (typeof error === "object" && error !== null && "msg" in error) {
    return String((error as { msg?: unknown }).msg || "");
  }

  return String(error || "");
};

const profileFailureMessage = (detail: string): string => {
  const reason = detail.trim();

  return reason
    ? `登录状态确认失败：${reason}。请检查浏览器 Cookie 是否可用，或确认 COOKIE_SECURE 与当前访问协议匹配。`
    : "登录状态确认失败，请检查浏览器 Cookie 是否可用。";
};

export const getLogin = (data?: {
  user: string;
  pass: string;
}): Promise<UserResult> => {
  return adminLogin(data).then(async loginRes => {
    if (loginRes.code !== 1) {
      return { success: false, msg: loginRes.msg, data: null };
    }

    let lastProfileError = "";
    for (let attempt = 0; attempt < 5; attempt++) {
      try {
        const profile = await getAdminProfile();
        if (profile.code === 1) {
          return {
            success: true,
            data: profile.data
          };
        }

        lastProfileError = profile.msg || "";
      } catch (error) {
        lastProfileError = resolveErrorMessage(error);
      }

      await delay(150);
    }

    return {
      success: false,
      msg: profileFailureMessage(lastProfileError),
      data: null
    };
  });
};
