# Admin Login And Settings UX Design

## Context

The Vue admin console under `/console/` is now the only supported operator interface. Two UX issues need correction:

1. The login form should not present any apparent default credentials to operators.
2. The system settings page currently combines unrelated concerns into one large form with one save action, which does not match normal operational workflows.

The current implementation in `frontend/admin/src/views/system/settings/index.vue` mixes administrator credentials, payment flow defaults, QR code resources, and EPay compatibility credentials into one validation context and one submit action. This creates avoidable confusion and unnecessary save coupling.

## Goals

- Prevent the login screen from showing browser-autofilled credentials by default.
- Restructure `/system/settings` into clear operational sections.
- Make each section independently savable and independently validatable.
- Preserve the existing backend settings model and endpoint surface where practical.
- Clarify sensitive-field behavior such as “leave blank to keep unchanged”.

## Non-Goals

- No backend role system or multi-user admin redesign.
- No migration of settings into multiple backend pages.
- No redesign of the broader console navigation.
- No replacement of the existing settings persistence schema unless required by the section-save API contract.

## Recommended Approach

Use a single page with multiple operational cards, each card owning its own form state, validation scope, and save action.

This keeps the current route structure simple while matching real operator tasks:

- Security changes are isolated from payment changes.
- QR code updates are isolated from credentials and callback settings.
- EPay compatibility settings are isolated from core VPay payment settings.

This is the right tradeoff for the current console size: clearer than the current monolithic form, but lighter than a full multi-page settings split.

## Information Architecture

The `/system/settings` page should be reorganized into four top-level cards in this order.

### 1. Administrator Security

Fields:

- `user`
- `newPassword`
- `confirmPassword`

Behavior:

- The username is editable.
- Password inputs are always blank on load.
- Leaving both password fields blank means “do not change password”.
- If one password field is filled, both become required and must match.
- Save action updates only admin identity fields.

Primary CTA:

- `保存账号与密码`

Supporting copy:

- `留空表示不修改当前密码`

### 2. Payment Base Configuration

Fields:

- `close`
- `notifyUrl`
- `returnUrl`
- `key`
- `payQf`

Behavior:

- This section owns the core order/payment defaults.
- Validation applies only inside this card.
- Save action updates only these fields.

Primary CTA:

- `保存支付配置`

### 3. Default Collection QR Codes

Fields:

- `wxpay`
- `zfbpay`

Behavior:

- Existing decoded QR contents are loaded and previewed.
- Uploading an image attempts QR decoding immediately.
- Decoding success updates only local card state until saved.
- Decoding failure shows a clear error and keeps current saved value untouched.
- Manual text entry remains available as fallback.

Primary CTA:

- `保存收款码`

Supporting copy:

- `上传无金额收款码，解析成功后再保存`

### 4. EPay Compatibility

Fields:

- `epay_enabled`
- `epay_pid`
- `epay_name`
- `epay_key`
- `epay_private_key`
- `epay_public_key`

Behavior:

- MD5 and RSA generation actions affect only this card.
- Sensitive key fields may remain blank to preserve existing stored values.
- Public key may be shown from current config; private key should never be backfilled from the server.

Primary CTA:

- `保存易支付配置`

Supporting copy:

- `敏感密钥留空表示保持原值不变`

## Login UX Design

The login page should remain visually simple. The required behavioral adjustments are:

- Explicitly disable browser autofill heuristics where feasible on username and password inputs.
- Keep both fields empty on first render.
- Do not embed demo credentials in source or placeholders.
- Preserve keyboard submit and current session-auth flow.

Because browser autofill behavior varies, the implementation should use defensive mitigations rather than assuming one attribute fully disables autofill.

## Page Layout And Interaction Model

The settings page should keep the current route and broad admin visual language, but the content should move from one long generic form to a stacked card layout.

Recommended layout:

- One page header with a concise description.
- Four cards with short section descriptions.
- Each card contains only its own fields and its own action row.
- Sensitive fields use inline helper text instead of relying on placeholders alone.
- Loading and save feedback should be scoped to the active card where possible.

The page should feel operational and structured rather than decorative. It should optimize for confidence and low-error editing.

## Data And API Design

Frontend state should be split into four form models instead of one shared `formData`.

Recommended API contract:

- Keep using `/admin/index/getSettings` for initial load.
- Extend or adapt `/admin/index/saveSetting` so it can accept partial payloads safely.
- Each card submits only the fields it owns.

Expected backend behavior for partial saves:

- Unsent fields remain unchanged.
- Blank sensitive fields such as admin password, EPay MD5 key, and EPay private key remain unchanged unless the user explicitly provides a new value.
- Existing semantics for generated defaults and stored public configuration remain intact.

This design intentionally avoids introducing multiple backend endpoints unless implementation friction proves the existing save endpoint cannot safely support partial updates.

## Validation Rules

### Administrator Security

- `user` is required.
- `newPassword` and `confirmPassword` are optional together.
- If either password field is non-empty, both are required and must match.

### Payment Base Configuration

- `notifyUrl` is required.
- `returnUrl` is required.
- `key` is required.
- `close` is required and must be a positive integer.
- `payQf` must be one of the supported options.

### Default Collection QR Codes

- `wxpay` is required when saving this card.
- `zfbpay` is required when saving this card.
- Decoded values must be non-empty strings.

### EPay Compatibility

- No key fields are required when the feature is disabled.
- If enabled, `epay_pid` should be required.
- `epay_name` may default to the current legacy default.
- Blank `epay_key` and `epay_private_key` mean no update.

## Error Handling And Feedback

- Each card shows its own validation errors without blocking unrelated cards.
- Save success messaging should identify the affected section.
- Save failure messaging should identify the affected section.
- QR decode failure should not erase the currently saved preview/state.
- RSA and MD5 generation should show explicit “generated locally, click save to persist” messaging.

## Accessibility And UX Guardrails

- All inputs retain labels; helper text supplements but does not replace labels.
- Password managers and browser autofill should be discouraged without harming keyboard accessibility.
- Focus order should remain predictable inside each card.
- Card CTAs should remain visible without requiring users to scroll to the bottom of the entire page.
- The mobile layout should stack cleanly with action buttons remaining easy to tap.

## Testing Strategy

Frontend regression coverage should verify:

- Login view initializes with empty username and password values.
- Orders of operations do not accidentally repopulate password fields after settings reload.
- Section save payload builders send only owned fields.
- Password section omits password updates when fields are blank.
- EPay section omits sensitive blank fields from update payloads.
- QR decode failures do not overwrite previous form state.

Manual verification should cover:

1. Open `/console/#/login` and confirm fields render empty without visible default credentials.
2. Open `/console/#/system/settings`.
3. Change only the admin username and save.
4. Change only payment base settings and save.
5. Upload and save QR code values independently.
6. Generate EPay keys and confirm they are not persisted until the EPay section is saved.
7. Refresh the page and confirm password/private key fields remain blank while non-sensitive settings reload normally.

## Implementation Notes

- Prefer extracting each settings card into a focused component if the page becomes too large.
- Shared helper functions for section payload building and section hydration are encouraged.
- The backend save service may need a small adjustment so partial saves do not implicitly blank required fields from unrelated sections.

## Acceptance Criteria

- Login screen no longer appears to prefill credentials on initial visit.
- `/system/settings` is split into four operational cards with independent save actions.
- Saving one card does not require unrelated fields to be valid.
- Blank sensitive fields do not overwrite stored secrets.
- Operators can understand what each section controls without reading source code or guessing placeholder semantics.
