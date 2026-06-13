/* aureodev Addons Manager - Admin JS */
/* global aureodevData, wp, CodeMirror */
( function ( $ ) {
    'use strict';

    const data   = window.aureodevData || {};
    const nonce  = data.nonce;
    const ajax   = data.ajaxUrl;
    const str    = data.strings || {};

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function showResult( msg, type, $container ) {
        const $el = $container || $( '#aureodev-action-result' );
        $el.removeClass( 'is-success is-error' )
           .addClass( type === 'error' ? 'is-error' : 'is-success' )
           .text( msg )
           .show();
        if ( type !== 'error' ) {
            setTimeout( () => $el.fadeOut(), 4000 );
        }
    }

    function request( action, extra, onSuccess, onError ) {
        return $.post( ajax, $.extend( { action: 'aureodev_action', aureodev_action: action, nonce: nonce }, extra ) )
            .done( function ( res ) {
                if ( res.success ) {
                    onSuccess && onSuccess( res.data );
                } else {
                    const msg = res.data && res.data.message ? res.data.message : 'Erro desconhecido.';
                    onError ? onError( msg ) : showResult( msg, 'error' );
                }
            } )
            .fail( function () {
                const msg = 'Erro de conexão.';
                onError ? onError( msg ) : showResult( msg, 'error' );
            } );
    }

    // -------------------------------------------------------------------------
    // Ativar
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-activate', function () {
        const slug = $( this ).data( 'slug' );
        if ( ! confirm( str.confirm_activate || 'Ativar este addon?' ) ) return;
        const $btn = $( this ).prop( 'disabled', true ).text( 'Ativando...' );
        request( 'activate', { slug }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1000 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false ).text( 'Ativar' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Desativar
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-deactivate', function () {
        const slug = $( this ).data( 'slug' );
        if ( ! confirm( str.confirm_deactivate || 'Desativar?' ) ) return;
        const $btn = $( this ).prop( 'disabled', true );
        request( 'deactivate', { slug }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1000 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Instalar
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-install', function () {
        const slug = $( this ).data( 'slug' );
        const $btn = $( this ).prop( 'disabled', true ).text( str.installing || 'Instalando...' );
        request( 'install', { slug }, function ( d ) {
            showResult( d.message, 'success' );
            $btn.text( 'Instalado' ).addClass( 'button-disabled' );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false ).text( 'Instalar' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Atualizar
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-update', function () {
        const slug = $( this ).data( 'slug' );
        const $btn = $( this ).prop( 'disabled', true ).text( str.updating || 'Atualizando...' );
        request( 'update', { slug }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1200 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Deletar
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-delete', function ( e ) {
        e.preventDefault();
        const slug = $( this ).data( 'slug' );
        if ( ! confirm( str.confirm_delete || 'Deletar permanentemente?' ) ) return;
        request( 'delete', { slug }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1000 );
        } );
    } );

    // -------------------------------------------------------------------------
    // Reverter via dropdown (tabela installed)
    // -------------------------------------------------------------------------
    $( document ).on( 'change', '.aureodev-version-select', function () {
        const version = $( this ).val();
        const slug    = $( this ).data( 'slug' );
        if ( ! version ) return;
        if ( ! confirm( str.confirm_revert || 'Reverter para esta versão?' ) ) {
            $( this ).val( '' );
            return;
        }
        request( 'revert', { slug, version }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1200 );
        }, function ( msg ) {
            showResult( msg, 'error' );
        } );
    } );

    // Reverter via botão (editor sidebar)
    $( document ).on( 'click', '.aureodev-btn-revert', function () {
        const version = $( this ).data( 'version' );
        const slug    = $( this ).data( 'slug' );
        if ( ! confirm( str.confirm_revert || 'Reverter para esta versão?' ) ) return;
        const $btn = $( this ).prop( 'disabled', true );
        request( 'revert', { slug, version }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1200 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false );
        } );
    } );

    // -------------------------------------------------------------------------
    // Baixar ZIP
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-export-zip', function ( e ) {
        e.preventDefault();
        const slug = $( this ).data( 'slug' );
        const $btn = $( this ).prop( 'disabled', true ).text( 'Gerando...' );
        request( 'export_zip', { slug }, function ( d ) {
            window.location.href = d.download_url;
            $btn.prop( 'disabled', false ).text( '&#8681; Baixar ZIP' );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false ).text( '&#8681; Baixar ZIP' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Importar ZIP
    // -------------------------------------------------------------------------
    $( '#aureodev-import-form' ).on( 'submit', function ( e ) {
        e.preventDefault();
        const formData = new FormData( this );
        formData.append( 'action', 'aureodev_action' );
        formData.append( 'aureodev_action', 'import_zip' );
        formData.append( 'nonce', nonce );

        $.ajax( {
            url:         ajax,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
            success: function ( res ) {
                if ( res.success ) {
                    showResult( res.data.message, 'success' );
                    setTimeout( () => { window.location.href = window.location.href.split( '&action' )[ 0 ]; }, 1500 );
                } else {
                    showResult( res.data.message || 'Erro.', 'error' );
                }
            },
        } );
    } );

    // -------------------------------------------------------------------------
    // Sincronizar registry
    // -------------------------------------------------------------------------
    $( '#aureodev-refresh-registry' ).on( 'click', function () {
        const $btn = $( this ).prop( 'disabled', true ).text( 'Sincronizando...' );
        request( 'refresh_registry', {}, function ( d ) {
            showResult( d.message + ' (' + d.count + ' addons)', 'success' );
            setTimeout( () => location.reload(), 1200 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false ).text( '&#8635; Sincronizar' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Testar conexão GitHub
    // -------------------------------------------------------------------------
    $( '#aureodev-test-github' ).on( 'click', function () {
        const $btn    = $( this ).prop( 'disabled', true ).text( 'Testando...' );
        const $result = $( '#aureodev-test-result' );
        request( 'test_github', {}, function ( d ) {
            $result.html( '<span style="color:#16a34a">&#10003; ' + d.message + '</span>' );
            $btn.prop( 'disabled', false ).text( 'Testar Conexão' );
        }, function ( msg ) {
            $result.html( '<span style="color:#dc2626">&#10007; ' + msg + '</span>' );
            $btn.prop( 'disabled', false ).text( 'Testar Conexão' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Atualizar contexto do site
    // -------------------------------------------------------------------------
    $( '#aureodev-refresh-context' ).on( 'click', function () {
        const $btn = $( this ).prop( 'disabled', true ).text( 'Coletando...' );
        request( 'refresh_context', {}, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1200 );
        }, function ( msg ) {
            showResult( msg, 'error' );
            $btn.prop( 'disabled', false ).text( 'Atualizar Contexto' );
        } );
    } );

    // Exportar contexto como JSON
    $( '#aureodev-export-context' ).on( 'click', function () {
        request( 'export_context', {}, function ( d ) {
            $( '#aureodev-context-json' ).val( d.json );
            $( '#aureodev-context-export-modal' ).show();
        } );
    } );

    $( '#aureodev-close-modal' ).on( 'click', function () {
        $( '#aureodev-context-export-modal' ).hide();
    } );

    // -------------------------------------------------------------------------
    // Limpar logs
    // -------------------------------------------------------------------------
    $( '#aureodev-clear-logs' ).on( 'click', function () {
        const days = $( this ).data( 'days' ) || 90;
        if ( ! confirm( 'Remover logs com mais de ' + days + ' dias?' ) ) return;
        request( 'clear_logs', { days }, function ( d ) {
            showResult( d.message, 'success' );
            setTimeout( () => location.reload(), 1500 );
        } );
    } );

    // -------------------------------------------------------------------------
    // Editor — Publicar modal
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-btn-publish', function () {
        $( '#aureodev-publish-modal' ).show();
    } );

    $( '#aureodev-cancel-publish' ).on( 'click', function () {
        $( '#aureodev-publish-modal' ).hide();
    } );

    $( '#aureodev-confirm-publish' ).on( 'click', function () {
        const slug        = $( this ).data( 'slug' );
        const new_version = $( '#publish-version' ).val().trim();
        const changelog   = $( '#publish-changelog' ).val().trim();

        if ( ! new_version ) {
            $( '#aureodev-publish-result' ).html( '<span style="color:red">Informe a versão.</span>' );
            return;
        }

        if ( ! confirm( str.confirm_publish || 'Publicar no GitHub?' ) ) return;

        $( this ).prop( 'disabled', true ).text( 'Publicando...' );
        request( 'publish_github', { slug, new_version, changelog }, function ( d ) {
            $( '#aureodev-publish-result' ).html( '<span style="color:green">&#10003; ' + d.message + '</span>' );
            setTimeout( () => { $( '#aureodev-publish-modal' ).hide(); location.reload(); }, 2000 );
        }, function ( msg ) {
            $( '#aureodev-publish-result' ).html( '<span style="color:red">&#10007; ' + msg + '</span>' );
            $( '#aureodev-confirm-publish' ).prop( 'disabled', false ).text( 'Publicar' );
        } );
    } );

    // -------------------------------------------------------------------------
    // Editor — Trocar arquivo via sidebar (sem reload de página)
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '.aureodev-file-link', function ( e ) {
        e.preventDefault();
        const filename = $( this ).data( 'file' );
        const slug     = $( this ).data( 'slug' );

        $( '.aureodev-file-list li' ).removeClass( 'active' );
        $( this ).parent().addClass( 'active' );
        $( '.aureodev-current-file' ).text( '📄 ' + filename );
        $( '#aureodev-save-file' ).data( 'filename', filename );

        request( 'get_file_content', { slug, filename }, function ( d ) {
            if ( window.aureoEditor ) {
                window.aureoEditor.setValue( d.content );
                // Atualizar modo do CodeMirror
                const extMap = { php: 'application/x-httpd-php', js: 'javascript', css: 'css', html: 'htmlmixed', json: 'application/json' };
                const ext    = filename.split( '.' ).pop();
                window.aureoEditor.setOption( 'mode', extMap[ ext ] || 'text/plain' );
            }
        } );
    } );

    // -------------------------------------------------------------------------
    // Editor — Salvar arquivo
    // -------------------------------------------------------------------------
    $( document ).on( 'click', '#aureodev-save-file', function () {
        const slug     = $( this ).data( 'slug' );
        const filename = $( this ).data( 'filename' );
        const content  = window.aureoEditor ? window.aureoEditor.getValue() : $( '#aureodev-code-editor' ).val();
        const $status  = $( '#aureodev-save-status' );

        $( this ).prop( 'disabled', true ).text( str.saving || 'Salvando...' );
        $status.text( '' );

        request( 'save_file', { slug, filename, content }, function ( d ) {
            $status.text( '&#10003; Salvo!' ).css( 'color', '#16a34a' );
            setTimeout( () => $status.text( '' ), 3000 );
            $( '#aureodev-save-file' ).prop( 'disabled', false ).text( 'Salvar' );
        }, function ( msg ) {
            $status.text( '✗ ' + msg ).css( 'color', '#dc2626' );
            $( '#aureodev-save-file' ).prop( 'disabled', false ).text( 'Salvar' );
        } );
    } );

    // -------------------------------------------------------------------------
    // CodeMirror — inicializar editor
    // -------------------------------------------------------------------------
    $( function () {
        const $ta = $( '#aureodev-code-editor' );
        if ( ! $ta.length || typeof window.wp === 'undefined' || typeof window.wp.CodeMirror === 'undefined' ) {
            // Fallback: textarea simples visível
            $ta.show().css( { width: '100%', height: '500px', fontFamily: 'monospace', fontSize: '13px' } );
            return;
        }

        const mode = $ta.data( 'mode' ) || 'text/plain';

        window.aureoEditor = wp.CodeMirror( document.getElementById( 'aureodev-codemirror-container' ), {
            value:        $ta.val(),
            mode:         mode,
            lineNumbers:  true,
            lineWrapping: false,
            indentUnit:   4,
            tabSize:      4,
            theme:        'default',
            extraKeys: {
                'Ctrl-S': function () { $( '#aureodev-save-file' ).trigger( 'click' ); },
                'Cmd-S':  function () { $( '#aureodev-save-file' ).trigger( 'click' ); },
            },
        } );
    } );

} )( jQuery );
