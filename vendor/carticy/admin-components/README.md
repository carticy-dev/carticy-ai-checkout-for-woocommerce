# Carticy Admin Components

Reusable JavaScript components and utilities for Carticy WordPress admin interfaces. Provides tab navigation, notice dismissal, form utilities, and content box helpers.

## Installation

```bash
composer require carticy/admin-components
```

## Dependencies

- `carticy/design-system` - Design system CSS variables
- `carticy/admin-layout` - Admin layout framework
- jQuery 3.0+ (provided by WordPress)

## Usage

### Basic Setup

Enqueue the script in WordPress:

```php
wp_enqueue_script(
    'carticy-admin-components',
    plugins_url('vendor/carticy/admin-components/dist/admin-components.js', __FILE__),
    ['jquery'],
    '1.0.0',
    true
);
```

### Auto-Initialization

Components are automatically initialized on `$(document).ready()` with default settings:

```javascript
// Default initialization happens automatically
// Enables: notice dismiss + tab navigation
```

### Custom Initialization

Override defaults with custom options:

```javascript
jQuery(document).ready(function($) {
    CarticyAdmin.init({
        enableNoticeDismiss: true,
        enableTabs: true,
        tabOptions: {
            tabSelector: '.my-custom-tab',
            contentSelector: '.my-custom-content'
        }
    });
});
```

## Components

### 1. Notice Dismiss Handler

Automatically handles dismissal of admin notices with smooth animations.

**Auto-enabled** when using `.carticy-admin-notifications` container.

```javascript
// Manual initialization
CarticyAdmin.initNoticeDismiss();
```

**HTML Structure:**
```html
<div class="carticy-admin-notifications">
    <div class="notice notice-success is-dismissible">
        <p>Success message</p>
        <button type="button" class="notice-dismiss">
            <span class="screen-reader-text">Dismiss</span>
        </button>
    </div>
</div>
```

### 2. Tab Navigation System

Hash-based tab navigation with browser history support.

**Features:**
- Hash-based URLs (`#tab-name`)
- Browser back/forward button support
- Query parameter support (`?tab=tab-name`)
- Prevents page navigation for hash links
- Allows normal navigation for full URLs

```javascript
// Default initialization
CarticyAdmin.initTabs();

// Custom selectors
CarticyAdmin.initTabs({
    tabSelector: '.my-tabs .tab-link',
    contentSelector: '.my-content .tab-pane'
});
```

**HTML Structure:**
```html
<!-- Tab Navigation -->
<nav class="nav-tab-wrapper">
    <a href="#general" class="nav-tab nav-tab-active">General</a>
    <a href="#advanced" class="nav-tab">Advanced</a>
    <a href="/other-page" class="nav-tab">Other Page</a>
</nav>

<!-- Tab Content -->
<div id="general" class="tab-content">
    <p>General settings content</p>
</div>

<div id="advanced" class="tab-content" style="display: none;">
    <p>Advanced settings content</p>
</div>
```

### 3. Content Box Utilities

Helper functions for showing/hiding content boxes:

```javascript
// Toggle visibility
CarticyAdmin.ContentBox.toggle('.my-box');

// Show box
CarticyAdmin.ContentBox.show('.my-box');

// Hide box
CarticyAdmin.ContentBox.hide('.my-box');
```

### 4. Form Utilities

Helper functions for form handling:

```javascript
// Disable all form inputs
CarticyAdmin.Form.disable('#my-form');

// Enable all form inputs
CarticyAdmin.Form.enable('#my-form');

// Serialize form as object
const formData = CarticyAdmin.Form.serializeObject('#my-form');
console.log(formData);
// { field1: 'value1', field2: 'value2' }
```


### 5. Modal System

Display content in modal dialogs with customizable sizes, footer actions, and animations.

**Features:**
- 4 size options: small, medium (default), large, xlarge
- Optional footer with action buttons (Carticy branded styling)
- ESC key and overlay click to close
- Smooth fade and scale animations
- Dark mode support

```javascript
// Basic modal
CarticyAdmin.Modal.open({
    title: 'Modal Title',
    content: '<p>Your content here</p>'
});

// Modal with footer actions (buttons)
CarticyAdmin.Modal.open({
    title: 'Preview Data',
    content: '<pre>Data content here</pre>',
    size: 'large',
    actions: [
        {
            label: 'Copy to Clipboard',
            class: 'button-primary',
            onClick: function() {
                // Handle copy action
            }
        },
        {
            label: 'Cancel',
            class: 'button',
            onClick: function() {
                CarticyAdmin.Modal.close();
            }
        }
    ]
});

// Modal with callback
CarticyAdmin.Modal.open({
    title: 'Confirmation',
    content: '<p>Are you sure?</p>',
    onClose: function() {
        console.log('Modal closed');
    }
});

// Close modal programmatically
CarticyAdmin.Modal.close();
```

**Complete Example with Dynamic Content:**
```javascript
// Build content with jQuery
const $content = $('<pre>').text(JSON.stringify(data, null, 2));

CarticyAdmin.Modal.open({
    title: 'API Response',
    content: $content,
    size: 'large',
    actions: [
        {
            label: 'Copy',
            class: 'button-primary',
            onClick: function() {
                navigator.clipboard.writeText(JSON.stringify(data));
            }
        }
    ]
});
```

## API Reference

### CarticyAdmin.init(options)

Initialize all components with custom options.

**Parameters:**
- `options.enableNoticeDismiss` (boolean) - Enable notice dismiss handler (default: true)
- `options.enableTabs` (boolean) - Enable tab navigation (default: true)
- `options.tabOptions` (object) - Custom tab navigation options

### CarticyAdmin.initNoticeDismiss()

Initialize notice dismiss functionality.

### CarticyAdmin.initTabs(options)

Initialize tab navigation system.

**Parameters:**
- `options.tabSelector` (string) - Selector for tab elements (default: '.nav-tab')
- `options.contentSelector` (string) - Selector for content elements (default: '.tab-content')

### CarticyAdmin.ContentBox

Utilities for content box manipulation.

**Methods:**
- `.toggle(selector)` - Toggle visibility
- `.show(selector)` - Show box
- `.hide(selector)` - Hide box

### CarticyAdmin.Form

Utilities for form handling.

**Methods:**
- `.disable(formSelector)` - Disable form inputs
- `.enable(formSelector)` - Enable form inputs
- `.serializeObject(formSelector)` - Serialize form as object


### CarticyAdmin.Modal

Utilities for modal dialog management.

**Methods:**
- `.open(options)` - Open modal dialog
  - `options.title` (string) - Modal title
  - `options.content` (string|jQuery) - Modal content (HTML string or jQuery object)
  - `options.size` (string) - Modal size: 'small', 'medium' (default), 'large', 'xlarge'
  - `options.actions` (array) - Optional footer action buttons: `[{label: 'Text', class: 'button-primary', onClick: function(){}}]`
  - `options.onClose` (function) - Callback function when modal closes
- `.close()` - Close currently open modal


## Example: Complete Setup

```php
// PHP: Enqueue assets with proper dependencies
add_action('admin_enqueue_scripts', function($hook) {
    // Only load on your admin pages
    if ('toplevel_page_my-plugin' !== $hook) {
        return;
    }

    // Design system (foundation)
    wp_enqueue_style(
        'carticy-design-system',
        plugins_url('vendor/carticy/design-system/dist/design-system.css', __FILE__),
        [],
        '1.0.0'
    );

    // Admin layout (depends on design-system)
    wp_enqueue_style(
        'carticy-admin-layout',
        plugins_url('vendor/carticy/admin-layout/dist/admin-layout.css', __FILE__),
        ['carticy-design-system'],
        '1.0.0'
    );

    // Admin components JS (depends on jQuery)
    wp_enqueue_script(
        'carticy-admin-components',
        plugins_url('vendor/carticy/admin-components/dist/admin-components.js', __FILE__),
        ['jquery'],
        '1.0.0',
        true
    );
});
```

```javascript
// JavaScript: Custom initialization
jQuery(document).ready(function($) {
    // Custom tab setup
    CarticyAdmin.initTabs({
        tabSelector: '.my-custom-tabs .tab',
        contentSelector: '.tab-panel'
    });

    // Custom button click handler
    $('.my-toggle-button').on('click', function() {
        CarticyAdmin.ContentBox.toggle('.my-collapsible-content');
    });

    // Form submission handler
    $('#my-settings-form').on('submit', function(e) {
        e.preventDefault();

        // Disable form during submission
        CarticyAdmin.Form.disable(this);

        // Get form data
        const formData = CarticyAdmin.Form.serializeObject(this);

        // Send AJAX request
        $.post(ajaxurl, formData, function(response) {
            // Re-enable form
            CarticyAdmin.Form.enable('#my-settings-form');

            // Show success message
            $('.my-results').html(response.message).show();
        });
    });
});
```

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11+ (with jQuery 3.x)

## Development

```bash
# Build JavaScript
npm run build

# Watch for changes
npm run watch
```

## File Structure

```
admin-components/
├── src/
│   └── admin-components.js       # Source JavaScript
├── dist/
│   └── admin-components.js       # Built JavaScript
├── composer.json
├── package.json
└── README.md
```

## License

Proprietary - For Carticy internal use only
