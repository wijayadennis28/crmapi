---
name: TOP CRM Monthly Report
description: A packing-docket finance desk for the TOP group.
colors:
  ink: "#141414"
  paper: "#fbfbf8"
  surface: "#ffffff"
  concrete: "#eceee9"
  rule: "#d5d8d2"
  muted: "#62665f"
  signal: "#c62828"
  signal-soft: "#fdecec"
  signal-deep: "#9f1d1d"
  signal-on-dark: "#ff8a80"
  ok: "#1f6b4f"
  danger: "#7f1d1d"
  rail-hover: "#22221f"
  rail-line: "#2a2a27"
  row-hover: "#fdf1f0"
  surface-hover: "#fdfdfb"
  dash: "#c3c7bf"
  chart-grid: "#e6e8e2"
  print-black: "#000000"
typography:
  wordmark-xl:
    fontSize: "72px"
  wordmark-lg:
    fontSize: "54px"
  wordmark-md:
    fontSize: "46px"
  wordmark:
    fontSize: "38px"
  headline-lg:
    fontSize: "30px"
  display:
    fontFamily: "Bricolage Grotesque, Archivo, Helvetica Neue, Arial, sans-serif"
    fontSize: "28px"
    fontWeight: 700
    lineHeight: 1.05
    letterSpacing: "-0.025em"
  headline:
    fontSize: "26px"
  title-lg:
    fontSize: "24px"
  title:
    fontSize: "22px"
  title-sm:
    fontSize: "20px"
  body-lg:
    fontSize: "18px"
  body:
    fontFamily: "Public Sans, Archivo, Helvetica Neue, Arial, sans-serif"
    fontSize: "14px"
    fontWeight: 400
    lineHeight: 1.45
  body-md:
    fontSize: "13px"
  data:
    fontSize: "12.5px"
  data-sm:
    fontSize: "12px"
  small:
    fontSize: "11.5px"
  small-xs:
    fontSize: "11px"
  label-lg:
    fontSize: "10.5px"
  label-md:
    fontSize: "10px"
  label:
    fontFamily: "ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, monospace"
    fontSize: "9.5px"
    fontWeight: 700
    letterSpacing: "0.1em"
  label-sm:
    fontSize: "9px"
  micro:
    fontSize: "8.5px"
  micro-xs:
    fontSize: "8px"
rounded:
  sm: "1px"
  md: "2px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "16px"
  lg: "24px"
  xl: "32px"
components:
  button-primary:
    backgroundColor: "{colors.ink}"
    textColor: "{colors.paper}"
    rounded: "{rounded.md}"
    padding: "0 12px"
    height: "34px"
  button-primary-hover:
    backgroundColor: "{colors.signal}"
    textColor: "{colors.ink}"
  button-signal:
    backgroundColor: "{colors.signal}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    padding: "0 12px"
    height: "34px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "36px"
---

# Design System: TOP CRM Monthly Report

## Overview

**Creative North Star: "The Packing Docket"**

A finance desk modeled on warehouse paperwork: shipping dockets, packing slips, stamped approvals, and ledger rules. The interface is paper on a concrete work surface, with ink structure and one strip of signal red. It is dense because the work is dense.

It refuses the generic SaaS dashboard: no blue accent, no soft rounded cards, no shadowed stat tiles, no gradient chrome. It also refuses literal pastiche: no cardboard texture, no fake barcodes, no novelty stamps where a status label will do.

**Key Characteristics:**
- Hard rules, square sheets, tabular numbers.
- One signal color, used for selection, totals, and focus.
- Dense tables and a persistent ledger rail.
- Stamped statuses, numbered sections, and document-style headers.
- Print-first report layout.

## Colors

A restrained ink-and-paper palette with one high-visibility accent.

### Primary
- **Signal Red** (`#c62828`): active navigation, total rows, primary actions, focus marks. It should occupy less than 15% of a screen.
- **Signal Red Deep** (`#9f1d1d`): hover and emphasis on red surfaces.
- **Signal Red on Ink** (`#ff8a80`): small red marks on the ink rail, where the main red is too dark.
- **Signal Red Soft** (`#fdecec`): tinted total rows and focused inputs.

### Neutral
- **Ink** (`#141414`): rail, body text, strong rules, primary buttons.
- **Concrete** (`#eceee9`): application ground.
- **Paper** (`#fbfbf8`): page surface.
- **White** (`#ffffff`): tables and sheets.
- **Rule** (`#d5d8d2`): hairlines.
- **Muted** (`#62665f`): labels, metadata, secondary text.

### Semantic
- **Deep Green** (`#1f6b4f`): closed, positive, recovered values.
- **Deep Oxblood** (`#7f1d1d`): missing, negative, destructive actions. It is darker than the signal red so the accent and the warning state do not collapse into one.

**The One Signal Rule.** Red is the only decorative accent. Never add a second accent hue to solve a hierarchy problem; use weight, rules, or space.

**The Two Reds Rule.** Signal red is for action and selection. Deep oxblood is for negative and missing states. Do not use the bright signal red for an error, and do not use oxblood for a primary button.

**The No Blue Rule.** Blue is not a brand color. Status is ink/green/oxblood; selection is signal red.

## Typography

**Display Font:** Bricolage Grotesque (fallbacks: Archivo, Helvetica Neue, Arial, sans-serif)  
**Body Font:** Public Sans (fallbacks: Archivo, Helvetica Neue, Arial, sans-serif)  
**Record Font:** system monospace (ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas)

**Character:** Bricolage Grotesque carries the wordmark and page titles with a collage-grotesque edge and visible ink traps. Public Sans carries body copy, table content, and figures with a neutral, compact rhythm that stays legible at 12–14px. Small uppercase record labels use the system mono, so metadata reads as stamped record rather than prose. The roles follow the DigitalNest type system; the packing-docket palette and layout stay.

### Hierarchy
- **Display** (Bricolage 700, 28px, 1.05, -.025em): page titles and wordmark only.
- **Headline** (Bricolage 700, 18–22px): report figures and prominent numbers.
- **Title** (Record 600, 9–11px, uppercase, .1em): sheet titles, column heads, tags, and stamps.
- **Body** (Public Sans 400, 14px, 1.45): prose, form labels, table content.
- **Label** (Record 700, 9.5px, uppercase, .1em): metadata, eyebrows, rail numbers.
- **Data** (Public Sans 400–700, 12.5px, tabular): every number in a table or input.

**The Tabular Rule.** All figures use tabular numerals. Align numbers right. Never center a money value.

## Layout

- Fixed 224px ink rail on desktop; off-canvas drawer below 900px.
- Main content maxes at 1400px with 28–32px gutters.
- Sheets use a 3px ink top rule, 1px body border, and square corners.
- Dashboard figures are a four-column strip divided by rules, not cards.
- Monthly input is a two-column layout: ledger matrix plus a 292px sticky rail. Below 1120px the rail stacks.
- Report is a single-column statement with numbered sections; tables can scroll horizontally on narrow screens.
- Spacing rhythm: 8 / 16 / 24 / 32px.

## Elevation & Depth

Flat by default. Depth comes from tonal layering (concrete ground, paper sheet, white table) and rule weight, not shadows. The only shadow-like separation allowed is a hard 2px rule above a sticky action bar.

**The Flat-By-Default Rule.** No box-shadow on cards, buttons, inputs, or navigation. A surface earns elevation by being a sheet, not by floating.

## Motion

Motion is continuity, not theater. The back office gets one authored navigation moment: the red active block in the rail slides from the section you left to the section you opened, while the page crossfades quickly. This is a functional signal of where you are, not a page-load sequence.

- Use the native View Transitions API for cross-document navigation; no JavaScript motion library.
- Keep navigation motion under 350ms: 120ms out, 220ms in, 320ms for the rail block.
- Disable the rail morph below 900px, where the rail is off-canvas.
- `prefers-reduced-motion: reduce` opts out; the app then navigates instantly.
- No scroll reveals, staggered entrances, or decoration loops in the back office.

## Shapes

- Sheets and tables: square corners.
- Buttons, inputs, tags: 2px radius maximum.
- Stamps: 1px border, slight -2° rotation, square corners.
- Chart bars: square; no rounded bar caps.
- Avoid pills except for tiny status dots.

## Components

### Buttons
- **Shape:** 2px radius, 34px height, uppercase 10.5px label.
- **Primary:** ink background, paper text; hover flips to signal red with paper text.
- **Signal:** signal red background, paper text; used for the main save/close action.
- **Outline:** transparent with ink border; hover fills red with paper text.
- **Focus:** 2px ink outline, 2px offset. Disabled: 40% opacity.

### Inputs
- **Style:** 1px muted border, white ground, 2px radius, 36px height. Desktop text 14px; small screens use 16px text and 44px touch targets.
- **Focus:** ink border plus 2px signal-red ring.
- **Matrix cells:** boxed whole-number inputs with a small `Rp` prefix; a dash placeholder marks a blank cell; pale red tint on focus; missing cells get a deep-oxblood left mark only after interaction.
- **Numbers:** whole rupiah or whole rolls only; decimals are rejected. Right-aligned, tabular; a small mono formatted preview sits under each cell.
- **Labels:** a visible month label sits beside the period picker; the Group column is explained above the matrix.

### Tables
- Header: 9.5px uppercase, muted, 1px ink bottom rule.
- Rows: 1px rule, pale red hover.
- Total rows: 2px ink top rule, 3px double bottom rule, pale red tint.
- Negative values in deep oxblood; positive in deep green.

### Navigation
- Ink rail with numbered links (`01`–`04`).
- Active: signal red field, paper text, red left mark.
- Mobile: sticky ink bar with a `Menu` label; drawer closes on navigation or scrim click.

### Signature Components
- **Stamp:** open/closed status, bordered and slightly rotated.
- **Completion ticks:** 26 small boxes; filled boxes turn signal red.
- **Target meter:** signal-red fill against a bordered track, with a black target tick and a legend.
- **Route control:** Auto / TOP / MMP / SAE segmented radio per mapping row.

## Do's and Don'ts

### Do:
- **Do** keep tables dense and numbers tabular.
- **Do** use one signal red per screen for selection, totals, or the primary action.
- **Do** use hard rules, square sheets, and stamped statuses to create hierarchy.
- **Do** preserve the print stylesheet on the report; hide navigation and actions.
- **Do** show missing inputs and fallback mappings explicitly.

### Don't:
- **Don't** add gradients, glassmorphism, glow, or soft card shadows.
- **Don't** introduce blue, purple, or a second accent hue.
- **Don't** round cards or use pill-shaped containers.
- **Don't** use emoji or icon tiles as navigation.
- **Don't** hide the API/manual distinction; every figure must show its source.
- **Don't** sacrifice table density for decorative whitespace.
