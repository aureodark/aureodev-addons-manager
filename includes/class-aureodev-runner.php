<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Runner {

    private static $error_slugs = array();

    public function run( $addon ) {
        $slug = $addon->slug;
        $type = $addon->type;
        $hook = $addon->hook ?: 'plugins_loaded';

        $addon_dir = AUREODEV_ADDONS_DIR . $slug . '/current/';
        $main_file = $addon_dir . 'main.php';

        if ( ! file_exists( $main_file ) && $type !== 'css' && $type !== 'js' ) {
            Aureodev_Addons::mark_error( $slug, 'main.php não encontrado.' );
            return;
        }

        switch ( $type ) {
            case 'css':
                add_action( 'wp_enqueue_scripts', function() use ( $slug, $addon_dir ) {
                    $css_file = $addon_dir . 'style.css';
                    if ( file_exists( $css_file ) ) {
                        wp_enqueue_style(
                            'aureodev-addon-' . $slug,
                            AUREODEV_ADDONS_URL . $slug . '/current/style.css',
                            array(),
                            filemtime( $css_file )
                        );
                    }
                } );
                break;

            case 'js':
                add_action( 'wp_enqueue_scripts', function() use ( $slug, $addon_dir ) {
                    $js_file = $addon_dir . 'script.js';
                    if ( file_exists( $js_file ) ) {
                        wp_enqueue_script(
                            'aureodev-addon-' . $slug,
                            AUREODEV_ADDONS_URL . $slug . '/current/script.js',
                            array( 'jquery' ),
                            filemtime( $js_file ),
                            true
                        );
                    }
                } );
                break;

            case 'shortcode':
                $this->run_php_safe( $slug, $main_file, 'init' );
                // Enfileirar CSS/JS do shortcode se existirem
                add_action( 'wp_enqueue_scripts', function() use ( $slug, $addon_dir ) {
                    if ( file_exists( $addon_dir . 'style.css' ) ) {
                        wp_enqueue_style( 'aureodev-addon-' . $slug, AUREODEV_ADDONS_URL . $slug . '/current/style.css', array(), filemtime( $addon_dir . 'style.css' ) );
                    }
                    if ( file_exists( $addon_dir . 'script.js' ) ) {
                        wp_enqueue_script( 'aureodev-addon-' . $slug, AUREODEV_ADDONS_URL . $slug . '/current/script.js', array( 'jquery' ), filemtime( $addon_dir . 'script.js' ), true );
                    }
                } );
                break;

            case 'snippet':
            case 'plugin':
            default:
                $this->run_php_safe( $slug, $main_file, $hook );
                // Enfileirar assets se existirem
                add_action( 'wp_enqueue_scripts', function() use ( $slug, $addon_dir ) {
                    if ( file_exists( $addon_dir . 'style.css' ) ) {
                        wp_enqueue_style( 'aureodev-addon-' . $slug, AUREODEV_ADDONS_URL . $slug . '/current/style.css', array(), filemtime( $addon_dir . 'style.css' ) );
                    }
                    if ( file_exists( $addon_dir . 'script.js' ) ) {
                        wp_enqueue_script( 'aureodev-addon-' . $slug, AUREODEV_ADDONS_URL . $slug . '/current/script.js', array( 'jquery' ), filemtime( $addon_dir . 'script.js' ), true );
                    }
                } );
                break;
        }
    }

    private function run_php_safe( $slug, $file, $hook ) {
        // Registrar shutdown handler para erros fatais ANTES de qualquer execução
        if ( ! in_array( $slug, self::$error_slugs, true ) ) {
            self::$error_slugs[] = $slug;
            register_shutdown_function( array( $this, 'handle_shutdown' ), $slug );
        }

        // Marcar no contexto de execução qual addon está rodando
        add_action( $hook, function() use ( $slug, $file ) {
            // Guardar o addon atual em curso para o shutdown handler saber qual falhou
            $GLOBALS['aureodev_running'] = $slug;

            ob_start();
            try {
                include $file;
            } catch ( Throwable $e ) {
                ob_end_clean();
                $error_msg = sprintf( '%s in %s on line %d', $e->getMessage(), $e->getFile(), $e->getLine() );
                Aureodev_Addons::mark_error( $slug, $error_msg );
                $GLOBALS['aureodev_running'] = null;
                return;
            }
            ob_end_flush();
            $GLOBALS['aureodev_running'] = null;
        }, 10 );
    }

    public function handle_shutdown( $slug ) {
        $error = error_get_last();
        if ( ! $error ) {
            return;
        }

        $fatal_types = array( E_ERROR, E_PARSE, E_COMPILE_ERROR, E_CORE_ERROR );
        if ( ! in_array( $error['type'], $fatal_types, true ) ) {
            return;
        }

        // Só agir se o addon estava em execução quando o fatal ocorreu
        if ( ( $GLOBALS['aureodev_running'] ?? null ) !== $slug ) {
            return;
        }

        $error_msg = sprintf( '%s in %s on line %d', $error['message'], $error['file'], $error['line'] );
        Aureodev_Addons::mark_error( $slug, $error_msg );
    }
}
