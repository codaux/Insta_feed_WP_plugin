# Insta_feed_WP_plugin

This project contains a custom WordPress plugin for displaying an Instagram feed through AJAX, Masonry layout, a shortcode, and an Elementor widget.

## Project Parts

1. WordPress plugin: `wordpress-plugin/Insta_feed_WP_plugin`
2. Uploadable plugin ZIP builder: `scripts/build-plugin-zip.ps1`
3. Shortcode reference snippet: `snippets/elementor-instagram-feed.html`

## Manual WordPress Installation

For manual installation, copy this directory into the target WordPress site's `wp-content/plugins` directory:

```text
wordpress-plugin/Insta_feed_WP_plugin
```

After copying it, activate `Insta_feed_WP_plugin` from the WordPress Plugins screen.

## Build an Uploadable Plugin ZIP

Run this command from the project root:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\build-plugin-zip.ps1
```

The generated package will be created at:

```text
dist/Insta_feed_WP_plugin.zip
```

Upload that ZIP from `Plugins > Add New > Upload Plugin` in the WordPress admin panel. If the plugin is already installed, WordPress will offer to replace the current version with the uploaded package.

Increase the `Version` value in `wordpress-plugin/Insta_feed_WP_plugin/Insta_feed_WP_plugin.php` before publishing a new release.

## Required Configuration

Define the Instagram Graph API access token and user ID in `wp-config.php`:

```php
define('INSTAGRAM_ACCESS_TOKEN_grpxl', 'YOUR_ACCESS_TOKEN');
define('INSTAGRAM_USER_ID_grpxl', 'YOUR_USER_ID');
```

If these constants are missing, the plugin shows an admin notice and the AJAX request returns an error.

## Elementor Usage

After activating the plugin, Elementor will include an `Insta_feed_WP_plugin` widget in the General category. Add that widget to the page; no raw HTML copy-paste is required.

You can also use Elementor's Shortcode widget with:

```text
[Insta_feed_WP_plugin]
```

A lowercase alias is also registered:

```text
[insta_feed_wp_plugin]
```

The load more button text can be customized:

```text
[Insta_feed_WP_plugin button_text="Show More"]
```

The file `snippets/elementor-instagram-feed.html` is kept only as a quick shortcode reference.

## Project Structure

```text
.
├── README.md
├── dist
│   └── Insta_feed_WP_plugin.zip
├── scripts
│   └── build-plugin-zip.ps1
├── snippets
│   └── elementor-instagram-feed.html
└── wordpress-plugin
    └── Insta_feed_WP_plugin
        ├── Insta_feed_WP_plugin.php
        ├── includes
        │   └── class-Insta-feed-WP-plugin-elementor-widget.php
        └── assets
            ├── css
            │   └── instagram-feed.css
            └── js
                └── instagram-feed.js
```

## Current Behavior

The plugin registers an AJAX action named `get_instagram_photos`, fetches media from the Instagram Graph API, excludes posts whose caption contains `#ex`, and returns rendered photo HTML to the frontend script.

On the frontend, the script loads the first batch automatically, uses Masonry and imagesLoaded for layout, opens a modal on photo click, and continues pagination through the load more button.

## Faster Development Updates

For quick CSS and JavaScript changes, work against a local or staging WordPress install and sync `wordpress-plugin/Insta_feed_WP_plugin` directly into that site's `wp-content/plugins/Insta_feed_WP_plugin` directory. Once the change is ready, build the ZIP and upload it to production.

For a more professional update flow, the next step is to publish versioned GitHub releases and add a custom updater to the plugin. That would make updates appear inside the WordPress admin panel like regular plugin updates, but it requires a repository and a release process.
