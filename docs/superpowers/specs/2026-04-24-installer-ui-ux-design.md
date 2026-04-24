# VPay Installer UI/UX Design

## Context

VPay has a web installer and upgrader built with ThinkPHP views under `view/install/`.
The current flow is functionally complete but visually minimal: pages render plain headings,
lists, forms, and result text. The goal is to improve trust, clarity, and safety without
changing the core backend installation or migration flow.

This design covers both first-time installation and existing-system upgrade pages:

- `view/install/entry.php`
- `view/install/check.php`
- `view/install/form.php`
- `view/install/confirm.php`
- `view/install/progress.php`
- `view/install/recover.php`
- `view/install/success.php`

## Scope

Use the "trusted installer wizard" approach.

The implementation should:

- Improve the visual design and interaction quality of install and upgrade pages.
- Keep backend controller state flow mostly stable.
- Add lightweight front-end behavior only where it improves safety or reduces mistakes.
- Avoid build tools, external CDN dependencies, and large JavaScript frameworks.
- Preserve existing installer routes and form posts.

The implementation should not:

- Replace the installer with the Vue admin frontend.
- Add real-time backend progress tracking.
- Change migration execution semantics.
- Add a new asset build pipeline.

## Visual Direction

Use a professional, trustworthy light theme suited to a payment system installer.

- Background: soft teal-tinted surface with subtle gradients or decorative shapes.
- Primary color: teal, close to `#0F766E`.
- Secondary/action color: professional blue, close to `#0369A1`.
- Main surface: white cards with clear borders and soft shadows.
- Typography: system-safe Chinese UI stack, with careful spacing and readable sizes.
- Icon style: inline SVG only, no emoji icons.
- Motion: subtle fade/slide-in and status highlighting, disabled for reduced-motion users.

The UI should avoid generic purple/pink AI gradients and avoid decorative elements that
make operational status harder to scan.

## Page Architecture

All installer pages should share a unified shell:

- Header with product name, current mode, and state message.
- Step progress indicator.
- Main content card area.
- Consistent action button styles, status badges, alerts, and form controls.

Suggested mode-specific step labels:

- First install: Environment Check, Database Config, Admin Account, Execute Install, Complete.
- Upgrade: Environment Check, Version Confirm, Admin Verify, Execute Upgrade, Complete.
- Recovery: Detect Issue, Read Details, Repair, Retry.

The current route determines which step is active. The backend does not need to track a
new step enum for this iteration; the view can infer active state from available data and
page context.

## Install Check And Form

The check page should present environment checks as status cards instead of a plain list.

- Passing checks use a success badge and short confirmation text.
- Failed checks use a danger badge and explain that the user must fix the server
  environment and refresh the page.
- The primary install action stays disabled when checks fail.

The install form should be grouped into two clear sections:

- Database connection: host, database name, user, password, port, charset, default language.
- Admin account: username, password, confirm password.

Field behavior:

- Every field has an explicit `label` and stable `id`.
- Use helpful hints for fields likely to confuse users, such as host, port, charset, and
  admin password.
- Preserve non-sensitive submitted values after validation failure.
- Never re-render database password or admin passwords after validation failure.
- Password fields include show/hide toggles.

## Upgrade Confirm Form

The upgrade page should make risk and intent explicit.

- Show current version and target version as a prominent version transition.
- Show pending migrations in a compact list.
- Show a safety notice reminding the user to back up code, database, and `.env` before
  continuing.
- Ask for existing administrator credentials before executing upgrade.
- Keep failure copy generic for invalid credentials: "管理员账号或密码不正确".

The upgrade submit button should be visually distinct and include the version transition
nearby so the user knows what action will happen.

## Entry, Progress, Success, Recovery

`entry.php` should become a concise landing state page:

- Show whether the system needs install, upgrade, recovery, or is locked.
- Present one primary action for the next safe step.
- Show a short explanation of what will happen next.

`progress.php` should show execution as a protected operation:

- Display "正在执行，请勿关闭或刷新页面" style copy.
- Render any provided steps as a timeline.
- If no steps are available, still show a stable waiting state.

`success.php` should give a clear next step:

- For install: show admin user and `.env` path, then link to `/console/`.
- For upgrade: show from/to version, executed migration count, then link to `/console/`.
- Avoid implying success until the backend result is already returned.

`recover.php` should guide repair:

- Show the failed step.
- Show the error message.
- If manual `.env` content exists, show target path and content in a readable code block.
- Add a copy button for the `.env` content when available.
- Provide actions to return to `/install/check` and refresh recovery details.

## Interaction Logic

Use small progressive-enhancement JavaScript embedded in the installer shell or included
by the installer views.

Required interactions:

- Disable submitting forms after first submit and update button text to indicate work is
  in progress.
- Add password visibility toggles for password inputs marked for toggling.
- Focus the error summary on load when validation errors exist.
- Add copy-to-clipboard behavior for recovery `.env` content when available.

The JavaScript must fail safely: if JS is disabled, forms still submit normally and all
critical information remains visible.

## Accessibility

The installer must remain usable with keyboard and assistive technology.

- All inputs have associated labels.
- Validation summaries use `role="alert"` and can receive focus.
- Focus states are visible for buttons, links, inputs, and password toggles.
- Color is never the only status indicator; status text is always present.
- Buttons use disabled states only when the action is genuinely unavailable.
- Motion respects `prefers-reduced-motion`.
- Layout has no horizontal scroll at 375px width.

## Error Handling

Errors should be actionable and grouped by severity:

- Environment failures: tell the user to fix server prerequisites and refresh.
- Form validation failures: show a top summary and keep the form visible.
- Upgrade credential failures: show the generic invalid-credentials message.
- Runtime failures: route to recovery and explain the failed step.
- Manual `.env` write failures: show target path, content, and copy action.

Do not expose stack traces or sensitive values in the UI.

## Testing

Update existing PHP view/controller tests rather than adding a browser test framework.

Test coverage should verify:

- Entry page renders the state badge and primary action.
- Check page renders environment status text and install form fields.
- Install form does not render sensitive password values after validation failure.
- Upgrade page renders current version, target version, migrations, backup warning, and
  credential fields.
- Recover page renders manual `.env` copy UI when content exists.
- Success page renders install and upgrade summaries plus `/console/` next-step link.

JavaScript behavior can remain manually verified in this iteration because no browser test
tooling exists in the PHP test suite.

## Implementation Boundaries

Prefer small view partials to keep files readable. A reasonable structure is:

- A shared installer shell partial for HTML head, styling, layout, and JS.
- Small helper snippets or PHP arrays for step labels and status badges.
- Existing business partials continue to render install and upgrade form-specific content.

If the shell abstraction becomes awkward in ThinkPHP templates, it is acceptable to keep a
small amount of duplication between full-page views, but shared CSS and JS should still be
centralized to avoid divergence.

## Acceptance Criteria

- Install and upgrade pages share a coherent trusted wizard visual language.
- The flow works without build tooling or external network access.
- First install, upgrade, success, progress, and recovery pages are responsive.
- Submit buttons prevent accidental double submission when JavaScript is enabled.
- Password fields can be shown or hidden without exposing values by default.
- Error summaries are accessible and visibly prominent.
- Upgrade page clearly communicates backup expectations before execution.
- Existing installer routes and backend install/upgrade behavior remain compatible.
- Relevant existing tests pass after view expectations are updated.
