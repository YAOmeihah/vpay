export type OrderDateRangeValue = [Date, Date] | [];

export type OrderFilters = {
  type: string;
  state: string;
  keyword: string;
  amount: string;
  dateRange: OrderDateRangeValue;
  terminalId: string;
  channelId: string;
  page: number;
};

export type OrderQueryParams = {
  page: number;
  limit: number;
  type?: string;
  state?: string;
  keyword?: string;
  amount?: string;
  createStart?: number;
  createEnd?: number;
  terminalId?: number;
  channelId?: number;
};

export function createDefaultOrderFilters(): OrderFilters {
  return {
    type: "",
    state: "",
    keyword: "",
    amount: "",
    dateRange: [],
    terminalId: "",
    channelId: "",
    page: 1
  };
}

function trimValue(value: unknown): string {
  return String(value ?? "").trim();
}

function toPositiveInteger(value: unknown): number | undefined {
  const normalized = trimValue(value);
  if (!/^[1-9]\d*$/.test(normalized)) return undefined;
  return Number(normalized);
}

function toUnixSeconds(value: unknown): number | undefined {
  const date = value instanceof Date ? value : new Date(value as any);
  const timestamp = date.getTime();
  if (Number.isNaN(timestamp)) return undefined;
  return Math.floor(timestamp / 1000);
}

export function buildOrderQueryParams(
  filters: OrderFilters,
  limit: number
): OrderQueryParams {
  const params: OrderQueryParams = {
    page: filters.page,
    limit
  };

  const type = trimValue(filters.type);
  const state = trimValue(filters.state);
  const keyword = trimValue(filters.keyword);
  const amount = trimValue(filters.amount);
  const terminalId = toPositiveInteger(filters.terminalId);
  const channelId = toPositiveInteger(filters.channelId);
  const [start, end] = filters.dateRange;
  const createStart = toUnixSeconds(start);
  const createEnd = toUnixSeconds(end);

  if (type !== "") params.type = type;
  if (state !== "") params.state = state;
  if (keyword !== "") params.keyword = keyword;
  if (/^[0-9]+(?:\.[0-9]+)?$/.test(amount)) params.amount = amount;
  if (createStart !== undefined) params.createStart = createStart;
  if (createEnd !== undefined) params.createEnd = createEnd;
  if (terminalId !== undefined) params.terminalId = terminalId;
  if (channelId !== undefined) params.channelId = channelId;

  return params;
}
