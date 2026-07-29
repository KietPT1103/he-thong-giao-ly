---
version: alpha
name: "Anthropic Home — Ivory & Slate"
description: "Anthropic's homepage uses a warm off-white ivory background (#faf9f5) paired with a near-black slate (#141413) to create a high-contrast, editorially serious aesthetic. Two proprietary typefaces. Anthropic Serif and Anthropic Sans. establish a clear hierarchy: the serif handles display and body reading, while the sans handles UI labels, navigation, and utility text. The layout is spacious with generous section padding, a constrained max-width container, and minimal use of color accent. The CTA button is a filled near-black pill. Depth is conveyed almost entirely through surface color contrast (ivory vs. dark slate sections) rather than shadows."
colors:
  ivory-medium: "#f0eee6"
  accent: "#c6613f"
  clay: "#d97757"
  ivory-light: "#faf9f5"
  primary-cta-fill: "#141413"
  slate-dark: "#141413"
  cloud-dark: "#87867f"
  cloud-medium: "#b0aea5"
  oat: "#e3dacc"
  slate-faded-10: "#141413"
typography:
  display-xl-sans-bold:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "57.73px"
    fontWeight: "700"
    lineHeight: "63.5px"
  display-xl-serif-regular:
    fontFamily: "\"Anthropic Serif\", Georgia, sans-serif"
    fontSize: "57.73px"
    fontWeight: "400"
    lineHeight: "63.5px"
  body-serif-m:
    fontFamily: "\"Anthropic Serif\", Georgia, sans-serif"
    fontSize: "20px"
    fontWeight: "400"
    lineHeight: "28px"
  body-serif-s:
    fontFamily: "\"Anthropic Serif\", Georgia, sans-serif"
    fontSize: "16px"
    fontWeight: "400"
    lineHeight: "22.4px"
  ui-sans-m-regular:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "16px"
    fontWeight: "400"
    lineHeight: "22.4px"
  ui-sans-m-medium:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "16px"
    fontWeight: "500"
    lineHeight: "22.4px"
  ui-sans-m-semibold:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "16px"
    fontWeight: "600"
    lineHeight: "22.4px"
  heading-sans-semibold:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "24px"
    fontWeight: "600"
    lineHeight: "31.2px"
  label-sans-s:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "12px"
    fontWeight: "400"
    lineHeight: "16.8px"
    letterSpacing: "-0.24px"
  label-sans-s-medium:
    fontFamily: "\"Anthropic Sans\", Arial, sans-serif"
    fontSize: "12px"
    fontWeight: "500"
    lineHeight: "16.8px"
  serif-subheading-semibold:
    fontFamily: "\"Anthropic Serif\", Georgia, sans-serif"
    fontSize: "18px"
    fontWeight: "600"
    lineHeight: "25.2px"
  mono-regular:
    fontFamily: "\"Anthropic Mono\", monospace"
    fontSize: "16px"
    fontWeight: "400"
    lineHeight: "22.4px"
rounded:
  radius-small: "0.25rem"
  radius-main: "0.5rem"
  radius-large: "1rem"
  radius-xl: "1.5rem"
  radius-round: "100vw"
spacing:
  space-1: "0.25rem"
  space-2: "0.5rem"
  space-3: "0.75rem"
  space-4: "1rem"
  space-5: "1.5rem"
  space-6: "2rem"
  space-7: "2.5rem"
  space-8: "3rem"
  space-9: "4rem"
  space-11: "6rem"
  section-space-small: "4rem"
  section-space-main: "10rem"
  site-gutter: "2rem"
  site-margin: "64px"
---

## Overview

Anthropic's homepage uses a warm off-white ivory background (#faf9f5) paired with a near-black slate (#141413) to create a high-contrast, editorially serious aesthetic. Two proprietary typefaces. Anthropic Serif and Anthropic Sans. establish a clear hierarchy: the serif handles display and body reading, while the sans handles UI labels, navigation, and utility text. The layout is spacious with generous section padding, a constrained max-width container, and minimal use of color accent. The CTA button is a filled near-black pill. Depth is conveyed almost entirely through surface color contrast (ivory vs. dark slate sections) rather than shadows.

**Signature traits:**
- Dual typeface system: Pairs "Anthropic Sans", Arial, sans-serif and "Anthropic Serif", Georgia, sans-serif across the type hierarchy.
- Soft, rounded geometry: Generous corner rounding up to 9999px.
- Layered elevation: Depth comes from 1 validated shadow token.

## Colors

The palette uses 10 validated color tokens across 1 theme profile. Semantic roles stay attached to observed usage so generation agents can choose accents without inventing new color meaning.

**Semantic naming:**
- **action-text** maps to `slate-dark`: Role "text" is grounded by usage context "Primary text, headings, nav links, borders, button fill — dominant foreground color across all zones".
- **surface-background** maps to `ivory-light`: Role "background" is grounded by usage context "Primary page background, inverted text on dark sections, nav background".
- **surface-primary** maps to `ivory-medium`: Role "primary" is grounded by usage context "Secondary surface, card backgrounds, section dividers".
- **border-border** maps to `cloud-medium`: Role "border" is grounded by usage context "Subtle borders, muted text, divider lines, footer secondary text".

### Primary Brand
- **Ivory Medium** (#f0eee6): Secondary surface, card backgrounds, section dividers. Role: primary. {authored: rgb(240, 238, 230), space: rgb}
- **Accent** (#c6613f): Deeper terracotta accent variant, paired with clay for brand warmth. Role: accent.
- **Clay** (#d97757): Brand accent swatch — reserved for highlights, not used in primary UI chrome. Role: accent.

### Text Scale
- **Primary CTA Fill** (#141413): Filled 'Try Claude' button background — the primary call-to-action surface. Role: text. {authored: rgb(20, 20, 19), space: rgb, alpha: 0.1}
- **Slate Dark** (#141413): Primary text, headings, nav links, borders, button fill — dominant foreground color across all zones. Role: text. {authored: rgb(20, 20, 19), space: rgb, alpha: 0.1}

### Interactive
- **Cloud Dark** (#87867f): Secondary muted text, nav dropdown borders, agate/caption text. Role: border. {authored: rgb(135, 134, 127), space: rgb}
- **Cloud Medium** (#b0aea5): Subtle borders, muted text, divider lines, footer secondary text. Role: border. {authored: rgb(176, 174, 165), space: rgb}
- **Oat** (#e3dacc): Footer accent surface, localized decorative border. Role: border. {authored: rgb(227, 218, 204), space: rgb}
- **Slate Faded 10** (#141413): Hairline borders, nav dropdown top border, subtle dividers. Role: border. {authored: rgb(20, 20, 19), space: rgb, alpha: 0.1}

### Surface & Shadows
- **Ivory Light** (#faf9f5): Primary page background, inverted text on dark sections, nav background. Role: background. {authored: rgb(250, 249, 245), space: rgb}

## Typography

Typography uses "Anthropic Sans", Arial, sans-serif, "Anthropic Serif", Georgia, sans-serif, "Anthropic Mono", monospace across extracted hierarchy roles. Keep hierarchy mapped to these token rows before adding decorative type styles.

Mixes "Anthropic Sans", Arial, sans-serif and "Anthropic Serif", Georgia, sans-serif and "Anthropic Mono", monospace for visual contrast. Weight range spans bold, regular, medium, semi-bold. Sizes range from 12px to 57.73px.

### Font Roles
- **Headline Font**: Anthropic Sans
- **Body Font**: Anthropic Sans

### Type Scale Evidence
| Role | Font | Size | Weight | Line Height | Letter Spacing | Stack / Features | Notes |
|------|------|------|--------|-------------|----------------|------------------|-------|
| Hero headline — largest display text on page | "Anthropic Sans", Arial, sans-serif | 57.73px | 700 | 63.5px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Hero headline serif variant — used for underlined emphasis words | "Anthropic Serif", Georgia, sans-serif | 57.73px | 400 | 63.5px | normal | "Anthropic Serif", Georgia, sans-serif | Extracted token |
| Primary body copy, article text, hero description paragraph | "Anthropic Serif", Georgia, sans-serif | 20px | 400 | 28px | normal | "Anthropic Serif", Georgia, sans-serif | Extracted token |
| Secondary body text, card descriptions | "Anthropic Serif", Georgia, sans-serif | 16px | 400 | 22.4px | normal | "Anthropic Serif", Georgia, sans-serif | Extracted token |
| Navigation links, general UI text | "Anthropic Sans", Arial, sans-serif | 16px | 400 | 22.4px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Nav labels, medium-emphasis UI text | "Anthropic Sans", Arial, sans-serif | 16px | 500 | 22.4px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Section subheadings, emphasized UI labels | "Anthropic Sans", Arial, sans-serif | 16px | 600 | 22.4px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Card titles, section headings | "Anthropic Sans", Arial, sans-serif | 24px | 600 | 31.2px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Captions, metadata labels, footer fine print | "Anthropic Sans", Arial, sans-serif | 12px | 400 | 16.8px | -0.24px | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Badge labels, tag text | "Anthropic Sans", Arial, sans-serif | 12px | 500 | 16.8px | normal | "Anthropic Sans", Arial, sans-serif | Extracted token |
| Card subheadings, pull quotes | "Anthropic Serif", Georgia, sans-serif | 18px | 600 | 25.2px | normal | "Anthropic Serif", Georgia, sans-serif | Extracted token |
| Code snippets, technical inline text | "Anthropic Mono", monospace | 16px | 400 | 22.4px | normal | "Anthropic Mono", monospace | Extracted token |

## Layout

Responsive system uses 3 breakpoint tier(s): mobile, tablet, desktop.

This system uses a 4px base grid with scale values 4, 8, 12, 16, 24, 32, 40, 48, 64, 96, 160.

### Responsive Strategy
- **mobile (<= 991px)**: Constrain layout for small viewports and prioritize vertical stacking.
- **tablet (768-991px)**: Increase spacing and column structure for medium-width viewports.
- **desktop (Unknown)**: Expand layout density and horizontal composition for wide viewports.

### Spacing System
| Token | Value | Px | Notes |
|------|-------|----|-------|
| space-1 | 0.25rem | 4 | Mapped to --_spacing---space--1 |
| space-2 | 0.5rem | 8 | Mapped to --_spacing---space--2 |
| space-3 | 0.75rem | 12 | Mapped to --_spacing---space--3 |
| space-4 | 1rem | 16 | Mapped to --_spacing---space--4 |
| space-5 | 1.5rem | 24 | Mapped to --_spacing---space--5 |
| space-6 | 2rem | 32 | Mapped to --_spacing---space--6 |
| space-7 | 2.5rem | 40 | Mapped to --_spacing---space--7 |
| space-8 | 3rem | 48 | Mapped to --_spacing---space--8 |
| space-9 | 4rem | 64 | Mapped to --_spacing---space--9 |
| site-margin | 64px | 64 | Mapped to --site--margin |
| space-11 | 6rem | 96 | Mapped to --_spacing---space--11 |
| section-space-main | 10rem | 160 | Mapped to --_spacing---section-space--main |

## Elevation & Depth

Keep depth flat unless validated shadow or interaction evidence appears in the extraction payload. Do not invent shadows beyond this evidence boundary.

### Shadow Evidence
| Shadow Token | Layers | Details |
|--------------|--------|---------|
| card-elevation | 3 | 0px 2px 2px 0px rgba(0, 0, 0, 0.01) |

### Interaction Signals
| Theme | Signal | Evidence |
|-------|--------|----------|
| Light | outline-color | rgb(20, 20, 19) ; rgb(250, 249, 245) ; rgb(176, 174, 165) |
| Light | outline-width | 3px ; 2px ; 0px |
| Light | outline-offset | 0px ; 4px ; -2px |
| Light | transform | matrix(1, 0, 0, 1, 0, 0) ; matrix(1, 0, 0, 1, 0, 1) ; matrix(1, 0, 0, 1, -50.4, 0) |

## Shapes

Shape language maps directly to rounded tokens. Keep component corners consistent with the role mapping below before introducing bespoke geometry.

### Radius Roles
| Token | Value | Px | Role Mapping |
|------|-------|----|--------------|
| radius-small | 0.25rem | 4 | Subtle corner |
| radius-main | 0.5rem | 8 | Control corner |
| radius-large | 1rem | 16 | Card corner |
| radius-xl | 1.5rem | 24 | Large surface corner |
| radius-round | 100vw | 9999 | Large surface corner |

### Geometry Evidence
| Radius Token | Shape | Units |
|--------------|-------|-------|
| radius-small | 0.25rem | rem |
| radius-main | 0.5rem | rem |
| radius-large | 1rem | rem |
| radius-xl | 1.5rem | rem |
| radius-round | 100vw | vw |

## Components

(none detected)

## Do's and Don'ts

Guardrails protect Dual typeface system, Soft, rounded geometry, Layered elevation without adding unsupported visual claims.

| Do | Don't |
|----|---------|
| Do maintain consistent spacing using the base grid | Don't make unsupported claims about absent visual features |
| Do maintain WCAG AA contrast ratios (4.5:1 for normal text) | Don't mix rounded and sharp corners in the same view |
| Do use the primary color only for the single most important action per screen |  |
| Do verify evidence before writing new design-system guidance |  |

## Responsive Evidence

### Breakpoints
| Name | Width | Key Changes |
|------|-------|-------------|
| Mobile | <= 479px | screen and (max-width: 479px) |
| Mobile | <= 501px | only screen and (max-width: 501px) |
| Mobile | <= 676px | screen and (max-width: 676px) |
| Mobile | <= 767px | screen and (max-width: 767px) |
| Breakpoint 5 | <= 991px | screen and (max-width: 991px) |
| Tablet | 768-991px | screen and (min-width: 768px) and (max-width: 991px) |
| Tablet | >= 768px | (min-width: 768px) |
| Breakpoint 8 | Unknown | (prefers-color-scheme: light) |

## Agent Prompt Guide

### Example Component Prompts
- Create button component using validated primary color role and spacing tokens.
- Create card component with mapped radius role and evidence-backed elevation.
- Create form input component using inferred typography hierarchy and border roles.

### Iteration Guide
1. Start with extracted palette and typography roles only.
2. Map spacing and radius directly from token tables before visual polish.
3. Apply component patterns one section at a time and compare against source intent.
4. Keep elevation claims tied to explicit evidence in output.
5. Iterate with smallest diffs and re-check section hierarchy after each change.
