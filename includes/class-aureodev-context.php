<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Context {

    public static function collect() {
        if ( ! function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $active_plugins = get_option( 'active_plugins', array() );
        $plugin_names   = array();
        foreach ( $active_plugins as $plugin_file ) {
            $plugin_data    = get_plugin_data( WP_PLUGIN_DIR . '/' . $plugin_file, false, false );
            $plugin_names[] = array(
                'file'    => $plugin_file,
                'name'    => $plugin_data['Name'] ?? $plugin_file,
                'version' => $plugin_data['Version'] ?? '',
            );
        }

        $theme        = wp_get_theme();
        $parent_theme = $theme->parent();

        $builders = self::detect_builders( $active_plugins );

        $context = array(
            'site_name'       => get_bloginfo( 'name' ),
            'site_url'        => home_url(),
            'admin_email'     => get_option( 'admin_email' ),
            'wp_version'      => get_bloginfo( 'version' ),
            'php_version'     => phpversion(),
            'language'        => get_bloginfo( 'language' ),
            'active_theme'    => array(
                'name'    => $theme->get( 'Name' ),
                'slug'    => $theme->get_stylesheet(),
                'version' => $theme->get( 'Version' ),
                'parent'  => $parent_theme ? $parent_theme->get( 'Name' ) : null,
            ),
            'builders'        => $builders,
            'active_plugins'  => $plugin_names,
            'plugin_count'    => count( $active_plugins ),
            'is_multisite'    => is_multisite(),
            'collected_at'    => current_time( 'mysql' ),
        );

        update_option( 'aureodev_site_context', $context );

        return $context;
    }

    public static function get() {
        return get_option( 'aureodev_site_context', array() );
    }

    public static function get_summary() {
        $ctx = self::get();
        if ( empty( $ctx ) ) {
            return array();
        }
        return array(
            'site_name'    => $ctx['site_name'] ?? '',
            'site_url'     => $ctx['site_url'] ?? '',
            'wp_version'   => $ctx['wp_version'] ?? '',
            'php_version'  => $ctx['php_version'] ?? '',
            'theme'        => $ctx['active_theme']['name'] ?? '',
            'builders'     => implode( ', ', array_keys( array_filter( $ctx['builders'] ?? array() ) ) ),
            'plugin_count' => $ctx['plugin_count'] ?? 0,
            'collected_at' => $ctx['collected_at'] ?? '',
        );
    }

    private static function detect_builders( $active_plugins ) {
        $builders = array(
            'Elementor'      => false,
            'Elementor Pro'  => false,
            'Bricks'         => false,
            'Divi'           => false,
            'Beaver Builder' => false,
            'Oxygen'         => false,
            'Breakdance'     => false,
            'WPBakery'       => false,
            'Gutenberg'      => true, // sempre presente no WP moderno
        );

        $checks = array(
            'Elementor'      => 'elementor/elementor.php',
            'Elementor Pro'  => 'elementor-pro/elementor-pro.php',
            'Bricks'         => 'bricks/bricks.php',
            'Beaver Builder' => 'beaver-builder-lite-version/fl-builder.php',
            'Oxygen'         => 'oxygen/functions.php',
            'Breakdance'     => 'breakdance/plugin.php',
            'WPBakery'       => 'js_composer/js_composer.php',
        );

        foreach ( $checks as $builder => $plugin_file ) {
            if ( in_array( $plugin_file, $active_plugins, true ) ) {
                $builders[ $builder ] = true;
            }
        }

        // Divi pode ser tema ou plugin
        $template = get_option( 'template' );
        if ( 'Divi' === $template || in_array( 'divi-builder/divi-builder.php', $active_plugins, true ) ) {
            $builders['Divi'] = true;
        }

        return array_filter( $builders );
    }

    public static function export_json() {
        $context = self::get();
        if ( empty( $context ) ) {
            $context = self::collect();
        }
        return wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
    }
}
