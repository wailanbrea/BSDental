---
name: Clinical Precision
colors:
  surface: '#FFFFFF'
  surface-dim: '#cbdbf5'
  surface-bright: '#f8f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#eff4ff'
  surface-container: '#e5eeff'
  surface-container-high: '#dce9ff'
  surface-container-highest: '#d3e4fe'
  on-surface: '#0b1c30'
  on-surface-variant: '#3e4947'
  inverse-surface: '#213145'
  inverse-on-surface: '#eaf1ff'
  outline: '#6e7977'
  outline-variant: '#bdc9c6'
  surface-tint: '#006a63'
  primary: '#005c55'
  on-primary: '#ffffff'
  primary-container: '#0f766e'
  on-primary-container: '#a3faef'
  inverse-primary: '#80d5cb'
  secondary: '#3b665f'
  on-secondary: '#ffffff'
  secondary-container: '#bdece2'
  on-secondary-container: '#416c65'
  tertiary: '#7f4025'
  on-tertiary: '#ffffff'
  tertiary-container: '#9c573a'
  on-tertiary-container: '#ffe5db'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#9cf2e8'
  primary-fixed-dim: '#80d5cb'
  on-primary-fixed: '#00201d'
  on-primary-fixed-variant: '#00504a'
  secondary-fixed: '#bdece2'
  secondary-fixed-dim: '#a2d0c6'
  on-secondary-fixed: '#00201c'
  on-secondary-fixed-variant: '#224e47'
  tertiary-fixed: '#ffdbce'
  tertiary-fixed-dim: '#ffb598'
  on-tertiary-fixed: '#370e00'
  on-tertiary-fixed-variant: '#72361b'
  background: '#F8FAFC'
  on-background: '#0b1c30'
  surface-variant: '#d3e4fe'
  surface-muted: '#F1F5F9'
  text-main: '#0F172A'
  border-subtle: '#E2E8F0'
  success: '#16A34A'
  info: '#2563EB'
  warning: '#D97706'
  danger: '#DC2626'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 32px
    fontWeight: '700'
    lineHeight: 40px
    letterSpacing: -0.02em
  headline-md:
    fontFamily: Inter
    fontSize: 24px
    fontWeight: '600'
    lineHeight: 32px
    letterSpacing: -0.01em
  headline-sm:
    fontFamily: Inter
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-lg:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-md:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-md:
    fontFamily: Inter
    fontSize: 13px
    fontWeight: '500'
    lineHeight: 18px
    letterSpacing: 0.01em
  label-sm:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
  data-mono:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  base: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  sidebar-width: 260px
  sidebar-collapsed: 72px
  container-max: 1440px
---

## Brand & Style

The design system for this professional dental clinic ERP is built on the principles of **Clinical Precision** and **Premium Trust**. It moves away from the clutter of generic administration templates, favoring a high-end, specialized medical aesthetic that balances operational efficiency with a calming, professional atmosphere.

The style is **Modern Clinical Minimalism**. It utilizes heavy whitespace, crisp 1px borders, and a sophisticated teal primary palette to evoke cleanliness and technological advancement. Unlike typical SaaS products, this design system treats medical data (odontograms, clinical histories, and budgets) with a "density with clarity" approach—ensuring that complex information remains legible and accessible without overwhelming the practitioner.

Key characteristics:
- **Hygienic Clarity:** Ample negative space and a light-filled background (`#F8FAFC`) to reduce cognitive load in a high-stress clinical environment.
- **Structural Integrity:** Elements are contained within well-defined, soft-cornered surfaces with minimal, diffused shadows to maintain a flat but layered hierarchy.
- **Functional Sophistication:** Use of tabular numbers for financial data and high-contrast typography for critical clinical alerts.

## Colors

The color palette is anchored by a deep medical teal, chosen for its association with health, sterility, and professionalism.

- **Primary Teal (#0F766E):** Used for primary actions, active navigation states, and brand identifiers. It provides high contrast against the light surfaces.
- **Secondary Mint (#CCFBF1):** A "soft primary" used for backgrounds of highlighted items, selected rows, or light-mode buttons to maintain brand presence without the visual weight of the deep teal.
- **The Neutrals:** We use a cool-toned slate gray scale. The background (`#F8FAFC`) is slightly tinted to prevent eye strain from pure white, while the surfaces (`#FFFFFF`) pop forward to indicate interactive zones.
- **Semantic Logic:** Success, Warning, and Danger colors are used sparingly for status badges and clinical alerts. Within clinical contexts (like an odontogram), these colors represent specific medical conditions and should never be used decoratively.

## Typography

This design system uses **Inter** exclusively to ensure maximum legibility and a systematic, modern feel.

- **Data Density:** For financial figures, balances, and clinical measurements, the `data-mono` class must be applied. This enables "Tabular Figures" (`tnum`), ensuring that numbers align vertically in tables and budgets, which is critical for medical auditing.
- **Hierarchy:** Headlines use a semi-bold weight and tighter letter spacing to create a professional, grounded appearance. 
- **Body Text:** The standard reading size is 14px (`body-md`), which allows for significant information density required by ERP workflows without sacrificing readability.
- **Mobile Scaling:** For mobile viewports, `display-lg` should scale down to 24px and `headline-md` to 20px to prevent layout breaks in narrow columns.

## Layout & Spacing

The layout is a **Hybrid Grid System** designed for high-density professional use.

- **Sidebar Navigation:** A fixed left-hand sidebar (260px) serves as the primary navigation. It collapses to an icon-only view (72px) on medium screens to prioritize work area real estate.
- **The Work Canvas:** Content is housed in a "Surface" area with 24px padding on desktop and 16px on mobile.
- **12-Column Grid:** For dashboards and complex forms, use a 12-column grid with 20px gutters. 
- **Spacing Rhythm:** An 8px linear scale is used for all layout offsets. Use 4px (xs) for internal component spacing (e.g., icon to text) and 16px (md) for standard padding between sections.
- **Breakpoints:**
  - **Desktop (>=1280px):** Fixed Sidebar, Full Layout.
  - **Tablet (768px - 1279px):** Collapsed Sidebar (Rail mode) or Drawer.
  - **Mobile (<768px):** Bottom Sheet or Top-nav Drawer, stacked cards.

## Elevation & Depth

To maintain a "clinical" and "flat" aesthetic, the design system avoids heavy shadows and skeuomorphism. Instead, it uses **Tonal Layers** and **Low-Contrast Outlines**.

- **Surfaces:** The main background is `#F8FAFC`. All primary content "cards" use `#FFFFFF` with a 1px border of `#E2E8F0`. 
- **Shadows:** Use a single, "Subtle Lift" shadow for floating elements like Modals, Drawers, and Popovers: `0px 4px 12px rgba(15, 23, 42, 0.05)`. Avoid shadows on standard page sections or data tables.
- **Active States:** Instead of elevation, use color shifts. An active card or selected item should use a 1px border of `primary_color_hex` or a background tint of `secondary_color_hex`.
- **Glassmorphism:** Reserved exclusively for the Topbar during scroll, using a 12px backdrop-blur and 80% opacity on `#FFFFFF`.

## Shapes

The shape language is professional and approachable, avoiding both the clinical harshness of sharp corners and the "childish" nature of overly rounded pills.

- **Standard Radius:** 8px (`rounded-md`) is the default for buttons, input fields, and small cards.
- **Container Radius:** 12px (`rounded-xl`) is used for the main application container and large dashboard cards.
- **Interactive Elements:** Checkboxes use a 4px radius, while status badges and avatars use a 100% (circle) radius for quick visual differentiation.

## Components

### Buttons
- **Primary:** Solid `#0F766E` with white text. High-contrast. 8px radius.
- **Secondary:** Background `#CCFBF1` with `#0F766E` text. No border.
- **Ghost:** Transparent background with `#64748B` text, shifting to `#F1F5F9` on hover.

### Input Fields & Selects
- 1px border (`#E2E8F0`), 8px radius. 
- Focus state: 1px border `#0F766E` with a 2px outer ring of `#CCFBF1` at 50% opacity.
- Labels: `label-md` in `#0F172A`, placed 6px above the input.

### Data Tables
- Header: `#F1F5F9` background, `label-sm` text color.
- Rows: 1px bottom border only. Use `data-mono` for all numerical columns.
- Hover state: Row background changes to `#F8FAFC`.

### Status Badges (Chips)
- Rounded-full. Use a 10% opacity background of the semantic color (Success, Info, Danger) with a 100% opacity text color of the same hue.

### Specialized: The Odontogram
- **Anatomy:** Minimalist vector representation of teeth.
- **Interaction:** Pieces must change fill color based on clinical status (e.g., Red for Caries, Blue for Filled, Green for Healthy).
- **Labels:** Numbers must be `label-sm` and clearly positioned above/below the arcades.

### Specialized: Patient Header
- A permanent or semi-permanent banner in clinical views. Features a `PatientAvatar`, Name in `headline-sm`, and high-visibility clinical alerts (e.g., "Allergic to Penicillin") in a `Danger` badge.