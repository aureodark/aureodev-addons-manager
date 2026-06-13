<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$settings     = get_option( 'aureodev_settings', array() );
$setup_done   = get_option( 'aureodev_setup_complete' );
$active_tab   = sanitize_text_field( $_GET['tab'] ?? 'setup' );
settings_errors( 'aureodev' );
?>
<div class="wrap aureodev-wrap">
    <h1 class="aureodev-header"><span class="aureodev-logo">&#9889;</span> Configurações</h1>

    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-settings&tab=setup' ) ); ?>" class="nav-tab <?php echo $active_tab === 'setup' ? 'nav-tab-active' : ''; ?>">Configuração Inicial</a>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=aureodev-settings&tab=advanced' ) ); ?>" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Avançado</a>
    </nav>

    <?php if ( $active_tab === 'setup' ) : ?>

    <?php if ( ! $setup_done ) : ?>
    <div class="aureodev-setup-banner">
        <h2>Bem-vindo ao aureodev Addons Manager!</h2>
        <p>Configure a conexão com seu repositório GitHub para começar a gerenciar seus addons.</p>
    </div>
    <?php endif; ?>

    <div class="aureodev-card aureodev-card-setup">
        <h3>Conexão com GitHub</h3>
        <p>Crie um <strong>Personal Access Token</strong> no GitHub com permissão de leitura no repositório privado onde seus addons estão armazenados.</p>
        <p><a href="https://github.com/settings/tokens/new?scopes=repo&description=aureodev+Addons+Manager" target="_blank" rel="noopener">Criar token no GitHub &rarr;</a></p>

        <form method="post" action="">
            <?php wp_nonce_field( 'aureodev_settings_save' ); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="github_repo">Repositório</label></th>
                    <td>
                        <input type="text" id="github_repo" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ?? 'aureodark/addons-registry' ); ?>" class="regular-text" placeholder="aureodark/addons-registry" required>
                        <p class="description">Formato: <code>usuario/nome-do-repositorio</code> (ex: <code>aureo/addons-registry</code>)</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="github_token">Token GitHub</label></th>
                    <td>
                        <input type="password" id="github_token" name="github_token" value="" class="regular-text" placeholder="ghp_xxxxxxxxxxxxxxxxxxxx">
                        <?php if ( ! empty( $settings['github_token'] ) ) : ?>
                        <p class="description aureodev-token-saved">&#10003; Token salvo. Deixe em branco para manter o atual.</p>
                        <?php else : ?>
                        <p class="description">Insira seu Personal Access Token do GitHub.</p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <input type="submit" name="aureodev_save_settings" class="button button-primary" value="Salvar e Conectar">
                <button type="button" class="button" id="aureodev-test-github">Testar Conexão</button>
            </p>
            <div id="aureodev-test-result"></div>
        </form>

        <?php if ( $setup_done ) : ?>
        <div class="aureodev-setup-status">
            <h4>Status do Site Detectado</h4>
            <?php
            $ctx = Aureodev_Context::get();
            if ( $ctx ) :
                $ctx_full = Aureodev_Context::get_summary();
            ?>
            <ul class="aureodev-context-list">
                <li><strong>Site:</strong> <?php echo esc_html( $ctx_full['site_name'] ); ?> &mdash; <?php echo esc_html( $ctx_full['site_url'] ); ?></li>
                <li><strong>WordPress:</strong> <?php echo esc_html( $ctx_full['wp_version'] ); ?> / PHP <?php echo esc_html( $ctx_full['php_version'] ); ?></li>
                <li><strong>Tema:</strong> <?php echo esc_html( $ctx_full['theme'] ); ?></li>
                <?php if ( $ctx_full['builders'] ) : ?>
                <li><strong>Builders:</strong> <?php echo esc_html( $ctx_full['builders'] ); ?></li>
                <?php endif; ?>
                <li><strong>Plugins ativos:</strong> <?php echo esc_html( $ctx_full['plugin_count'] ); ?></li>
            </ul>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ( $active_tab === 'advanced' ) : ?>

    <div class="aureodev-card">
        <h3>Opções Avançadas</h3>
        <form method="post" action="">
            <?php wp_nonce_field( 'aureodev_settings_save' ); ?>
            <input type="hidden" name="github_repo" value="<?php echo esc_attr( $settings['github_repo'] ?? '' ); ?>">
            <table class="form-table">
                <tr>
                    <th scope="row">Manter dados ao desinstalar</th>
                    <td>
                        <label>
                            <input type="checkbox" name="keep_data_on_uninstall" value="1" <?php checked( $settings['keep_data_on_uninstall'] ?? 0, 1 ); ?>>
                            Manter tabelas e opções do banco de dados ao deletar o plugin
                        </label>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="aureodev_save_settings" class="button button-primary" value="Salvar">
            </p>
        </form>
    </div>

    <?php endif; ?>
</div>
