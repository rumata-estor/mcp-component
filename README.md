# modxMCP — MODX 3 fork

Ветка `modx3` — адаптация modxMCP для **MODX Revolution 3.x** без зависимости от deprecated MODX 2 class aliases.

Целевая совместимость: **MODX 3.2.2-pl** и актуальная ветка **MODX 3.x**. До завершения проверки на staging используйте именно ветку `modx3`, а не transport-релизы исходного проекта.

## Установка на сайт

1. Для текущей MODX 3-ветки сначала собрать transport-пакет из `modx3` на тестовой установке MODX 3, затем установить его через **Дополнения → Установщик → Загрузить пакет**.
2. Открыть **Дополнения → modxMCP** и скопировать токен (компонент уже включён, токен сгенерирован при установке).

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
