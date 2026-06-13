<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$slug    = sanitize_text_field( $_GET['slug'] ?? '' );
$addon   = $slug ? Aureodev_Addons::get_by_slug( $slug ) : null;
$files   = $slug ? Aureodev_Addons::get_addon_files( $slug ) : array();
$current_file = sanitize_text_field( $_GET['file'] ?? ( array_key_first( $files ) ?? 'main.php' ) );
$versions = $slug ? Aureodev_Addons::list_versions( $slug ) : array();
?>
<div class="wrap aureodev-wrap aureodev-editor-wrap">
    <h1 class="aureodev-header"><span class="aureodev-logo">&#9889;</span> Editor</h1>

    <?php if ( ! $addon ) : ?>
    <div class="aureodev-empty">
        <p>Selecione um addon para editar.</p>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-installed' ) ); ?>" class="button">Ver Instalados</a>
    </div>
    <?php else : ?>

    <div class="aureodev-editor-header">
        <div class="aureodev-editor-info">
            <h2><?php echo esc_html( $addon->name ); ?></h2>
            <span class="aureodev-addon-type aureodev-type-<?php echo esc_attr( $addon->type ); ?>"><?php echo esc_html( $addon->type ); ?></span>
            <span class="aureodev-editor-version">v<?php echo esc_html( $addon->version ); ?></span>
            <span class="aureodev-status aureodev-status-<?php echo esc_attr( $addon->status ); ?>">
                <?php echo $addon->status === 'active' ? '&#9679; Ativo' : ( $addon->status === 'error' ? '&#9888; Erro' : '&#9675; Inativo' ); ?>
            </span>
        </div>
        <div class="aureodev-editor-controls">
            <?php if ( $addon->status === 'active' ) : ?>
            <button class="button aureodev-btn-deactivate" data-slug="<?php echo esc_attr( $slug ); ?>">Desativar</button>
            <?php else : ?>
            <button class="button button-primary aureodev-btn-activate" data-slug="<?php echo esc_attr( $slug ); ?>">Ativar</button>
            <?php endif; ?>
            <button class="button aureodev-btn-export-zip" data-slug="<?php echo esc_attr( $slug ); ?>">&#8681; Baixar ZIP</button>
            <button class="button aureodev-btn-publish" data-slug="<?php echo esc_attr( $slug ); ?>">&#8679; Publicar no GitHub</button>
        </div>
    </div>

    <div class="aureodev-editor-layout">

        <!-- Sidebar: arquivos -->
        <div class="aureodev-editor-sidebar">
            <h4>Arquivos</h4>
            <ul class="aureodev-file-list">
                <?php foreach ( $files as $filename => $content ) :
                    $ext = pathinfo( $filename, PATHINFO_EXTENSION );
                ?>
                <li class="<?php echo $filename === $current_file ? 'active' : ''; ?>">
                    <a href="<?php echo esc_url( add_query_arg( array( 'slug' => $slug, 'file' => $filename ) ) ); ?>" class="aureodev-file-link" data-file="<?php echo esc_attr( $filename ); ?>" data-slug="<?php echo esc_attr( $slug ); ?>">
                        <span class="aureodev-file-icon aureodev-ext-<?php echo esc_attr( $ext ); ?>">&#128196;</span>
                        <?php echo esc_html( $filename ); ?>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>

            <?php if ( ! empty( $versions ) ) : ?>
            <h4 class="aureodev-sidebar-section">Versões / Reverter</h4>
            <ul class="aureodev-version-list">
                <?php foreach ( array_reverse( $versions ) as $v ) : ?>
                <li>
                    <span class="aureodev-version-label"><?php echo esc_html( $v ); ?></span>
                    <button class="button button-small aureodev-btn-revert" data-slug="<?php echo esc_attr( $slug ); ?>" data-version="<?php echo esc_attr( $v ); ?>">Restaurar</button>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-debug&slug=' . urlencode( $slug ) ) ); ?>" class="button aureodev-sidebar-logs-btn">&#128203; Ver Logs</a>
        </div>

        <!-- Editor principal -->
        <div class="aureodev-editor-main">
            <?php
            $file_content = $files[ $current_file ] ?? '';
            $ext          = pathinfo( $current_file, PATHINFO_EXTENSION );
            $ext_map = array(
                'php'  => 'application/x-httpd-php',
                'js'   => 'javascript',
                'css'  => 'css',
                'html' => 'htmlmixed',
                'json' => 'application/json',
            );
            $cm_mode = isset( $ext_map[ $ext ] ) ? $ext_map[ $ext ] : 'text/plain';
            ?>
            <div class="aureodev-editor-toolbar">
                <span class="aureodev-current-file">&#128196; <?php echo esc_html( $current_file ); ?></span>
                <div class="aureodev-editor-toolbar-actions">
                    <button class="button button-primary" id="aureodev-save-file" data-slug="<?php echo esc_attr( $slug ); ?>" data-filename="<?php echo esc_attr( $current_file ); ?>">Salvar</button>
                    <span id="aureodev-save-status"></span>
                </div>
            </div>

            <textarea id="aureodev-code-editor"
                      data-mode="<?php echo esc_attr( $cm_mode ); ?>"
                      data-slug="<?php echo esc_attr( $slug ); ?>"
                      data-filename="<?php echo esc_attr( $current_file ); ?>"
                      style="display:none;"><?php echo esc_textarea( $file_content ); ?></textarea>

            <div id="aureodev-codemirror-container"></div>
        </div>
    </div><!-- .aureodev-editor-layout -->

    <!-- Modal: Publicar no GitHub -->
    <div id="aureodev-publish-modal" class="aureodev-modal" style="display:none;">
        <div class="aureodev-modal-content">
            <h3>Publicar no GitHub</h3>
            <p>Defina a nova versão e uma mensagem de changelog. Os arquivos atuais do addon serão enviados ao repositório.</p>
            <table class="form-table">
                <tr>
                    <th><label for="publish-version">Nova Versão</label></th>
                    <td><input type="text" id="publish-version" placeholder="1.2.0" class="regular-text" value="<?php echo esc_attr( $addon->version ); ?>"></td>
                </tr>
                <tr>
                    <th><label for="publish-changelog">Changelog</label></th>
                    <td><textarea id="publish-changelog" rows="3" class="large-text" placeholder="O que mudou nesta versão..."></textarea></td>
                </tr>
            </table>
            <p class="submit">
                <button class="button button-primary" id="aureodev-confirm-publish" data-slug="<?php echo esc_attr( $slug ); ?>">Publicar</button>
                <button class="button" id="aureodev-cancel-publish">Cancelar</button>
            </p>
            <div id="aureodev-publish-result"></div>
        </div>
    </div>

    <?php endif; ?>

    <div id="aureodev-action-result" class="aureodev-action-result" style="display:none;"></div>
</div>
