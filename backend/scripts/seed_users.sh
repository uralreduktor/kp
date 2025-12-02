#!/bin/bash
# Скрипт для создания начальных пользователей

cd "$(dirname "$0")/.." || exit 1

echo "🌱 Создание пользователей из PASSWORD_PROTECTION.md..."
poetry run python -m app.cli.seed_users




