---
name: Clinical Precision
colors:
  surface: '#faf8ff'
  surface-dim: '#d2d9f4'
  surface-bright: '#faf8ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f2f3ff'
  surface-container: '#eaedff'
  surface-container-high: '#e2e7ff'
  surface-container-highest: '#dae2fd'
  on-surface: '#131b2e'
  on-surface-variant: '#3e4947'
  inverse-surface: '#283044'
  inverse-on-surface: '#eef0ff'
  outline: '#6e7977'
  outline-variant: '#bdc9c6'
  surface-tint: '#006a63'
  primary: '#005c55'
  on-primary: '#ffffff'
  primary-container: '#0f766e'
  on-primary-container: '#a3faef'
  inverse-primary: '#80d5cb'
  secondary: '#505f76'
  on-secondary: '#ffffff'
  secondary-container: '#d0e1fb'
  on-secondary-container: '#54647a'
  tertiary: '#0047bf'
  on-tertiary: '#ffffff'
  tertiary-container: '#1e5fe7'
  on-tertiary-container: '#e6e9ff'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#9cf2e8'
  primary-fixed-dim: '#80d5cb'
  on-primary-fixed: '#00201d'
  on-primary-fixed-variant: '#00504a'
  secondary-fixed: '#d3e4fe'
  secondary-fixed-dim: '#b7c8e1'
  on-secondary-fixed: '#0b1c30'
  on-secondary-fixed-variant: '#38485d'
  tertiary-fixed: '#dbe1ff'
  tertiary-fixed-dim: '#b4c5ff'
  on-tertiary-fixed: '#00174b'
  on-tertiary-fixed-variant: '#003ea8'
  background: '#faf8ff'
  on-background: '#131b2e'
  surface-variant: '#dae2fd'
typography:
  display-lg:
    fontFamily: Inter
    fontSize: 30px
    fontWeight: '600'
    lineHeight: 38px
    letterSpacing: -0.02em
  display-md:
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
  section-title:
    fontFamily: Inter
    fontSize: 18px
    fontWeight: '500'
    lineHeight: 26px
  body-md:
    fontFamily: Inter
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  body-sm:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '400'
    lineHeight: 20px
  label-caps:
    fontFamily: Inter
    fontSize: 12px
    fontWeight: '600'
    lineHeight: 16px
    letterSpacing: 0.05em
  data-tabular:
    fontFamily: Inter
    fontSize: 14px
    fontWeight: '500'
rounded:
  sm: 0.125rem
  DEFAULT: 0.25rem
  md: 0.375rem
  lg: 0.5rem
  xl: 0.75rem
  full: 9999px
spacing:
  unit: 4px
  container-margin: 24px
  gutter: 16px
  section-gap: 32px
  compact-padding: 8px
  standard-padding: 16px
---

## Brand & Style

The design system is engineered for high-performance clinical environments. It prioritizes information density, visual clarity, and a sense of institutional security. The style is **Corporate / Modern** with a lean toward **Minimalism**, stripping away superfluous ornamentation to focus on data accuracy and user workflow.

The emotional response should be one of competence and reliability. By utilizing a sophisticated teal palette rather than standard medical blues, the UI feels "High-Tech" rather than "Clinical." The interface utilizes sharp layouts, subtle tonal layering, and professional-grade typography to differentiate itself from generic SaaS templates.

## Colors

The palette is anchored by a deep Teal primary color, signaling both health and technical sophistication. 

- **Primary & Action:** Teal is used for primary actions, progress indicators, and active states. 
- **Surface Strategy:** The system uses a triple-layer background approach: `#F8FAFC` for the global canvas, `#FFFFFF` for primary content cards/panels, and `#F1F5F9` for nested utility areas or secondary sidebars.
- **Feedback:** Semantic colors (Success, Warning, Danger) are applied with high-contrast text on soft-tinted backgrounds for status badges to maintain professional restraint.

## Typography

This design system uses **Inter** exclusively for its neutral, highly legible characteristics. 

- **Data Density:** Body-sm (14px) is the standard for data-heavy tables and forms to maximize information density.
- **Numerical Integrity:** All currency, timestamps, and clinical measurements must use the `data-tabular` style (tabular-nums) to ensure vertical alignment in columns.
- **Hierarchy:** Section titles use a medium weight to provide clear structural anchoring without the visual weight of large display fonts.

## Layout & Spacing

The layout utilizes a **Fixed Grid** model for management screens to ensure data remains predictable across monitor sizes. 

- **Grid:** A 12-column system with 16px gutters. In dense clinical dashboards, a 4px baseline grid governs all internal component spacing.
- **Breakpoints:**
  - **Desktop (1280px+):** Full 12-column visibility with persistent left navigation.
  - **Tablet (768px - 1279px):** Collapsed sidebar, 8-column layout.
  - **Mobile (<767px):** Single column, 16px horizontal margins, top-bar navigation.
- **Information Density:** Components should favor `compact-padding` for data tables and `standard-padding` for form layouts.

## Elevation & Depth

To maintain a "High-Tech" and clean aesthetic, this design system avoids heavy shadows. 

- **Tonal Layers:** Depth is primarily communicated through color shifts. The background is `#F8FAFC`, while elevated "Surface" cards are `#FFFFFF`. 
- **Low-Contrast Outlines:** Every card and interactive element uses a 1px border (`#E2E8F0`). 
- **Shadows:** Use only one level of elevation shadow for floating elements like dropdowns or modals: `0px 4px 12px rgba(15, 23, 42, 0.08)`. For standard page elements, rely on borders rather than shadows.

## Shapes

The shape language is **Soft**. A 4px (0.25rem) radius is the standard for buttons, input fields, and small components. This provides a professional, "tooled" look that feels more precise than fully rounded alternatives.

- **Standard (rounded):** 4px for buttons, inputs, and checkboxes.
- **Large (rounded-lg):** 8px for cards and primary containers.
- **Extra Large (rounded-xl):** 12px for modals and large flyout panels.

## Components

- **Buttons:** Primary buttons use the Teal palette with white text. Secondary buttons use a white background with a 1px border. States (Hover/Active) are signaled by shifting to the darker `primary_hover` teal.
- **Input Fields:** Use 1px borders in `#E2E8F0`. Focus states use a 2px Teal border with no outer glow. Labels are 12px Medium, positioned above the field.
- **Data Tables:** High-density design. Row height is 40px. Header background is `#F1F5F9`. Borders are used between rows, but not columns, to maintain horizontal flow.
- **Chips/Badges:** Small (12px), semi-bold text. For status indicators, use `primary_soft` background with `primary_color` text for a clean, non-obtrusive look.
- **Cards:** White background, 1px border, 8px corner radius. No shadow unless the card is interactive or draggable.
- **Clinical Components:** Dental charts and treatment plans should use high-contrast outlines and the Teal accent color to highlight selected areas, avoiding red/green unless indicating actual pathology or health.