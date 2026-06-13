<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Updater {

    public static function check_all_updates() {
        $github  = new Aureodev_Github();
        $registry = $github->fetch_registry( true ); // forçar atualização do cache

        if ( is_wp_error( $registry ) ) {
            return;
        }

        $installed = Aureodev_Addons::get_all();
        foreach ( $installed as $addon ) {
            foreach ( $registry as $remote ) {
                if ( ( $remote['slug'] ?? '' ) === $addon->slug ) {
                    if ( version_compare( $remote['version'], $addon->version, '>' ) ) {
                        // Marcar que há atualização disponível
                        update_option( "aureodev_update_available_{$addon->slug}", $remote['version'] );
                    } else {
                        delete_option( "aureodev_update_available_{$addon->slug}" );
                    }
                    break;
                }
            }
        }
    }

    public static function get_available_update( $slug ) {
        return get_option( "aureodev_update_available_{$slug}", null );
    }

    public static function has_updates() {
        $installed = Aureodev_Addons::get_all();
        foreach ( $installed as $addon ) {
            if ( self::get_available_update( $addon->slug ) ) {
                return true;
            }
        }
        return false;
    }

    public static function count_updates() {
        $installed = Aureodev_Addons::get_all();
        $count     = 0;
        foreach ( $installed as $addon ) {
            if ( self::get_available_update( $addon->slug ) ) {
                $count++;
            }
        }
        return $count;
    }

    public static function publish_to_github( $slug, $new_version, $changelog ) {
        $files = Aureodev_Addons::get_addon_files( $slug );
        if ( empty( $files ) ) {
            return new WP_Error( 'no_files', "Addon '{$slug}' não possui arquivos para publicar." );
        }

        $github = new Aureodev_Github();
        $result = $github->publish_addon( $slug, $new_version, $changelog, $files );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Atualizar versão no banco local
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aureodev_addons',
            array( 'version' => $new_version, 'active_version' => $new_version, 'updated_at' => current_time( 'mysql' ) ),
            array( 'slug' => $slug ),
            array( '%s', '%s', '%s' ),
            array( '%s' )
        );

        Aureodev_Debug::log( $slug, 'publish', array(
            'version'   => $new_version,
            'changelog' => $changelog,
        ) );

        return $result;
    }
}
