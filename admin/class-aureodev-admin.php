<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_init', array( $this, 'handle_redirects' ) );
        add_action( 'wp_ajax_aureodev_action', array( $this, 'handle_ajax' ) );
    }

    public function register_menus() {
        $update_count = Aureodev_Updater::count_updates();
        $menu_title   = $update_count > 0
            ? sprintf( 'aureodev Addons <span class="awaiting-mod">%d</span>', $update_count )
            : 'aureodev Addons';

        add_menu_page(
            'aureodev Addons Manager',
            $menu_title,
            'manage_options',
            'aureodev-dashboard',
            array( $this, 'page_dashboard' ),
            'dashicons-admin-plugins',
            81
        );

        add_submenu_page( 'aureodev-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'aureodev-dashboard', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'aureodev-dashboard', 'Browse Addons', 'Browse Addons', 'manage_options', 'aureodev-browse', array( $this, 'page_browse' ) );
        add_submenu_page( 'aureodev-dashboard', 'Instalados', 'Instalados', 'manage_options', 'aureodev-installed', array( $this, 'page_installed' ) );
        add_submenu_page( 'aureodev-dashboard', 'Editor', 'Editor', 'manage_options', 'aureodev-editor', array( $this, 'page_editor' ) );
        add_submenu_page( 'aureodev-dashboard', 'Debug & Logs', 'Debug & Logs', 'manage_options', 'aureodev-debug', array( $this, 'page_debug' ) );
        add_submenu_page( 'aureodev-dashboard', 'Configurações', 'Configurações', 'manage_options', 'aureodev-settings', array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'aureodev' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'aureodev-admin',
            AUREODEV_URL . 'admin/assets/admin.css',
            array(),
            AUREODEV_VERSION
        );

        // CodeMirror para o editor
        wp_enqueue_script( 'wp-codemirror' );
        wp_enqueue_style( 'wp-codemirror' );

        wp_enqueue_script(
            'aureodev-admin',
            AUREODEV_URL . 'admin/assets/admin.js',
            array( 'jquery', 'wp-util' ),
            AUREODEV_VERSION,
            true
        );

        wp_localize_script( 'aureodev-admin', 'aureodevData', array(
            'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
            'nonce'         => wp_create_nonce( 'aureodev_nonce' ),
            'pluginVersion' => AUREODEV_VERSION,
            'strings'       => array(
                'confirm_activate'   => 'Ativar este addon?',
                'confirm_deactivate' => 'Desativar este addon?',
                'confirm_delete'     => 'Deletar permanentemente este addon e todos os seus arquivos? Esta ação não pode ser desfeita.',
                'confirm_revert'     => 'Reverter para esta versão? A versão atual será salva como backup.',
                'confirm_publish'    => 'Publicar no GitHub? Isso sobrescreverá os arquivos no repositório.',
                'saving'             => 'Salvando...',
                'installing'         => 'Instalando...',
                'updating'           => 'Atualizando...',
            ),
        ) );
    }

    public function handle_redirects() {
        if ( get_transient( 'aureodev_activation_redirect' ) ) {
            delete_transient( 'aureodev_activation_redirect' );
            if ( ! get_option( 'aureodev_setup_complete' ) ) {
                wp_safe_redirect( admin_url( 'admin.php?page=aureodev-settings&tab=setup' ) );
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public function page_dashboard() {
        require_once AUREODEV_PATH . 'admin/views/page-dashboard.php';
    }

    public function page_browse() {
        require_once AUREODEV_PATH . 'admin/views/page-browse.php';
    }

    public function page_installed() {
        require_once AUREODEV_PATH . 'admin/views/page-installed.php';
    }

    public function page_editor() {
        require_once AUREODEV_PATH . 'admin/views/page-editor.php';
    }

    public function page_debug() {
        require_once AUREODEV_PATH . 'admin/views/page-debug.php';
    }

    public function page_settings() {
        // Salvar configurações
        if ( isset( $_POST['aureodev_save_settings'] ) && check_admin_referer( 'aureodev_settings_save' ) ) {
            $token = sanitize_text_field( $_POST['github_token'] ?? '' );
            $repo  = sanitize_text_field( $_POST['github_repo'] ?? '' );

            $settings = array(
                'github_token'           => $token ? Aureodev_Github::encrypt( $token ) : ( get_option( 'aureodev_settings', array() )['github_token'] ?? '' ),
                'github_repo'            => $repo,
                'keep_data_on_uninstall' => isset( $_POST['keep_data_on_uninstall'] ) ? 1 : 0,
            );

            update_option( 'aureodev_settings', $settings );
            update_option( 'aureodev_setup_complete', 1 );

            // Forçar coleta de contexto do site
            Aureodev_Context::collect();

            add_settings_error( 'aureodev', 'saved', 'Configurações salvas com sucesso!', 'success' );
        }

        require_once AUREODEV_PATH . 'admin/views/page-setup.php';
    }

    // -------------------------------------------------------------------------
    // AJAX Handler
    // -------------------------------------------------------------------------

    public function handle_ajax() {
        check_ajax_referer( 'aureodev_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ) );
        }

        $action = sanitize_text_field( $_POST['aureodev_action'] ?? '' );
        $slug   = sanitize_text_field( $_POST['slug'] ?? '' );

        switch ( $action ) {

            case 'install':
                $result = Aureodev_Addons::install( $slug );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Addon '{$slug}' instalado com sucesso." ) );
                break;

            case 'activate':
                Aureodev_Addons::activate( $slug );
                wp_send_json_success( array( 'message' => "Addon '{$slug}' ativado." ) );
                break;

            case 'deactivate':
                Aureodev_Addons::deactivate( $slug );
                wp_send_json_success( array( 'message' => "Addon '{$slug}' desativado." ) );
                break;

            case 'update':
                $result = Aureodev_Addons::update( $slug );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                delete_option( "aureodev_update_available_{$slug}" );
                wp_send_json_success( array( 'message' => "Addon '{$slug}' atualizado." ) );
                break;

            case 'delete':
                $result = Aureodev_Addons::delete( $slug );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Addon '{$slug}' deletado." ) );
                break;

            case 'revert':
                $version = sanitize_text_field( $_POST['version'] ?? '' );
                $result  = Aureodev_Addons::revert( $slug, $version );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Addon '{$slug}' revertido para versão {$version}." ) );
                break;

            case 'save_file':
                $filename = sanitize_file_name( $_POST['filename'] ?? '' );
                $content  = wp_unslash( $_POST['content'] ?? '' );
                $result   = Aureodev_Addons::edit_file( $slug, $filename, $content );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => 'Arquivo salvo com sucesso.' ) );
                break;

            case 'publish_github':
                $new_version = sanitize_text_field( $_POST['new_version'] ?? '' );
                $changelog   = sanitize_textarea_field( $_POST['changelog'] ?? '' );
                $result      = Aureodev_Updater::publish_to_github( $slug, $new_version, $changelog );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Addon '{$slug}' publicado no GitHub como v{$new_version}." ) );
                break;

            case 'export_zip':
                $zip_path = Aureodev_Addons::export_zip( $slug );
                if ( is_wp_error( $zip_path ) ) {
                    wp_send_json_error( array( 'message' => $zip_path->get_error_message() ) );
                }
                $download_url = add_query_arg( array(
                    'action' => 'aureodev_download_zip',
                    'slug'   => $slug,
                    'nonce'  => wp_create_nonce( 'aureodev_download_' . $slug ),
                ), admin_url( 'admin-ajax.php' ) );
                wp_send_json_success( array( 'download_url' => $download_url ) );
                break;

            case 'refresh_registry':
                $github   = new Aureodev_Github();
                $registry = $github->fetch_registry( true );
                if ( is_wp_error( $registry ) ) {
                    wp_send_json_error( array( 'message' => $registry->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => 'Registry atualizado com sucesso.', 'count' => count( $registry ) ) );
                break;

            case 'test_github':
                $github = new Aureodev_Github();
                $result = $github->test_connection();
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Conectado ao repositório: {$result['repo']}", 'data' => $result ) );
                break;

            case 'refresh_context':
                $context = Aureodev_Context::collect();
                wp_send_json_success( array( 'message' => 'Contexto do site atualizado.', 'context' => Aureodev_Context::get_summary() ) );
                break;

            case 'export_context':
                $json = Aureodev_Context::export_json();
                wp_send_json_success( array( 'json' => $json ) );
                break;

            case 'clear_logs':
                $days = absint( $_POST['days'] ?? 30 );
                Aureodev_Debug::clear_logs( $days );
                wp_send_json_success( array( 'message' => "Logs mais antigos que {$days} dias removidos." ) );
                break;

            case 'get_file_content':
                $filename = sanitize_file_name( $_POST['filename'] ?? '' );
                $content  = Aureodev_Addons::get_file_content( $slug, $filename );
                if ( null === $content ) {
                    wp_send_json_error( array( 'message' => 'Arquivo não encontrado.' ) );
                }
                wp_send_json_success( array( 'content' => $content ) );
                break;

            case 'list_versions':
                $versions = Aureodev_Addons::list_versions( $slug );
                wp_send_json_success( array( 'versions' => $versions ) );
                break;

            case 'import_zip':
                if ( empty( $_FILES['addon_zip'] ) ) {
                    wp_send_json_error( array( 'message' => 'Nenhum arquivo enviado.' ) );
                }
                $file     = $_FILES['addon_zip'];
                $tmp_path = $file['tmp_name'];
                if ( ! $slug ) {
                    $slug = sanitize_title( pathinfo( $file['name'], PATHINFO_FILENAME ) );
                }
                $result = Aureodev_Addons::import_zip( $tmp_path, $slug );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array( 'message' => "Addon '{$slug}' importado com sucesso." ) );
                break;

            case 'get_releases':
                $releases = Aureodev_Self_Updater::get_releases( true );
                if ( is_wp_error( $releases ) ) {
                    wp_send_json_error( array( 'message' => $releases->get_error_message() ) );
                }
                wp_send_json_success( array( 'releases' => $releases ) );
                break;

            case 'install_release':
                $tag = sanitize_text_field( $_POST['tag'] ?? '' );
                if ( ! $tag ) {
                    wp_send_json_error( array( 'message' => 'Tag da release não informada.' ) );
                }
                $result = Aureodev_Self_Updater::install_release( $tag );
                if ( is_wp_error( $result ) ) {
                    wp_send_json_error( array( 'message' => $result->get_error_message() ) );
                }
                wp_send_json_success( array(
                    'message'     => "Plugin atualizado para {$result['tag']} com sucesso. Recarregando...",
                    'new_version' => $result['new_version'],
                ) );
                break;

            default:
                wp_send_json_error( array( 'message' => 'Ação desconhecida.' ) );
        }
    }
}

// Handler separado para download de ZIP (não-AJAX, retorna arquivo)
add_action( 'wp_ajax_aureodev_download_zip', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Acesso negado.' );
    }
    $slug  = sanitize_text_field( $_GET['slug'] ?? '' );
    $nonce = sanitize_text_field( $_GET['nonce'] ?? '' );

    if ( ! wp_verify_nonce( $nonce, 'aureodev_download_' . $slug ) ) {
        wp_die( 'Token inválido.' );
    }

    $zip_path = Aureodev_Addons::export_zip( $slug );
    if ( is_wp_error( $zip_path ) ) {
        wp_die( $zip_path->get_error_message() );
    }

    header( 'Content-Type: application/zip' );
    header( 'Content-Disposition: attachment; filename="' . $slug . '.zip"' );
    header( 'Content-Length: ' . filesize( $zip_path ) );
    readfile( $zip_path );
    unlink( $zip_path );
    exit;
} );
