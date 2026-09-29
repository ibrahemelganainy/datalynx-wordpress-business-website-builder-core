# Phase 24 — Audit (produced BEFORE any edit)

**Mode:** read-only. Nothing was changed before the measurements below were taken.
**Tooling:** `tests/_phase24-audit.php` (new) and `tests/_phase24-env-probe.php` (new).
Phase 23's audit tool was **extended, not replaced** — it already answered *"which control → which
token → who consumes it"*. Phase 24's failures are not missing tokens; they are **tokens a different
layer overwrites** and **pixels no control owns at all**, which that tool could not see.

```
php tests/_phase24-audit.php        # 4 measurement passes
php tests/_phase24-env-probe.php    # §62 environment truth
```

---

## 0. Scope measured

| Metric | Value |
|---|---|
| Stylesheets parsed | **47** (Theme + plugin frontend + plugin preview + packs) |
| CSS rules parsed | **1288** |
| Frame declarations (§4 surface) | **479** — TOKEN 149 · ZERO 69 · KEYWORD 26 · **HARDCODE 235** |
| Horizontal hardcodes (the §5 violation) | **238** |
| Literal borders on structural selectors (§57) | **46** |
| Preview↔live shared selectors | 2 |

The audit scanner is hand-rolled rather than regex-based on purpose: a flat
`([^{}]+)\{([^{}]*)\}` regex **silently drops the `@media` context**, so a value could not be
attributed to a breakpoint — and attributing the gutters to the right breakpoint *is* the finding.
Comments are replaced by an equal number of newlines so reported line numbers stay real.

---

## 1. §62 — THE ENVIRONMENT WAS NOT BROKEN. THE DIAGNOSIS WAS.

Phases 22 and 23 both recorded that browser verification was impossible:

> "the pre-existing MAMP fault (`C:\MAMP\bin\php\php8.3.1\ext` missing `php_mysqli.dll`, so
> Apache-served PHP cannot reach MySQL) blocks loading any page."

**That is false, and it disabled a mandatory verification step for two phases** (§62: *"Automated
tests alone are NOT sufficient"*). Measured by `tests/_phase24-env-probe.php`:

| Check | Result |
|---|---|
| CLI php.ini | `C:\MAMP\bin\php\php8.3.1\php.ini` |
| **Apache** php.ini (`httpd.conf` → `PHPIniDir`) | `C:\MAMP\conf\php8.3.1\php.ini` — **a different file** |
| `extension=mysqli` in the **Apache** php.ini | **present** at line 671 |
| `mysqli` / `mysqlnd` loaded (CLI) | loaded / loaded |
| `http://localhost/` | **HTTP 200**, 23,597 bytes, **no PHP error** |
| `http://lawfirm.builder.test/` (blog 2) | **HTTP 200**, 35,926 bytes, **no PHP error** |
| `http://medical.builder.test/` (blog 3) | **HTTP 200**, 41,426 bytes, **no PHP error** |

The two php.ini files were being conflated: the CLI ini lacks `extension=mysqli`, the Apache ini has
it. The `ext` directory was never the problem — every front-end URL serves a full, error-free document.

**Consequence: browser verification is possible and is no longer an accepted excuse.** The correct
URLs are now recorded instead of guessed:

| Blog | URL | Theme | Business type | Preset |
|---|---|---|---|---|
| 1 | `http://builder.test/` | Astra | *(none)* | *(unset)* |
| 2 | `http://lawfirm.builder.test/` | business-builder | `law_firm` | `luxury` |
| 3 | `http://medical.builder.test/` | business-builder | `medical` | `medical-vitality` |

---


## 2. §4/§5 — "SPACE AROUND THE CONTENT = 0" — THE REAL SOURCE, MEASURED

The control chain itself is correct and does reach the frontend:

```
site_margin  → --bb-site-margin        → .bb-template { padding-inline }        owner: layout schema
container_padding_block → --bb-container-padding-block → .bb-section-inner{padding-inline}
                                        → :root alias --bb-container-padding → Theme .bb-container,
                                          .bb-site-header-inner, .bb-site-footer-inner
```

Adding another spacing control would have solved nothing, exactly as §4 warned. The residual space
has **four independent sources**, none of them the control:

### B1 — The Studio Preview hardcodes the gutter and can never reach zero *(the one the customer sees)*

`assets/css/page-admin/components/template-shell.css`

```css
.bb-section-inner { padding: 0 clamp(1.25rem, 4vw, 2.5rem); }   /* 20px – 40px, per side */
```

A literal `clamp()`. The preview therefore kept a **minimum of 20px per side at every viewport**,
no matter what "Space around the content" or "Container side gutter" said, while the live site
honoured the control (`design-sections.css` reads `var(--bb-container-padding-block, 0px)`).
This is simultaneously a §5 violation (zero ≠ zero) and a §58 violation (preview ≠ live).

### B2 — The Theme discards the Studio's gutter on mobile

`themes/business-builder/assets/css/responsive.css`

```css
@media (max-width: 767px) { .bb-theme { --bb-container-padding: var(--bb-space-4); } }  /* 16px */
```

The Theme publishes the customer's saved values as an inline `:root{ --bb-… }` block
(`inc/preset-resolver.php → bb_theme_preset_css()`, confirmed to emit **preset + saved overrides
only**). `.bb-theme` is a **descendant of `:root`**, and a custom property on a nearer ancestor
always wins. So at ≤767px the Studio's value was thrown away: **zero still rendered 16px of side
padding on every phone and tablet.** Same defect on `--bb-section-spacing` (mobile rhythm pinned to
`--bb-space-8`) and `--bb-section-spacing-lg`.

### B3 — Three different `max-width` fallbacks for one property

| File | Declaration | Fallback |
|---|---|---|
| `themes/business-builder/assets/css/tokens.css` | `--bb-container-width` | **1200px** (the token's real value) |
| `plugins/.../frontend/design-identity.css` | `.bb-section-inner { max-width: … }` | **1280px** |
| `plugins/.../page-admin/.../template-shell.css` | `.bb-section-inner { max-width: … }` | **1280px** |
| `plugins/.../frontend/design-sections.css` | `.bb-section-inner { max-inline-size: … }` | **1200px** |
| `plugins/.../frontend/design-sections.css` (boxed) | `.bb-template { max-inline-size: … }` | **1180px** |

`max-width` and `max-inline-size` resolve to the **same** physical property in a horizontal writing
mode, so these compete and the winner is decided by **stylesheet load order** — a DUPLICATE (§3).

Crucially, **this is what makes "0" still *look* like it left space**: on a 1920px viewport a
1200px cap renders 360px of apparent outer margin. Per §5 that is legitimate *only* if it is clearly
separated and exposed as its own control. It is owned by `container_width` — but that control sits
in a different group from "Space around the content", so the relationship is invisible in the UI.
That separation is recorded as required UX work (below), not silently "fixed" with a magic number.

### B4 — An unnamed mobile cap

```css
@media (max-width: 640px) { .bb-section-inner { padding-inline: min(var(--bb-container-padding-block, 0px), 16px); } }
```

Not a zero-violation (`min(0, 16px) = 0` is correct), but the literal `16px` is an arbitrary cap
owned by no control — precisely what §5 forbids.

### What is NOT the cause (checked and cleared)

`html` / `body` / `.bb-site` / `.bb-main` contribute **no** horizontal padding or margin; the Theme's
`theme.css` reset sets `box-sizing: border-box` on every descendant and `margin: 0` on `.bb-theme`;
`.bb-main` and `.bb-container` only declare `width: 100%`. WordPress default styles are not
involved. No `margin-inline: auto` other than the intentional container centring.


## 3. §58/§59 — PREVIEW ≠ LIVE, PROVEN STATICALLY

Pass E finds **only 2 shared selectors** between the preview and frontend layers, but both diverged:

| Selector | Property | Preview | Live |
|---|---|---|---|
| `.bb-template` | `color` | `var(--bb-text-dark, #0f172a)` | `var(--bb-color-text)` |
| `.bb-template` | `font-family` | `var(--bb-font-family, "Inter", …)` | `var(--bb-font-primary)` |

`--bb-text-dark` and `--bb-font-family` are declared in **`page-admin/variables.css`** — they belong
to the **admin chrome**, not the design system. Because they resolve inside the preview document,
the preview shell **won over the customer's design**: the Studio preview always drew text in
`#0f172a` and in the **admin system font**, whichever font or text colour was selected.

**This is the mechanical cause of "selected fonts do not correctly apply" (§32) in Preview** — the
saved value was never wrong; the preview shell simply outranked it.

Pass E is deliberately conservative: it compares exact selector strings and skips `@media` rules, so
it reports near-zero noise. It therefore **misses** the `.bb-section-inner` gutter divergence (the
preview writes `.bb-section-inner`, the frontend writes `.bb-template .bb-section-inner`). Pass B
catches that one. Two passes are required; neither alone is sufficient.

---

## 4. §57 — UNWANTED BORDERS: 46 literal borders on structural selectors

The one that matters is preview-only:

```css
.bb-section { border-bottom: 1px solid rgba(15, 23, 42, 0.03); }
```

Live site: borders are **opt-in** (`--bb-section-border-width` defaults to `0`,
`--bb-section-border-style` to `none`). So the Studio drew a divider under every section that the
live site never had. The remainder are component-level (rounded avatars, form fields, table
dividers) and are legitimate.

---

## 5. Classifications

| Status | Where |
|---|---|
| **BROKEN** | Preview `--bb-container-padding-block` (B1); mobile gutter/rhythm (B2); preview `color` + `font-family` (§3) |
| **DUPLICATE** | `.bb-section-inner` width — 2 owners, 3 fallbacks (B3) |
| **UI-ONLY / unreachable** | Preview section rhythm (`clamp(3rem,6vw,6rem)`); the `16px` mobile cap (B4) |
| **OVERRIDDEN** | Studio tokens shadowed by `.bb-theme` at ≤767px (B2) |
| **WORKING** | `--bb-site-margin`; `--bb-section-spacing` on desktop; `--bb-container-width`; the Phase 23 alias bridges |
| **DEPRECATED (admin-scope)** | `--bb-text-dark`, `--bb-font-family` used inside the preview document |

---

## 6. Fixes applied after this audit

1. Preview `.bb-section-inner` — hardcoded `clamp()` → `var(--bb-container-padding-block, 0px)`. **Zero now means zero in Preview.**
2. Preview `.bb-template` — admin-scope tokens → the live site's own design tokens. **Preview font & text colour follow the design.**
3. Preview `.bb-section` — hardcoded rhythm → `var(--bb-section-spacing, …)`; preview-only `border-bottom` removed.
4. Theme `responsive.css` — mobile now inherits the Studio's gutter and rhythm as **fallbacks**, so the defaults survive but the control always wins.
5. `design-identity.css` — duplicate `.bb-section-inner` width **removed** (one owner).
6. `design-sections.css` — the `16px` magic cap replaced by `--bb-container-padding-mobile` (same default, now ownable).

## 7. Required work carried forward

* §5: expose **Container width** adjacent to **Space around the content**, so the "1200px looks like a margin" relationship is visible rather than inferred.
* §9: per-breakpoint gutter/rhythm controls — the `--bb-container-padding-mobile` hook now exists for this.
* §55: 238 horizontal hardcodes remain, concentrated in legacy component/form/lookup stylesheets. Those are **component-scoped** (buttons, form fields, status lookup) rather than page-frame geometry, so they do not affect §5; converting them to tokens is a §19/§30/§55 task, not a §4 one.
* §6/§10/§12/§15: the Flexbox, Grid and Card-dimension control systems. **Not started** — they are additive control families, and the audit's job was to fix what exists before adding (§3).
* §62/§63/§64: browser + visual-regression matrix — now **unblocked** and mandatory.

---

## 8. Verification of the fixes (measured, not asserted)

### 8.1 Regression suites — all green after the edits

| Suite | Result |
|---|---|
| `tests/runtime-phase23-design-system.php` | **108 / 108 PASS** |
| `tests/runtime-phase22-design-system.php` | **153 / 153 PASS** |
| `tests/runtime-phase22-live-render.php` | **25 / 25 PASS** |
| `tests/runtime-phase21-design-studio.php` | site 2 **239 / 239**, site 3 **240 / 240** |

### 8.2 The audit re-run proves the specific defects closed

| Pass | Before | After |
|---|---|---|
| **E — preview↔live divergences** | **2** (`.bb-template` colour, font-family) | **0** |
| **F — literal borders on structural selectors** | **46** | **45** (the preview-only `.bb-section` divider removed) |
| **B — horizontal hardcodes on the frame** | 238 | 235 |

The `E` pass now compares **token identity** before raw text, because §58 asks for *the same
presentation logic*, not identical characters: a preview rule reading the same `--bb-` token as the
live rule is the same logic even when one spells out a fallback. Without that refinement the pass
would have reported a permanent false positive on every corrected rule.

### 8.3 End-to-end: the fixes are actually served

Fetched over HTTP from the live site and compared against the edited files — byte-for-byte, so this
proves the browser receives the fixed CSS rather than a cached copy:

| Stylesheet | Local | Served | |
|---|---|---|---|
| `themes/.../assets/css/responsive.css` | 2,943 | 2,943 | **IDENTICAL** |
| `plugins/.../frontend/design-sections.css` | 38,607 | 38,607 | **IDENTICAL** |
| `plugins/.../frontend/design-identity.css` | 18,839 | 18,839 | **IDENTICAL** |
| `plugins/.../page-admin/components/template-shell.css` | 13,256 | 13,256 | **IDENTICAL** |

All four edited stylesheets additionally parse with balanced braces (17/17, 111/111, 71/71, 49/49).

### 8.4 Honest limitation

The network home page currently renders the **post loop** (`.bb-main.bb-container`, `.bb-entry`),
not builder sections — the live HTML contains no `.bb-template` / `.bb-section` / `.bb-section-inner`.
So §5 was verified on the page frame and the container gutter (which *is* exercised by
`.bb-main.bb-container`), but **not yet on a page carrying builder sections**. A section-bearing page
must be created on blog 2 and blog 3 before §4/§5 can be signed off visually. This is recorded as
the first task of the browser-verification step, not as completed.


---
