# FP Bio Standalone

🔗 A beautiful, minimal "Link in Bio" landing page for WordPress that completely bypasses your theme.

## Features

- **Standalone Page**: Renders `/bio` as a clean, theme-free landing page
- **Mobile-First Design**: Optimized for Instagram and social media traffic
- **Dark/Light Mode**: Automatic theme detection or manual override
- **Customizable**: Primary color, logo, and description settings
- **Click Tracking**: Tracks link clicks (when used with FP Publisher)
- **Fast**: No theme overhead, minimal CSS/JS
- **SEO Ready**: Open Graph meta tags included

## Installation

### Via Git Updater (Recommended)

1. Install [Git Updater](https://github.com/afragen/git-updater) on your WordPress site
2. Go to **Settings → Git Updater → Install Plugin**
3. Enter: `FranPass87/FP-Bio-Standalone`
4. Click **Install Plugin**

### Manual Installation

1. Download the latest release
2. Upload to `/wp-content/plugins/fp-bio-standalone/`
3. Activate the plugin through the 'Plugins' menu
4. Visit `/bio` on your site

## Configuration

After activation, go to **Settings → FP Bio** to configure:

- **Primary Color**: Brand color for buttons and accents
- **Theme**: Auto (follows system), Light, or Dark
- **Logo URL**: Custom logo (uses site logo if empty)
- **Description**: Short bio text

## How Links Are Displayed

The plugin looks for links in this order:

1. **FP Publisher Bio Links** (if FP Publisher is installed)
2. **Page Content** (fallback: links from a page with slug "bio")

### Option 1: Using FP Publisher

If you use FP Publisher on your main site, links are automatically synced when you publish content with "Add to Bio Link" enabled.

### Option 2: Manual Links

Create a page with slug `bio` and add links in the content. The plugin will extract and display them.

Example content:

```html
<a href="https://example.com/shop">🛍️ Shop Now</a>
<a href="https://example.com/about">👋 About Us</a>
<a href="https://youtube.com/@channel">📺 YouTube</a>
```

## Screenshots

![Bio Page Light Mode](screenshot-1.png)
![Bio Page Dark Mode](screenshot-2.png)

## Requirements

- WordPress 5.8+
- PHP 7.4+

## Changelog

### 1.2.1
- Fix emoji/icon encoding with proper UTF-8 handling
- Improved link parsing to correctly extract emoji icons

### 1.2.0
- Add custom logo width and height settings
- Smart border radius (circular for square logos, rounded for rectangular)

### 1.1.1
- Add favicon support (uses WordPress site icon)

### 1.1.0
- Fix link parsing from FP Publisher content
- Change attribution to "Francesco Passeri"
- Improved design with colored buttons

### 1.0.0
- Initial release
- Standalone bio page rendering
- Dark/Light mode support
- Admin settings page
- Click tracking integration
- FP Publisher compatibility

## License

GPL v2 or later

## Author

[Francesco Passeri](https://francescopasseri.com)
