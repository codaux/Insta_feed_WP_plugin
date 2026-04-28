# Insta_feed_WP_plugin

This project contains a custom WordPress plugin for displaying an Instagram feed through AJAX, Masonry layout, a shortcode, and an Elementor widget.

## Project Parts

1. WordPress plugin: `wordpress-plugin/Insta_feed_WP_plugin`
2. Uploadable plugin ZIP builder: `scripts/build-plugin-zip.ps1`
3. Elementor HTML fallback snippet: `snippets/elementor-instagram-feed.html`
4. GitHub Releases updater for public release-based plugin updates

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

The `dist/` directory is ignored by Git. Upload the generated ZIP as a GitHub Release asset instead of committing it to the repository.

## Required Configuration

Define the Instagram Graph API access token and user ID in `wp-config.php`:

```php
define('INSTAGRAM_ACCESS_TOKEN_grpxl', 'YOUR_ACCESS_TOKEN');
define('INSTAGRAM_USER_ID_grpxl', 'YOUR_USER_ID');
```

If these constants are missing, the plugin shows an admin notice and the AJAX request returns an error.

Do not commit real access tokens, user IDs, `.env` files, or server-specific configuration to this repository.

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

The file `snippets/elementor-instagram-feed.html` contains the legacy HTML markup for Elementor's HTML widget. Use it only if you do not want to use the plugin widget or Elementor's Shortcode widget.

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
        │   ├── class-Insta-feed-WP-plugin-elementor-widget.php
        │   └── class-Insta-feed-WP-plugin-updater.php
        └── assets
            ├── css
            │   └── instagram-feed.css
            └── js
                └── instagram-feed.js
```

## Current Behavior

The plugin registers an AJAX action named `get_instagram_photos`, fetches media from the Instagram Graph API, excludes posts whose caption contains `#ex`, and returns rendered photo HTML to the frontend script.

On the frontend, the script loads the first batch automatically, uses Masonry and imagesLoaded for layout, opens a modal on photo click, and continues pagination through the load more button.

## GitHub Release Updates

This plugin checks the latest public GitHub release from:

```text
https://github.com/codaux/Insta_feed_WP_plugin
```

To publish an update:

1. Update `Version` in `wordpress-plugin/Insta_feed_WP_plugin/Insta_feed_WP_plugin.php`.
2. Build the ZIP with `scripts/build-plugin-zip.ps1`.
3. Commit and push the source changes.
4. Create a GitHub Release using a tag like `v3.4.1`.
5. Upload `dist/Insta_feed_WP_plugin.zip` to the release assets.

WordPress will detect the new version from the latest GitHub Release and install the release asset when the plugin is updated from the admin panel.

## Faster Development Updates

For quick CSS and JavaScript changes, work against a local or staging WordPress install and sync `wordpress-plugin/Insta_feed_WP_plugin` directly into that site's `wp-content/plugins/Insta_feed_WP_plugin` directory. Once the change is ready, build the ZIP and upload it to production.

For production updates, use the GitHub Release flow above so WordPress can show updates inside the admin panel like regular plugin updates.
