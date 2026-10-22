# Fastpik Reference Analysis

## Scope and evidence

Reference: [Fastpik](https://fastpik.id/id), inspected through public pages and owner-authorized photographer accounts on October 21, 2026. The signed-in product displayed version 2.0.6. This is a dated external observation, not a specification of Fastpik internals or a promise that the service will remain unchanged.

The first account's trial had expired. A second account allowed fresh-project tests. Three synthetic projects were created: `SnapPik Audit 2026-10-21`, `SnapPik Batch A`, and `SnapPik Batch B`, grouped in a `SnapPik Audit` folder. A studio display name and a 4R print template were configured for testing. Dummy phone numbers were used, and no WhatsApp messages were sent. No source Drive photos were changed or deleted. Credentials, client capability URLs, and source folder identifiers are deliberately excluded here.

Evidence labels:

- **Tested:** a workflow was performed and the resulting visible or downloaded state inspected.
- **Inspected:** controls, descriptions, or configuration were seen; the whole behavior was not proven.
- **Public claim:** described on Fastpik's public pages, without independent end-to-end verification.
- **Unavailable:** no authorized environment or necessary external resource was available.

## Actors and boundaries

Fastpik's photographer workspace labels parts of its UI as “admin.” It manages that photographer's clients/projects. No platform-owner/super-admin credentials were available. SnapPik's platform-admin flow in PRD is a product design, not a discovered Fastpik platform-admin screen.

## Feature inventory

| Area | Observed behavior | Evidence |
| --- | --- | --- |
| Public site | Product, pricing, FAQ, terms/privacy links; Indonesian/English and theme controls | Inspected |
| Account access | Email/password sign-in; registration, Google login, forgot-password and remember-me controls | Sign-in tested; other auth paths inspected only |
| Photographer dashboard | Project totals, active/expired links, client/extra progress, upcoming deadlines, recent projects | Inspected |
| Account/package | Trial counter and expiration, current package, subscription-history view | Inspected; three-project trial limit tested |
| Project creation/edit | Client name, Drive source, subfolders, cover, initial layout, notes, WhatsApp contacts, selectable client modules | Tested for Drive creation/edit; NAS source inspected only |
| Initial configuration | New account creation form initially failed to load settings; after saving initial studio settings, creation worked | Tested recovery sequence; internal cause unknown |
| Contact validation | Creation required valid client/admin WhatsApp numbers even without sending a message | Tested |
| Source and folders | Owner-supplied root contained no direct photos and two subfolders: Original with 301, Edited with 83 | Tested with subfolder detection on |
| Gallery entry | Cover image, project name, menu of enabled modes, locale/theme controls | Tested |
| Selection | Click selection, visible count, maximum-choice feedback, review, edit before confirmation | Tested; fourth photo rejected at allowance three |
| Autosave and tracking | Photographer status showed three choices before confirmation, with “selecting” status | Tested across client and photographer views; offline durability was not tested |
| Confirmation | Confirmation dialogue, locked-success page, optional WhatsApp handoff | Tested through lock; no WhatsApp handoff sent |
| Underfilled allowance | One photo could be confirmed with maximum allowance two | Tested on Batch A |
| Reopening | Photographer enabled choosing again; client pressed Choose Again; prior three choices remained editable | Tested |
| Photo viewer | Zoom, thumbnails, navigation controls, selection control, keyboard hints | Open/close and selection context tested; every keyboard/gesture was not tested |
| Photo notes | Up to 500 characters shown by UI; note saved and visible to photographer | Save/display tested; boundary-length enforcement not tested |
| Extra photos | Separate allowance and confirmation; prior primary choices locked and displayed separately | Tested: three primary plus one extra |
| Print photos | Global enable, reusable size/quantity templates, per-project selection, optional full-quota requirement | Tested: 4R x 2; 1/2 rejected and 2/2 confirmed |
| Downloads | Individual download control, selected-photo ZIP, progress/stop controls, Download All | Individual file and two-photo ZIP tested; stop not exercised |
| Project organization | Search, filters, ordering, folders, manage/select/move controls | Folder creation and bulk move of three projects tested; filter options inspected |
| Batch | Multi-row form and advanced per-row settings; spreadsheet import and Client Desk entry points | Two-row creation tested; file import and Client Desk sync not executed |
| Sharing/reminders | Copy links/templates, WhatsApp destinations, reminder templates, RAW request to freelance | Copy-success feedback tested; message sending not executed |
| Project gates | Separate selection/download/extra/print duration and password fields, shared-password toggles | Inspected; new passwords and expiry transitions not exercised |
| Expired projects | Existing expired project showed disabled download and confirmed selection results | Inspected on first account; passage of a live deadline not simulated |
| Notifications | Selecting, confirmed, choosing-again, package/deadline notices; unread indicators | State-change notices observed; sound/mark-all not tested |
| Customization | Vendor URL slug, entry/menu descriptions, metadata SEO variables, project defaults, cover picker | Studio-name save tested; remaining controls inspected |
| Telegram | Chat ID, language, selection/download reminder types and H-1/H-3/H-5/H-7/H-14 controls | Inspected; bot not connected and no notification sent |
| Client Desk | Enable control, API-key generation, sync status/log | Inspected; no key generated and no integration activated |
| NAS/WebDAV beta | Multiple NAS connections and public HTTPS/read-account instructions | Inspected; no NAS endpoint available |
| Custom domain | Branding/setup offer and configuration entry point | Public claim/inspected; no domain purchase or DNS changes |
| Platform administration | Accounts, merchant operations, tenancy and internal support tools | Unavailable |

The public [feature page](https://fastpik.id/id/features) also claims nested folders to five levels, drag-and-drop, responsive design, batch spreadsheet import, review mode, extra photos, Telegram, custom domain, themes and multiple languages. Only the tested/inspected portions above have direct evidence. The [pricing page](https://fastpik.id/id/pricing) described trial and paid options with Mayar/QRIS and manual renewal at inspection time; no payment was made.

## Download findings

1. An individual edited JPEG reached the browser's download destination.
2. For a two-photo selection, the first received ZIP contained only one photo. A single repeat produced both photos. This establishes an inconsistent sample, not its root cause or a general failure rate. The original incomplete archive and repeated complete archive were inspected as ZIP entries.
3. Download All displayed a dialogue directing the client to Google Drive for all 384 source photos, even from the Edited folder view. No all-photo ZIP was produced by that button in this test.
4. ZIP entries preserved an Edited folder path. Behavior for duplicate filenames, large archives, expired links during download, and changed source files was not tested.

## Implications for SnapPik

Use [PRD](../product/PRD.md) for approved scope. The following conclusions explain its design; they do not add requirements independently:

- Separate the selection and finished-photo catalogues, rather than exposing both folders in one ambiguous selection list.
- Save choices before confirmation and make the pending/confirmed distinction visible.
- Keep allowance enforcement and confirmation atomic on the server.
- Prove complete single-archive generation; never equate “a ZIP downloaded” with “every expected file is included.”
- Provide recovery for unavailable settings, Drive errors, and failed archives. Requiring unrelated contact data should not block a basic gallery.
- Scope client access by project/module. The platform-admin workflow must be designed explicitly instead of inferred from a photographer sidebar.

## Limits of this analysis

This audit is not a Fastpik security assessment or access-control bypass test. Password protection, all registration/reset paths, exact deadline transitions, destructive deletion, large-scale ZIP behavior, offline/concurrent client edits, real payments, messages, external integrations, and platform-owner administration remain unverified end to end. No result here establishes Fastpik's database, framework, security guarantees, or internal billing implementation.
