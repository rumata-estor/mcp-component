# MODX3 MCP

MCP-сервер и компонент для **MODX Revolution 3.x**, основанный на оригинальном modxMCP и переработанный под native MODX 3 API.

Целевая совместимость: **MODX Revolution 3.x**. CI проверяет core processors на MODX 3.2.2-pl, 3.2.4-pl и актуальной ветке 3.x.

## Установка на сайт

### Рекомендуемый способ для MODX 3: headless

modxMCP не обязан быть установлен как пакет MODX. Для нашей схемы основной вариант — скрытая headless-установка:

- **нет записи в Package Manager**;
- создаётся пункт «Компоненты → MODX3 MCP» и экран графа связей;
- файлы размещаются в `core/components/modxmcp/` и `assets/components/modxmcp/`;
- создаются namespace `modxmcp`, системные настройки `modxmcp.*` и Manager menu;
- API-токен создаётся автоматически во время установки, если ещё не задан;
- повторный запуск того же скрипта обновляет файлы и сохраняет существующие значения настроек.

Разместить репозиторий на сервере рядом с MODX (либо указать путь к `config.core.php`) и выполнить:

```bash
php _build/install.headless.php
```

Если `config.core.php` находится вне дерева репозитория:

```bash
MODX_CONFIG_CORE=/full/path/to/config.core.php php _build/install.headless.php
```

После выполнения скрипт выводит endpoint и API token. Установка доступна **только через CLI** и не создаёт веб-инсталлятор.

Для обновления после новых изменений в ветке `modx3` используется тот же запуск:

```bash
git pull
php _build/install.headless.php
```

### Transport package

Сборка `modx3mcp-*.transport.zip` предназначена для штатной установки через Package Manager MODX и является основным переносимым вариантом для установки на другой сайт.

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