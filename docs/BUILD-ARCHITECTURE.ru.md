[English](BUILD-ARCHITECTURE.md) | **Русский**

# Архитектура сборки

Одно дерево исходников коннектора создаёт два платформенных релизных артефакта:

- **MODX Revolution 2.8.x** — ключ платформы `modx2`, transport-пакет `modxmcp-<version>-pl.transport.zip`;
- **MODX Revolution 3.x** — ключ платформы `modx3`, transport-пакет `modx3mcp-<version>-pl.transport.zip`.

Обе сборки используют один MCP-клиент, один публичный контракт действий и один модульный Runtime из `core/components/modxmcp/src/`.

Общая часть включает:

- `core/components/modxmcp/src/`;
- Tool-классы, registry, extras, endpoint-логику и документацию;
- платформенно-нейтральные assets и manager-логику там, где MODX 2 и MODX 3 ведут себя одинаково.

Платформенные build/bootstrap-файлы находятся в `_build/platform/<platform>/`. Скрипт `_build/prepare-release.py` создаёт чистое staging-дерево для выбранной платформы и накладывает только нужный overlay.

Архитектура специально избегает разбросанных по доменным Tool-классам проверок вида `if (MODX_VERSION...)`. Различия MODX 2/3 изолированы на границе bootstrap/build и за `PlatformInterface`.

## Подготовка release-дерева

MODX 3:

```bash
python3 _build/prepare-release.py --platform modx3 --output /tmp/modxmcp-modx3
```

MODX 2:

```bash
python3 _build/prepare-release.py --platform modx2 --output /tmp/modxmcp-modx2
```

Каждый transport-пакет нужно собирать на установке MODX той же основной версии.

## Статус версии 1.1.0

Версия 1.1.0 — первая общая линия исходников для MODX 2 и MODX 3.

- модульное покрытие действий: **182/182**;
- MODX 3.2.4-pl: полный live regression завершён;
- совместимость процессоров MODX 3: проверяется на 3.2.2-pl, 3.2.4-pl и актуальной ветке 3.x;
- MODX 2: platform staging и статическая/PHP-совместимость проходят;
- перед публикацией 1.1.0 как окончательного стабильного релиза требуется отдельный live release-smoke на MODX 2.
