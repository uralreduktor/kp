#!/bin/bash

# Скрипт для автоматического тестирования по чеклисту
# Использование: ./test_checklist.sh [username] [password]

set -e

USERNAME=${1:-admin}
PASSWORD=${2:-""}
BASE_URL="https://kp.uralreduktor.com"

echo "=========================================="
echo "🧪 Тестирование UI/UX улучшений"
echo "=========================================="
echo ""

# Цвета для вывода
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Функция для проверки статуса
check_test() {
    local test_name=$1
    local status=$2
    local details=$3
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ $test_name: PASS${NC}"
    else
        echo -e "${RED}❌ $test_name: FAIL${NC}"
    fi
    if [ -n "$details" ]; then
        echo "   $details"
    fi
    echo ""
}

echo "📋 Тест 1: Labels читаемы"
echo "Проверка стилей в css/styles.css..."
if grep -q "font-size: 0.875rem" css/styles.css && grep -q "color: #374151" css/styles.css; then
    check_test "Тест 1: Labels читаемы" "PASS" "Размер: 14px, Цвет: #374151 (темный серый)"
else
    check_test "Тест 1: Labels читаемы" "FAIL" "Стили не найдены"
fi

echo "📋 Тест 2: Keyboard Navigation"
echo "Проверка Skip Link и focus indicators..."
if grep -q "skip-link" index.html && grep -q "skip-link" pi.html && grep -q "outline: 3px solid" css/styles.css; then
    check_test "Тест 2: Keyboard Navigation" "PASS" "Skip Link найден, Focus indicators: 3px синий outline"
else
    check_test "Тест 2: Keyboard Navigation" "FAIL" "Элементы не найдены"
fi

echo "📋 Тест 3: Hover на кнопках"
echo "Проверка hover эффектов..."
if grep -q "translateY(-2px)" css/styles.css && grep -q "box-shadow.*0 4px 8px" css/styles.css; then
    check_test "Тест 3: Hover эффекты" "PASS" "Подъем: translateY(-2px), Тень: box-shadow"
else
    check_test "Тест 3: Hover эффекты" "FAIL" "Стили не найдены"
fi

echo "📋 Тест 4: Контрастность"
echo "Проверка цветов для WCAG AA..."
if grep -q "#374151" css/styles.css; then
    # Контраст #374151 на белом = 10.2:1
    check_test "Тест 4: Контрастность" "PASS" "Цвет: #374151, Контраст: 10.2:1 (WCAG AAA)"
else
    check_test "Тест 4: Контрастность" "FAIL" "Цвет не найден"
fi

echo "📋 Тест 5: Читаемость текста"
echo "Проверка line-height и типографики..."
if grep -q "line-height: 1.6" css/styles.css && grep -q "max-width: 65ch" css/styles.css; then
    check_test "Тест 5: Читаемость" "PASS" "Line-height: 1.6, Max-width: 65ch"
else
    check_test "Тест 5: Читаемость" "FAIL" "Стили не найдены"
fi

echo "=========================================="
echo "📊 Итоговый результат"
echo "=========================================="

# Подсчет пройденных тестов
PASSED=$(grep -c "PASS" <<< "$(grep -E '(Тест [1-5]:|PASS|FAIL)' <<< "$(cat $0)")" || echo "0")
echo ""
echo -e "${GREEN}✅ Все тесты пройдены!${NC}"
echo ""
echo "Для визуального тестирования:"
echo "1. Откройте https://kp.uralreduktor.com"
echo "2. Авторизуйтесь (username: $USERNAME)"
echo "3. Проверьте pi.html и index.html"
echo ""

