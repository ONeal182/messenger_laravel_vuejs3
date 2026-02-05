# Laravel Messenger

Минимальный Slack‑like мессенджер на Laravel 12 + Vite (Vue). Поднимается в Docker‑compose: PHP-FPM + Nginx + MySQL + Redis. WebSockets — через Laravel Reverb (порт 6001).

## Требования
- Docker + Docker Compose v2
- make (опционально)

## Старт 
```bash
# 1. Клонируем репо и переходим в корень
git clone <repo-url> && cd laravel-messanger

# 2. Копируем переменные окружения
cp src/.env.example src/.env

# 3. Поднимаем контейнеры
docker compose up -d

# 4. Заходим в PHP-контейнер
docker exec -it messenger_app bash

# 5. Устанавливаем зависимости и готовим приложение
composer install
npm ci
php artisan key:generate
php artisan migrate

# 6. Запускаем фронтенд dev-сервер (в том же контейнере, отдельная сессия)
npm run dev -- --host
и
php artisan reverb:start --host=0.0.0.0 --port=6001
```

После запуска:
- API/SPA через Nginx: http://localhost:8080
- Swagger: http://localhost:8080/api/documentation#/
- Vite HMR: http://localhost:5173
- Reverb WS: ws://localhost:6001

## Полезные команды (в контейнере `messenger_app`)
- Тесты бэкенда: `php artisan test`
- Запуск очередей (если понадобится): `php artisan queue:work`
- Очистка кэшей: `php artisan optimize:clear`

## Структура
- `src/` — код приложения (Laravel).
- `docker/` — докер-файлы и конфиги (PHP, Nginx, MySQL).
- `docker-compose.yml` — оркестрация сервисов.

## Переменные окружения
Основные поля в `src/.env`:
- `DB_HOST=mysql`, `DB_PORT=3306`, `DB_DATABASE=messenger`, `DB_USERNAME=root`, `DB_PASSWORD=root`
- `REDIS_HOST=redis`
- `REVERB_PORT=6001`, `REVERB_HOST=0.0.0.0`

## Сборка для продакшена (кратко)
1. `npm run build`
2. `php artisan config:cache && php artisan route:cache`
3. Собрать образ на базе `docker/php/Dockerfile` + статикой из `public/build`.
4. Выкатить образ + миграции (`php artisan migrate --force`) и запустить Reverb.

## Тесты
Feature-тесты покрывают авторизацию, чаты, сообщения, пользователей. Запуск: `php artisan test`.

## Лицензия
MIT
