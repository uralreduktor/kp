# Миграция на FastAPI + Postgres Auth

## ✅ Выполнено

1. ✅ Создан FastAPI backend проект в `/var/www/kp/backend`
2. ✅ Настроена БД Postgres (kp_db на порту 5433)
3. ✅ Применены миграции Alembic (таблицы users, sessions, trusted_devices, audit_log, password_resets)
4. ✅ Реализованы auth endpoints (`/api/auth/login`, `/api/auth/refresh`, `/api/auth/logout`, `/api/auth/me`)
5. ✅ Реализованы device endpoints (`/api/devices/`, `/api/devices/revoke`)
6. ✅ Созданы пользователи из PASSWORD_PROTECTION.md
7. ✅ Интегрирован фронтенд (index.html) с новым API
8. ✅ Подготовлена новая конфигурация nginx без Basic Auth

## 🚀 Применение изменений

### Шаг 1: Запуск FastAPI сервера

```bash
cd /var/www/kp/backend
poetry run uvicorn app.main:app --host 0.0.0.0 --port 8001
```

Или через systemd (после установки):

```bash
sudo cp /var/www/kp/backend/kp-auth-backend.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable kp-auth-backend
sudo systemctl start kp-auth-backend
sudo systemctl status kp-auth-backend
```

### Шаг 2: Применение новой конфигурации nginx

```bash
cd /var/www/kp
sudo ./scripts/apply_nginx_fastapi.sh
```

Скрипт автоматически:
- Создаст резервную копию текущей конфигурации
- Применит новую конфигурацию
- Проверит синтаксис
- Перезагрузит nginx

### Шаг 3: Проверка работы

1. Откройте https://kp.uralreduktor.com
2. Должна появиться форма логина (без Basic Auth)
3. Войдите с одним из созданных пользователей:
   - `admin@kp.uralreduktor.com` / `@kp2025#@`
   - `SidorkinV@kp.uralreduktor.com` / `Svl@kp2025`
   - и т.д.

## 📋 Структура проекта

```
/var/www/kp/
├── backend/                    # FastAPI проект
│   ├── app/
│   │   ├── api/routes/        # API роутеры
│   │   ├── core/              # Конфигурация
│   │   ├── models/            # SQLAlchemy модели
│   │   ├── repositories/      # Слой доступа к БД
│   │   ├── schemas/           # Pydantic схемы
│   │   ├── services/          # Бизнес-логика
│   │   └── utils/             # Утилиты
│   ├── alembic/               # Миграции БД
│   ├── scripts/               # Вспомогательные скрипты
│   └── .env                   # Переменные окружения
├── js/
│   └── auth.js                # Модуль авторизации для фронтенда
├── index.html                 # Главная страница (обновлена)
└── nginx-site-fastapi.conf    # Новая конфигурация nginx
```

## 🔧 API Endpoints

### Auth
- `POST /api/auth/login` - Вход пользователя
- `POST /api/auth/refresh` - Обновление сессии через устройство
- `POST /api/auth/logout` - Выход
- `GET /api/auth/me` - Информация о текущем пользователе

### Devices
- `GET /api/devices/` - Список доверенных устройств
- `POST /api/devices/revoke` - Отзыв устройства

### Health
- `GET /api/health/ping` - Проверка работоспособности

## 🔒 Безопасность

- ✅ HTTPS обязателен (SSL сертификаты Let's Encrypt)
- ✅ Cookies: `Secure`, `HttpOnly`, `SameSite=Lax`
- ✅ Пароли хэшируются Argon2id
- ✅ Токены хэшируются bcrypt
- ✅ Device fingerprint для защиты от кражи токенов
- ✅ Rate limiting (планируется через Redis)

## 📊 База данных

- **БД:** `kp_db` на порту 5433
- **Пользователь:** `kp`
- **Таблицы:**
  - `users` - пользователи
  - `sessions` - активные сессии
  - `trusted_devices` - доверенные устройства
  - `audit_log` - журнал аудита
  - `password_resets` - сбросы паролей

## 🔄 Откат изменений

Если нужно вернуться к Basic Auth:

```bash
# Восстановить резервную копию nginx конфига
sudo cp /etc/nginx/sites-available/kp.uralreduktor.com.backup.* /etc/nginx/sites-available/kp.uralreduktor.com
sudo nginx -t
sudo systemctl reload nginx

# Остановить FastAPI сервер
sudo systemctl stop kp-auth-backend
```

## 🐛 Troubleshooting

### FastAPI сервер не запускается

```bash
# Проверить логи
sudo journalctl -u kp-auth-backend -f

# Проверить порт 8001
sudo netstat -tlnp | grep 8001

# Проверить .env файл
cat /var/www/kp/backend/.env
```

### Ошибки подключения к БД

```bash
# Проверить доступность Postgres
PGPASSWORD='xf3x3VRpDVF' psql -h localhost -p 5433 -U kp -d kp_db -c "SELECT 1;"

# Проверить DATABASE_URL в .env
grep DATABASE_URL /var/www/kp/backend/.env
```

### Nginx не проксирует запросы

```bash
# Проверить конфигурацию
sudo nginx -t

# Проверить логи nginx
sudo tail -f /var/log/nginx/kp.uralreduktor.com.error.log

# Проверить, что FastAPI слушает на порту 8001
curl http://localhost:8001/api/health/ping
```

## 📝 Следующие шаги

1. Настроить systemd service для автоматического запуска
2. Добавить rate limiting через Redis
3. Настроить мониторинг (Prometheus/Grafana)
4. Добавить логирование в audit_log
5. Реализовать страницу управления устройствами во фронтенде

