export type SecuritySection = {
  user: string;
  newPassword: string;
  confirmPassword: string;
};

export type PaymentSection = {
  notifyUrl: string;
  returnUrl: string;
  key: string;
  notifySslVerify: string;
  close: string;
  payQf: string;
  allocationStrategy: "fixed_priority" | "round_robin";
};

export type SettingsSections = {
  security: SecuritySection;
  payment: PaymentSection;
};

export function createSettingsSections(): SettingsSections {
  return {
    security: {
      user: "",
      newPassword: "",
      confirmPassword: ""
    },
    payment: {
      notifyUrl: "",
      returnUrl: "",
      key: "",
      notifySslVerify: "1",
      close: "",
      payQf: "1",
      allocationStrategy: "fixed_priority"
    }
  };
}

export function hydrateSettingsSections(
  sections: SettingsSections,
  payload: Record<string, any>
) {
  sections.security.user = String(payload.user ?? "");
  sections.security.newPassword = "";
  sections.security.confirmPassword = "";

  sections.payment.notifyUrl = String(payload.notifyUrl ?? "");
  sections.payment.returnUrl = String(payload.returnUrl ?? "");
  sections.payment.key = String(payload.key ?? "");
  sections.payment.notifySslVerify = String(payload.notify_ssl_verify ?? "1");
  sections.payment.close = String(payload.close ?? "");
  sections.payment.payQf = String(payload.payQf ?? "1");
  sections.payment.allocationStrategy = (payload.allocationStrategy === "round_robin"
    ? "round_robin"
    : "fixed_priority");
}

export function buildSecurityPayload(
  section: SecuritySection
) {
  const payload: Record<string, string> = {
    user: section.user
  };

  if (section.newPassword.trim() !== "") {
    payload.pass = section.newPassword;
  }

  return payload;
}

export function buildPaymentPayload(
  section: PaymentSection
) {
  return {
    notifyUrl: section.notifyUrl,
    returnUrl: section.returnUrl,
    key: section.key,
    notify_ssl_verify: section.notifySslVerify,
    close: section.close,
    payQf: section.payQf,
    allocationStrategy: section.allocationStrategy
  };
}
