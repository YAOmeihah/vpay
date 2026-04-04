import type { Router } from "vue-router";

const CHUNK_RELOAD_MARKER = "vpay:chunk-reload-marker";
const CHUNK_ERROR_PATTERN =
  /Failed to fetch dynamically imported module|Importing a module script failed|Loading chunk [\w-]+ failed/i;

type StorageLike = Pick<Storage, "getItem" | "setItem" | "removeItem">;

export function isRecoverableChunkLoadError(error: unknown): boolean {
  const text =
    typeof error === "string"
      ? error
      : error instanceof Error
        ? `${error.name} ${error.message}`
        : String(error ?? "");

  return CHUNK_ERROR_PATTERN.test(text);
}

export function shouldReloadAfterChunkError(
  storage: StorageLike,
  marker: string
): boolean {
  if (storage.getItem(CHUNK_RELOAD_MARKER) === marker) {
    return false;
  }

  storage.setItem(CHUNK_RELOAD_MARKER, marker);
  return true;
}

export function clearChunkReloadMarker(storage: StorageLike): void {
  storage.removeItem(CHUNK_RELOAD_MARKER);
}

export function installChunkReloadRecovery(
  router: Router,
  browserWindow: Window = window,
  storage: StorageLike = sessionStorage
): void {
  const recover = (reason: unknown) => {
    if (!isRecoverableChunkLoadError(reason)) return;

    const marker = browserWindow.location.href;
    if (!shouldReloadAfterChunkError(storage, marker)) return;

    browserWindow.location.reload();
  };

  browserWindow.addEventListener("vite:preloadError", event => {
    event.preventDefault();
    recover((event as Event & { payload?: unknown }).payload ?? event);
  });

  router.onError(error => {
    recover(error);
  });
}
