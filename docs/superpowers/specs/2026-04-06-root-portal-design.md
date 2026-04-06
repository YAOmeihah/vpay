# Root Portal Design

## Summary

Replace the current root-path instant redirect with a mixed-purpose portal page for `/`.

The new root page should serve two audiences at once:

- Administrators who need a fast path into the management console
- Merchants or integrators who need a clear path to API and callback documentation

This page is not a marketing landing page and not a login page. It is a lightweight system portal that explains what the platform is, where to go next, and which core capabilities are available.

## Goals

- Make `/` a useful entry portal instead of a blind redirect
- Keep `/console/` as the only admin console entrypoint
- Give merchant/integration users a clear documentation path
- Present the platform as a payment infrastructure system, not a generic admin template
- Keep the page lightweight, readable, and mobile-friendly

## Non-Goals

- Redesign the admin console under `/console/`
- Change payment flow behavior or merchant API contracts
- Add account flows, login widgets, or live dashboards to the root page
- Turn the root page into a marketing-heavy product website

## Current State

- [`public/index.html`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/public/index.html) immediately redirects users to `/console/`
- [`public/index.php`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/public/index.php) is the ThinkPHP entry file and should keep that framework responsibility
- [`docs/frontend-admin.md`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/docs/frontend-admin.md) already treats `/console/` as the only admin entry
- [`docs/payment-api.md`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/docs/payment-api.md) already provides a repository-native replacement for old API help pages

The missing piece is a stable, purposeful root portal that connects those two destinations.

## Audience

### Primary audience

- System administrators and operators
- Merchants or developers integrating payment APIs

### Secondary audience

- Internal team members who need a quick orientation page

## Design Direction

The page should feel like an enterprise payment gateway portal:

- Clear
- Trustworthy
- Functional
- Slightly technical
- Visually intentional, but not flashy

The tone should avoid both extremes:

- Too plain: feels like a forgotten redirect page
- Too promotional: feels disconnected from the actual system

## Information Architecture

The page should use a single-page structure with six blocks.

### 1. Top navigation

Purpose:

- Quickly orient the user
- Expose the two main destinations without scrolling

Content:

- Left: `VPay`
- Right: `接口文档`
- Right: `管理后台`

### 2. Hero section

Purpose:

- Explain what this system is in one glance
- Provide immediate action choices

Content:

- Title: `支付接入与管理控制台`
- Supporting copy describing the page as the unified entry for payment access, callback references, and backend operations
- Primary CTA: `进入管理后台`
- Secondary CTA: `查看接口文档`
- Small tertiary link: `了解监控回调与支付状态流程`

### 3. Capability cards

Purpose:

- Help users self-identify where to go
- Make the homepage feel like a portal, not a dead-end splash

Cards:

- 管理后台
- 商户接入
- 监控与回调

Each card should contain:

- A short title
- A one-line description
- A direct action link

### 4. Integration steps

Purpose:

- Give new integrators an immediate mental model of the workflow

Structure:

1. 配置通信密钥与回调地址
2. 调用创建订单接口发起支付
3. 通过异步通知与查询接口确认结果

This block should be concise and visually scan-friendly.

### 5. Current available entry points

Purpose:

- Provide practical route-level orientation
- Reduce ambiguity around where core functions live

Suggested content:

- 管理控制台：`/console/`
- 支付接口：`/createOrder` `/getOrder` `/checkOrder` `/closeOrder`
- 监控接口：`/getState` `/appHeart` `/appPush` `/closeEndOrder`

This block should look like an operational quick-reference, not a full API doc.

### 6. Footer

Purpose:

- Close the page cleanly
- Repeat only the most important destinations

Content:

- `VPay`
- `管理后台`
- `接口文档`
- `系统版本`

## Visual System

### Look and feel

Use a restrained enterprise interface style:

- Flat and crisp
- Light structural layering
- Strong typography hierarchy
- Minimal decorative motion
- No exaggerated glassmorphism, neon, or template-dashboard aesthetics

### Color palette

- Primary: `#16324F`
- Secondary: `#2F6BFF`
- Accent: `#F59E0B`
- Background: `#F7FAFC`
- Text: `#1F2937`

Reasoning:

- The navy base supports trust and system seriousness
- Electric blue keeps navigation and CTAs readable
- Amber is reserved for emphasis, not saturation-heavy decoration

### Typography

Typography should create a subtle distinction between portal and admin.

Recommended direction:

- Headings: modern geometric/grotesk feel similar to `Manrope` or `Space Grotesk`
- Body: highly legible sans style similar to `Inter`

The implementation may use web fonts or close local/system equivalents, but the visual intent should remain:

- Headings feel modern and confident
- Body text feels technical and readable

### Background and depth

Avoid a flat plain white page. Use a subtle structural background:

- soft grid
- restrained radial highlight
- very light geometric linework

Depth should come mostly from contrast, spacing, and borders rather than heavy shadows.

## Layout Behavior

### Desktop

Hero section should use a two-column layout:

- Left: title, supporting copy, CTA group
- Right: stacked or offset capability cards

Below the hero, the remaining sections should move into a centered single-column flow with clear spacing rhythm.

### Mobile

The page should collapse to a single column:

- Hero copy first
- CTA buttons stacked or wrapped cleanly
- Capability cards below
- Integration steps simplified to a vertical list

No horizontal scrolling is acceptable.

## Interaction Rules

- `/` should no longer auto-redirect to `/console/`
- `/console/` remains the only admin console destination
- CTA hierarchy must be clear: admin and docs are both first-class, but admin can be visually primary
- Hover states should use color, border, or subtle lift only
- Focus states must remain visible for keyboard users
- Motion should be limited to fade/slide reveals and standard transition timing

## Content Tone

Content should sound operational, not promotional.

Preferred tone:

- short
- exact
- infrastructure-oriented
- confidence without hype

Avoid:

- vague slogans
- startup-marketing phrases
- emotional persuasion copy

## Implementation Notes

- The root portal should replace the current redirect content in [`public/index.html`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/public/index.html)
- [`public/index.php`](D:/Hrlni/Desktop/phpEnv/www/vpay.test/public/index.php) should not gain portal-rendering logic
- The portal can link into repository-backed docs and existing live routes
- The page should be self-contained and easy to maintain

## Verification Criteria

The design is successful when:

- Visiting `/` shows a portal page rather than an automatic redirect
- Users can clearly choose between admin and documentation paths within a few seconds
- The page feels consistent with a payment infrastructure product
- The portal remains readable on mobile and desktop
- `/console/` still functions as the only admin console entrypoint
- The page does not introduce confusion with payment runtime flows

## Risks And Mitigations

### Risk: the page feels too much like a marketing site

Mitigation:

- Keep copy operational
- Keep sections compact
- Prefer links and system explanations over persuasion blocks

### Risk: the page feels too close to an admin dashboard

Mitigation:

- Use a portal structure, not metric cards
- Avoid backend-style chrome and table-heavy layout

### Risk: root path becomes cluttered

Mitigation:

- Limit the page to navigation, capability overview, and quick reference
- Push detailed content to docs rather than expanding the portal endlessly

## Recommended Next Step

Create an implementation plan that updates the root page content and visual structure while preserving all current backend behavior.
