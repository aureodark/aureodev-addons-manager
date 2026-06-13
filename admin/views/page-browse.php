<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$github   = new Aureodev_Github();
$registry = $github->fetch_registry();
$installed = Aureodev_Addons::get_all();
$installed_slugs = wp_list_pluck( $installed, 'slug' );

$filter_tag  = sanitize_text_field( $_GET['tag'] ?? '' );
$filter_type = sanitize_text_field( $_GET['type'] ?? '' );
$search      = sanitize_text_field( $_GET['s'] ?? '' );

if ( ! is_wp_error( $registry ) && $registry ) {
    // Coletar todas as tags
    $all_tags  = array();
    foreach ( $registry as $addon ) {
        foreach ( ( $addon['tags'] ?? array() ) as $tag ) {
            $all_tags[ $tag ] = true;
        }
    }
    $all_tags = array_keys( $all_tags );
    sort( $all_tags );
}
?>
<div class="wrap aureodev-wrap">
    <h1 class="aureodev-header"><span class="aureodev-logo">&#9889;</span> Browse Addons</h1>

    <?php if ( is_wp_error( $registry ) ) : ?>
    <div class="notice notice-error">
        <p><strong>Erro ao carregar registry:</strong> <?php echo esc_html( $registry->get_error_message() ); ?></p>
        <p><a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-settings' ) ); ?>">Verificar configurações</a></p>
    </div>
    <?php else : ?>

    <div class="aureodev-browse-toolbar">
        <form method="get" action="" class="aureodev-filter-form">
            <input type="hidden" name="page" value="aureodev-browse">
            <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="Buscar addon..." class="aureodev-search">

            <select name="type">
                <option value="">Todos os tipos</option>
                <?php foreach ( array( 'plugin', 'snippet', 'shortcode', 'css', 'js' ) as $t ) : ?>
                <option value="<?php echo esc_attr( $t ); ?>" <?php selected( $filter_type, $t ); ?>><?php echo esc_html( ucfirst( $t ) ); ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ( ! empty( $all_tags ) ) : ?>
            <select name="tag">
                <option value="">Todas as tags</option>
                <?php foreach ( $all_tags as $tag ) : ?>
                <option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $filter_tag, $tag ); ?>><?php echo esc_html( $tag ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <button type="submit" class="button">Filtrar</button>
            <button type="button" class="button" id="aureodev-refresh-registry">&#8635; Sincronizar</button>
        </form>
    </div>

    <?php if ( empty( $registry ) ) : ?>
    <div class="aureodev-empty">
        <p>Nenhum addon encontrado no registry. Verifique se o arquivo <code>registry.json</code> existe no seu repositório.</p>
    </div>
    <?php else : ?>

    <div class="aureodev-addon-grid">
        <?php foreach ( $registry as $addon ) :
            $slug    = $addon['slug'] ?? '';
            $tags    = $addon['tags'] ?? array();
            $type    = $addon['type'] ?? 'snippet';
            $name    = $addon['name'] ?? $slug;
            $desc    = $addon['description'] ?? '';
            $version = $addon['version'] ?? '1.0.0';
            $origin  = $addon['origin_site'] ?? '';

            // Filtros
            if ( $search && stripos( $name, $search ) === false && stripos( $desc, $search ) === false ) continue;
            if ( $filter_type && $type !== $filter_type ) continue;
            if ( $filter_tag && ! in_array( $filter_tag, $tags, true ) ) continue;

            $is_installed = in_array( $slug, $installed_slugs, true );
            $update_avail = Aureodev_Updater::get_available_update( $slug );

            // Versão instalada
            $installed_version = '';
            foreach ( $installed as $inst ) {
                if ( $inst->slug === $slug ) {
                    $installed_version = $inst->version;
                    break;
                }
            }
        ?>
        <div class="aureodev-addon-card <?php echo $is_installed ? 'is-installed' : ''; ?>">
            <div class="aureodev-addon-card-header">
                <span class="aureodev-addon-type aureodev-type-<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $type ); ?></span>
                <?php if ( $update_avail ) : ?>
                <span class="aureodev-badge aureodev-badge-update">Update v<?php echo esc_html( $update_avail ); ?></span>
                <?php elseif ( $is_installed ) : ?>
                <span class="aureodev-badge aureodev-badge-installed">Instalado v<?php echo esc_html( $installed_version ); ?></span>
                <?php endif; ?>
            </div>

            <h3 class="aureodev-addon-name"><?php echo esc_html( $name ); ?></h3>
            <p class="aureodev-addon-desc"><?php echo esc_html( $desc ); ?></p>

            <?php if ( $origin ) : ?>
            <p class="aureodev-addon-origin">&#128197; Origem: <strong><?php echo esc_html( $origin ); ?></strong></p>
            <?php endif; ?>

            <div class="aureodev-addon-tags">
                <?php foreach ( $tags as $tag ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'tag', $tag ) ); ?>" class="aureodev-tag"><?php echo esc_html( $tag ); ?></a>
                <?php endforeach; ?>
            </div>

            <div class="aureodev-addon-footer">
                <span class="aureodev-addon-version">v<?php echo esc_html( $version ); ?></span>
                <div class="aureodev-addon-actions">
                    <?php if ( $update_avail ) : ?>
                    <button class="button button-primary aureodev-btn-update" data-slug="<?php echo esc_attr( $slug ); ?>">Atualizar</button>
                    <?php elseif ( $is_installed ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-editor&slug=' . urlencode( $slug ) ) ); ?>" class="button">Editar</a>
                    <?php else : ?>
                    <button class="button button-primary aureodev-btn-install" data-slug="<?php echo esc_attr( $slug ); ?>">Instalar</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div><!-- .aureodev-addon-grid -->

    <?php endif; ?>
    <?php endif; ?>

    <div id="aureodev-action-result" class="aureodev-action-result" style="display:none;"></div>
</div>
