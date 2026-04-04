import { defineStore } from "pinia";
import {
  type userType,
  store,
  router,
  resetRouter,
  routerArrays,
  storageLocal
} from "../utils";
import { type UserResult, getLogin } from "@/api/user";
import { adminLogout } from "@/api/admin/auth";
import { useMultiTagsStoreHook } from "./multiTags";
import { userKey } from "@/utils/auth";

export const useUserStore = defineStore("pure-user", {
  state: (): userType => ({
    // 头像
    avatar: (storageLocal().getItem(userKey) as any)?.avatar ?? "",
    // 用户名
    username: (storageLocal().getItem(userKey) as any)?.username ?? "",
    // 昵称
    nickname: (storageLocal().getItem(userKey) as any)?.nickname ?? "",
    // 页面级别权限
    roles: (storageLocal().getItem(userKey) as any)?.roles ?? [],
    // 按钮级别权限
    permissions: (storageLocal().getItem(userKey) as any)?.permissions ?? [],
    // 是否勾选了登录页的免登录
    isRemembered: false,
    // 登录页的免登录存储几天，默认7天
    loginDay: 7
  }),
  actions: {
    /** 存储头像 */
    SET_AVATAR(avatar: string) {
      this.avatar = avatar;
    },
    /** 存储用户名 */
    SET_USERNAME(username: string) {
      this.username = username;
    },
    /** 存储昵称 */
    SET_NICKNAME(nickname: string) {
      this.nickname = nickname;
    },
    /** 存储角色 */
    SET_ROLES(roles: Array<string>) {
      this.roles = roles;
    },
    /** 存储按钮级别权限 */
    SET_PERMS(permissions: Array<string>) {
      this.permissions = permissions;
    },
    /** 存储是否勾选了登录页的免登录 */
    SET_ISREMEMBERED(bool: boolean) {
      this.isRemembered = bool;
    },
    /** 设置登录页的免登录存储几天 */
    SET_LOGINDAY(value: number) {
      this.loginDay = Number(value);
    },
    /** 登入 */
    async loginByUsername(data) {
      return new Promise<UserResult>((resolve, reject) => {
        getLogin(data)
          .then(result => {
            if (result?.success) {
              this.SET_AVATAR(result.data.avatar);
              this.SET_USERNAME(result.data.username);
              this.SET_NICKNAME(result.data.nickname);
              this.SET_ROLES(result.data.roles);
              this.SET_PERMS(result.data.permissions);
              storageLocal().setItem(userKey, result.data);
            }
            resolve(result);
          })
          .catch(error => {
            reject(error);
          });
      });
    },
    /** 登出 */
    async logOut() {
      await adminLogout().catch(() => undefined);
      this.username = "";
      this.roles = [];
      this.permissions = [];
      storageLocal().removeItem(userKey);
      useMultiTagsStoreHook().handleTags("equal", [...routerArrays]);
      resetRouter();
      router.push("/login");
    }
  }
});

export function useUserStoreHook() {
  return useUserStore(store);
}
