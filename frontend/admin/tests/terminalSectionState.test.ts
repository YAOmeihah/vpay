import assert from "node:assert/strict";
import test from "node:test";

import {
  createSettingsSections,
  hydrateSettingsSections
} from "../src/views/system/settings/sectionState.ts";

test("hydrateSettingsSections keeps legacy defaults but exposes terminal strategy state", () => {
  const sections = createSettingsSections();

  hydrateSettingsSections(sections, {
    monitorKey: "legacy-key",
    wxpay: "weixin://legacy",
    zfbpay: "alipay://legacy",
    allocationStrategy: "round_robin"
  });

  assert.equal(sections.payment.monitorKey, "legacy-key");
  assert.equal(sections.payment.allocationStrategy, "round_robin");
});
