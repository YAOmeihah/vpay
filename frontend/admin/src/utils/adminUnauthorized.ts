import { storageLocal } from "@pureadmin/utils";
import { message } from "@/utils/message";
import { userKey, type DataInfo } from "@/utils/auth";

export const ADMIN_UNAUTHORIZED_CODE = 40101;
export const SESSION_EXPIRED_MESSAGE = "登录已过期，请重新登录";

let unauthorizedMessageVisible = false;

export function isAdminUnauthorized(
  status: number | undefined,
  code: unknown
): boolean {
  return status === 401 || Number(code) === ADMIN_UNAUTHORIZED_CODE;
}

export function hasStoredAdminUser(): boolean {
  return Boolean(storageLocal().getItem<DataInfo<number>>(userKey));
}

export function notifyAdminSessionExpired(): void {
  if (unauthorizedMessageVisible) {
    return;
  }

  unauthorizedMessageVisible = true;
  message(SESSION_EXPIRED_MESSAGE, {
    type: "error",
    grouping: true,
    onClose: () => {
      unauthorizedMessageVisible = false;
    }
  });
}
