<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Github {

    private $token;
    private $repo;
    private $api_base = 'https://api.github.com';

    public function __construct() {
        $settings    = get_option( 'aureodev_settings', array() );
        $this->token = $this->decrypt( $settings['github_token'] ?? '' );
        $this->repo  = $settings['github_repo'] ?? '';
    }

    // -------------------------------------------------------------------------
    // Registry
    // -------------------------------------------------------------------------

    public function fetch_registry( $force = false ) {
        $cache_key = 'aureodev_registry_cache';

        if ( ! $force ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $response = $this->request( "/repos/{$this->repo}/contents/registry.json" );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $decoded = json_decode( base64_decode( $response['content'] ), true );
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return new WP_Error( 'json_parse_error', 'Erro ao decodificar registry.json do GitHub.' );
        }

        set_transient( $cache_key, $decoded, HOUR_IN_SECONDS );
        return $decoded;
    }

    public function get_addon_meta( $slug ) {
        $registry = $this->fetch_registry();
        if ( is_wp_error( $registry ) ) {
            return $registry;
        }
        foreach ( $registry as $addon ) {
            if ( ( $addon['slug'] ?? '' ) === $slug ) {
                return $addon;
            }
        }
        return new WP_Error( 'addon_not_found', "Addon '{$slug}' não encontrado no registry." );
    }

    // -------------------------------------------------------------------------
    // Download
    // -------------------------------------------------------------------------

    public function download_addon( $slug ) {
        $meta = $this->get_addon_meta( $slug );
        if ( is_wp_error( $meta ) ) {
            return $meta;
        }

        $files    = $meta['files'] ?? array( 'main.php' );
        $base_path = "addons/{$slug}";
        $downloaded = array();

        foreach ( $files as $filename ) {
            $response = $this->request( "/repos/{$this->repo}/contents/{$base_path}/{$filename}" );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            $content = base64_decode( $response['content'] );
            $downloaded[ $filename ] = $content;
        }

        // addon.json sempre incluído
        $addon_json_response = $this->request( "/repos/{$this->repo}/contents/{$base_path}/addon.json" );
        if ( ! is_wp_error( $addon_json_response ) ) {
            $downloaded['addon.json'] = base64_decode( $addon_json_response['content'] );
        }

        return array(
            'meta'  => $meta,
            'files' => $downloaded,
        );
    }

    // -------------------------------------------------------------------------
    // Publicar addon de volta ao GitHub
    // -------------------------------------------------------------------------

    public function publish_addon( $slug, $new_version, $changelog, $files ) {
        $results = array();
        $base_path = "addons/{$slug}";

        foreach ( $files as $filename => $content ) {
            $path     = "{$base_path}/{$filename}";
            $existing = $this->request( "/repos/{$this->repo}/contents/{$path}" );
            $sha      = ! is_wp_error( $existing ) ? ( $existing['sha'] ?? null ) : null;

            $body = array(
                'message' => "Update {$slug}/{$filename} to v{$new_version}: {$changelog}",
                'content' => base64_encode( $content ),
            );
            if ( $sha ) {
                $body['sha'] = $sha;
            }

            $result = $this->request( "/repos/{$this->repo}/contents/{$path}", 'PUT', $body );
            $results[ $filename ] = ! is_wp_error( $result );
        }

        // Atualizar registry.json
        $this->update_registry_version( $slug, $new_version, $changelog );

        return $results;
    }

    private function update_registry_version( $slug, $new_version, $changelog ) {
        $path     = 'registry.json';
        $existing = $this->request( "/repos/{$this->repo}/contents/{$path}" );
        if ( is_wp_error( $existing ) ) {
            return;
        }

        $registry = json_decode( base64_decode( $existing['content'] ), true );
        if ( ! is_array( $registry ) ) {
            return;
        }

        foreach ( $registry as &$addon ) {
            if ( ( $addon['slug'] ?? '' ) === $slug ) {
                $addon['version'] = $new_version;
                $addon['updated'] = current_time( 'Y-m-d' );
                $addon['changelog'] = $changelog;
                break;
            }
        }
        unset( $addon );

        $body = array(
            'message' => "registry: bump {$slug} to v{$new_version}",
            'content' => base64_encode( wp_json_encode( $registry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ),
            'sha'     => $existing['sha'],
        );
        $this->request( "/repos/{$this->repo}/contents/{$path}", 'PUT', $body );

        // Limpar cache após publicação
        delete_transient( 'aureodev_registry_cache' );
    }

    // -------------------------------------------------------------------------
    // Verificação de conexão
    // -------------------------------------------------------------------------

    public function test_connection() {
        if ( empty( $this->token ) || empty( $this->repo ) ) {
            return new WP_Error( 'missing_config', 'Token ou repositório não configurados.' );
        }
        $response = $this->request( "/repos/{$this->repo}" );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        return array(
            'repo'        => $response['full_name'] ?? $this->repo,
            'private'     => $response['private'] ?? false,
            'description' => $response['description'] ?? '',
        );
    }

    // -------------------------------------------------------------------------
    // HTTP
    // -------------------------------------------------------------------------

    private function request( $endpoint, $method = 'GET', $body = null ) {
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->token,
                'Accept'        => 'application/vnd.github.v3+json',
                'User-Agent'    => 'aureodev-addons-manager/' . AUREODEV_VERSION,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 30,
        );

        if ( $body ) {
            $args['body'] = wp_json_encode( $body );
        }

        $url      = $this->api_base . $endpoint;
        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code >= 400 ) {
            $message = $body['message'] ?? "Erro HTTP {$code}";
            return new WP_Error( 'github_api_error', $message, array( 'status' => $code ) );
        }

        return $body;
    }

    // -------------------------------------------------------------------------
    // Criptografia do token
    // -------------------------------------------------------------------------

    public static function encrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        $key = wp_salt( 'auth' );
        $iv  = openssl_random_pseudo_bytes( 16 );
        $enc = openssl_encrypt( $value, 'AES-256-CBC', $key, 0, $iv );
        return base64_encode( $iv . '::' . $enc );
    }

    private function decrypt( $value ) {
        if ( empty( $value ) ) {
            return '';
        }
        try {
            $decoded = base64_decode( $value );
            list( $iv, $enc ) = explode( '::', $decoded, 2 );
            $key = wp_salt( 'auth' );
            return openssl_decrypt( $enc, 'AES-256-CBC', $key, 0, $iv );
        } catch ( Exception $e ) {
            return '';
        }
    }
}
