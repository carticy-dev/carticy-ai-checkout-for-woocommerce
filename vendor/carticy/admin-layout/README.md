# Carticy Admin Layout

Reusable WordPress admin layout framework for all Carticy products. Provides unified header, footer, navigation, content structure, and notification system.

## Installation

```bash
composer require carticy/admin-layout
```

## Dependencies

- `carticy/design-system` - Required for CSS variables

## Usage

### Basic Layout Setup

```php
// Include template
require_once PLUGIN_DIR . '/vendor/carticy/admin-layout/templates/layout-wrapper.php';

// Or pass data to template
$page_data = [
    'page_title' => 'My Admin Page',
    'content' => function() {
        echo '<p>Page content here</p>';
    },
    'content_data' => [],
    'header_data' => [
        'actions' => '<button class="button">Action</button>'
    ],
    'footer_data' => [
        'product_name' => 'My Plugin',
        'product_version' => '1.0.0',
    ],
    'show_test_badge' => false,
];

extract($page_data);
require PLUGIN_DIR . '/vendor/carticy/admin-layout/templates/layout-wrapper.php';
```

### Enqueuing Assets in WordPress

```php
// Enqueue design-system first (dependency)
wp_enqueue_style(
    'carticy-design-system',
    plugins_url('vendor/carticy/design-system/dist/design-system.css', __FILE__),
    [],
    '1.0.0'
);

// Then enqueue admin-layout
wp_enqueue_style(
    'carticy-admin-layout',
    plugins_url('vendor/carticy/admin-layout/dist/admin-layout.css', __FILE__),
    ['carticy-design-system'],
    '1.0.0'
);
```

### Template Variables

#### layout-wrapper.php
- `$page_title` (string, required) - Page title for header
- `$content` (callable, required) - Content rendering function
- `$content_data` (array) - Data passed to content function
- `$header_data` (array) - Header configuration
- `$footer_data` (array) - Footer configuration
- `$show_notifications` (bool) - Display WordPress notifications

#### layout-header.php
- `$page_title` (string, required) - Page title
- `$show_test_badge` (bool) - Show test mode badge
- `$test_badge_text` (string) - Custom badge text
- `$header_data['actions']` (string) - Custom header HTML

#### layout-footer.php
- `$product_name` (string) - Product name (default: 'Carticy')
- `$product_version` (string) - Version number
- `$powered_by_url` (string) - Link URL (default: https://carticy.com/)
- `$logo_url` (string) - Logo image URL

## Features

### Layout Components
- **Header**: Page title, test mode badge, custom actions
- **Content Area**: White content box with notifications support
- **Footer**: Branding, version info, logo
- **Notifications**: WordPress admin notices with custom styling and dismiss functionality

### Navigation Systems
- **Tab Navigation**: `.carticy-tabs-wrapper` with `.carticy-tab` classes
- **Legacy Support**: Compatible with `.nav-tab-wrapper` and `.nav-tab`

### Table Styling
- Branded table styles for `.carticy-table` and `.widefat`
- Zebra striping for alternating rows
- Column width utilities (`.col-fixed-xs`, `.col-fixed-sm`, etc.)
- Hover states with Carticy brand colors

### Button System
- **Primary Button**: Carticy brand purple with hover states
- **Secondary Button**: Light purple background with borders
- Consistent styling across all button types

### Responsive Design
- Mobile-optimized layouts for tablets (782px) and phones (600px)
- Stacked navigation on small screens
- Flexible content padding

## CSS Classes

### Layout Structure
- `.carticy-admin-layout` - Main wrapper
- `.carticy-admin-header` - Header container
- `.carticy-admin-content` - Content area
- `.carticy-admin-footer` - Footer container

### Notifications
- `.carticy-admin-notifications` - Notification container
- `.notice.inline` - Inline notices (smaller, more subtle)
- `.notice-success`, `.notice-warning`, `.notice-error`, `.notice-info`

### Tables
- `.carticy-table` - Custom table styling
- `.col-fixed-xs`, `.col-fixed-sm`, `.col-fixed-md`, `.col-fixed-lg` - Fixed width columns
- `.col-constrained`, `.col-constrained-lg` - Responsive width columns
- `.col-actions` - Action column (centered, nowrap)

### Navigation
- `.carticy-tabs-wrapper` - Tab container
- `.carticy-tab` - Individual tab
- `.carticy-tab-active` - Active tab state

## Development

```bash
# Build CSS
npm run build

# Watch for changes
npm run watch
```

## File Structure

```
admin-layout/
├── src/
│   └── admin-layout.css          # Source CSS
├── dist/
│   └── admin-layout.css          # Built CSS
├── templates/
│   ├── layout-wrapper.php        # Main layout wrapper
│   ├── layout-header.php         # Header template
│   └── layout-footer.php         # Footer template
├── composer.json
├── package.json
└── README.md
```

## License

Proprietary - For Carticy internal use only
