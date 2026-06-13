<div align="center">

```
 █████╗ ██╗   ██╗██████╗ ███████╗ ██████╗ ██████╗ ███████╗██╗   ██╗
██╔══██╗██║   ██║██╔══██╗██╔════╝██╔═══██╗██╔══██╗██╔════╝██║   ██║
███████║██║   ██║██████╔╝█████╗  ██║   ██║██║  ██║█████╗  ██║   ██║
██╔══██║██║   ██║██╔══██╗██╔══╝  ██║   ██║██║  ██║██╔══╝  ╚██╗ ██╔╝
██║  ██║╚██████╔╝██║  ██║███████╗╚██████╔╝██████╔╝███████╗ ╚████╔╝ 
╚═╝  ╚═╝ ╚═════╝ ╚═╝  ╚═╝╚══════╝ ╚═════╝ ╚═════╝ ╚══════╝  ╚═══╝  
                                                                      
        A D D O N S   M A N A G E R   —   v 1 . 0 . 0
```

**Plugin-mestre WordPress para gerenciar snippets, plugins e shortcodes criados com IA.**  
Controle de versão. Execução segura. Deploy em um clique. Tudo dentro do WP Admin.

---

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=flat-square&logo=php&logoColor=white)
![GitHub](https://img.shields.io/badge/GitHub-Private%20Registry-181717?style=flat-square&logo=github&logoColor=white)
![License](https://img.shields.io/badge/License-Private%20%2F%20Internal-red?style=flat-square)
![Status](https://img.shields.io/badge/Status-Active-00C851?style=flat-square)

</div>

---

## O Problema

Desenvolvedores que criam código personalizado com IA para múltiplos clientes WordPress enfrentam um gargalo inevitável:

- Um snippet de login criado para o **cliente A** funcionaria perfeitamente no **cliente B** — mas está perdido numa pasta local
- Ao editar um snippet ativo, um erro de sintaxe **derruba o site inteiro**
- Não há histórico de versões acessível de dentro do WP Admin
- Replicar um addon entre sites significa copiar manualmente, ajustar nomes, classes, configurações
- O código que a IA gera hoje fica obsoleto amanhã, e atualizar todos os sites onde ele roda é trabalhoso

## A Solução

**aureodev Addons Manager** é um plugin-mestre instalado nos sites dos clientes que resolve cada um desses pontos:

```
┌─────────────────────────────────────────────────────────────────┐
│                     GitHub Private Registry                      │
│                  aureodark/addons-registry                       │
│                                                                  │
│  registry.json  ──►  lista de todos os addons + versões         │
│  addons/                                                         │
│    login-personalizado/  ──►  main.php, style.css               │
│    header-fixo-elementor/  ──►  main.php, script.js             │
│    shortcode-mapa-google/  ──►  main.php                        │
└──────────────────────────────┬──────────────────────────────────┘
                               │  GitHub API (token encriptado)
              ┌────────────────▼─────────────────┐
              │        WP Admin — Cliente A       │
              │   aureodev Addons Manager         │
              │                                   │
              │   Browse → Install → Activate     │
              │   Edit → Save → Revert            │
              │   Publish back to GitHub          │
              └───────────────────────────────────┘
```

---

## Funcionalidades

### Gerenciamento de Addons
- **Browse & Install** — lista todos os addons do repositório GitHub com filtros por tipo, tag e busca
- **Ativação segura** — cada addon roda isolado com output buffering + try/catch + shutdown handler
- **Auto-desativação** — se um addon gerar erro fatal, é desativado automaticamente e o site continua no ar
- **Import via ZIP** — importa addons exportados de outros sites sem precisar do GitHub

### Controle de Versão Local
- Cada addon mantém um **histórico completo de versões** em disco
- Backup automático criado antes de **qualquer** alteração (update, edição, reversão)
- **Reversão em 1 clique** para qualquer versão anterior

```
/wp-content/aureodev-addons/login-personalizado/
├── current/          ← versão ativa agora
│   ├── main.php
│   └── style.css
└── backups/
    ├── v1.0.0/       ← instalação original
    ├── v1.1.0/       ← primeira edição
    └── edit-20260613-143022/  ← backup antes de edição manual
```

### Editor Inline
- Editor **CodeMirror** completo dentro do WP Admin
- Syntax highlighting para PHP, JS, CSS, HTML, JSON
- Troca de arquivo sem reload de página
- **Ctrl+S / Cmd+S** para salvar
- Publicação direta no GitHub com versionamento e changelog

### Contexto Inteligente do Site
- Coleta automática de dados do site na instalação: nome, URL, WordPress, PHP, tema, builders ativos
- Detecção de Elementor, Elementor Pro, Bricks, Divi, Beaver Builder, Oxygen, Breakdance, WPBakery
- **Exportar Contexto como JSON** — para usar como contexto ao criar novos addons na IA

### Debug & Auditoria
- **Audit Log** completo de todas as ações (install, activate, edit, revert, error, publish...)
- **Health Check** — status de conexão GitHub, diretórios, addons com erro, atualizações disponíveis
- **WP Debug Log** — últimas 50 linhas do debug.log diretamente no WP Admin

---

## Tipos de Addon

| Tipo | Hook de Execução | Arquivos | Caso de Uso |
|------|-----------------|----------|-------------|
| `plugin` | `plugins_loaded` | `main.php` + assets | Funcionalidade completa, integra com outros plugins |
| `snippet` | configurável (`init`, `wp_head`...) | `main.php` + assets | Modificações de comportamento do WP |
| `shortcode` | `init` | `main.php` + assets | `[meu_shortcode]` em posts e páginas |
| `css` | `wp_enqueue_scripts` | `style.css` | Customização visual global |
| `js` | `wp_enqueue_scripts` | `script.js` | Comportamento no frontend |

---

## Estrutura do Repositório GitHub

```
aureodark/addons-registry/
│
├── registry.json              ← manifest central (lido pelo plugin)
│
└── addons/
    ├── login-personalizado/
    │   ├── addon.json         ← metadados
    │   ├── main.php
    │   └── style.css
    └── header-fixo-elementor/
        ├── addon.json
        ├── main.php
        └── script.js
```

### Formato do `addon.json`

```json
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
```

---

## Fluxos de Trabalho

### Reutilizar um Addon entre Clientes

```
Browse Addons
  └─► filtrar por tag "pingo"
      └─► encontrar "login-personalizado" (origem: Pingo)
          └─► Instalar no site MarkDigital
              └─► Editar via Editor inline
                  └─► Testar
                      ├─► OK → Publicar no GitHub como v1.3.0
                      └─► Erro → Reverter para v1.2.0 (1 clique)
```

### Editar no PC com IA e Subir

```
Installed
  └─► Baixar ZIP do addon
      └─► Editar localmente com IA / terminal
          └─► Importar ZIP → backup automático criado
              └─► Testar
                  ├─► OK → Editor → Publicar no GitHub
                  └─► Erro → Reverter instantâneo
```

### Criar Contexto para a IA

```
Dashboard
  └─► Exportar Contexto → JSON com:
      ├─► Nome e URL do site
      ├─► Versão WordPress e PHP
      ├─► Tema ativo
      ├─► Builders detectados (Elementor, Bricks, Divi...)
      └─► Lista de plugins ativos
          └─► Colar no chat da IA como contexto
              └─► Gerar addon específico para este site
```

---

## Segurança

| Camada | Implementação |
|--------|--------------|
| Token GitHub | `openssl_encrypt` AES-256-CBC com `wp_salt('auth')` como chave |
| Páginas Admin | `current_user_can('manage_options')` em todas as rotas |
| Formulários | WordPress Nonces em todos os forms e requisições AJAX |
| Entradas | `sanitize_text_field`, `sanitize_file_name`, `wp_unslash` |
| Execução de addons | Output buffering + `try/catch(Throwable)` + shutdown handler |
| Diretório de addons | `index.php` de proteção gerado automaticamente |

---

## Arquitetura Interna

```
aureodev-addons-manager/
│
├── aureodev-addons-manager.php   Bootstrap, constantes, hooks de ciclo de vida
│
├── includes/
│   ├── class-aureodev-core.php       Singleton loader, cron, admin notices
│   ├── class-aureodev-context.php    Coleta e exporta dados do site
│   ├── class-aureodev-github.php     GitHub API — fetch, download, publish
│   ├── class-aureodev-addons.php     CRUD, versioning, ZIP import/export
│   ├── class-aureodev-runner.php     Execução segura de addons PHP
│   ├── class-aureodev-updater.php    Checagem de versões e publicação
│   └── class-aureodev-debug.php      Audit log, health check, WP debug log
│
├── admin/
│   ├── class-aureodev-admin.php      Menus WP Admin + AJAX handler central
│   ├── views/
│   │   ├── page-dashboard.php        Status do site e addons
│   │   ├── page-setup.php            Configuração inicial + wizard
│   │   ├── page-browse.php           Catálogo de addons do GitHub
│   │   ├── page-installed.php        Gerenciamento local
│   │   ├── page-editor.php           Editor CodeMirror inline
│   │   └── page-debug.php            Logs, health check, WP debug log
│   └── assets/
│       ├── admin.css                 UI com CSS custom properties
│       └── admin.js                  AJAX, CodeMirror, interações
│
├── registry-example/                 Exemplo de registry.json e addon
├── CLAUDE.md                         Briefing completo para IAs
└── README.md                         Este arquivo
```

---

## Banco de Dados

Duas tabelas criadas automaticamente na ativação:

**`wp_aureodev_addons`** — registro de addons instalados

```sql
id            BIGINT        PRIMARY KEY
slug          VARCHAR(100)  UNIQUE — identificador do addon
name          VARCHAR(200)  — nome legível
version       VARCHAR(20)   — versão instalada
type          VARCHAR(20)   — plugin | snippet | shortcode | css | js
tags          TEXT          — tags separadas por vírgula
status        VARCHAR(20)   — active | inactive | error
hook          VARCHAR(100)  — hook WordPress de execução
active_version VARCHAR(20)  — versão atualmente ativa
installed_at  DATETIME
updated_at    DATETIME
```

**`wp_aureodev_audit_log`** — histórico de todas as ações

```sql
id          BIGINT    PRIMARY KEY
addon_slug  VARCHAR   — qual addon foi afetado
action      VARCHAR   — install | update | activate | deactivate | edit
                        delete | revert | error | import | publish
user_id     BIGINT    — quem executou (0 = sistema/cron)
details     LONGTEXT  — JSON com detalhes (versão, arquivo, erro...)
created_at  DATETIME
```

---

## Requisitos

| Requisito | Versão Mínima |
|-----------|--------------|
| WordPress | 6.0+ |
| PHP | 7.4+ |
| Extensão PHP | `openssl`, `ZipArchive` |
| GitHub | Personal Access Token com permissão `repo` |

---

## Instalação

**1. Zipar o plugin**
```bash
cd plugins-wordpress/
zip -r aureodev-addons-manager.zip aureodev-addons-manager/
```

**2. Instalar no WordPress**
```
WP Admin → Plugins → Adicionar Novo → Enviar Plugin → Ativar
```

**3. Configurar o repositório GitHub**
```
aureodev Addons → Configurações → Inserir token GitHub + nome do repo
```
O repositório `aureodark/addons-registry` já vem configurado por padrão.

**4. Primeiro uso**
```
aureodev Addons → Browse Addons → Instalar → Ativar
```

---

## Convenções para Novos Addons

Todo addon no registry deve seguir a estrutura:

```
addons/{slug}/
├── addon.json    obrigatório — metadados
├── main.php      obrigatório para tipos PHP
├── style.css     opcional
└── script.js     opcional
```

Slugs usam `kebab-case`. Tags identificam o cliente de origem e tecnologias envolvidas.  
Versões seguem `semver`: `MAJOR.MINOR.PATCH`.

---

<div align="center">

**Construído por [Aureo Fernandes](https://aureofernandes.com.br) — @aureodark**

*Desenvolvido com IA. Gerenciado com aureodev.*

</div>
