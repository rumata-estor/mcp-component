# modxMCP — MODX 3 fork

Ветка `modx3` — адаптация modxMCP для **MODX Revolution 3.x** без зависимости от deprecated MODX 2 class aliases.

Целевая совместимость: **MODX 3.2.2-pl** и актуальная ветка **MODX 3.x**. До завершения проверки на staging используйте именно ветку `modx3`, а не transport-релизы исходного проекта.

## Установка на сайт

### Рекомендуемый способ для MODX 3: headless

modxMCP не обязан быть установлен как пакет MODX. Для нашей схемы основной вариант — скрытая headless-установка:

- **нет записи в Package Manager**;
- **нет пункта «Компоненты → modxMCP»**;
- файлы размещаются в `core/components/modxmcp/` и `assets/components/modxmcp/`;
- создаются только namespace `modxmcp` и системные настройки `modxmcp.*`;
- токен создаётся автоматически при первом запуске;
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

Сборка `modxMCP3-*.transport.zip` оставлена как дополнительный вариант для тех случаев, когда компонент нужно регистрировать в Package Manager MODX. Для нашей агентной схемы она не требуется.

## Подключение

В `.mcp.json` проекта указать адрес сайта и токен, затем переподключить MCP (`/mcp`):

```json
{
  "mcpServers": {
    "modx": {
      "command": "npx",
      "args": ["-y", "github:rumata-estor/mcp-component#modx3"],
      "env": {
        "MODX_MCP_SITE_URL": "http://САЙТ/assets/components/modxmcp/api.php",
        "MODX_MCP_TOKEN": "ваш-токен"
      }
    }
  }
}
```
