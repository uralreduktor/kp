#!/bin/bash
# Скрипт для установки systemd service для автозапуска FastAPI

set -e

SERVICE_NAME="kp-auth-backend"
SERVICE_FILE="/var/www/kp/backend/kp-auth-backend.service"
SYSTEMD_PATH="/etc/systemd/system/${SERVICE_NAME}.service"

echo "🔧 Установка systemd service для автозапуска FastAPI..."

# Проверяем права
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Этот скрипт требует sudo прав"
    echo "Запустите: sudo $0"
    exit 1
fi

# Проверяем наличие файла service
if [ ! -f "$SERVICE_FILE" ]; then
    echo "❌ Файл service не найден: $SERVICE_FILE"
    exit 1
fi

# Копируем service файл
echo "📋 Копирование service файла..."
cp "$SERVICE_FILE" "$SYSTEMD_PATH"

# Перезагружаем systemd
echo "🔄 Перезагрузка systemd daemon..."
systemctl daemon-reload

# Включаем автозапуск
echo "✅ Включение автозапуска..."
systemctl enable "$SERVICE_NAME"

# Запускаем сервис
echo "🚀 Запуск сервиса..."
systemctl start "$SERVICE_NAME"

# Проверяем статус
echo ""
echo "📊 Статус сервиса:"
systemctl status "$SERVICE_NAME" --no-pager -l || true

echo ""
echo "✨ Готово! Сервис установлен и запущен."
echo ""
echo "Полезные команды:"
echo "  sudo systemctl status $SERVICE_NAME    # Проверить статус"
echo "  sudo systemctl restart $SERVICE_NAME   # Перезапустить"
echo "  sudo systemctl stop $SERVICE_NAME      # Остановить"
echo "  sudo systemctl disable $SERVICE_NAME   # Отключить автозапуск"
echo "  sudo journalctl -u $SERVICE_NAME -f   # Просмотр логов"

