<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$context = Aureodev_Context::get_summary();
$all     = Aureodev_Addons::get_all();
$active  = Aureodev_Addons::get_addons_by_status( 'active' );
$errors  = Aureodev_Addons::get_addons_by_status( 'error' );
$updates = Aureodev_Updater::count_updates();
?>
<div class="wrap aureodev-wrap">
    <h1 class="aureodev-header">
        <span class="aureodev-logo">&#9889;</span> aureodev Addons Manager
        <span class="aureodev-version">v<?php echo esc_html( AUREODEV_VERSION ); ?></span>
    </h1>

    <?php if ( empty( $context ) ) : ?>
    <div class="notice notice-warning">
        <p><strong>Configuração inicial necessária.</strong> <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-settings&tab=setup' ) ); ?>">Configure o plugin agora &rarr;</a></p>
    </div>
    <?php endif; ?>

    <div class="aureodev-cards">

        <div class="aureodev-card">
            <h3>Site</h3>
            <?php if ( $context ) : ?>
            <ul class="aureodev-context-list">
                <li><strong>Nome:</strong> <?php echo esc_html( $context['site_name'] ?? '' ); ?></li>
                <li><strong>URL:</strong> <?php echo esc_html( $context['site_url'] ?? '' ); ?></li>
                <li><strong>WordPress:</strong> <?php echo esc_html( $context['wp_version'] ?? '' ); ?></li>
                <li><strong>PHP:</strong> <?php echo esc_html( $context['php_version'] ?? '' ); ?></li>
                <li><strong>Tema:</strong> <?php echo esc_html( $context['theme'] ?? '' ); ?></li>
                <?php if ( $context['builders'] ) : ?>
                <li><strong>Builders:</strong> <?php echo esc_html( $context['builders'] ); ?></li>
                <?php endif; ?>
                <li><strong>Plugins ativos:</strong> <?php echo esc_html( $context['plugin_count'] ?? 0 ); ?></li>
                <?php if ( $context['collected_at'] ) : ?>
                <li class="aureodev-meta">Coletado em: <?php echo esc_html( $context['collected_at'] ); ?></li>
                <?php endif; ?>
            </ul>
            <div class="aureodev-card-actions">
                <button class="button" id="aureodev-refresh-context">Atualizar Contexto</button>
                <button class="button" id="aureodev-export-context">Exportar JSON</button>
            </div>
            <?php else : ?>
            <p class="aureodev-muted">Nenhum contexto coletado ainda.</p>
            <button class="button button-primary" id="aureodev-refresh-context">Coletar Agora</button>
            <?php endif; ?>
        </div>

        <div class="aureodev-card aureodev-card-stats">
            <h3>Addons</h3>
            <div class="aureodev-stats">
                <div class="aureodev-stat">
                    <span class="aureodev-stat-number"><?php echo count( $all ); ?></span>
                    <span class="aureodev-stat-label">Total</span>
                </div>
                <div class="aureodev-stat aureodev-stat-active">
                    <span class="aureodev-stat-number"><?php echo count( $active ); ?></span>
                    <span class="aureodev-stat-label">Ativos</span>
                </div>
                <?php if ( $errors ) : ?>
                <div class="aureodev-stat aureodev-stat-error">
                    <span class="aureodev-stat-number"><?php echo count( $errors ); ?></span>
                    <span class="aureodev-stat-label">Com Erro</span>
                </div>
                <?php endif; ?>
                <?php if ( $updates > 0 ) : ?>
                <div class="aureodev-stat aureodev-stat-update">
                    <span class="aureodev-stat-number"><?php echo $updates; ?></span>
                    <span class="aureodev-stat-label">Atualizações</span>
                </div>
                <?php endif; ?>
            </div>
            <div class="aureodev-card-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-browse' ) ); ?>" class="button button-primary">Browse Addons</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-installed' ) ); ?>" class="button">Gerenciar</a>
            </div>
        </div>

        <?php if ( ! empty( $errors ) ) : ?>
        <div class="aureodev-card aureodev-card-error">
            <h3>&#9888; Addons com Erro</h3>
            <ul>
                <?php foreach ( $errors as $err ) : ?>
                <li>
                    <strong><?php echo esc_html( $err->name ); ?></strong>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-debug&slug=' . urlencode( $err->slug ) ) ); ?>" class="button button-small">Ver Log</a>
                    <button class="button button-small aureodev-btn-deactivate" data-slug="<?php echo esc_attr( $err->slug ); ?>">Limpar Erro</button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

    </div><!-- .aureodev-cards -->

    <div id="aureodev-context-export-modal" class="aureodev-modal" style="display:none;">
        <div class="aureodev-modal-content">
            <h3>Contexto do Site (JSON)</h3>
            <p class="aureodev-muted">Copie e use como contexto para a IA ao criar novos addons.</p>
            <textarea id="aureodev-context-json" rows="20" readonly style="width:100%;font-family:monospace;font-size:12px;"></textarea>
            <button class="button" onclick="document.getElementById('aureodev-context-json').select();document.execCommand('copy');this.textContent='Copiado!';">Copiar</button>
            <button class="button" id="aureodev-close-modal">Fechar</button>
        </div>
    </div>
</div>
