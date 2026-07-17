# EspoCRM Integration for Evolution CMS

Пакет обеспечивает единый вход (SSO) администраторов Evolution CMS в EspoCRM по протоколу OpenID Connect, а также встраивает интерфейс CRM в админ‑панель CMS.

## Возможности
- OIDC‑провайдер (Authorization Code Flow с PKCE) на базе `league/oauth2-server`.
- Генерация `id_token` с поддержкой `nonce`.
- Хранение авторизационных кодов в БД.
- Эндпоинты: `/oidc/authorize`, `/oidc/token`, `/oidc/userinfo`, `/oidc/jwks`.
- Встроенный iframe для доступа к EspoCRM из панели управления CMS.

## Требования
- Evolution CMS ≥ 3.x (с Laravel‑компонентами).
- PHP ≥ 8.2 с расширениями openssl, json, pdo_mysql, sodium (рекомендуется).
- EspoCRM, установленная отдельно (пакет не содержит CRM).
По умолчанию ожидается, что EspoCRM доступна по пути /manager/media/espoCRM.

## Установка
1. Установка пакета
   ```bash
   php artisan package:installrequire roilafx/Espocrmevo "*"
   ```
2. Публикация стилей и скриптов
   ```bash
   php artisan vendor:publish --provider="roilafx\Espocrmevo\EspocrmevoServiceProvider"
   ```
3. Выполните миграции:
   ```bash
   php artisan migrate
   ```
4. Сгенерируйте RSA‑ключи (в core/storage/keys):
   ```bash
   openssl genrsa -out storage/keys/private.key 2048
   openssl rsa -in storage/keys/private.key -pubout -out storage/keys/public.key
   ```
5. Сгенерируйте ключ шифрования
    Выполните в терминале:
    ```bash
    php -r "echo 'def00000' . bin2hex(random_bytes(32));"
    ```

6. Полученный ключ занести в файл .env именем encryptionKey

7. Установить EspoCRM
   Перейдите в модуль и выполните установку. Потребуется дополнительная БД. 
   Я брал за основу вот этот релиз и на нем все тестировал [GitHub](https://github.com/espocrm/espocrm/releases/tag/10.0.2)


## Настройка

### Создание OIDC‑клиента
Пакет предоставляет консольную команду для создания клиента:

   ```bash
   php artisan espocrmevo:create-client --id=espocrm --redirect="https://your.domain/manager/media/espoCRM/oauth-callback.php" --name="EspoCRM"
   ```
Если не указать --secret, будет сгенерирован случайный ключ длиной 64 символа.
redirect можно скопировать из Админиистративной панели EcpoCRM Администрирование -> Аутентификация -> OIDC
Запись появится в таблице oidc_clients.

### Переменные окружения (.env)
|------------|------------|--------------|
| Переменная |	Назначение | По умолчанию |
|------------|------------|--------------|
| OIDC_ISSUER | Издатель токенов (iss) | route.local |
| OIDC_AUDIENCE | Получатель токенов (aud) | espocrm |
| OIDC_KEY_ID | Идентификатор ключа (kid) в JWKS | 1 |
|encryptionKey | Ключ шифрования OAuth‑кодов (обязателен) | — |


### Конфигурация EspoCRM
В административной панели EspoCRM:  
**Администрирование -> Аутентификация -> OIDC**

- Клиент ID: `espocrm`
- Секрет клиента: если задавали
- Authorization Endpoint: `https://your.domain/oidc/authorize`
- Token Endpoint: `https://your.domain/oidc/token`
- UserInfo Endpoint: `https://your.domain/oidc/userinfo`
- JWKS Endpoint: `https://your.domain/oidc/jwks`
- Scopes: `openid profile email phone`
- Username Claim: `username` (рекомендуется)
- Создать пользователя: +
- Включить PKCE: +

## Структура пакета
```
core/custom/packages/espocrmevo/
├── src/
│   ├── Commands/              # CreateClient
│   ├── Controllers/           # OIDCController, CrmController
│   ├── Entities/              # Сущности токенов, кодов
│   ├── Grants/                # NonceAuthCodeGrant
│   ├── Models/                # OidcClient, AuthCode
│   ├── Repositories/          # Репозитории для League
│   ├── RequestTypes/          # NonceAuthorizationRequest
│   ├── migrations/            # Миграции таблиц
│   └── EspocrmevoServiceProvider.php
├── routes.php                 # Маршруты для iframe
├── routes_oidc.php            # Маршруты OIDC
└── README.md
```

## Ключевые особенности реализации
- `nonce` передаётся не через сессию, а через расширенный объект авторизации и БД.
- Шифрование кода – через `defuse/php-encryption` (как в библиотеке).
- Поддержка PKCE включена по умолчанию.
