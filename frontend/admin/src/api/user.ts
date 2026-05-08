import { adminLogin, getAdminProfile } from "./admin/auth";
import { setAdminCsrfToken } from "@/utils/csrf";

type LoginProfile = {
  avatar: string;
  username: string;
  nickname: string;
  roles: string[];
  permissions: string[];
  csrfToken?: string;
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

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null;

const errorPayload = (error: unknown): Record<string, unknown> | null => {
  if (!isRecord(error)) {
    return null;
  }

  const response = error.response;
  if (isRecord(response) && isRecord(response.data)) {
    return response.data;
  }

  return error;
};

const resolveErrorMessage = (error: unknown): string => {
  const payload = errorPayload(error);
  const data = isRecord(payload?.data) ? payload.data : null;
  const installUrl =
    typeof data?.installUrl === "string" ? data.installUrl.trim() : "";
  const payloadMessage = String(
    payload?.msg ?? payload?.message ?? ""
  ).trim();

  if (installUrl !== "") {
    return `${payloadMessage || "系统需要安装或升级"}，请前往 ${installUrl} 完成安装或升级。`;
  }

  if (payloadMessage !== "") {
    return payloadMessage;
  }

  if (error instanceof Error) {
    return error.message;
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
  return adminLogin(data)
    .then(async (loginRes): Promise<UserResult> => {
      if (loginRes.code !== 1) {
        return { success: false, msg: loginRes.msg, data: null };
      }
      setAdminCsrfToken(loginRes.data?.csrfToken);

      let lastProfileError = "";
      for (let attempt = 0; attempt < 5; attempt++) {
        try {
          const profile = await getAdminProfile();
          if (profile.code === 1) {
            setAdminCsrfToken(profile.data?.csrfToken);
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
    })
    .catch((error): UserResult => ({
      success: false,
      msg: resolveErrorMessage(error),
      data: null
    }));
};
