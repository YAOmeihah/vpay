import assert from "node:assert/strict";
import test from "node:test";

import {
  createLoginFormState,
  loginPasswordInputProps,
  loginUsernameInputProps
} from "../src/views/login/formState.ts";

test("login form state starts empty and uses autofill-resistant input props", () => {
  assert.deepEqual(createLoginFormState(), {
    user: "",
    pass: ""
  });

  assert.equal(loginUsernameInputProps.autocomplete, "off");
  assert.equal(loginUsernameInputProps.name, "vpay-admin-user");
  assert.equal(loginPasswordInputProps.autocomplete, "new-password");
  assert.equal(loginPasswordInputProps.name, "vpay-admin-pass");
});
