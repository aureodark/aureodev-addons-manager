<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$filter_slug   = sanitize_text_field( $_GET['slug'] ?? '' );
$filter_action = sanitize_text_field( $_GET['action_filter'] ?? '' );
$filter_date   = sanitize_text_field( $_GET['date_from'] ?? '' );
$page          = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 50;
$offset        = ( $page - 1 ) * $per_page;

$args = array(
    'limit'  => $per_page,
    'offset' => $offset,
);
if ( $filter_slug )   $args['slug']   = $filter_slug;
if ( $filter_action ) $args['action'] = $filter_action;
if ( $filter_date )   $args['date_from'] = $filter_date . ' 00:00:00';

$logs  = Aureodev_Debug::get_logs( $args );
$total = Aureodev_Debug::count_logs( $args );
$pages = ceil( $total / $per_page );

$health = Aureodev_Debug::get_health_status();
$wp_log = Aureodev_Debug::get_wp_debug_log( 50 );

$all_addons = Aureodev_Addons::get_all();
$active_tab = sanitize_text_field( $_GET['tab'] ?? 'logs' );
?>
<div class="wrap aureodev-wrap">
    <h1 class="aureodev-header"><span class="aureodev-logo">&#9889;</span> Debug & Logs</h1>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'logs' ) ); ?>" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">Audit Log</a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'health' ) ); ?>" class="nav-tab <?php echo $active_tab === 'health' ? 'nav-tab-active' : ''; ?>">Health Check</a>
        <a href="<?php echo esc_url( add_query_arg( 'tab', 'wplog' ) ); ?>" class="nav-tab <?php echo $active_tab === 'wplog' ? 'nav-tab-active' : ''; ?>">WP Debug Log</a>
    </nav>

    <?php if ( $active_tab === 'logs' ) : ?>

    <div class="aureodev-debug-toolbar">
        <form method="get" action="">
            <input type="hidden" name="page" value="aureodev-debug">
            <input type="hidden" name="tab" value="logs">

            <?php if ( ! empty( $all_addons ) ) : ?>
            <select name="slug">
                <option value="">Todos os addons</option>
                <?php foreach ( $all_addons as $a ) : ?>
                <option value="<?php echo esc_attr( $a->slug ); ?>" <?php selected( $filter_slug, $a->slug ); ?>><?php echo esc_html( $a->name ); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>

            <select name="action_filter">
                <option value="">Todas as ações</option>
                <?php foreach ( array( 'install', 'update', 'activate', 'deactivate', 'edit', 'delete', 'revert', 'error', 'import', 'publish' ) as $act ) : ?>
                <option value="<?php echo esc_attr( $act ); ?>" <?php selected( $filter_action, $act ); ?>><?php echo esc_html( Aureodev_Debug::get_action_label( $act ) ); ?></option>
                <?php endforeach; ?>
            </select>

            <input type="date" name="date_from" value="<?php echo esc_attr( $filter_date ); ?>">
            <button type="submit" class="button">Filtrar</button>

            <?php if ( $filter_slug || $filter_action || $filter_date ) : ?>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-debug&tab=logs' ) ); ?>" class="button">Limpar filtros</a>
            <?php endif; ?>
        </form>

        <div class="aureodev-debug-toolbar-right">
            <span class="aureodev-muted"><?php echo number_format( $total ); ?> registro(s)</span>
            <button class="button" id="aureodev-clear-logs" data-days="90">Limpar &gt; 90 dias</button>
        </div>
    </div>

    <?php if ( empty( $logs ) ) : ?>
    <div class="aureodev-empty"><p>Nenhum log encontrado.</p></div>
    <?php else : ?>
    <table class="wp-list-table widefat fixed striped aureodev-table">
        <thead>
            <tr>
                <th style="width:140px;">Data/Hora</th>
                <th style="width:160px;">Addon</th>
                <th style="width:100px;">Ação</th>
                <th style="width:60px;">Usuário</th>
                <th>Detalhes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $logs as $log ) :
                $details = json_decode( $log->details ?? '{}', true );
                $user    = $log->user_id ? get_userdata( $log->user_id ) : null;
            ?>
            <tr class="aureodev-log-<?php echo esc_attr( $log->action ); ?>">
                <td class="aureodev-log-date"><?php echo esc_html( $log->created_at ); ?></td>
                <td>
                    <?php if ( $log->addon_slug ) : ?>
                    <a href="<?php echo esc_url( add_query_arg( array( 'slug' => $log->addon_slug, 'tab' => 'logs' ) ) ); ?>">
                        <?php echo esc_html( $log->addon_slug ); ?>
                    </a>
                    <?php else : ?>
                    <span class="aureodev-muted">&mdash;</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="aureodev-log-action aureodev-log-action-<?php echo esc_attr( $log->action ); ?>">
                        <?php echo esc_html( Aureodev_Debug::get_action_label( $log->action ) ); ?>
                    </span>
                </td>
                <td><?php echo $user ? esc_html( $user->user_login ) : '<span class="aureodev-muted">sistema</span>'; ?></td>
                <td class="aureodev-log-details">
                    <?php if ( ! empty( $details ) ) : ?>
                    <?php if ( isset( $details['error'] ) ) : ?>
                    <code class="aureodev-error-code"><?php echo esc_html( $details['error'] ); ?></code>
                    <?php else : ?>
                    <small><?php echo esc_html( wp_json_encode( $details ) ); ?></small>
                    <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ( $pages > 1 ) : ?>
    <div class="tablenav bottom">
        <div class="tablenav-pages">
            <?php
            echo paginate_links( array(
                'base'    => add_query_arg( 'paged', '%#%' ),
                'format'  => '',
                'total'   => $pages,
                'current' => $page,
            ) );
            ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; // empty logs ?>

    <?php elseif ( $active_tab === 'health' ) : ?>

    <div class="aureodev-health-grid">

        <div class="aureodev-card aureodev-health-card">
            <h3>Status Geral</h3>
            <ul class="aureodev-health-list">
                <li class="<?php echo $health['setup_complete'] ? 'health-ok' : 'health-fail'; ?>">
                    <?php echo $health['setup_complete'] ? '&#10003;' : '&#10007;'; ?> Configuração inicial
                </li>
                <li class="<?php echo $health['github_token'] ? 'health-ok' : 'health-fail'; ?>">
                    <?php echo $health['github_token'] ? '&#10003;' : '&#10007;'; ?> GitHub Token configurado
                </li>
                <li class="<?php echo $health['github_connection'] ? 'health-ok' : 'health-fail'; ?>">
                    <?php echo $health['github_connection'] ? '&#10003;' : '&#10007;'; ?> Conexão com GitHub
                    <?php if ( isset( $health['github_repo_info'] ) ) : ?>
                    <small class="aureodev-muted">(<?php echo esc_html( $health['github_repo_info']['repo'] ?? '' ); ?>)</small>
                    <?php endif; ?>
                </li>
                <li class="<?php echo $health['addons_dir'] ? 'health-ok' : 'health-fail'; ?>">
                    <?php echo $health['addons_dir'] ? '&#10003;' : '&#10007;'; ?> Diretório de addons existe
                </li>
                <li class="<?php echo $health['addons_dir_writable'] ? 'health-ok' : 'health-fail'; ?>">
                    <?php echo $health['addons_dir_writable'] ? '&#10003;' : '&#10007;'; ?> Diretório de addons com permissão de escrita
                </li>
                <li class="<?php echo $health['wp_debug_log'] ? 'health-ok' : 'health-warn'; ?>">
                    <?php echo $health['wp_debug_log'] ? '&#10003;' : '&#9888;'; ?> WP_DEBUG_LOG
                    <?php echo $health['wp_debug_log'] ? '(ativo)' : '(inativo)'; ?>
                </li>
            </ul>
            <div class="aureodev-health-actions">
                <button class="button" id="aureodev-test-github">Testar GitHub</button>
                <span id="aureodev-test-result"></span>
            </div>
        </div>

        <div class="aureodev-card aureodev-health-card">
            <h3>Addons</h3>
            <ul class="aureodev-health-list">
                <li>Total instalados: <strong><?php echo $health['total_addons']; ?></strong></li>
                <li class="<?php echo $health['active_addons'] > 0 ? 'health-ok' : ''; ?>">
                    Ativos: <strong><?php echo $health['active_addons']; ?></strong>
                </li>
                <?php if ( $health['error_addons'] > 0 ) : ?>
                <li class="health-fail">
                    &#9888; Com erro: <strong><?php echo $health['error_addons']; ?></strong>
                    <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'logs', 'action_filter' => 'error' ) ) ); ?>">Ver logs de erro</a>
                </li>
                <?php else : ?>
                <li class="health-ok">&#10003; Nenhum addon com erro</li>
                <?php endif; ?>
                <?php if ( $health['updates_available'] > 0 ) : ?>
                <li class="health-warn">&#9888; <?php echo $health['updates_available']; ?> atualização(ões) disponível(eis)</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="aureodev-card aureodev-health-card">
            <h3>Debug Log do WordPress</h3>
            <ul class="aureodev-health-list">
                <li>WP_DEBUG: <strong><?php echo $health['wp_debug'] ? 'Sim' : 'Não'; ?></strong></li>
                <li>WP_DEBUG_LOG: <strong><?php echo $health['wp_debug_log'] ? 'Sim' : 'Não'; ?></strong></li>
            </ul>
            <p class="description">Para ativar WP_DEBUG_LOG, adicione ao <code>wp-config.php</code>:</p>
            <code style="display:block;padding:8px;background:#f0f0f0;margin:8px 0;">define( 'WP_DEBUG', true );<br>define( 'WP_DEBUG_LOG', true );<br>define( 'WP_DEBUG_DISPLAY', false );</code>
            <?php if ( $health['last_check'] ) : ?>
            <p class="aureodev-muted">Último health check: <?php echo esc_html( $health['last_check'] ); ?></p>
            <?php endif; ?>
        </div>

    </div>

    <?php elseif ( $active_tab === 'wplog' ) : ?>

    <div class="aureodev-card">
        <h3>Últimas 50 linhas do debug.log</h3>
        <?php if ( empty( $wp_log ) ) : ?>
        <p class="aureodev-muted">Arquivo <code>wp-content/debug.log</code> não encontrado ou vazio. Ative <code>WP_DEBUG_LOG</code> no wp-config.php.</p>
        <?php else : ?>
        <div class="aureodev-wp-log">
            <?php foreach ( array_reverse( $wp_log ) as $line ) : ?>
            <div class="aureodev-log-line <?php echo stripos( $line, 'error' ) !== false ? 'aureodev-log-error-line' : ''; ?>"><?php echo esc_html( $line ); ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <div id="aureodev-action-result" class="aureodev-action-result" style="display:none;"></div>
</div>
