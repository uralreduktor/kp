# Управление пользователями

## Создание пользователей из seed файла

Создать начальных пользователей из `PASSWORD_PROTECTION.md`:

```bash
cd /var/www/kp/backend
poetry run python -m app.cli.seed_users
```

Пересоздать пользователей (удалить существующих и создать заново):

```bash
poetry run python -m app.cli.seed_users --force
```

## Управление пользователями через CLI

### Список всех пользователей

```bash
poetry run users list
```

### Создать нового пользователя

```bash
# Интерактивный режим (пароль будет запрошен)
poetry run users create user@example.com

# Сделать суперпользователем (интерактивно)
poetry run users create admin@example.com --superuser
```

**Важно:** 
- Флаг `--superuser` должен быть указан при создании пользователя
- Чтобы сделать существующего пользователя суперпользователем, используйте прямое обновление в БД или пересоздайте пользователя

### Изменить пароль

```bash
# Интерактивный режим (пароль будет запрошен)
poetry run users update-password user@example.com
```

### Удалить пользователя

```bash
# С подтверждением
poetry run users delete user@example.com

# Без подтверждения
poetry run users delete user@example.com --yes
```

### Активировать/деактивировать пользователя

```bash
poetry run users toggle-active user@example.com
```

## Примеры использования

### Создать обычного пользователя

```bash
poetry run users create newuser@kp.uralreduktor.com
```

### Создать администратора

```bash
poetry run users create admin2@kp.uralreduktor.com --superuser
```

### Изменить пароль существующего пользователя

```bash
poetry run users update-password admin@kp.uralreduktor.com
```

### Временно отключить пользователя

```bash
poetry run users toggle-active user@example.com
```

### Полностью удалить пользователя

```bash
poetry run users delete user@example.com --yes
```

## Важные замечания

- При удалении пользователя также удаляются все его сессии и доверенные устройства
- Пароли хэшируются с использованием Argon2id
- Суперпользователи имеют полный доступ ко всем функциям системы
- Деактивированные пользователи не могут войти в систему

