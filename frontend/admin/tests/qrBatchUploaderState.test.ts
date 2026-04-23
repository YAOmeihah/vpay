import assert from "node:assert/strict";
import test from "node:test";
import { readFileSync } from "node:fs";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import {
  buildPendingQrRow,
  type UploadFileLike
} from "../src/components/admin/qrBatchUploaderState.ts";

const testDir = dirname(fileURLToPath(import.meta.url));

test("buildPendingQrRow keeps a pending row even when QR decoding throws", async () => {
  const file = new File(["demo"], "demo.png", { type: "image/png" });
  const uploadFile: UploadFileLike = { raw: file };

  const result = await buildPendingQrRow(uploadFile, {
    createPreviewUrl: current => `blob:${current.name}`,
    decodeQr: async () => {
      throw new Error("decoder crashed");
    }
  });

  assert.equal(result.warning, "二维码解析失败，可手动填写地址");
  assert.ok(result.row);
  assert.equal(result.row?.previewUrl, "blob:demo.png");
  assert.equal(result.row?.decodedUrl, "");
  assert.equal(result.row?.status, "pending");
});

test("buildPendingQrRow rejects selections without a raw file", async () => {
  const result = await buildPendingQrRow({}, {
    createPreviewUrl: () => "blob:missing",
    decodeQr: async () => ""
  });

  assert.equal(result.warning, "所选文件无效，请重新选择");
  assert.equal(result.row, null);
});

test("QrBatchUploader uses an explicit native file input trigger", () => {
  const source = readFileSync(
    resolve(testDir, "../src/components/admin/QrBatchUploader.vue"),
    "utf8"
  );

  assert.match(source, /triggerFileDialog/);
  assert.match(source, /type=\"file\"/);
  assert.doesNotMatch(source, /<el-upload/);
});
