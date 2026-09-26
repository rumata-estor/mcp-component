# modxMCP — MODX 3 fork

Ветка `modx3` — адаптация modxMCP для **MODX Revolution 3.x** без зависимости от deprecated MODX 2 class aliases.

Целевая совместимость: актуальная ветка **MODX 3.x**. Текущие изменения проверены на **MODX 3.2.4-pl**. До выпуска отдельного transport-релиза используйте ветку `modx3`.

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

## Что дополнительно проверено для MODX 3

В ветке `modx3` проверены и исправлены несколько несовместимостей MODX 2 → MODX 3:

- type hints для `modSystemSetting`, `xPDOObject` и `xPDOQuery` переведены на MODX 3 / xPDO 3 namespaces;
- `update_resource_tvs` проверяет результат `setTVValue()` и возвращает ошибку, если значение TV не удалось сохранить;
- добавлен `modx_list_tv_values` — показывает все явно сохранённые значения конкретного TV, включая возможные старые значения после смены шаблона ресурса;
- добавлен `modx_clear_tv_values` — без `confirm=true` работает только как preview, с подтверждением очищает сохранённые значения TV;
- `modx_list_resources` возвращает `deleted`, `deletedon` и `deletedby`, что позволяет корректно работать с состоянием корзины.

На тестовом MODX 3.2.4-pl проверены обычные element/resource вызовы, системные настройки, чтение TV-значений и dry-run очистки TV.

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
