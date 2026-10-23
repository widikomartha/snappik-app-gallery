# SnapPik Design System

**Status: READY FOR REVIEW; TASK-001 foundation and TASK-013 integrated presentation implemented.** The owner approved documenting the ZenGrid direction, Motion animation and light/dark appearance on October 22, 2026 and subsequently requested their implementation. Tokens, shared controls/shells, appearance initialization, finite Motion recipes and integrated product presentation now exist. TASK-013 prerequisite acceptance and final package sign-off remain pending. [Package state](../index.md).

## Authority and reference

This document owns visual tokens, component presentation and animation recipes. [PRD REQ-021 and REQ-022](../product/PRD.md#req-021--light-and-dark-appearance) own appearance and interaction behavior; [Architecture](../engineering/ARCHITECTURE.md#browser-appearance-and-motion) owns technical integration. Visual presentation must never change permissions, commercial rules or server-confirmed state.

The earlier owner-selected [ZenGrid reference](https://designmd.ai/chef/zengrid) informed the initial neutral, photo-led foundation. The owner-approved illustrated monochrome direction below now supplies the shared visual language. SnapPik's dark palette, accessible text colors, operational components and animation recipes below are local adaptations, not claims about a downloaded reference implementation. The published reference page was inspected; no source kit or dependency has been installed.

On October 24, 2026, the owner approved and requested implementation of a separate public landing treatment: monochrome, Manrope headings, rounded original illustrations, small pastel accents, richer illustration animation and complete Indonesian product content. The [landing decision brief](LANDING_DIRECTION.md) records that approval. The owner subsequently explicitly requested extending this design to the remaining pages, adapting each layout, illustration and animation to its task. The shared application now uses Manrope/DM Sans, monochrome tokens, rounded surfaces and small pastel illustration accents. The landing keeps its richer composition and pausable decorative loops; operational screens use finite illustration entrances.

## Approved illustrated direction and landing treatment

The public landing uses these scoped overrides in `tokens.css`. Existing token meanings remain unchanged. Pastels are decorative illustration fills, not status colors or text colors.

| Token | Light | Dark |
| --- | --- | --- |
| `--color-bg` | `#F5F5F5` | `#171717` |
| `--color-surface` | `#FFFFFF` | `#242424` |
| `--color-surface-subtle` | `#DEDEDE` | `#393939` |
| `--color-text` | `#171717` | `#F5F5F5` |
| `--color-text-muted` | `#626262` | `#B5B5B5` |
| `--color-border` | `#C9C9C9` | `#4C4C4C` |
| `--color-control-border` | `#737373` | `#B5B5B5` |
| `--color-primary` / `--color-on-primary` | `#171717` / `#F5F5F5` | `#F5F5F5` / `#171717` |
| `--color-focus` | `#171717` | `#F5F5F5` |
| `--color-illustration-neutral` | `#737373` | `#B5B5B5` |
| `--color-illustration-sage` | `#CFE5D7` | `#A4C2AD` |
| `--color-illustration-lavender` | `#E0D8F0` | `#B2A5CD` |
| `--color-illustration-peach` | `#F2D4C4` | `#D5B09A` |

The reference gray `#737373` is used for illustration details and control boundaries, rather than supporting text. Supporting text uses `#626262` in light appearance to retain readable contrast. Headings use the self-hosted Manrope Latin 700 subset with its SIL OFL license; body text and controls retain DM Sans. Hero type scales from approximately 34px to 61px, with a 38px narrow-screen treatment. Supporting section headings use approximately 29–36px. CSS expresses these sizes in relative units. Rounded illustrated frames and pricing panels use approximately 15–29px radii; CTA links use pill shapes. Rounded gallery frames never crop, filter or recolor real client photographs.

The approved content order is hero, value strip, photographer problem/benefit, three-step workflow, selection example, delivery, project controls, Drive source disclosure, trial/Pro, FAQ and final registration CTA. Navigation links target these sections. Registration and login use real application routes. Pro price/duration come from `WelcomeController`'s current persisted plan; missing prices show an explicit unavailable message. The synthetic interactive gallery is labeled as a simulation and never writes project selections. Public legal/support links appear only when configured through the existing shell.

Landing illustration float, drift, check and ZIP accents use a seven-second cycle; the decorative flow path uses a three-second dash cycle. These decorative loops are an owner-approved exception to the finite-motion rule below. A visible pause control stops all of them. CSS and a subscribed reduced-motion preference disable motion immediately; offscreen scenes pause through one shared observer. Essential content remains visible, and animations never claim a real save, payment or archive result. Existing Motion section reveals remain finite.

The owner subsequently requested a cleaner landing and Iconsax icons. Use the Linear Iconsax style through the pinned `iconsax-reactjs` dependency for theme/motion controls, the value strip, feature markers, benefit lists, CTA arrows and FAQ chevrons. Icons inherit `currentColor`; decorative icons are hidden from assistive technology. The shared theme control uses a moon/sun icon with an accessible action label and native tooltip. The illustration pause/play control is a 44px icon button beside the hero artwork; omit it when reduced motion disables the animation. The header keeps login and section navigation, with registration CTAs in the page content. Remove redundant header registration, the separate illustration-status caption and the closing secondary pricing CTA. Preserve the synthetic-demo label and source/trial/paid-entitlement disclosures.

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
| `--color-bg` | `#F5F5F5` | `#171717` | Page background |
| `--color-surface` | `#FFFFFF` | `#242424` | Cards, fields, dialogs |
| `--color-surface-subtle` | `#EFEFEF` | `#303030` | Grouped sections, metadata strips |
| `--color-surface-hover` | `#E9E9E9` | `#393939` | Neutral interactive hover |
| `--color-text` | `#171717` | `#F5F5F5` | Headings, body, labels |
| `--color-text-muted` | `#626262` | `#B5B5B5` | Supporting text on neutral surfaces |
| `--color-text-tertiary` | `#626262` | `#B5B5B5` | Metadata on page or surface only |
| `--color-border` | `#C9C9C9` | `#4C4C4C` | Decorative dividers; not a control's sole identifier |
| `--color-control-border` | `#737373` | `#B5B5B5` | Input bounds, selection indicators |
| `--color-primary` | `#171717` | `#F5F5F5` | Primary button background |
| `--color-on-primary` | `#FFFFFF` | `#171717` | Primary button text and icons |
| `--color-focus` | `#171717` | `#F5F5F5` | Visible outline with surface-colored separation |
| `--color-selected-bg` | `#E6E6E6` | `#393939` | Selected row or photo metadata strip |
| `--color-success` / `--color-success-bg` | `#3F6212` / `#ECFCCB` | `#BEF264` / `#24301B` | Confirmed successful operation |
| `--color-warning` / `--color-warning-bg` | `#854D0E` / `#FEF3C7` | `#FDE68A` / `#332B18` | Needs attention, frozen access |
| `--color-error` / `--color-error-bg` | `#B91C1C` / `#FEE2E2` | `#FCA5A5` / `#381F1F` | Validation or operational failure |

Use `--color-text` on `--color-surface-subtle` for informational banners. Pair semantic foregrounds with their matching semantic backgrounds; accompany color with an icon and readable text. Links use underlined text with a visible focus state. On selected/hover surfaces, use normal or muted text rather than light-theme tertiary text. Disabled controls retain readable labels and a textual explanation where the reason is not clear; do not fade the whole component with opacity.

Reference accents are not automatically suitable for small text. Text must meet 4.5:1 contrast, or 3:1 for large text; meaningful control boundaries, icons and focus indicators must meet 3:1 against adjacent colors. Decorative borders are exempt from being the sole functional signal. These rules follow [W3C text contrast](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html) and [non-text contrast](https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html).

Validate actual rendered text/background pairs and control boundaries in both themes; browser results are recorded in the decision brief. Decorative pastel fills are reserved for original illustrations and never substitute for semantic state colors.

## Typography, spacing and geometry

| Role | Implemented font | Size / line height | Weight |
| --- | --- | --- | --- |
| Product H1 | Manrope, system sans-serif fallback | 24px narrow, 28–36px wide / 1.2 | 700 |
| H2 | Manrope, system sans-serif fallback | 24px / 1.3 | 700 |
| H3 / H4 | Manrope, system sans-serif fallback | 18px / 1.4 | 700 |
| Body / forms | DM Sans, system sans-serif fallback | 16px / 1.5 | 400 |
| Compact table / supporting copy | DM Sans, system sans-serif fallback | 15px / 1.5; 13px / 1.5 | 400–500 |
| Identifiers / filenames when useful | System monospace | 13px / 1.5 | 400 |

Use relative font units in CSS and preserve browser zoom. Critical instructions, prices and errors must not use tiny type. Self-host the selected font assets after verifying their distribution license; use `font-display: swap` and fallback fonts. Do not introduce a third-party font request as a runtime requirement.

Use spacing steps 4, 8, 12, 24, 36, 48, 72, 96 and 120px. The 12px rhythm structures content; 4/8px handle icon and label detail. Use 48px between major mobile sections and 72–120px on spacious desktop public pages. Operational panels use 24–36px padding on wide screens and 16–24px on narrow screens.

Use a 1280px maximum public content width and 1440px maximum operational workspace width, with 16px narrow and 24–48px wide gutters. Initial layout breakpoints are 640px and 1024px; verify fluid behavior between them. Gallery columns adapt to available width; reduce columns rather than make labels or controls too small. Product panels use 24px radii, fields 14px, illustration/module frames 20–32px and action buttons pill shapes. Gallery preview frames are rounded while the full original photo remains visible with `object-fit: contain`. Separate surfaces with borders and tone changes, without decorative shadows, gradients or glow.

### Layout adaptations

Authentication uses a two-column illustration/story and form composition, collapsing to a compact illustrated banner above the form on mobile. Photographer and admin workspaces share a sidebar with Iconsax navigation and active-page indication; below 1024px it becomes a wrapping navigation grid. Dashboard/project cards show actual server state, the client selection allowance and a compact selection meter. Settings group project identity, source/access rules, publication controls and sharing actions separately. Billing separates checkout and history; admin summaries separate accounts, payments and jobs. Client pages use a smaller illustrated heading and full-width photo grids, with separate selection and delivery entry cards.

Original SVG scenes cover accounts, projects, selection, delivery, billing and administration. They have no definition IDs, carry no domain state and are hidden from assistive technology. Shared status and dialog controls use Iconsax with explicit accessible names. Product illustration entrances run once for 800ms with offsets between -3px and 4px, become static under reduced motion and never gate content. No perpetual decoration is introduced on operational screens.

### Task hierarchy and product language

The owner's October 24 follow-up requests clearer explanations and stronger layout hierarchy. Preserve the accepted typography, monochrome tokens and illustration language while adapting working screens to their actual task:

- Explain the action, its effect and the next step in Indonesian. Prefer visible user concepts such as saved choices, finished photos and gallery access over internal terms such as operation IDs, snapshots and published catalogues. Technical identifiers and export-escaping details belong in expandable secondary content.
- Distinguish saved draft choices from confirmed choices. Explain that confirmation locks changes and that the photographer must reopen selection. Describe the shared list across devices without implying independent selections per visitor.
- Keep project name, allowance and the selection source visible initially. Group optional contacts, unconfigured delivery, passwords/deadlines and Drive setup guidance into labeled native disclosures; expand fields with validation errors and existing delivery sources. Explain that the selection allowance limits client choices rather than gallery storage, that delivery can be added later and that saving a draft does not publish it.
- On wide project screens, separate the editing column from publication/sharing controls. On narrow screens, put sharing/publication before the settings form in both visual and DOM order. New projects show source setup guidance; the three-step explanation is omitted once selection is published. Archive and link replacement remain available in a secondary disclosure.
- Give client module cards a clear purpose and direct opening action. Consolidate selection count and review navigation, keep routine save feedback compact, and make failures persistent with explicit recovery choices. Folder navigation is a compact toolbar rather than another prominent card.
- Separate results/history from filename export on wide screens and stack them on narrow screens. Keep confirmation state visible so a saved draft is not mistaken for a final editing brief. Reduce header artwork to supporting scale on operational screens; real photos retain their full original rendering.

The clipboard action copies the existing project URL, provides success acknowledgement only after completion and selects the field for manual copying if access is denied. No messaging integration is implied. These are presentation changes within the existing product contracts, not authorization for additional provider services or deferred features. Follow-up verification is recorded in the [decision brief](LANDING_DIRECTION.md).

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

Use a shared ease-out curve `(0.2, 0, 0, 1)` for these finite transitions. Outside the approved public landing illustration exception above, do not use perpetual decorative animation. Scroll hijacking, parallax, mandatory splash sequences and animated photographic color changes remain excluded throughout. A busy indicator can loop only while genuinely pending; in reduced motion use a static status label instead. A selected count must not announce every animation frame.

Apply `MotionConfig reducedMotion="user"` globally and use `useReducedMotion` for custom recipes. The global option alone does not disable every custom loop, stagger or CSS animation; handle these explicitly. Follow the official [Motion accessibility guidance](https://motion.dev/docs/react-accessibility). Essential content/actions remain usable if animations are disabled or interrupted.

## Performance and responsive constraints

- Unlimited gallery content must not mean mounting or animating every photo at once. Use incremental loading and windowing where needed, with stable file identities, accessible item positions and predictable keyboard navigation. Do not discard saved selections when tiles unmount.
- Animate only visible mounted items. Avoid whole-catalogue `layout` measurement, scroll subscriptions per tile and synchronous decoding of all originals. Use responsive previews and lazy loading; reserve media dimensions. Do not claim a particular frame rate without measurement.
- Use layout/shared-image animation only when both endpoints are mounted and dimensions are known; fall back to a fade when the origin tile has been windowed out. Animation must not remount the gallery or cancel an active download.
- Keep sticky mobile actions clear of focus targets, safe areas and the on-screen keyboard. At 200% zoom, labels and critical controls remain reachable without truncating essential copy. Respect forced-colors mode with visible native/custom focus and selected indicators.

## Implementation review evidence

Delivery is covered by [TASK-001 and TASK-013](../planning/BACKLOG.md). [Project structure](../engineering/PROJECT_STRUCTURE.md) records implemented components; no component catalogue tool is required as a separate service. TASK-001 verifies synthetic component states and shells. [TASK-013 evidence](../planning/TASK-013_EVIDENCE.md) records local integrated journeys, actual browser appearance/focus/contrast checks and synthetic gallery/job/provider fixtures. Real-provider prerequisite acceptance remains open.

Before task completion, inspect public/auth, photographer, client and admin layouts at 360px and 1440px in both themes. Include pending, selected, locked, frozen, empty, error, success and disabled states; keyboard/lightbox focus; stored/system/blocked-storage appearance; and reduced-motion sessions. Measure rendered text/control contrast, verify no theme flash, and demonstrate a theme switch during an unsaved form, selection save and ZIP job without losing state.

Use a large synthetic gallery to verify bounded mounted tiles and no catalogue-wide animation work. Inspect interrupted navigation and rapid selection feedback under slow/failed requests. Capture screenshots and reproducible browser-check results; documentation token arithmetic is not application verification. [Backlog](../planning/BACKLOG.md) owns task status and completion evidence.
