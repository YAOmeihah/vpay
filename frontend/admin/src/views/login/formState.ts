export function createLoginFormState() {
  return {
    user: "",
    pass: ""
  };
}

export const loginUsernameInputProps = {
  autocomplete: "off",
  name: "vpay-admin-user"
} as const;

export const loginPasswordInputProps = {
  autocomplete: "new-password",
  name: "vpay-admin-pass"
} as const;
