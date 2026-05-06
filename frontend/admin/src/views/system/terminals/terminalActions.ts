type TerminalLike = {
  id?: string | number;
  terminal_name?: string | number;
  terminal_code?: string | number;
};

function formatTerminalIdentity(row: TerminalLike): string {
  const name = String(row.terminal_name ?? "").trim();
  const code = String(row.terminal_code ?? "").trim();
  const id = String(row.id ?? "").trim();

  if (name !== "" && code !== "") {
    return `${name} / ${code}`;
  }

  if (name !== "") {
    return name;
  }

  if (code !== "") {
    return code;
  }

  return id !== "" ? `ID ${id}` : "当前终端";
}

export function buildDeleteTerminalConfirmMessage(row: TerminalLike): string {
  return `确认删除终端（${formatTerminalIdentity(row)}）？删除后会同时清理该终端下的支付通道配置；若存在未支付订单，后端会阻止删除。`;
}

export function buildResetTerminalKeyConfirmMessage(row: TerminalLike): string {
  return `确认重置终端（${formatTerminalIdentity(row)}）的监控密钥？重置后，已有监控绑定链接或二维码需要重新配置。`;
}
