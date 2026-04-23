import assert from "node:assert/strict";
import test from "node:test";

import {
  createSettingsSections,
  hydrateSettingsSections
} from "../src/views/system/settings/sectionState.ts";

test("hydrateSettingsSections ignores removed single-terminal defaults but keeps terminal strategy state", () => {
  const sections = createSettingsSections();

  hydrateSettingsSections(sections, {
    monitorKey: "legacy-key",
    wxpay: "weixin://legacy",
    zfbpay: "alipay://legacy",
    allocationStrategy: "round_robin"
  });

  assert.equal("monitorKey" in sections.payment, false);
  assert.equal("qrcode" in sections, false);
  assert.equal(sections.payment.allocationStrategy, "round_robin");
});
