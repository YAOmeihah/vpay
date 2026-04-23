export type PaymentSlotType = 1 | 2;

export type PaymentSlotInput = {
  id?: number | string | null;
  terminal_id?: number | string | null;
  type: PaymentSlotType;
  channel_name?: string | null;
  status?: string | null;
  pay_url?: string | null;
  exists?: boolean | null;
  slot_key?: string | null;
  slot_label?: string | null;
};

export type PaymentSlot = {
  id?: number;
  terminalId: number;
  type: PaymentSlotType;
  slotKey: "wechat" | "alipay";
  slotLabel: "微信" | "支付宝";
  channelName: string;
  status: "enabled" | "disabled";
  payUrl: string;
  exists: boolean;
};

const SLOT_META = {
  1: {
    slotKey: "wechat" as const,
    slotLabel: "微信" as const,
    defaultChannelName: "微信收款"
  },
  2: {
    slotKey: "alipay" as const,
    slotLabel: "支付宝" as const,
    defaultChannelName: "支付宝收款"
  }
};

export function buildPaymentSlots(
  channels: PaymentSlotInput[],
  terminalId: number
): PaymentSlot[] {
  const indexed = new Map<number, PaymentSlotInput>();
  for (const channel of channels) {
    indexed.set(Number(channel.type), channel);
  }

  return ([1, 2] as const).map(type => {
    const meta = SLOT_META[type];
    const current = indexed.get(type);

    return {
      id:
        current?.id === null || current?.id === undefined
          ? undefined
          : Number(current.id),
      terminalId:
        current?.terminal_id === null || current?.terminal_id === undefined
          ? terminalId
          : Number(current.terminal_id),
      type,
      slotKey:
        current?.slot_key === "wechat" || current?.slot_key === "alipay"
          ? current.slot_key
          : meta.slotKey,
      slotLabel:
        current?.slot_label === "微信" || current?.slot_label === "支付宝"
          ? current.slot_label
          : meta.slotLabel,
      channelName:
        String(current?.channel_name ?? "").trim() || meta.defaultChannelName,
      status: current?.status === "enabled" ? "enabled" : "disabled",
      payUrl: String(current?.pay_url ?? ""),
      exists: Boolean(current?.exists ?? current?.id)
    };
  });
}

export function paymentTypeLabel(type: PaymentSlotType): "微信" | "支付宝" {
  return SLOT_META[type].slotLabel;
}

export function defaultChannelName(type: PaymentSlotType): string {
  return SLOT_META[type].defaultChannelName;
}
