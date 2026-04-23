import assert from "node:assert/strict";
import test from "node:test";

import {
  clearChunkReloadMarker,
  isRecoverableChunkLoadError,
  shouldReloadAfterChunkError
} from "../src/utils/chunkReload.ts";

class MemoryStorage {
  private readonly store = new Map<string, string>();

  getItem(key: string): string | null {
    return this.store.has(key) ? this.store.get(key)! : null;
  }

  setItem(key: string, value: string): void {
    this.store.set(key, value);
  }

  removeItem(key: string): void {
    this.store.delete(key);
  }
}

test("isRecoverableChunkLoadError identifies lazy chunk and module fetch failures", () => {
  assert.equal(
    isRecoverableChunkLoadError(new Error("Failed to fetch dynamically imported module")),
    true
  );
  assert.equal(isRecoverableChunkLoadError(new Error("Loading chunk 9 failed")), true);
  assert.equal(isRecoverableChunkLoadError(new Error("Importing a module script failed")), true);
  assert.equal(isRecoverableChunkLoadError(new Error("普通业务错误")), false);
});

test("shouldReloadAfterChunkError only allows one reload per marker", () => {
  const storage = new MemoryStorage();

  assert.equal(shouldReloadAfterChunkError(storage, "/orders/index"), true);
  assert.equal(shouldReloadAfterChunkError(storage, "/orders/index"), false);
  assert.equal(shouldReloadAfterChunkError(storage, "/dashboard"), true);
});

test("clearChunkReloadMarker removes the one-shot guard", () => {
  const storage = new MemoryStorage();

  assert.equal(shouldReloadAfterChunkError(storage, "/orders/index"), true);
  clearChunkReloadMarker(storage);
  assert.equal(shouldReloadAfterChunkError(storage, "/orders/index"), true);
});
