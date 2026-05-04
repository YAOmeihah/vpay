const csrfTokenKey = "vpay-admin-csrf-token";

export const getAdminCsrfToken = (): string => {
  return window.localStorage.getItem(csrfTokenKey) ?? "";
};

export const setAdminCsrfToken = (token?: string | null): void => {
  const value = String(token ?? "").trim();
  if (value === "") {
    window.localStorage.removeItem(csrfTokenKey);
    return;
  }

  window.localStorage.setItem(csrfTokenKey, value);
};
