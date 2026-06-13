<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Aureodev_Addons {

    // -------------------------------------------------------------------------
    // Leitura
    // -------------------------------------------------------------------------

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table   = $wpdb->prefix . 'aureodev_addons';
        $where   = '1=1';
        $params  = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }
        if ( ! empty( $args['type'] ) ) {
            $where   .= ' AND type = %s';
            $params[] = $args['type'];
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = "SELECT * FROM $table WHERE $where ORDER BY name ASC";
        if ( $params ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }
        return $wpdb->get_results( $sql );
    }

    public static function get_active_addons() {
        return self::get_all( array( 'status' => 'active' ) );
    }

    public static function get_addons_by_status( $status ) {
        return self::get_all( array( 'status' => $status ) );
    }

    public static function get_by_slug( $slug ) {
        global $wpdb;
        $table = $wpdb->prefix . 'aureodev_addons';
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE slug = %s", $slug ) );
    }

    // -------------------------------------------------------------------------
    // Instalar
    // -------------------------------------------------------------------------

    public static function install( $slug ) {
        $github   = new Aureodev_Github();
        $download = $github->download_addon( $slug );

        if ( is_wp_error( $download ) ) {
            return $download;
        }

        $meta  = $download['meta'];
        $files = $download['files'];

        // Salvar arquivos em /wp-content/aureodev-addons/{slug}/current/
        $result = self::save_version( $slug, $meta['version'], $files );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Registrar ou atualizar no banco
        $existing = self::get_by_slug( $slug );
        if ( $existing ) {
            self::update_record( $slug, $meta );
        } else {
            self::insert_record( $slug, $meta );
        }

        Aureodev_Debug::log( $slug, 'install', array(
            'version' => $meta['version'],
            'type'    => $meta['type'] ?? 'snippet',
        ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Salvar versão (current + backup)
    // -------------------------------------------------------------------------

    public static function save_version( $slug, $version, $files ) {
        $addon_base = AUREODEV_ADDONS_DIR . $slug . '/';
        $current    = $addon_base . 'current/';
        $backup     = $addon_base . 'backups/' . $version . '/';

        // Fazer backup do current antes de sobrescrever
        if ( file_exists( $current ) ) {
            self::copy_dir( $current, $backup );
        }

        // Criar diretório current
        if ( ! wp_mkdir_p( $current ) ) {
            return new WP_Error( 'dir_create_failed', "Não foi possível criar o diretório para o addon '{$slug}'." );
        }

        // Escrever arquivos
        foreach ( $files as $filename => $content ) {
            $filepath = $current . $filename;
            $dir      = dirname( $filepath );
            if ( ! file_exists( $dir ) ) {
                wp_mkdir_p( $dir );
            }
            file_put_contents( $filepath, $content );
        }

        // Atualizar addon.json com versão ativa
        $addon_json = json_decode( $files['addon.json'] ?? '{}', true );
        $addon_json['active_version'] = $version;
        file_put_contents( $addon_base . 'addon.json', wp_json_encode( $addon_json, JSON_PRETTY_PRINT ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Ativar / Desativar
    // -------------------------------------------------------------------------

    public static function activate( $slug ) {
        self::set_status( $slug, 'active' );
        Aureodev_Debug::log( $slug, 'activate' );
    }

    public static function deactivate( $slug ) {
        self::set_status( $slug, 'inactive' );
        Aureodev_Debug::log( $slug, 'deactivate' );
    }

    public static function mark_error( $slug, $error_details = '' ) {
        self::set_status( $slug, 'error' );
        Aureodev_Debug::log( $slug, 'error', array( 'error' => $error_details ) );
    }

    private static function set_status( $slug, $status ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aureodev_addons',
            array( 'status' => $status, 'updated_at' => current_time( 'mysql' ) ),
            array( 'slug' => $slug ),
            array( '%s', '%s' ),
            array( '%s' )
        );
    }

    // -------------------------------------------------------------------------
    // Editar arquivo
    // -------------------------------------------------------------------------

    public static function edit_file( $slug, $filename, $content ) {
        $path = AUREODEV_ADDONS_DIR . $slug . '/current/' . $filename;
        if ( ! file_exists( $path ) ) {
            return new WP_Error( 'file_not_found', "Arquivo '{$filename}' não encontrado no addon '{$slug}'." );
        }

        // Backup antes de editar
        $backup_dir = AUREODEV_ADDONS_DIR . $slug . '/backups/edit-' . date( 'Ymd-His' ) . '/';
        wp_mkdir_p( $backup_dir );
        copy( $path, $backup_dir . $filename );

        file_put_contents( $path, wp_unslash( $content ) );

        Aureodev_Debug::log( $slug, 'edit', array( 'file' => $filename ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Atualizar
    // -------------------------------------------------------------------------

    public static function update( $slug ) {
        return self::install( $slug ); // install já faz backup antes de sobrescrever
    }

    // -------------------------------------------------------------------------
    // Deletar
    // -------------------------------------------------------------------------

    public static function delete( $slug ) {
        global $wpdb;

        $dir = AUREODEV_ADDONS_DIR . $slug;
        if ( file_exists( $dir ) ) {
            self::delete_dir( $dir );
        }

        $wpdb->delete( $wpdb->prefix . 'aureodev_addons', array( 'slug' => $slug ), array( '%s' ) );
        Aureodev_Debug::log( $slug, 'delete' );

        return true;
    }

    // -------------------------------------------------------------------------
    // Listar versões locais
    // -------------------------------------------------------------------------

    public static function list_versions( $slug ) {
        $backups_dir = AUREODEV_ADDONS_DIR . $slug . '/backups/';
        if ( ! file_exists( $backups_dir ) ) {
            return array();
        }
        $versions = array_filter( scandir( $backups_dir ), function( $d ) use ( $backups_dir ) {
            return $d !== '.' && $d !== '..' && is_dir( $backups_dir . $d );
        } );
        return array_values( $versions );
    }

    // -------------------------------------------------------------------------
    // Reverter para versão
    // -------------------------------------------------------------------------

    public static function revert( $slug, $version ) {
        $backup_dir = AUREODEV_ADDONS_DIR . $slug . '/backups/' . $version . '/';
        if ( ! file_exists( $backup_dir ) ) {
            return new WP_Error( 'version_not_found', "Versão '{$version}' não encontrada localmente para '{$slug}'." );
        }

        $current_dir = AUREODEV_ADDONS_DIR . $slug . '/current/';

        // Salvar current atual como backup antes de reverter
        $now_backup = AUREODEV_ADDONS_DIR . $slug . '/backups/before-revert-' . date( 'Ymd-His' ) . '/';
        if ( file_exists( $current_dir ) ) {
            self::copy_dir( $current_dir, $now_backup );
        }

        // Copiar versão antiga para current
        self::copy_dir( $backup_dir, $current_dir );

        // Atualizar addon.json
        $addon_json_path = AUREODEV_ADDONS_DIR . $slug . '/addon.json';
        if ( file_exists( $addon_json_path ) ) {
            $meta = json_decode( file_get_contents( $addon_json_path ), true );
            $meta['active_version'] = $version;
            file_put_contents( $addon_json_path, wp_json_encode( $meta, JSON_PRETTY_PRINT ) );
        }

        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aureodev_addons',
            array( 'version' => $version, 'active_version' => $version, 'updated_at' => current_time( 'mysql' ) ),
            array( 'slug' => $slug ),
            array( '%s', '%s', '%s' ),
            array( '%s' )
        );

        Aureodev_Debug::log( $slug, 'revert', array( 'to_version' => $version ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Exportar como ZIP
    // -------------------------------------------------------------------------

    public static function export_zip( $slug ) {
        $current_dir = AUREODEV_ADDONS_DIR . $slug . '/current/';
        if ( ! file_exists( $current_dir ) ) {
            return new WP_Error( 'addon_not_found', "Addon '{$slug}' não está instalado." );
        }

        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'zip_unavailable', 'Extensão ZipArchive não disponível no servidor.' );
        }

        $zip_path = sys_get_temp_dir() . "/{$slug}.zip";
        $zip      = new ZipArchive();
        $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE );

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $current_dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ( $files as $file ) {
            $relative = substr( $file->getRealPath(), strlen( $current_dir ) );
            $zip->addFile( $file->getRealPath(), $slug . '/' . $relative );
        }

        $zip->close();
        return $zip_path;
    }

    // -------------------------------------------------------------------------
    // Importar ZIP
    // -------------------------------------------------------------------------

    public static function import_zip( $zip_path, $slug ) {
        if ( ! class_exists( 'ZipArchive' ) ) {
            return new WP_Error( 'zip_unavailable', 'Extensão ZipArchive não disponível.' );
        }

        $zip = new ZipArchive();
        if ( $zip->open( $zip_path ) !== true ) {
            return new WP_Error( 'zip_open_failed', 'Não foi possível abrir o arquivo ZIP.' );
        }

        $extract_to = sys_get_temp_dir() . '/aureodev_import_' . $slug . '_' . time() . '/';
        wp_mkdir_p( $extract_to );
        $zip->extractTo( $extract_to );
        $zip->close();

        // Ler addon.json para pegar metadados
        $addon_json_path = $extract_to . $slug . '/addon.json';
        if ( ! file_exists( $addon_json_path ) ) {
            // Tentar raiz
            $addon_json_path = $extract_to . 'addon.json';
        }

        $meta = array( 'slug' => $slug, 'version' => '1.0.0', 'name' => $slug, 'type' => 'snippet' );
        if ( file_exists( $addon_json_path ) ) {
            $meta = array_merge( $meta, json_decode( file_get_contents( $addon_json_path ), true ) ?? array() );
        }

        // Montar files array
        $source_dir = file_exists( $extract_to . $slug ) ? $extract_to . $slug . '/' : $extract_to;
        $files      = array();
        $iterator   = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $source_dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $file ) {
            $relative          = substr( $file->getRealPath(), strlen( $source_dir ) );
            $files[ $relative ] = file_get_contents( $file->getRealPath() );
        }

        $result = self::save_version( $slug, $meta['version'], $files );

        // Limpar temp
        self::delete_dir( $extract_to );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $existing = self::get_by_slug( $slug );
        if ( $existing ) {
            self::update_record( $slug, $meta );
        } else {
            self::insert_record( $slug, $meta );
        }

        Aureodev_Debug::log( $slug, 'import', array( 'version' => $meta['version'] ) );

        return true;
    }

    // -------------------------------------------------------------------------
    // Banco de dados
    // -------------------------------------------------------------------------

    private static function insert_record( $slug, $meta ) {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'aureodev_addons',
            array(
                'slug'           => $slug,
                'name'           => $meta['name'] ?? $slug,
                'version'        => $meta['version'] ?? '1.0.0',
                'type'           => $meta['type'] ?? 'snippet',
                'tags'           => is_array( $meta['tags'] ?? null ) ? implode( ',', $meta['tags'] ) : ( $meta['tags'] ?? '' ),
                'status'         => 'inactive',
                'hook'           => $meta['hook'] ?? 'plugins_loaded',
                'active_version' => $meta['version'] ?? '1.0.0',
                'installed_at'   => current_time( 'mysql' ),
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
        );
    }

    private static function update_record( $slug, $meta ) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'aureodev_addons',
            array(
                'name'           => $meta['name'] ?? $slug,
                'version'        => $meta['version'] ?? '1.0.0',
                'type'           => $meta['type'] ?? 'snippet',
                'tags'           => is_array( $meta['tags'] ?? null ) ? implode( ',', $meta['tags'] ) : ( $meta['tags'] ?? '' ),
                'hook'           => $meta['hook'] ?? 'plugins_loaded',
                'active_version' => $meta['version'] ?? '1.0.0',
                'updated_at'     => current_time( 'mysql' ),
            ),
            array( 'slug' => $slug ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%s' )
        );
    }

    // -------------------------------------------------------------------------
    // Utilitários de sistema de arquivos
    // -------------------------------------------------------------------------

    public static function get_addon_files( $slug ) {
        $dir = AUREODEV_ADDONS_DIR . $slug . '/current/';
        if ( ! file_exists( $dir ) ) {
            return array();
        }
        $result   = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS )
        );
        foreach ( $iterator as $file ) {
            $relative           = substr( $file->getRealPath(), strlen( $dir ) );
            $result[ $relative ] = file_get_contents( $file->getRealPath() );
        }
        return $result;
    }

    public static function get_file_content( $slug, $filename ) {
        $path = AUREODEV_ADDONS_DIR . $slug . '/current/' . $filename;
        if ( ! file_exists( $path ) ) {
            return null;
        }
        return file_get_contents( $path );
    }

    private static function copy_dir( $src, $dst ) {
        wp_mkdir_p( $dst );
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $src, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ( $iterator as $item ) {
            $relative = substr( $item->getRealPath(), strlen( $src ) );
            if ( $item->isDir() ) {
                wp_mkdir_p( $dst . $relative );
            } else {
                copy( $item->getRealPath(), $dst . ltrim( $relative, '/\\' ) );
            }
        }
    }

    private static function delete_dir( $dir ) {
        if ( ! file_exists( $dir ) ) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ( $iterator as $item ) {
            $item->isDir() ? rmdir( $item->getRealPath() ) : unlink( $item->getRealPath() );
        }
        rmdir( $dir );
    }
}
