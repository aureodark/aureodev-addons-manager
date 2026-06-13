<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$addons      = Aureodev_Addons::get_all();
$filter_status = sanitize_text_field( $_GET['status'] ?? '' );

if ( $filter_status ) {
    $addons = array_filter( $addons, function( $a ) use ( $filter_status ) {
        return $a->status === $filter_status;
    } );
}

$counts = array(
    'all'      => count( Aureodev_Addons::get_all() ),
    'active'   => count( Aureodev_Addons::get_addons_by_status( 'active' ) ),
    'inactive' => count( Aureodev_Addons::get_addons_by_status( 'inactive' ) ),
    'error'    => count( Aureodev_Addons::get_addons_by_status( 'error' ) ),
);
?>
<div class="wrap aureodev-wrap">
    <h1 class="aureodev-header">
        <span class="aureodev-logo">&#9889;</span> Addons Instalados
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-installed&action=import' ) ); ?>" class="page-title-action">Importar ZIP</a>
    </h1>

    <?php if ( ( $_GET['action'] ?? '' ) === 'import' ) : ?>
    <div class="aureodev-card">
        <h3>Importar Addon via ZIP</h3>
        <p>Faça upload de um addon exportado (.zip). O slug será extraído do <code>addon.json</code> dentro do arquivo.</p>
        <form id="aureodev-import-form" enctype="multipart/form-data">
            <table class="form-table">
                <tr>
                    <th><label for="import-slug">Slug (opcional)</label></th>
                    <td><input type="text" id="import-slug" name="slug" placeholder="meu-addon" class="regular-text"><p class="description">Se vazio, usa o nome do arquivo.</p></td>
                </tr>
                <tr>
                    <th><label for="import-zip">Arquivo ZIP</label></th>
                    <td><input type="file" id="import-zip" name="addon_zip" accept=".zip" required></td>
                </tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Importar</button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-installed' ) ); ?>" class="button">Cancelar</a>
            </p>
        </form>
    </div>
    <?php endif; ?>

    <ul class="subsubsub">
        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-installed' ) ); ?>" <?php echo ! $filter_status ? 'class="current"' : ''; ?>>Todos <span class="count">(<?php echo $counts['all']; ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'active' ) ); ?>" <?php echo $filter_status === 'active' ? 'class="current"' : ''; ?>>Ativos <span class="count">(<?php echo $counts['active']; ?>)</span></a> |</li>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'inactive' ) ); ?>" <?php echo $filter_status === 'inactive' ? 'class="current"' : ''; ?>>Inativos <span class="count">(<?php echo $counts['inactive']; ?>)</span></a> |</li>
        <?php if ( $counts['error'] > 0 ) : ?>
        <li><a href="<?php echo esc_url( add_query_arg( 'status', 'error' ) ); ?>" <?php echo $filter_status === 'error' ? 'class="current"' : ''; ?>>Com Erro <span class="count aureodev-count-error">(<?php echo $counts['error']; ?>)</span></a></li>
        <?php endif; ?>
    </ul>

    <?php if ( empty( $addons ) ) : ?>
    <div class="aureodev-empty">
        <p>Nenhum addon instalado ainda. <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-browse' ) ); ?>">Browse Addons</a></p>
    </div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped aureodev-table">
        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Versão</th>
                <th>Status</th>
                <th>Tags</th>
                <th>Atualização</th>
                <th>Versões</th>
                <th>Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $addons as $addon ) :
                $update_avail = Aureodev_Updater::get_available_update( $addon->slug );
                $versions     = Aureodev_Addons::list_versions( $addon->slug );
                $tags         = $addon->tags ? explode( ',', $addon->tags ) : array();
            ?>
            <tr class="aureodev-row-<?php echo esc_attr( $addon->status ); ?>">
                <td>
                    <strong><?php echo esc_html( $addon->name ); ?></strong>
                    <div class="row-actions">
                        <span><a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-editor&slug=' . urlencode( $addon->slug ) ) ); ?>">Editar</a> |</span>
                        <span><a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-debug&slug=' . urlencode( $addon->slug ) ) ); ?>">Logs</a> |</span>
                        <span><a href="#" class="aureodev-btn-export-zip" data-slug="<?php echo esc_attr( $addon->slug ); ?>">Baixar ZIP</a> |</span>
                        <span class="delete"><a href="#" class="aureodev-btn-delete" data-slug="<?php echo esc_attr( $addon->slug ); ?>">Deletar</a></span>
                    </div>
                </td>
                <td><span class="aureodev-addon-type aureodev-type-<?php echo esc_attr( $addon->type ); ?>"><?php echo esc_html( $addon->type ); ?></span></td>
                <td><?php echo esc_html( $addon->version ); ?></td>
                <td>
                    <?php if ( $addon->status === 'active' ) : ?>
                        <span class="aureodev-status aureodev-status-active">&#9679; Ativo</span>
                    <?php elseif ( $addon->status === 'error' ) : ?>
                        <span class="aureodev-status aureodev-status-error">&#9888; Erro</span>
                    <?php else : ?>
                        <span class="aureodev-status aureodev-status-inactive">&#9675; Inativo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php foreach ( $tags as $tag ) : ?>
                    <span class="aureodev-tag"><?php echo esc_html( trim( $tag ) ); ?></span>
                    <?php endforeach; ?>
                </td>
                <td>
                    <?php if ( $update_avail ) : ?>
                    <button class="button button-small aureodev-btn-update" data-slug="<?php echo esc_attr( $addon->slug ); ?>">v<?php echo esc_html( $update_avail ); ?> disponível</button>
                    <?php else : ?>
                    <span class="aureodev-muted">&mdash;</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( ! empty( $versions ) ) : ?>
                    <select class="aureodev-version-select" data-slug="<?php echo esc_attr( $addon->slug ); ?>">
                        <option value="">Reverter para...</option>
                        <?php foreach ( array_reverse( $versions ) as $v ) : ?>
                        <option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $v ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php else : ?>
                    <span class="aureodev-muted">&mdash;</span>
                    <?php endif; ?>
                </td>
                <td class="aureodev-actions-cell">
                    <?php if ( $addon->status === 'active' ) : ?>
                    <button class="button button-small aureodev-btn-deactivate" data-slug="<?php echo esc_attr( $addon->slug ); ?>">Desativar</button>
                    <?php else : ?>
                    <button class="button button-small button-primary aureodev-btn-activate" data-slug="<?php echo esc_attr( $addon->slug ); ?>">Ativar</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <div id="aureodev-action-result" class="aureodev-action-result" style="display:none;"></div>
</div>
