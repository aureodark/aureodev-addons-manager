# aureodev Addons Manager — Briefing para IA

## Quem é o desenvolvedor

**Aureo Fernandes** — desenvolvedor WordPress freelancer (aureofernandes.com.br).
Trabalha com sites de clientes identificados por nome.
Usa IA + terminal no PC para criar e editar código. O fluxo de trabalho principal envolve gerar código com IA, testar localmente e subir para o GitHub.

---

## O que é este plugin

`aureodev-addons-manager` é um **plugin-mestre WordPress** instalado nos sites dos clientes de Aureo.

Ele resolve um problema específico: Aureo gera muitos snippets, plugins personalizados e shortcodes com IA ao longo do tempo. Sem um sistema centralizado, esses códigos ficam espalhados, são difíceis de reutilizar entre clientes, não têm controle de versão acessível de dentro do WP Admin, e um erro num snippet pode derrubar o site inteiro.

**Este plugin não é um produto público. É uma ferramenta interna de Aureo**, instalada nos sites que ele gerencia.

---

## O que são os "Addons"

Addons são os códigos que Aureo cria para os sites dos clientes. Podem ser:

| Tipo | Descrição | Exemplo |
|------|-----------|---------|
| `plugin` | Funcionalidade completa carregada no `plugins_loaded` | Checkout personalizado WooCommerce |
| `snippet` | Trecho PHP executado em um hook específico | Remover campo do checkout |
| `shortcode` | Registra um `[shortcode]` no WordPress | `[mapa_google lat="" lng=""]` |
| `css` | Folha de estilos enfileirada via `wp_enqueue_style` | Customização visual global |
| `js` | Script enfileirado via `wp_enqueue_script` | Animação, comportamento no frontend |

Cada addon é armazenado no repositório GitHub privado `aureodark/addons-registry` e instalado localmente no site via este plugin.

---

## Repositório GitHub dos Addons

**Repositório:** `aureodark/addons-registry` (privado)

Estrutura esperada no repositório:

```
registry.json              ← manifest com todos os addons
addons/
  {slug}/
    addon.json             ← metadados do addon
    main.php               ← arquivo principal PHP
    style.css              ← (opcional) estilos
    script.js              ← (opcional) scripts
```

### Formato do `registry.json`

Array JSON onde cada item descreve um addon:

```json
[
  {
    "slug": "login-personalizado",
    "name": "Login Personalizado",
    "description": "Customiza a tela de login com logo e CSS do cliente.",
    "version": "1.2.0",
    "type": "snippet",
    "hook": "init",
    "tags": ["pingo", "login", "css", "branding"],
    "files": ["main.php", "style.css"],
    "origin_site": "Pingo",
    "created": "2025-03-10",
    "updated": "2026-05-20",
    "requires": [],
    "changelog": "Refatorado para suportar logo por URL."
  }
]
```

### Formato do `addon.json` (dentro de cada addon)

Mesmos campos do item do `registry.json`, mas isolado por addon.

---

## Arquitetura do Plugin

```
aureodev-addons-manager/
├── CLAUDE.md                          ← este arquivo
├── aureodev-addons-manager.php        ← bootstrap, constantes, hooks de ativação
├── uninstall.php
├── includes/
│   ├── class-aureodev-core.php        ← singleton loader, cron, admin notices, activate/deactivate
│   ├── class-aureodev-context.php     ← coleta dados do site (nome, WP, PHP, tema, builders)
│   ├── class-aureodev-github.php      ← API GitHub (fetch registry, download addon, publish, encrypt token)
│   ├── class-aureodev-addons.php      ← CRUD de addons: install, activate, deactivate, edit, revert, export/import ZIP
│   ├── class-aureodev-runner.php      ← execução segura com output buffering + try/catch + shutdown handler
│   ├── class-aureodev-updater.php     ← check de versões disponíveis, publish_to_github, count_updates
│   └── class-aureodev-debug.php       ← audit log, health check, WP debug log, get_action_label
├── admin/
│   ├── class-aureodev-admin.php       ← menus WP Admin, AJAX handler central (todos os actions)
│   ├── views/
│   │   ├── page-dashboard.php         ← status do site, cards de contexto e addons, exportar JSON
│   │   ├── page-setup.php             ← wizard de configuração + tab avançado
│   │   ├── page-browse.php            ← lista addons do GitHub com filtros por tag/tipo/busca
│   │   ├── page-installed.php         ← gerencia addons locais, reverter versão, import ZIP
│   │   ├── page-editor.php            ← editor CodeMirror inline, sidebar de arquivos e versões, publicar no GitHub
│   │   └── page-debug.php             ← audit log filtrado, health check, WP debug.log
│   └── assets/
│       ├── admin.css                  ← UI com CSS custom properties, responsivo
│       └── admin.js                   ← AJAX handlers, CodeMirror init, interações
├── addons/                            ← placeholder (addons ficam em /wp-content/aureodev-addons/)
└── registry-example/                  ← exemplo de registry.json e addon completo para referência
```

---

## Constantes disponíveis no plugin

```php
AUREODEV_VERSION    // '1.0.0'
AUREODEV_FILE       // caminho absoluto do arquivo principal
AUREODEV_PATH       // caminho absoluto da pasta do plugin (com barra final)
AUREODEV_URL        // URL da pasta do plugin (com barra final)
AUREODEV_ADDONS_DIR // WP_CONTENT_DIR . '/aureodev-addons/'
AUREODEV_ADDONS_URL // WP_CONTENT_URL . '/aureodev-addons/'
```

---

## Banco de dados

Duas tabelas criadas na ativação do plugin:

### `wp_aureodev_addons`
Registro de todos os addons instalados neste site.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | bigint | PK auto increment |
| slug | varchar(100) | Identificador único do addon |
| name | varchar(200) | Nome legível |
| version | varchar(20) | Versão atual instalada |
| type | varchar(20) | plugin / snippet / shortcode / css / js |
| tags | text | Tags separadas por vírgula |
| status | varchar(20) | active / inactive / error |
| hook | varchar(100) | Hook WordPress de execução (ex: init, wp_head) |
| active_version | varchar(20) | Versão atualmente ativa |
| installed_at | datetime | Data de instalação |
| updated_at | datetime | Data da última atualização |

### `wp_aureodev_audit_log`
Log de todas as ações realizadas sobre addons.

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | bigint | PK auto increment |
| addon_slug | varchar(100) | Slug do addon afetado |
| action | varchar(50) | install / update / activate / deactivate / edit / delete / revert / error / import / publish |
| user_id | bigint | ID do usuário WP que executou (0 = sistema/cron) |
| details | longtext | JSON com detalhes extras (versão, arquivo editado, mensagem de erro) |
| created_at | datetime | Data/hora do evento |

---

## Sistema de versões locais por addon

Cada addon instalado no site mantém seu próprio histórico de versões em disco:

```
/wp-content/aureodev-addons/{slug}/
├── current/           ← versão ativa no momento
│   ├── main.php
│   ├── style.css
│   └── script.js
├── backups/
│   ├── v1.0.0/        ← primeira versão instalada
│   ├── v1.1.0/        ← versão anterior
│   └── edit-20260613-143022/  ← backup automático antes de edição manual
└── addon.json         ← metadados com campo active_version
```

**Backup é criado automaticamente antes de:**
- Instalar uma atualização do GitHub
- Editar qualquer arquivo pelo editor inline
- Reverter para uma versão anterior

---

## Fluxos de trabalho principais

### 1. Reutilizar addon de outro site
```
Browse Addons → filtrar por tag do site de origem (ex: "pingo")
→ Instalar → editar via Editor inline ou baixar ZIP
→ Testar → se erro: Reverter (1 clique)
→ Se ficou bom: Publicar no GitHub como nova versão
```

### 2. Editar addon no PC com IA e subir
```
Installed → Baixar ZIP do addon
→ Editar localmente com IA/terminal
→ Installed → Importar ZIP → backup automático criado
→ Testar → Reverter se necessário
→ Editor → Publicar no GitHub (pede nova versão + changelog)
```

### 3. Exportar contexto do site para usar como contexto na IA
```
Dashboard → Exportar Contexto → baixa JSON com:
  site_name, site_url, wp_version, php_version,
  active_theme, builders detectados, lista de plugins ativos
→ Colar como contexto no chat da IA ao criar novo addon para este site
```

---

## Execução segura de addons (IMPORTANTE)

O `Aureodev_Runner` garante que um addon com erro **não derruba o site**:

1. `output buffering` envolve o `include` do arquivo PHP
2. `try/catch (Throwable)` captura exceções
3. `register_shutdown_function` captura erros fatais (E_ERROR, E_PARSE, E_COMPILE_ERROR)
4. Em qualquer caso de erro: addon é marcado como `error` no BD, log registrado, `admin_notice` exibido no WP Admin, site continua funcionando normalmente

---

## AJAX actions disponíveis

Todos via `wp_ajax_aureodev_action` com o campo `aureodev_action`:

| Action | Parâmetros | Descrição |
|--------|-----------|-----------|
| `install` | slug | Baixa e instala addon do GitHub |
| `activate` | slug | Ativa addon |
| `deactivate` | slug | Desativa addon |
| `update` | slug | Atualiza addon do GitHub |
| `delete` | slug | Remove addon e arquivos |
| `revert` | slug, version | Reverte para versão local |
| `save_file` | slug, filename, content | Salva arquivo editado inline |
| `publish_github` | slug, new_version, changelog | Publica addon no GitHub |
| `export_zip` | slug | Gera ZIP do addon para download |
| `import_zip` | slug (opcional), addon_zip (file) | Importa addon via ZIP |
| `refresh_registry` | — | Força atualização do cache do registry.json |
| `test_github` | — | Testa conexão com GitHub |
| `refresh_context` | — | Re-coleta dados do site |
| `export_context` | — | Retorna JSON do contexto do site |
| `clear_logs` | days | Remove logs antigos |
| `get_file_content` | slug, filename | Retorna conteúdo de arquivo do addon |
| `list_versions` | slug | Lista versões locais disponíveis |

Download direto de ZIP: `wp_ajax_aureodev_download_zip` (não-AJAX, retorna arquivo).

---

## Segurança

- Token GitHub encriptado com `openssl_encrypt` (AES-256-CBC) usando `wp_salt('auth')` como chave
- Todas as páginas admin verificam `current_user_can('manage_options')`
- Nonces em todos os formulários (`aureodev_settings_save`) e AJAX (`aureodev_nonce`)
- Sanitização com `sanitize_text_field`, `sanitize_file_name`, `sanitize_textarea_field`, `wp_unslash`

---

## Convenções de código

- Prefixo de classes: `Aureodev_`
- Prefixo de funções/options/transients: `aureodev_`
- Prefixo de tabelas BD: `{$wpdb->prefix}aureodev_`
- Prefixo de hooks WP: `aureodev_`
- Prefixo de assets: `aureodev-admin-*`
- PHP mínimo: 7.4 (sem `match` nem arrow functions — compatibilidade máxima com servidores de clientes)
- WordPress mínimo: 6.0
- Sem dependências externas (sem Composer, sem bibliotecas externas)

---

## O que NÃO existe ainda (oportunidades de expansão)

- Autenticação por site (o token atual dá acesso ao repo inteiro — poderia ser fine-grained por cliente)
- Notificação por email quando addon entra em erro
- Suporte a múltiplos repositórios GitHub (para separar addons por cliente)
- Interface para criar novo `addon.json` e fazer push de addon novo direto do WP Admin (sem precisar commitar manualmente)
- Agendamento de execução de snippets (ex: rodar só em determinados dias/horários)
- Dependências entre addons (addon A requer addon B ativo)
