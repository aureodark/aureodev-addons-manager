<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Core {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies() {
        require_once AUREODEV_PATH . 'includes/class-aureodev-context.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-github.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-addons.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-runner.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-updater.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-debug.php';
        require_once AUREODEV_PATH . 'includes/class-aureodev-self-updater.php';

        if ( is_admin() ) {
            require_once AUREODEV_PATH . 'admin/class-aureodev-admin.php';
        }
    }

    private function init_hooks() {
        // Executar addons ativos
        add_action( 'plugins_loaded', array( $this, 'run_active_addons' ), 20 );

        // Verificar atualizações via cron
        add_action( 'aureodev_check_updates', array( 'Aureodev_Updater', 'check_all_updates' ) );

        // Admin notices para addons com erro
        add_action( 'admin_notices', array( $this, 'show_error_notices' ) );

        // Integrar self-updater com o sistema de updates do WordPress
        add_filter( 'pre_set_site_transient_update_plugins', array( 'Aureodev_Self_Updater', 'check_for_update' ) );

        if ( is_admin() ) {
            new Aureodev_Admin();
        }
    }

    public function run_active_addons() {
        $addons = Aureodev_Addons::get_active_addons();
        if ( empty( $addons ) ) {
            return;
        }
        $runner = new Aureodev_Runner();
        foreach ( $addons as $addon ) {
            $runner->run( $addon );
        }
    }

    public function show_error_notices() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $error_addons = Aureodev_Addons::get_addons_by_status( 'error' );
        if ( empty( $error_addons ) ) {
            return;
        }
        $names = implode( ', ', wp_list_pluck( $error_addons, 'name' ) );
        echo '<div class="notice notice-error"><p>';
        printf(
            '<strong>aureodev Addons Manager:</strong> Os seguintes addons foram desativados por erro: <strong>%s</strong>. <a href="%s">Ver logs</a>',
            esc_html( $names ),
            esc_url( admin_url( 'admin.php?page=aureodev-debug' ) )
        );
        echo '</p></div>';
    }

    public static function activate() {
        self::create_tables();
        self::create_addons_dir();
        self::schedule_cron();
        self::set_defaults();
        // Redirecionar para wizard de setup
        set_transient( 'aureodev_activation_redirect', true, 30 );
    }

    private static function set_defaults() {
        // Só define padrões se ainda não houver configuração salva
        if ( ! get_option( 'aureodev_settings' ) ) {
            update_option( 'aureodev_settings', array(
                'github_token'           => '',
                'github_repo'            => 'aureodark/addons-registry',
                'keep_data_on_uninstall' => 0,
            ) );
        }
    }

    public static function deactivate() {
        wp_clear_scheduled_hook( 'aureodev_check_updates' );
        delete_transient( 'aureodev_registry_cache' );
    }

    public static function uninstall() {
        if ( ! get_option( 'aureodev_keep_data_on_uninstall', false ) ) {
            global $wpdb;
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aureodev_addons" );
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}aureodev_audit_log" );
            delete_option( 'aureodev_settings' );
            delete_option( 'aureodev_site_context' );
            delete_option( 'aureodev_setup_complete' );
        }
    }

    private static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_addons = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aureodev_addons (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            slug varchar(100) NOT NULL,
            name varchar(200) NOT NULL,
            version varchar(20) NOT NULL DEFAULT '1.0.0',
            type varchar(20) NOT NULL DEFAULT 'snippet',
            tags text,
            status varchar(20) NOT NULL DEFAULT 'inactive',
            hook varchar(100) DEFAULT 'plugins_loaded',
            active_version varchar(20),
            installed_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug)
        ) $charset_collate;";

        $sql_audit = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}aureodev_audit_log (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            addon_slug varchar(100),
            action varchar(50) NOT NULL,
            user_id bigint(20),
            details longtext,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY idx_slug (addon_slug),
            KEY idx_action (action),
            KEY idx_created (created_at)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_addons );
        dbDelta( $sql_audit );
    }

    private static function create_addons_dir() {
        if ( ! file_exists( AUREODEV_ADDONS_DIR ) ) {
            wp_mkdir_p( AUREODEV_ADDONS_DIR );
            // Proteger diretório de listagem direta
            file_put_contents( AUREODEV_ADDONS_DIR . 'index.php', '<?php // Silence is golden.' );
        }
    }

    private static function schedule_cron() {
        if ( ! wp_next_scheduled( 'aureodev_check_updates' ) ) {
            wp_schedule_event( time(), 'daily', 'aureodev_check_updates' );
        }
    }
}
