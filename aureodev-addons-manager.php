<?php
/**
 * Plugin Name: aureodev Addons Manager
 * Plugin URI:  https://wertcomunicacao.com.br
 * Description: Gerenciador de snippets, plugins e shortcodes criados com IA. Conecta ao repositório GitHub privado para listar, instalar, versionar e executar addons com segurança.
 * Version:     1.0.0
 * Author:      Aureo Fernandes
 * Author URI:  https://wertcomunicacao.com.br
 * Text Domain: aureodev-addons
 * Requires PHP: 7.4
 * Requires at least: 6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'AUREODEV_VERSION',    '1.0.0' );
define( 'AUREODEV_FILE',       __FILE__ );
define( 'AUREODEV_PATH',       plugin_dir_path( __FILE__ ) );
define( 'AUREODEV_URL',        plugin_dir_url( __FILE__ ) );
define( 'AUREODEV_ADDONS_DIR', WP_CONTENT_DIR . '/aureodev-addons/' );
define( 'AUREODEV_ADDONS_URL', WP_CONTENT_URL . '/aureodev-addons/' );

require_once AUREODEV_PATH . 'includes/class-aureodev-core.php';

register_activation_hook( __FILE__, array( 'Aureodev_Core', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Aureodev_Core', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Aureodev_Core', 'uninstall' ) );

add_action( 'plugins_loaded', array( 'Aureodev_Core', 'get_instance' ) );
