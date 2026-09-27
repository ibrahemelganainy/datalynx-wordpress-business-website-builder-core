# PHASE 10 — COMPONENT ARCHITECTURE AUDIT & INVENTORY

Read-only audit performed before any change. Evidence quoted from source.

## 1. Where repeated card markup currently lives

| Existing markup | File | Renderer | Domain | Reusable? | Proposed component | Owner |
| --- | --- | --- | --- |
| Lawyer card `article.bb-lawyer-card` | `packs/LawFirm/Sections/LawFirmSections.php:772-824` | `render_lawyers_section()` | LawFirm | **Yes — domain** | `components/lawyer-card.php` | **LawFirm Pack** |
| Service card `article.bb-service-card` | same file `:867-883` | `render_legal_services_section()` | LawFirm | Yes — domain | `components/service-card.php` | **LawFirm Pack** |
| Practice area card `article.bb-practice-area-card` | same file `:921-937` | `render_practice_areas_section()` | LawFirm (taxonomy) | Yes — domain | `components/practice-area-card.php` | **LawFirm Pack** |
| Testimonial card `article.bb-testimonial-card` | same file `:981-1004` | `render_testimonials_section()` | LawFirm | Yes — domain | `components/testimonial-card.php` | **LawFirm Pack** |
| FAQ item `div.bb-faq-item` | same file `:1039-1042` | `render_faq_section()` | LawFirm | Yes — domain | `components/faq-item.php` | **LawFirm Pack** |
| Section heading `.bb-section-heading` | same file `:689-709` | `render_heading()` | Generic | **Yes — generic** | Theme `bb-section-heading` partial | **Theme** |
| Empty state `.bb-empty-state` | same file `:716-719` | `render_empty()` | Generic | **Yes — generic** | Theme `bb-empty-state` partial | **Theme** |
| Feature card `article.bb-feature-card` | `includes/Builder/SectionRenderer.php:528-542` | `render_features_section()` | Generic (core) | Partly — different structure (icon + h3 + p) | *see Rejected* | Plugin |
| Generic item card `.bb-section-item-card` | `SectionRenderer.php:348-381` | `render_generic()` | Generic (core) | No — dynamic key→element map | *see Rejected* | Plugin |
| Contact card `.bb-contact-card` | `SectionRenderer.php:641-672` | `render_contact_section()` | Generic (core) | No — one-off | *see Rejected* | Plugin |

## 2. The real, extractable duplication

The five LawFirm cards share **only a shell** (surface / border / radius / shadow / hover
lift), then diverge completely in their bodies:

```
Shared shell:      .bb-X-card { display:flex; border-radius; background; border;
                                box-shadow; overflow:hidden; transition; }
                   .bb-X-card:hover { transform: translateY(-3px); box-shadow: …; }

Divergent bodies:  lawyer   → photo + .bb-lawyer-body{h3>a, role, meta, phone, email, profile}
                   service  → image|icon + h3 + p
                   practice → image|icon + h3 + p
                   testimonial → image + rating + blockquote + h3 + p
                   faq      → h3 + .bb-faq-answer (a <div>, not an <article>)
```

Therefore (§22) the correct extraction is **NOT** one mega-card. It is:
1. **Theme** — a generic `.bb-card` shell + shared `bb-section-heading` / `bb-empty-state` partials (true generic reuse, owned by the Theme).
2. **LawFirm Pack** — one domain card template per entity, replacing the inline `echo` chains, **preserving every existing class**.

## 3. CSS dependencies (who styles these classes)

| Class | CSS file | Hardcoded values that violate §12 |
| --- | --- | --- |
| `.bb-lawyer-card` | `assets/css/frontend/section-lawyers.css` | `#ffffff`, `rgba(15,23,42,…)` |
| `.bb-service-card` | `section-legal-services.css` | `rgba(255,255,255,.9)`, `Georgia` (luxury) |
| `.bb-practice-area-card` | `section-practice-areas.css` | `#b8843c`, `#dcc29c`, `Georgia` |
| `.bb-testimonial-card` | `section-testimonials.css` | `#b8843c`, `rgba(255,255,255,.88)`, `Georgia` |
| `.bb-faq-item` | `section-faq.css` | `#b8843c`, `#f6e7cf`, `#dcc29c`, `Georgia` |
| `.bb-*` (dark mode) | `design-tokens.css:341-346` | — |

These are **plugin-owned** (§26 of Phase 9 / this phase §11-§12). Converting them to `--bb-*`
tokens is in-scope because the phase explicitly requires component CSS to consume tokens and
forbids hardcoded values where a token exists.

## 4. JS dependencies

**None of the card classes are referenced by JavaScript.** The frontend JS binds to
`data-bb-*` attributes and stable IDs:

| JS file | Selectors used | Card classes? |
| --- | --- | --- |
| `status-lookup.js` | `[data-bb-lookup*]`, `.bb-lookup-button` | **No** |
| `manual-payment.js` | `[data-bb-manual*]`, `[data-bb-copy]` | **No** |
| `receipt-modal.js` | `[data-bb-receipt*]`, `#bb-payment-receipt` | **No** |
| `payment-billing.js` | `[data-bb-billing*]` | **No** |

→ Card class names are a **CSS-only contract** and may be safely reused verbatim.

## 5. PHP hooks attached around the markup

- `apply_filters('bb_section_classes', …)`, `apply_filters('bb_section_attributes', …)` —
  on the `<section>` wrapper in `SectionRenderer::render_section()`, **not** on cards.
- `do_action('bb_render_section_' . $type, …)` — dispatches to the pack renderer method.
- **No hook is attached to an individual card** → extraction is free to add `bb_before_card` /
  `bb_after_card` hooks only if beneficial (none required to preserve behaviour).

## 6. Accessibility / semantic dependencies (must be preserved)

- Lawyer: `article` ▸ `h3` ▸ `a.bb-lawyer-link` (profile URL), `tel:`/`mailto:` links, `target="_blank" rel="noopener noreferrer"` on LinkedIn.
- Testimonial: `blockquote` for the quote; `h3` for the author; rating `div`.
- FAQ: `h3` = question, `.bb-faq-answer` = answer (no `<details>` — a plain styled list; **do not introduce an accordion**).
- All cards are `article` **except FAQ** which is a `div`.

## 7. Defects found in the audit

| # | Defect | Location | Impact |
| --- | --- | --- | --- |
| 1 | **Rating stars are mojibake** — literal `'â˜…'` (UTF-8 `★` double-encoded) instead of `★` | `LawFirmSections.php:990` | Broken/mojibake stars on every testimonial with a rating |
| 2 | Hardcoded design values instead of `--bb-*` tokens | `section-*.css` (4 files) | Presets cannot restyle cards; §11/§12 violation |

## 8. Ownership decisions

```
THEME owns:   .bb-card shell, section heading, empty state, tokens, layout primitives
PLUGIN owns:  SectionRenderer core section markup (feature/contact/generic) — out of scope
LAWFIRM owns: lawyer / service / practice-area / testimonial / faq component templates
```

## 9. Extraction plan (implementation order)

1. Fix defect #1 (mojibake stars) — mandatory, tiny.
2. Add generic Theme primitives: `bb-section-heading`, `bb-empty-state` partials + `.bb-card` shell token alignment.
3. Create 5 LawFirm component templates under `packs/LawFirm/Sections/components/`.
4. Rewire the 5 render methods to call the components (preserving classes, semantics, escaping).
5. Rewrite the 4 section CSS files + FAQ css to consume `--bb-*` tokens (no hardcoded values).
6. Regression: Phase 9 suite, plugin suites, live HTTP, RTL, mobile, presets.