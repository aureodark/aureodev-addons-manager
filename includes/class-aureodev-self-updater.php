<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Self_Updater {

    const PLUGIN_REPO = 'aureodark/aureodev-addons-manager';
    const PLUGIN_SLUG = 'aureodev-addons-manager/aureodev-addons-manager.php';

    // -------------------------------------------------------------------------
    // Listar releases do GitHub
    // -------------------------------------------------------------------------

    public static function get_releases( $force = false ) {
        $cache_key = 'aureodev_self_releases_cache';

        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $settings = get_option( 'aureodev_settings', array() );
        $token    = self::decrypt_token( $settings['github_token'] ?? '' );

        $args = array(
            'headers' => array(
                'Accept'     => 'application/vnd.github.v3+json',
                'User-Agent' => 'aureodev-addons-manager/' . AUREODEV_VERSION,
            ),
            'timeout' => 15,
        );

        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get(
            'https://api.github.com/repos/' . self::PLUGIN_REPO . '/releases',
            $args
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $message = $body['message'] ?? "Erro HTTP {$code} ao buscar releases.";
            return new WP_Error( 'github_releases_error', $message );
        }

        if ( empty( $body ) ) {
            return new WP_Error( 'no_releases', 'Nenhuma release encontrada no repositório do plugin.' );
        }

        $releases = array();
        foreach ( $body as $release ) {
            // Pular prereleases e drafts, a menos que não haja nenhuma stable
            $zip_url = '';
            foreach ( ( $release['assets'] ?? array() ) as $asset ) {
                if ( substr( $asset['name'], -4 ) === '.zip' ) {
                    $zip_url = $asset['browser_download_url'];
                    break;
                }
            }

            // Fallback: usar o zipball automático do GitHub
            if ( ! $zip_url ) {
                $zip_url = $release['zipball_url'] ?? '';
            }

            $releases[] = array(
                'tag'         => $release['tag_name'],
                'name'        => $release['name'] ?: $release['tag_name'],
                'published'   => $release['published_at'],
                'prerelease'  => $release['prerelease'],
                'draft'       => $release['draft'],
                'zip_url'     => $zip_url,
                'body'        => $release['body'] ?? '',
                'html_url'    => $release['html_url'],
            );
        }

        set_transient( $cache_key, $releases, HOUR_IN_SECONDS );
        return $releases;
    }

    public static function get_latest_release() {
        $releases = self::get_releases();
        if ( is_wp_error( $releases ) || empty( $releases ) ) {
            return $releases;
        }
        // Primeira não-draft, não-prerelease
        foreach ( $releases as $r ) {
            if ( ! $r['draft'] && ! $r['prerelease'] ) {
                return $r;
            }
        }
        return $releases[0];
    }

    // -------------------------------------------------------------------------
    // Verificar se há atualização disponível (hook nativo do WP)
    // -------------------------------------------------------------------------

    public static function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $latest = self::get_latest_release();
        if ( is_wp_error( $latest ) || empty( $latest ) ) {
            return $transient;
        }

        $latest_version = ltrim( $latest['tag'], 'v' );

        if ( version_compare( $latest_version, AUREODEV_VERSION, '>' ) ) {
            $plugin_data = array(
                'slug'        => 'aureodev-addons-manager',
                'plugin'      => self::PLUGIN_SLUG,
                'new_version' => $latest_version,
                'url'         => $latest['html_url'],
                'package'     => $latest['zip_url'],
            );
            $transient->response[ self::PLUGIN_SLUG ] = (object) $plugin_data;
        }

        return $transient;
    }

    // -------------------------------------------------------------------------
    // Instalar uma release específica
    // -------------------------------------------------------------------------

    public static function install_release( $tag ) {
        $releases = self::get_releases( true );
        if ( is_wp_error( $releases ) ) {
            return $releases;
        }

        $target = null;
        foreach ( $releases as $r ) {
            if ( $r['tag'] === $tag ) {
                $target = $r;
                break;
            }
        }

        if ( ! $target ) {
            return new WP_Error( 'release_not_found', "Release '{$tag}' não encontrada." );
        }

        if ( empty( $target['zip_url'] ) ) {
            return new WP_Error( 'no_zip', "Release '{$tag}' não possui arquivo ZIP." );
        }

        // Usar o WP_Upgrader para instalar de forma segura
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        $settings = get_option( 'aureodev_settings', array() );
        $token    = self::decrypt_token( $settings['github_token'] ?? '' );

        // Fazer download manual com autenticação se necessário
        $zip_path = self::download_release_zip( $target['zip_url'], $tag, $token );
        if ( is_wp_error( $zip_path ) ) {
            return $zip_path;
        }

        $skin     = new WP_Ajax_Upgrader_Skin();
        $upgrader = new Plugin_Upgrader( $skin );

        // Desativar o plugin antes de substituir
        deactivate_plugins( self::PLUGIN_SLUG );

        $result = $upgrader->install( $zip_path, array(
            'overwrite_package' => true,
        ) );

        // Limpar ZIP temporário
        @unlink( $zip_path );

        // Limpar cache de releases
        delete_transient( 'aureodev_self_releases_cache' );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        if ( ! $result ) {
            $errors = $skin->get_errors();
            if ( is_wp_error( $errors ) && $errors->has_errors() ) {
                return $errors;
            }
            return new WP_Error( 'install_failed', 'Falha na instalação. Verifique as permissões do servidor.' );
        }

        // Reativar o plugin
        activate_plugin( self::PLUGIN_SLUG );

        Aureodev_Debug::log( null, 'self_update', array(
            'from_version' => AUREODEV_VERSION,
            'to_version'   => ltrim( $tag, 'v' ),
            'tag'          => $tag,
        ) );

        return array(
            'success'     => true,
            'new_version' => ltrim( $tag, 'v' ),
            'tag'         => $tag,
        );
    }

    // -------------------------------------------------------------------------
    // Download do ZIP com autenticação
    // -------------------------------------------------------------------------

    private static function download_release_zip( $url, $tag, $token = '' ) {
        $args = array(
            'timeout'  => 60,
            'headers'  => array(
                'User-Agent' => 'aureodev-addons-manager/' . AUREODEV_VERSION,
                'Accept'     => 'application/octet-stream',
            ),
            'stream'   => true,
            'filename' => get_temp_dir() . 'aureodev-plugin-' . sanitize_file_name( $tag ) . '.zip',
        );

        if ( $token ) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return new WP_Error( 'download_failed', "Falha ao baixar o ZIP da release (HTTP {$code})." );
        }

        return $args['filename'];
    }

    // -------------------------------------------------------------------------
    // Utilitário
    // -------------------------------------------------------------------------

    public static function has_update() {
        $latest = self::get_latest_release();
        if ( is_wp_error( $latest ) || empty( $latest ) ) {
            return false;
        }
        return version_compare( ltrim( $latest['tag'], 'v' ), AUREODEV_VERSION, '>' );
    }

    public static function format_date( $iso_date ) {
        if ( ! $iso_date ) {
            return '';
        }
        return date_i18n( 'd/m/Y', strtotime( $iso_date ) );
    }

    private static function decrypt_token( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        try {
            $decoded = base64_decode( $value );
            $parts   = explode( '::', $decoded, 2 );
            if ( count( $parts ) !== 2 ) {
                return '';
            }
            $key = wp_salt( 'auth' );
            return openssl_decrypt( $parts[1], 'AES-256-CBC', $key, 0, $parts[0] );
        } catch ( Exception $e ) {
            return '';
        }
    }
}
