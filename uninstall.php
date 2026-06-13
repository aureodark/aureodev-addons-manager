<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delegado ao Core para manter a lógica centralizada
require_once plugin_dir_path( __FILE__ ) . 'includes/class-aureodev-core.php';
Aureodev_Core::uninstall();
