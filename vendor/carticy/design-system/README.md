# Carticy Design System

Foundation CSS variables for all Carticy products. Provides brand colors, spacing scale, typography, and visual tokens.

## Installation

```bash
composer require carticy/design-system
```

## Usage

### In WordPress Plugins

```php
wp_enqueue_style(
    'carticy-design-system',
    plugins_url('vendor/carticy/design-system/dist/design-system.css', __FILE__),
    [],
    '1.0.0'
);
```

## CSS Variables

### Colors
- `--carticy-primary`: #1A0E6D (Deep purple, primary brand color)
- `--carticy-accent-1`: #361CBB (Medium purple accent)
- `--carticy-accent-2`: #8A87FE (Light purple accent)
- `--carticy-shade-50` through `--carticy-shade-950`: Purple scale
- `--carticy-grey-light`, `--carticy-grey`, `--carticy-grey-dark`: Grey scale
- `--carticy-base`: #FFFFFF (Base white)

### Spacing (8px base scale)
- `--carticy-spacing-xs`: 8px
- `--carticy-spacing-sm`: 16px
- `--carticy-spacing-md`: 24px
- `--carticy-spacing-lg`: 32px
- `--carticy-spacing-xl`: 40px

### Typography
- `--carticy-font-size-sm`: 14px
- `--carticy-font-size-base`: 16px
- `--carticy-font-size-lg`: 20px
- `--carticy-font-size-xl`: 24px
- `--carticy-line-height`: 1.6

### Visual Tokens
- `--carticy-radius`: 4px (Border radius)
- `--carticy-shadow-sm`, `--carticy-shadow-md`, `--carticy-shadow-lg`: Box shadows

## Development

```bash
# Build CSS
npm run build

# Watch for changes (copies source to dist)
npm run watch
```

## License

Proprietary - For Carticy internal use only
