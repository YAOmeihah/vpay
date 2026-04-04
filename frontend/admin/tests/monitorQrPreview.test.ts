import assert from "node:assert/strict";
import test from "node:test";

import { monitorQrPreviewStyle } from "../src/views/system/monitor/qrPreview.ts";

test("monitor QR preview keeps a restrained square size on desktop", () => {
  assert.deepEqual(monitorQrPreviewStyle, {
    width: "min(100%, 180px)",
    aspectRatio: "1 / 1",
    objectFit: "contain"
  });
});
