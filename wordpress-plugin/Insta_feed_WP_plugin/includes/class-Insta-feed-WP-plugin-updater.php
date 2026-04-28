<?php

defined('ABSPATH') || exit;

class Insta_Feed_WP_Plugin_Updater {
    const CACHE_KEY = 'Insta_feed_WP_plugin_github_release';
    const CACHE_TTL = 21600;

    public function __construct() {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
    }

    public function check_for_update($transient) {
        if (empty($transient->checked) || empty($transient->checked[INSTA_FEED_WP_PLUGIN_BASENAME])) {
            return $transient;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $transient;
        }

        $latest_version = $this->normalize_version($release['tag_name'] ?? '');
        if (!$latest_version || !version_compare($latest_version, INSTA_FEED_WP_PLUGIN_VERSION, '>')) {
            return $transient;
        }

        $download_url = $this->get_release_asset_url($release);
        if (!$download_url) {
            return $transient;
        }

        $transient->response[INSTA_FEED_WP_PLUGIN_BASENAME] = (object) [
            'id'          => INSTA_FEED_WP_PLUGIN_BASENAME,
            'slug'        => INSTA_FEED_WP_PLUGIN_GITHUB_REPO,
            'plugin'      => INSTA_FEED_WP_PLUGIN_BASENAME,
            'new_version' => $latest_version,
            'url'         => $release['html_url'] ?? $this->repository_url(),
            'package'     => $download_url,
            'tested'      => '',
            'requires'    => '',
        ];

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || empty($args->slug) || $args->slug !== INSTA_FEED_WP_PLUGIN_GITHUB_REPO) {
            return $result;
        }

        $release = $this->get_latest_release();
        if (!$release) {
            return $result;
        }

        $latest_version = $this->normalize_version($release['tag_name'] ?? INSTA_FEED_WP_PLUGIN_VERSION);
        $body = !empty($release['body']) ? wp_kses_post(nl2br($release['body'])) : 'See the GitHub release notes for details.';

        return (object) [
            'name'          => 'Insta_feed_WP_plugin',
            'slug'          => INSTA_FEED_WP_PLUGIN_GITHUB_REPO,
            'version'       => $latest_version,
            'author'        => 'Insta_feed_WP_plugin',
            'homepage'      => $this->repository_url(),
            'download_link' => $this->get_release_asset_url($release),
            'sections'      => [
                'description' => 'Custom WordPress Instagram feed plugin with AJAX, shortcode, Elementor widget support, and GitHub release updates.',
                'changelog'   => $body,
            ],
        ];
    }

    private function get_latest_release() {
        $cached = get_site_transient(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached ?: null;
        }

        $url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/latest',
            rawurlencode(INSTA_FEED_WP_PLUGIN_GITHUB_OWNER),
            rawurlencode(INSTA_FEED_WP_PLUGIN_GITHUB_REPO)
        );

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Accept' => 'application/vnd.github+json',
                'User-Agent' => 'Insta_feed_WP_plugin',
            ],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            set_site_transient(self::CACHE_KEY, null, 30 * MINUTE_IN_SECONDS);
            return null;
        }

        $release = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($release) || empty($release['tag_name'])) {
            set_site_transient(self::CACHE_KEY, null, 30 * MINUTE_IN_SECONDS);
            return null;
        }

        set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
        return $release;
    }

    private function get_release_asset_url(array $release) {
        foreach (($release['assets'] ?? []) as $asset) {
            if (($asset['name'] ?? '') === INSTA_FEED_WP_PLUGIN_RELEASE_ASSET && !empty($asset['browser_download_url'])) {
                return $asset['browser_download_url'];
            }
        }

        return $release['zipball_url'] ?? '';
    }

    private function normalize_version($version) {
        return ltrim((string) $version, "vV \t\n\r\0\x0B");
    }

    private function repository_url() {
        return sprintf(
            'https://github.com/%s/%s',
            INSTA_FEED_WP_PLUGIN_GITHUB_OWNER,
            INSTA_FEED_WP_PLUGIN_GITHUB_REPO
        );
    }
}
