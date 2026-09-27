# MODX3 MCP

MCP-сервер и компонент для **MODX Revolution 3.x**, основанный на оригинальном modxMCP и переработанный под native MODX 3 API.

Целевая совместимость: **MODX Revolution 3.x**. CI проверяет core processors на MODX 3.2.2-pl, 3.2.4-pl и актуальной ветке 3.x.

## Установка на сайт

### Headless-установка для агентной схемы

MODX3 MCP не обязан быть зарегистрирован в Package Manager. Для управляемой агентной схемы можно использовать CLI-only headless-установку:

- **нет записи в Package Manager**;
- создаётся пункт «Компоненты → MODX3 MCP» и экран графа связей;
- файлы размещаются в `core/components/modxmcp/` и `assets/components/modxmcp/`;
- создаются namespace `modxmcp`, системные настройки `modxmcp.*` и Manager menu;
- API-токен создаётся автоматически во время установки, если ещё не задан;
- installer явно допускает только MODX Revolution `>=3.0.0,<4.0.0`;
- повторный запуск обновляет файлы и сохраняет существующие значения всех `modxmcp.*` настроек;
- новые `core/assets` сначала собираются в staging-каталогах и только затем подменяют текущие; при ошибке до завершения installer'а предыдущие component trees восстанавливаются;
- runtime audit log `core/components/modxmcp/logs` сохраняется при headless update.

Разместить репозиторий на сервере рядом с MODX (либо указать путь к `config.core.php`) и выполнить:

```bash
php _build/install.headless.php
```

Если `config.core.php` находится вне дерева репозитория:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.headless.php
```

При первом запуске скрипт выводит endpoint и сгенерированный API token. При последующих обновлениях полный существующий token скрыт; для явного вывода используйте `--show-token`. Установка доступна **только через CLI** и не создаёт веб-инсталлятор.

Для обновления после новых изменений в ветке `modx3` используется тот же запуск:

```bash
git pull
php _build/install.headless.php
```

### Transport package

Для обычной переносимой установки на другой сайт предназначен штатный MODX transport package. Для версии `1.0.0-pl` его сигнатура — `modx3mcp-1.0.0-pl`, файл — `modx3mcp-1.0.0-pl.transport.zip`.

Package metadata содержит зависимость `modx >=3.0.0,<4.0.0`, поэтому Package Manager должен отклонить установку вне ветки MODX 3.x. При upgrade существующие значения `modxmcp.*` не перезаписываются.

Сборка через CLI:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/build.transport.php
```

Для release smoke-test на отдельном MODX 3-сайте можно использовать CLI verifier:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.transport.php
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.transport.php --action=uninstall
```

Успешный uninstall verifier требует полного удаления component directories, namespace `modxmcp`, обоих Manager menu и `modxmcp.*` settings; только после этого он выводит `TRANSPORT_UNINSTALL_VERIFY_OK`.

### Полный release smoke на втором сайте

На отдельном тестовом MODX 3-сайте весь цикл можно прогнать одной командой:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php bash _build/release.smoke.sh
```

Runner выполняет PHP lint, локальные regression-проверки при наличии Python/Node, сборку transport package, fresh install, HTTPS/token/API smoke, безопасный CRUD временного chunk, повторную установку с проверкой сохранности всех 16 settings, clean uninstall и финальную установку с read-only MCP smoke. API token в вывод не попадает. По умолчанию сайт остаётся с установленным компонентом; `--leave-uninstalled` оставляет его удалённым после проверки.

## Подключение

В `.mcp.json` проекта указать адрес сайта и токен, затем переподключить MCP (`/mcp`):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/mcp-component#modx3"],
      "env": {
        "MODX_MCP_SITE_URL": "https://САЙТ/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "ваш-токен"
      }
    }
  }
}
```

### Опциональный внешний audit hook

Для инфраструктур, которым нужен собственный task-level журнал изменений, клиент поддерживает необязательную переменную `MODX_MCP_AUDIT_HOOK`. В ней указывается абсолютный путь к исполняемой команде. После **успешного mutating MCP-вызова** клиент запускает её напрямую, без shell, и передаёт JSON через stdin: имя tool, аргументы, результат, `site_id`, actor metadata и пути автоматически созданных safety backup.

Если переменная не задана, поведение клиента не меняется и внешних зависимостей нет. Ошибка hook после уже успешной записи выводится как warning и не превращает выполненное изменение в ложный failure.

Hook работает с данными операции и поэтому должен считаться доверенной локальной интеграцией: храните его с ограниченными правами и самостоятельно редактируйте чувствительные значения перед записью в сторонний журнал. Read-only команды (`list/get/search/read/view/check/describe/find/suggest` и соответствующие служебные read actions) hook не вызывают.
