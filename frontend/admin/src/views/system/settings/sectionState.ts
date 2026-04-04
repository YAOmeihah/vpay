export type SecuritySection = {
  user: string;
  newPassword: string;
  confirmPassword: string;
};

export type PaymentSection = {
  notifyUrl: string;
  returnUrl: string;
  key: string;
  close: string;
  payQf: string;
};

export type QrcodeSection = {
  wxpay: string;
  zfbpay: string;
};

export type EpaySection = {
  epay_enabled: string;
  epay_pid: string;
  epay_name: string;
  epay_key: string;
  epay_private_key: string;
  epay_public_key: string;
};

export type SettingsSections = {
  security: SecuritySection;
  payment: PaymentSection;
  qrcode: QrcodeSection;
  epay: EpaySection;
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
      close: "",
      payQf: "1"
    },
    qrcode: {
      wxpay: "",
      zfbpay: ""
    },
    epay: {
      epay_enabled: "0",
      epay_pid: "",
      epay_name: "",
      epay_key: "",
      epay_private_key: "",
      epay_public_key: ""
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
  sections.payment.close = String(payload.close ?? "");
  sections.payment.payQf = String(payload.payQf ?? "1");

  sections.qrcode.wxpay = String(payload.wxpay ?? "");
  sections.qrcode.zfbpay = String(payload.zfbpay ?? "");

  sections.epay.epay_enabled = String(payload.epay_enabled ?? "0");
  sections.epay.epay_pid = String(payload.epay_pid ?? "");
  sections.epay.epay_name = String(payload.epay_name ?? "");
  sections.epay.epay_key = "";
  sections.epay.epay_private_key = "";
  sections.epay.epay_public_key = String(payload.epay_public_key ?? "");
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
    close: section.close,
    payQf: section.payQf
  };
}

export function buildQrcodePayload(
  section: QrcodeSection
) {
  return {
    wxpay: section.wxpay,
    zfbpay: section.zfbpay
  };
}

export function buildEpayPayload(
  section: EpaySection
) {
  const payload: Record<string, string> = {
    epay_enabled: section.epay_enabled,
    epay_pid: section.epay_pid,
    epay_name: section.epay_name,
    epay_public_key: section.epay_public_key
  };

  if (section.epay_key.trim() !== "") {
    payload.epay_key = section.epay_key;
  }
  if (section.epay_private_key.trim() !== "") {
    payload.epay_private_key = section.epay_private_key;
  }

  return payload;
}
