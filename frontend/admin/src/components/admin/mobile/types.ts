export type AdminMobileMoreActionType =
  | "primary"
  | "success"
  | "warning"
  | "danger"
  | "info";

export type AdminMobileMoreAction = {
  label: string;
  command: string;
  type?: AdminMobileMoreActionType;
  disabled?: boolean;
  loading?: boolean;
};
