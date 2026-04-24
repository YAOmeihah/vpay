import assert from "node:assert/strict";
import { describe, it } from "node:test";

import {
  canStartUpdate,
  normalizePreflightChecks,
  updateBadgeType
} from "../src/views/system/settings/updateState.ts";

describe("system update state", () => {
  it("only allows update when release is available and preflight passes", () => {
    assert.equal(canStartUpdate("update_available", true, false), true);
    assert.equal(canStartUpdate("up_to_date", true, false), false);
    assert.equal(canStartUpdate("update_available", false, false), false);
    assert.equal(canStartUpdate("update_available", true, true), false);
  });

  it("maps status to element plus badge types", () => {
    assert.equal(updateBadgeType("update_available"), "warning");
    assert.equal(updateBadgeType("up_to_date"), "success");
    assert.equal(updateBadgeType("check_failed"), "danger");
    assert.equal(updateBadgeType("ahead"), "info");
  });

  it("normalizes preflight checks", () => {
    assert.deepEqual(
      normalizePreflightChecks([
        { label: "ZipArchive", ok: false, message: "缺失" },
        { label: "磁盘空间", ok: true }
      ]),
      [
        { label: "ZipArchive", ok: false, message: "缺失" },
        { label: "磁盘空间", ok: true, message: "" }
      ]
    );
    assert.deepEqual(normalizePreflightChecks(null), []);
  });
});
