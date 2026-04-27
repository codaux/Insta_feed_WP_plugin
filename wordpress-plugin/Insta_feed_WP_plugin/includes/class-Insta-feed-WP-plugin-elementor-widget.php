<?php

defined('ABSPATH') || exit;

class Insta_Feed_WP_Plugin_Elementor_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'insta_feed_wp_plugin';
    }

    public function get_title() {
        return 'Insta_feed_WP_plugin';
    }

    public function get_icon() {
        return 'eicon-instagram-gallery';
    }

    public function get_categories() {
        return ['general'];
    }

    public function get_keywords() {
        return ['instagram', 'feed', 'Insta_feed_WP_plugin'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Content',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'button_text',
            [
                'label' => 'Load More Text',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Show More',
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        echo Insta_feed_WP_plugin_render_feed([
            'button_text' => $settings['button_text'] ?? 'Show More',
        ]);
    }
}
