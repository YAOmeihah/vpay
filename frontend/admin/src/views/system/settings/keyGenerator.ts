function toHex(bytes: Uint8Array): string {
  return Array.from(bytes, value => value.toString(16).padStart(2, "0")).join("");
}

export function generateSettingsKey(): string {
  if (globalThis.crypto?.getRandomValues) {
    return toHex(globalThis.crypto.getRandomValues(new Uint8Array(16)));
  }

  return Array.from({ length: 32 }, () =>
    Math.floor(Math.random() * 16).toString(16)
  ).join("");
}
