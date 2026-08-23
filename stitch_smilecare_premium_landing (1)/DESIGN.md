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
  secondary: '#216963'
  on-secondary: '#ffffff'
  secondary-container: '#a8ece5'
  on-secondary-container: '#266d68'
  tertiary: '#2d5951'
  on-tertiary: '#ffffff'
  tertiary-container: '#467169'
  on-tertiary-container: '#c5f3ea'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#9cf2e8'
  primary-fixed-dim: '#80d5cb'
  on-primary-fixed: '#00201d'
  on-primary-fixed-variant: '#00504a'
  secondary-fixed: '#abefe8'
  secondary-fixed-dim: '#8fd3cc'
  on-secondary-fixed: '#00201e'
  on-secondary-fixed-variant: '#00504b'
  tertiary-fixed: '#bdece2'
  tertiary-fixed-dim: '#a2d0c6'
  on-tertiary-fixed: '#00201c'
  on-tertiary-fixed-variant: '#224e47'
  background: '#faf8ff'
  on-background: '#131b2e'
  surface-variant: '#dae2fd'
typography:
  display-hero:
    fontFamily: Plus Jakarta Sans
    fontSize: 64px
    fontWeight: '700'
    lineHeight: '1.1'
    letterSpacing: -0.02em
  display-hero-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 42px
    fontWeight: '700'
    lineHeight: '1.2'
    letterSpacing: -0.01em
  headline-h2:
    fontFamily: Plus Jakarta Sans
    fontSize: 40px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-h2-mobile:
    fontFamily: Plus Jakarta Sans
    fontSize: 32px
    fontWeight: '600'
    lineHeight: '1.3'
  headline-h3:
    fontFamily: Plus Jakarta Sans
    fontSize: 24px
    fontWeight: '600'
    lineHeight: '1.4'
  body-lg:
    fontFamily: Plus Jakarta Sans
    fontSize: 18px
    fontWeight: '400'
    lineHeight: '1.6'
  body-md:
    fontFamily: Plus Jakarta Sans
    fontSize: 16px
    fontWeight: '400'
    lineHeight: '1.6'
  label-caps:
    fontFamily: Plus Jakarta Sans
    fontSize: 12px
    fontWeight: '700'
    lineHeight: '1'
    letterSpacing: 0.05em
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 8px
  container-max: 1280px
  gutter: 24px
  margin-mobile: 16px
  margin-desktop: 48px
  section-gap: 120px
---

## Brand & Style

The design system is centered on a "Clinical Premium" aesthetic, balancing medical-grade precision with a warm, high-end hospitality feel. It targets a discerning audience that values technological advancement and dental wellness without the sterile anxiety of traditional clinics.

The visual style leverages **Modern Minimalism** with a **Tactile Softness**. It uses generous whitespace to create a "breathable" atmosphere, ensuring that information feels digestible and calm. While the core is clinical, the use of soft rounded corners and deep teal accents moves the needle from "hospital cold" toward "boutique sanctuary." The UI should feel like a premium physical space: orderly, quiet, and meticulously clean.

## Colors

The palette is anchored by a sophisticated **Teal Primary**, chosen for its associations with health and calm stability. 

- **Backgrounds**: Use the off-white `#F8FAFC` for the page body to reduce glare and provide a subtle contrast against pure white cards.
- **Surfaces**: Pure white (`#FFFFFF`) is reserved for interactive cards, modals, and navigation components to signify hygiene and clarity.
- **Typography**: High-contrast dark text (`#0F172A`) ensures maximum readability, while the muted grey (`#64748B`) is used for auxiliary information and meta-data to maintain a clear visual hierarchy.
- **Accents**: Use the `Primary Soft` (`#CCFBF1`) for highlight states, tag backgrounds, and subtle callouts to prevent the interface from feeling too heavy.

## Typography

This design system uses **Plus Jakarta Sans** for its modern, friendly, yet professional character. The soft curves of the typeface mirror the "rounded" shape language of the UI elements.

- **Hero Headlines**: Utilize negative letter spacing on larger displays to create a tight, editorial look.
- **Body Text**: Maintain a line height of 1.6 to ensure long-form medical information is comfortable to read.
- **Labels**: Use the `label-caps` role for category tags or small subtitles to add a layer of structured authority without increasing font size.

## Layout & Spacing

The layout philosophy follows a **Fixed Grid** model for desktop, centered within a maximum width of 1280px to prevent excessive line lengths. 

- **Grid**: A 12-column grid system is used for desktop, 8-column for tablet, and 4-column for mobile.
- **Rhythm**: All spacing (padding, margins) must be a multiple of the **8px unit**.
- **Breathability**: Use the `section-gap` (120px) liberally between major landing page sections to reinforce the premium, unhurried feel. 
- **Content Density**: Low. Elements should be spaced generously to prevent cognitive load, mirroring the calm environment of the physical clinic.

## Elevation & Depth

To maintain a clean and clinical appearance, the design system avoids heavy shadows. Instead, it utilizes **Ambient Shadows** and **Tonal Layering**:

- **Card Elevation**: Use an extremely diffused shadow: `0px 4px 20px rgba(15, 23, 42, 0.04)`. This creates a soft lift that suggests depth without appearing "dirty" or heavy.
- **Surface Transitions**: For interactive states (like hovering over a service card), the shadow should slightly deepen to `0px 10px 30px rgba(15, 23, 42, 0.08)` and the element should subtly scale (1.01x).
- **Overlays**: Modals and dropdowns use a soft backdrop blur (8px) on the underlying content to focus the user's attention while maintaining the airy transparency of the brand.

## Shapes

The shape language is consistently **Rounded**. This softens the "clinical" edge of the design, making the brand feel more approachable and modern.

- **Base Components**: Buttons and input fields use `rounded` (0.5rem / 8px).
- **Container Elements**: Cards, images, and larger containers use `rounded-lg` (1rem / 16px) or `rounded-xl` (1.5rem / 24px) to define major content areas.
- **Photography**: All imagery should feature rounded corners to match the UI components. Avoid sharp, 90-degree angles in the interface layout.

## Components

### Buttons
- **Primary**: Filled with `#0F766E`, white text. No border. High-quality hover state shifts to `#115E59`.
- **Secondary**: Ghost style with a 1.5px border of `#0F766E` and matching text color.
- **Action Size**: Buttons should have ample internal padding (16px top/bottom, 32px left/right) to feel significant and premium.

### Cards
- Surfaces are `#FFFFFF` with the defined ambient shadow. 
- Padding inside cards is typically 32px to maintain the "breathable" theme.
- Service cards should integrate high-quality, brightly lit photography.

### Input Fields
- Backgrounds are white with an `#E2E8F0` border.
- On focus, the border transitions to `#0F766E` with a subtle outer glow using the `Primary Soft` color.

### Chips & Badges
- Used for dental specialties or status indicators.
- Use the `Primary Soft` (`#CCFBF1`) background with `Primary Dark` (`#115E59`) text for a low-contrast, sophisticated look.

### Interactive Lists
- For treatment lists or patient history, use clean rows separated by 1px `#E2E8F0` lines. 
- Avoid icons inside lists unless they are minimal, monochrome line icons (2px stroke).