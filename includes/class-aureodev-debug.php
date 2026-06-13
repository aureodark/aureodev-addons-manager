<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Debug {

    public static function log( $slug, $action, $details = array() ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aureodev_audit_log',
            array(
                'addon_slug' => $slug,
                'action'     => $action,
                'user_id'    => get_current_user_id(),
                'details'    => wp_json_encode( $details ),
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
    }

    public static function get_logs( $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'aureodev_audit_log';
        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['slug'] ) ) {
            $where   .= ' AND addon_slug = %s';
            $params[] = $args['slug'];
        }
        if ( ! empty( $args['action'] ) ) {
            $where   .= ' AND action = %s';
            $params[] = $args['action'];
        }
        if ( ! empty( $args['date_from'] ) ) {
            $where   .= ' AND created_at >= %s';
            $params[] = $args['date_from'];
        }
        if ( ! empty( $args['date_to'] ) ) {
            $where   .= ' AND created_at <= %s';
            $params[] = $args['date_to'];
        }

        $limit  = absint( $args['limit'] ?? 100 );
        $offset = absint( $args['offset'] ?? 0 );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql        = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $all_params = array_merge( $params, array( $limit, $offset ) );
        return $wpdb->get_results( $wpdb->prepare( $sql, $all_params ) );
    }

    public static function count_logs( $args = array() ) {
        global $wpdb;
        $table  = $wpdb->prefix . 'aureodev_audit_log';
        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['slug'] ) ) {
            $where   .= ' AND addon_slug = %s';
            $params[] = $args['slug'];
        }
        if ( ! empty( $args['action'] ) ) {
            $where   .= ' AND action = %s';
            $params[] = $args['action'];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        if ( $params ) {
            return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
        }
        return (int) $wpdb->get_var( $sql );
    }

    public static function clear_logs( $days = 30 ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}aureodev_audit_log WHERE created_at < %s",
            date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) )
        ) );
    }

    public static function get_health_status() {
        $github   = new Aureodev_Github();
        $settings = get_option( 'aureodev_settings', array() );

        $status = array(
            'setup_complete'  => (bool) get_option( 'aureodev_setup_complete' ),
            'github_token'    => ! empty( $settings['github_token'] ),
            'github_repo'     => ! empty( $settings['github_repo'] ),
            'github_connection' => false,
            'error_addons'    => 0,
            'active_addons'   => 0,
            'total_addons'    => 0,
            'updates_available' => Aureodev_Updater::count_updates(),
            'wp_debug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
            'wp_debug_log'    => defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG,
            'addons_dir'      => file_exists( AUREODEV_ADDONS_DIR ),
            'addons_dir_writable' => wp_is_writable( AUREODEV_ADDONS_DIR ),
            'last_check'      => get_option( 'aureodev_last_health_check' ),
        );

        if ( $status['github_token'] && $status['github_repo'] ) {
            $connection = $github->test_connection();
            $status['github_connection'] = ! is_wp_error( $connection );
            if ( ! is_wp_error( $connection ) ) {
                $status['github_repo_info'] = $connection;
            }
        }

        $error_addons  = Aureodev_Addons::get_addons_by_status( 'error' );
        $active_addons = Aureodev_Addons::get_active_addons();
        $all_addons    = Aureodev_Addons::get_all();

        $status['error_addons']  = count( $error_addons );
        $status['active_addons'] = count( $active_addons );
        $status['total_addons']  = count( $all_addons );

        update_option( 'aureodev_last_health_check', current_time( 'mysql' ) );

        return $status;
    }

    public static function get_wp_debug_log( $lines = 100 ) {
        $log_path = WP_CONTENT_DIR . '/debug.log';
        if ( ! file_exists( $log_path ) ) {
            return array();
        }

        $all_lines = file( $log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
        return array_slice( $all_lines, -$lines );
    }

    public static function toggle_wp_debug_log( $enable ) {
        // Tenta editar wp-config.php para ativar WP_DEBUG_LOG
        $config_path = ABSPATH . 'wp-config.php';
        if ( ! is_writable( $config_path ) ) {
            // Fallback: ini_set para a sessão atual (não persiste entre requests)
            if ( $enable ) {
                @ini_set( 'log_errors', 1 );
                @ini_set( 'error_log', WP_CONTENT_DIR . '/debug.log' );
            }
            return false; // indicar que não foi persistido
        }

        $content = file_get_contents( $config_path );

        if ( $enable ) {
            // Ativar WP_DEBUG_LOG se não estiver
            if ( strpos( $content, 'WP_DEBUG_LOG' ) === false ) {
                $content = str_replace(
                    "/* That's all, stop editing!",
                    "define( 'WP_DEBUG', true );\ndefine( 'WP_DEBUG_LOG', true );\ndefine( 'WP_DEBUG_DISPLAY', false );\n\n/* That's all, stop editing!",
                    $content
                );
            }
        } else {
            $content = preg_replace( "/define\(\s*'WP_DEBUG_LOG',\s*true\s*\);/", "define( 'WP_DEBUG_LOG', false );", $content );
        }

        file_put_contents( $config_path, $content );
        return true;
    }

    public static function get_action_label( $action ) {
        $labels = array(
            'install'    => 'Instalado',
            'update'     => 'Atualizado',
            'activate'   => 'Ativado',
            'deactivate' => 'Desativado',
            'edit'       => 'Editado',
            'delete'     => 'Deletado',
            'revert'     => 'Revertido',
            'error'      => 'Erro',
            'import'     => 'Importado',
            'publish'    => 'Publicado no GitHub',
        );
        return $labels[ $action ] ?? ucfirst( $action );
    }
}
