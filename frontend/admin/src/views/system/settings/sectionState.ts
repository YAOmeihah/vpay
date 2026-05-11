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

export type MaintenanceSection = {
  enabled: string;
  token: string;
  allowedIps: string;
  terminalOfflineTask: string;
  expiredOrderCleanupTask: string;
  lastRunAt: string;
  lastRunResult: string;
  telegramEnabled: string;
  telegramBotToken: string;
  telegramChatId: string;
  notifyTerminalOffline: string;
  notifyTerminalRecovered: string;
  notifyExpiredOrderCleanup: string;
  notifyMaintenanceException: string;
  notifyPaymentSuccess: string;
  notifyPaymentCallbackStatus: string;
};

export type SettingsSections = {
  security: SecuritySection;
  payment: PaymentSection;
  maintenance: MaintenanceSection;
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
    },
    maintenance: {
      enabled: "0",
      token: "",
      allowedIps: "",
      terminalOfflineTask: "1",
      expiredOrderCleanupTask: "1",
      lastRunAt: "",
      lastRunResult: "",
      telegramEnabled: "0",
      telegramBotToken: "",
      telegramChatId: "",
      notifyTerminalOffline: "1",
      notifyTerminalRecovered: "1",
      notifyExpiredOrderCleanup: "1",
      notifyMaintenanceException: "1",
      notifyPaymentSuccess: "1",
      notifyPaymentCallbackStatus: "1"
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
  sections.payment.allocationStrategy =
    payload.allocationStrategy === "round_robin"
      ? "round_robin"
      : "fixed_priority";

  sections.maintenance.enabled = String(payload.maintenance_enabled ?? "0");
  sections.maintenance.token = String(payload.maintenance_token ?? "");
  sections.maintenance.allowedIps = String(
    payload.maintenance_allowed_ips ?? ""
  );
  sections.maintenance.terminalOfflineTask = String(
    payload.maintenance_task_terminal_offline_check ?? "1"
  );
  sections.maintenance.expiredOrderCleanupTask = String(
    payload.maintenance_task_expired_order_cleanup ?? "1"
  );
  sections.maintenance.lastRunAt = String(
    payload.maintenance_last_run_at ?? ""
  );
  sections.maintenance.lastRunResult = String(
    payload.maintenance_last_run_result ?? ""
  );
  sections.maintenance.telegramEnabled = String(
    payload.notify_telegram_enabled ?? "0"
  );
  sections.maintenance.telegramBotToken = String(
    payload.notify_telegram_bot_token ?? ""
  );
  sections.maintenance.telegramChatId = String(
    payload.notify_telegram_chat_id ?? ""
  );
  sections.maintenance.notifyTerminalOffline = String(
    payload.notify_event_terminal_offline ?? "1"
  );
  sections.maintenance.notifyTerminalRecovered = String(
    payload.notify_event_terminal_recovered ?? "1"
  );
  sections.maintenance.notifyExpiredOrderCleanup = String(
    payload.notify_event_expired_order_cleanup ?? "1"
  );
  sections.maintenance.notifyMaintenanceException = String(
    payload.notify_event_maintenance_exception ?? "1"
  );
  sections.maintenance.notifyPaymentSuccess = String(
    payload.notify_event_payment_success ?? "1"
  );
  sections.maintenance.notifyPaymentCallbackStatus = String(
    payload.notify_payment_success_callback_status ?? "1"
  );
}

export function buildSecurityPayload(section: SecuritySection) {
  const payload: Record<string, string> = {
    user: section.user
  };

  if (section.newPassword.trim() !== "") {
    payload.pass = section.newPassword;
  }

  return payload;
}

export function buildPaymentPayload(section: PaymentSection) {
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

export function buildMaintenancePayload(section: MaintenanceSection) {
  return {
    maintenance_enabled: section.enabled,
    maintenance_token: section.token,
    maintenance_allowed_ips: section.allowedIps,
    maintenance_task_terminal_offline_check: section.terminalOfflineTask,
    maintenance_task_expired_order_cleanup: section.expiredOrderCleanupTask,
    notify_telegram_enabled: section.telegramEnabled,
    notify_telegram_bot_token: section.telegramBotToken,
    notify_telegram_chat_id: section.telegramChatId,
    notify_event_terminal_offline: section.notifyTerminalOffline,
    notify_event_terminal_recovered: section.notifyTerminalRecovered,
    notify_event_expired_order_cleanup: section.notifyExpiredOrderCleanup,
    notify_event_maintenance_exception: section.notifyMaintenanceException,
    notify_event_payment_success: section.notifyPaymentSuccess,
    notify_payment_success_callback_status: section.notifyPaymentCallbackStatus
  };
}
