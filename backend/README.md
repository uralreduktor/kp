# KP Auth Backend

FastAPI + Postgres сервис аутентификации для `kp.uralreduktor.com`.

## Quick start

```bash
poetry install
cp .env.example .env
# Настроить DATABASE_URL в .env
poetry run alembic upgrade head
poetry run python -m app.cli.seed_users  # Создать начальных пользователей
poetry run uvicorn app.main:app --reload
```

## Автозапуск сервера (systemd)

Для автоматического запуска FastAPI сервера при загрузке системы:

```bash
sudo /var/www/kp/backend/scripts/install_service.sh
```

Или вручную:

```bash
sudo cp /var/www/kp/backend/kp-auth-backend.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable kp-auth-backend
sudo systemctl start kp-auth-backend
sudo systemctl status kp-auth-backend
```

После установки сервис будет автоматически запускаться при перезагрузке системы и автоматически перезапускаться при сбоях.

## Управление пользователями

### Создание начальных пользователей

Для создания начальных пользователей из документации:

```bash
poetry run python -m app.cli.seed_users
```

Пересоздать пользователей (удалить существующих и создать заново):

```bash
poetry run python -m app.cli.seed_users --force
```

Или через скрипт:

```bash
./scripts/seed_users.sh
```

### CLI для управления пользователями

Доступна утилита `users` для управления пользователями:

```bash
# Список всех пользователей
poetry run users list

# Создать нового пользователя (интерактивно)
poetry run users create user@example.com

# Создать суперпользователя (интерактивно)
poetry run users create admin@example.com --superuser

# Изменить пароль (интерактивно)
poetry run users update-password user@example.com

# Удалить пользователя
poetry run users delete user@example.com --yes

# Активировать/деактивировать пользователя
poetry run users toggle-active user@example.com
```

Подробнее см. [USERS.md](USERS.md).

## API Endpoints

- `POST /api/auth/login` - Вход пользователя
- `POST /api/auth/refresh` - Обновление сессии через устройство
- `POST /api/auth/logout` - Выход
- `GET /api/auth/me` - Информация о текущем пользователе
- `GET /api/devices/` - Список доверенных устройств
- `POST /api/devices/revoke` - Отзыв устройства

## Тестирование

```bash
poetry run pytest
poetry run ruff check app/
poetry run mypy app/
```
