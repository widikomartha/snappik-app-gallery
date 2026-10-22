# SnapPik Design System

**Status: READY FOR REVIEW; TASK-001 foundation implemented.** The owner approved documenting the ZenGrid direction, Motion animation and light/dark appearance on October 22, 2026 and subsequently requested TASK-001 implementation. Tokens, shared controls/shells, appearance initialization and root Motion policy now exist. Complete product components/journeys remain planned; this does not record final package sign-off. [Package state](../index.md).

## Authority and reference

This document owns visual tokens, component presentation and animation recipes. [PRD REQ-021 and REQ-022](../product/PRD.md#req-021--light-and-dark-appearance) own appearance and interaction behavior; [Architecture](../engineering/ARCHITECTURE.md#browser-appearance-and-motion) owns technical integration. Visual presentation must never change permissions, commercial rules or server-confirmed state.

The owner-selected [ZenGrid reference](https://designmd.ai/chef/zengrid) informs the neutral palette, typography and photo-led grid. SnapPik's dark palette, accessible text colors, operational components and animation recipes below are local adaptations, not claims about a downloaded reference implementation. The published reference page was inspected; no source kit or dependency has been installed.

## Experience principles

- Give photographs the most space; keep actions and filenames legible without covering image content.
- Use consistent alignment and whitespace across public, authentication, photographer, client and admin surfaces. Dense admin data may use a tighter layout than the public gallery.
- Communicate saved, pending, frozen, locked, failed and successful states explicitly. An animation never substitutes for a server acknowledgement.
- Use restrained motion throughout interaction flows; do not delay an action until an entrance or exit finishes.
- Provide equal functionality in both themes, on touch and keyboard, and with reduced motion. Theme is independent of subscription and gallery access.

## Semantic color tokens

Use semantic CSS custom properties, not page-specific hex values. These opaque sRGB values are the initial SnapPik implementation palette. Every component must use the appropriate foreground/background pair; the table does not permit arbitrary combinations.

| Token | Light | Dark | Purpose |
| --- | --- | --- | --- |
| `--color-bg` | `#FAF9F6` | `#171614` | Page background |
| `--color-surface` | `#FFFFFF` | `#211F1C` | Cards, fields, dialogs |
| `--color-surface-subtle` | `#F0EEEA` | `#2B2824` | Grouped sections, metadata strips |
| `--color-surface-hover` | `#E7E3DD` | `#35312C` | Neutral interactive hover |
| `--color-text` | `#292524` | `#F5F5F4` | Headings, body, labels |
| `--color-text-muted` | `#57534E` | `#D6D3D1` | Supporting text on neutral surfaces |
| `--color-text-tertiary` | `#78716C` | `#A8A29E` | Metadata on page or surface only |
| `--color-border` | `#D6D3D1` | `#44403C` | Decorative dividers; not a control's sole identifier |
| `--color-control-border` | `#78716C` | `#A8A29E` | Input bounds, selection indicators |
| `--color-primary` | `#57534E` | `#E7E5E4` | Primary button background |
| `--color-on-primary` | `#FFFFFF` | `#292524` | Primary button text and icons |
| `--color-focus` | `#57534E` | `#E7E5E4` | Visible outline with surface-colored separation |
| `--color-selected-bg` | `#E7E5E4` | `#35312C` | Selected row or photo metadata strip |
| `--color-success` / `--color-success-bg` | `#3F6212` / `#ECFCCB` | `#BEF264` / `#24301B` | Confirmed successful operation |
| `--color-warning` / `--color-warning-bg` | `#854D0E` / `#FEF3C7` | `#FDE68A` / `#332B18` | Needs attention, frozen access |
| `--color-error` / `--color-error-bg` | `#B91C1C` / `#FEE2E2` | `#FCA5A5` / `#381F1F` | Validation or operational failure |

Use `--color-text` on `--color-surface-subtle` for informational banners. Pair semantic foregrounds with their matching semantic backgrounds; accompany color with an icon and readable text. Links use underlined text with a visible focus state. On selected/hover surfaces, use normal or muted text rather than light-theme tertiary text. Disabled controls retain readable labels and a textual explanation where the reason is not clear; do not fade the whole component with opacity.

Reference accents are not automatically suitable for small text. Text must meet 4.5:1 contrast, or 3:1 for large text; meaningful control boundaries, icons and focus indicators must meet 3:1 against adjacent colors. Decorative borders are exempt from being the sole functional signal. These rules follow [W3C text contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) and [non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).

Initial numeric checks on the opaque token pairs: light body/page 14.41:1, dark body/page 16.58:1; light tertiary/page 4.56:1; primary button text 7.63:1 light and 12.08:1 dark; semantic text/background pairs at least 5.30:1. These calculations do not verify rendered components, overlays, focus placement or browser accessibility. Recheck actual combinations during implementation.

## Typography, spacing and geometry

| Role | Planned font | Size / line height | Weight |
| --- | --- | --- | --- |
| Public hero / H1 | Raleway, sans-serif fallback | 32px narrow, 40px wide / 1.2 | 300 |
| H2 | Raleway, sans-serif fallback | 28px narrow, 32px wide / 1.25 | 300 |
| H3 / H4 | Raleway, sans-serif fallback | 24px / 1.3; 18px / 1.4 | 500 |
| Body / forms | DM Sans, system sans-serif fallback | 16px / 1.5 | 400 |
| Compact table / supporting copy | DM Sans, system sans-serif fallback | 15px / 1.5; 13px / 1.5 | 400–500 |
| Identifiers / filenames when useful | System monospace | 13px / 1.5 | 400 |

Use relative font units in CSS and preserve browser zoom. Critical instructions, prices and errors must not use tiny type. Self-host the selected font assets after verifying their distribution license; use `font-display: swap` and fallback fonts. Do not introduce a third-party font request as a runtime requirement.

Use spacing steps 4, 8, 12, 24, 36, 48, 72, 96 and 120px. The 12px rhythm structures content; 4/8px handle icon and label detail. Use 48px between major mobile sections and 72–120px on spacious desktop public pages. Operational panels use 24–36px padding on wide screens and 16–24px on narrow screens.

Use a 1280px maximum public content width and 1440px maximum operational workspace width, with 16px narrow and 24–48px wide gutters. Initial layout breakpoints are 640px and 1024px; verify fluid behavior between them. Gallery columns adapt to available width; reduce columns rather than make labels or controls too small. Cards/inputs use a restrained 4px radius; images use square corners. Separate surfaces with borders and tone changes, without decorative shadows, gradients or glow.

## Theme control and appearance

[REQ-021](../product/PRD.md#req-021--light-and-dark-appearance) defines default, persistence, storage-failure and state-preservation behavior. Use one shared appearance control in every SnapPik shell, reachable in the header or mobile navigation. Its visible label and accessible name identify the action, for example `Aktifkan tema gelap` / `Aktifkan tema terang`; icon-only presentation still needs that accessible name and a tooltip.

Apply the resolved theme to the document root before initial paint, including native controls through `color-scheme`. An explicit choice remains authoritative; when none is stored, follow system changes. Switching updates tokens in place without a route reload, component remount, focus loss or changes to form/selection/job state. If storage fails, preserve the choice in memory for the mounted application and retry no blocking write loop. Do not send theme to the server as a project setting.

Photo pixels remain unchanged: no theme filter, inversion, dimming or recoloring. A lightbox may darken its surrounding backdrop without darkening the photo element. Provider-hosted payment appearance remains outside SnapPik's guarantee. Arbitrary brand-color editing, custom themes and cross-device preference synchronization are not added to MVP.

## Shared components and states

| Component | Presentation and observable interaction |
| --- | --- |
| Buttons and icon actions | Primary, secondary, ghost and destructive variants; default, hover, focus-visible, pressed, pending and disabled states. Minimum 44px touch target. Pending prevents duplicate submission without moving the label; destructive meaning has text. |
| Inputs and forms | Persistent labels, descriptions and inline errors associated programmatically; preserve submitted values on failure. Use the functional border token and an independent focus outline. Password visibility and appearance controls never submit a form. |
| Project cards and dashboard rows | Show name, publication/selection state and next action; neutral shells with text badges. Card navigation and secondary actions are separate targets; no nested buttons or misleading clickable empty areas. |
| Gallery tile and selection control | Image plus filename/action strip outside its pixels. Show a checkbox/checkmark and text/state, not color alone. Thumbnail opening and selection are distinct, named keyboard actions. Keep server-save state visible and preserve the selection allowance feedback from PRD. |
| Preview / lightbox | Fit the complete photo with `object-fit: contain`; accessible previous/next/close buttons, filename and position. Move focus into the dialog, contain it while open, support Escape, and restore focus to the invoking item or a meaningful gallery fallback. No autoplay. |
| Dialogs, mobile navigation and menus | Label the panel, make background content inert while modal, and maintain focus through entry/exit. Closing a confirmation does not submit it. Menus support keyboard operation; tooltips cannot contain the only explanation of an action. |
| Tables and mobile records | Use semantic headers; keep prices, order states and administrative actions readable. At narrow widths use labeled records or a locally scrollable named table region; no page-level horizontal overflow. Never hide a core action solely to fit a column. |
| Badges, banners and toasts | Pair state color with wording/icons; announce async results without repeatedly announcing every render. Billing/save failures persist inline with a next action; a disappearing toast is not the sole error record. |
| Loading, empty and failure panels | Reserve image dimensions to prevent layout jumps. Explain empty-source, import-in-progress, access-denied, frozen and failed-job states distinctly; expose only actions permitted by PRD. Skeletons never imply photos or payments already exist. |
| Checkout, voucher and ZIP status | Preserve the server-quoted amount and state. Show real progress when measured; otherwise show an indeterminate operation and stage label. Success transitions occur after canonical success, never on a timer. |

## Motion system

Use the `motion` package's React entry point, `motion/react`, for shared animation primitives as documented by [Motion for React](https://motion.dev/docs/react). TASK-001 installs the actual dependency in the manifest/lockfile and configures the root reduced-motion policy. Simple color/focus transitions may use CSS; share durations and respect the same accessibility policy. Core interactions require no premium Motion UI package or second animation engine.

| Recipe | Default timing and movement | Reduced-motion behavior |
| --- | --- | --- |
| Button/icon feedback | 120ms; icon or button scale 0.98 on press, restored on release | No scale; immediate state or short color change |
| Card/field/status change | 180ms opacity/background; no moving form fields | Instant or opacity up to 100ms |
| Public section reveal | 280ms fade with at most 12px vertical travel, once on entry | Visible immediately; no reveal travel or delayed content |
| Gallery window entry | 180ms fade; at most six visible items staggered 30ms, no repeat on ordinary scroll-back | No stagger or movement |
| Dialog / mobile panel | 220ms fade, at most 12px translation; no exit longer than 180ms | Opacity up to 100ms; no translation |
| Preview / photo navigation | 240ms fade; optional shared image transition capped at 280ms for a mounted, loaded pair | Immediate image swap or short opacity transition |
| Page body transition | 180ms fade after navigation; preserve the shell and move focus appropriately | Immediate content, normal navigation focus |
| Selection indicator / count | 120–180ms visual feedback; pending versus saved stays explicit | Immediate state and accessible announcement |
| Theme switch | Optional 150ms surface/text color transition; never animate first paint | Immediate token change |

Use a shared ease-out curve `(0.2, 0, 0, 1)` for these finite transitions. No perpetual decorative animation, scroll hijacking, parallax, mandatory splash sequence or animated photographic color changes. A busy indicator can loop only while genuinely pending; in reduced motion use a static status label instead. A selected count must not announce every animation frame.

Apply `MotionConfig reducedMotion="user"` globally and use `useReducedMotion` for custom recipes. The global option alone does not disable every custom loop, stagger or CSS animation; handle these explicitly. Follow the official [Motion accessibility guidance](https://motion.dev/docs/react-accessibility). Essential content/actions remain usable if animations are disabled or interrupted.

## Performance and responsive constraints

- Unlimited gallery content must not mean mounting or animating every photo at once. Use incremental loading and windowing where needed, with stable file identities, accessible item positions and predictable keyboard navigation. Do not discard saved selections when tiles unmount.
- Animate only visible mounted items. Avoid whole-catalogue `layout` measurement, scroll subscriptions per tile and synchronous decoding of all originals. Use responsive previews and lazy loading; reserve media dimensions. Do not claim a particular frame rate without measurement.
- Use layout/shared-image animation only when both endpoints are mounted and dimensions are known; fall back to a fade when the origin tile has been windowed out. Animation must not remount the gallery or cancel an active download.
- Keep sticky mobile actions clear of focus targets, safe areas and the on-screen keyboard. At 200% zoom, labels and critical controls remain reachable without truncating essential copy. Respect forced-colors mode with visible native/custom focus and selected indicators.

## Implementation review evidence

Delivery is covered by [TASK-001 and TASK-013](../planning/BACKLOG.md). [Project structure](../engineering/PROJECT_STRUCTURE.md) distinguishes the existing foundation from future product components; no component catalogue tool is required as a separate service. TASK-001 verifies synthetic component states and shells. Real selections, jobs, photos, large-gallery performance and integrated product-flow checks below remain TASK-013 evidence.

Before task completion, inspect public/auth, photographer, client and admin layouts at 360px and 1440px in both themes. Include pending, selected, locked, frozen, empty, error, success and disabled states; keyboard/lightbox focus; stored/system/blocked-storage appearance; and reduced-motion sessions. Measure rendered text/control contrast, verify no theme flash, and demonstrate a theme switch during an unsaved form, selection save and ZIP job without losing state.

Use a large synthetic gallery to verify bounded mounted tiles and no catalogue-wide animation work. Inspect interrupted navigation and rapid selection feedback under slow/failed requests. Capture screenshots and reproducible browser-check results; documentation token arithmetic is not application verification. [Backlog](../planning/BACKLOG.md) owns task status and completion evidence.
