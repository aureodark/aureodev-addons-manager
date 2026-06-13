<?php
/**
 * Login Personalizado
 * Addon: login-personalizado | Versão: 1.2.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// URL da logo (configurar aqui ou buscar de options)
$logo_url = get_option( 'aureodev_login_logo_url', '' );

add_action( 'login_enqueue_scripts', function() use ( $logo_url ) {
    wp_enqueue_style( 'aureodev-login', plugin_dir_url( __FILE__ ) . 'style.css', array(), '1.2.0' );
    if ( $logo_url ) {
        wp_add_inline_style( 'aureodev-login', sprintf(
            '.login h1 a { background-image: url(%s) !important; }',
            esc_url( $logo_url )
        ) );
    }
} );

// Redirecionar logo para o site
add_filter( 'login_headerurl', fn() => home_url() );
add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
