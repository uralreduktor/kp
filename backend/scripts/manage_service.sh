#!/bin/bash
# Утилита для управления systemd service

SERVICE_NAME="kp-auth-backend"

case "${1:-status}" in
    start)
        echo "🚀 Запуск сервиса..."
        sudo systemctl start "$SERVICE_NAME"
        sudo systemctl status "$SERVICE_NAME" --no-pager -l
        ;;
    stop)
        echo "⏹️  Остановка сервиса..."
        sudo systemctl stop "$SERVICE_NAME"
        sudo systemctl status "$SERVICE_NAME" --no-pager -l
        ;;
    restart)
        echo "🔄 Перезапуск сервиса..."
        sudo systemctl restart "$SERVICE_NAME"
        sudo systemctl status "$SERVICE_NAME" --no-pager -l
        ;;
    status)
        sudo systemctl status "$SERVICE_NAME" --no-pager -l
        ;;
    logs)
        echo "📋 Логи сервиса (Ctrl+C для выхода):"
        sudo journalctl -u "$SERVICE_NAME" -f
        ;;
    enable)
        echo "✅ Включение автозапуска..."
        sudo systemctl enable "$SERVICE_NAME"
        ;;
    disable)
        echo "❌ Отключение автозапуска..."
        sudo systemctl disable "$SERVICE_NAME"
        ;;
    *)
        echo "Использование: $0 {start|stop|restart|status|logs|enable|disable}"
        echo ""
        echo "Команды:"
        echo "  start    - Запустить сервис"
        echo "  stop     - Остановить сервис"
        echo "  restart  - Перезапустить сервис"
        echo "  status   - Показать статус"
        echo "  logs     - Показать логи (follow mode)"
        echo "  enable   - Включить автозапуск"
        echo "  disable  - Отключить автозапуск"
        exit 1
        ;;
esac

