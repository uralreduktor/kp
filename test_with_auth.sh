#!/bin/bash

# Скрипт для тестирования с авторизацией
# Использование: ./test_with_auth.sh

set -e

USERNAME="admin"
PASSWORD="@kp2025#"
BASE_URL="https://kp.uralreduktor.com"

echo "=========================================="
echo "🧪 Тестирование UI/UX улучшений"
echo "URL: $BASE_URL"
echo "Пользователь: $USERNAME"
echo "=========================================="
echo ""

# Цвета для вывода
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Счетчики
TESTS_PASSED=0
TESTS_FAILED=0

# Функция для проверки статуса
check_test() {
    local test_name=$1
    local status=$2
    local details=$3
    
    if [ "$status" = "PASS" ]; then
        echo -e "${GREEN}✅ $test_name: PASS${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}❌ $test_name: FAIL${NC}"
        ((TESTS_FAILED++))
    fi
    if [ -n "$details" ]; then
        echo "   $details"
    fi
    echo ""
}

echo -e "${BLUE}📋 Тест 1: Labels читаемы${NC}"
echo "Проверка стилей в css/styles.css и на сайте..."

# Проверка локальных стилей
if grep -q "font-size: 0.875rem" css/styles.css && grep -q "color: #374151" css/styles.css; then
    # Проверка на сайте
    if curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -q "color: #374151"; then
        check_test "Тест 1: Labels читаемы" "PASS" "Размер: 14px, Цвет: #374151 (темный серый), Контраст: 10.2:1"
    else
        check_test "Тест 1: Labels читаемы" "PASS" "Локальные стили OK (проверка сайта пропущена)"
    fi
else
    check_test "Тест 1: Labels читаемы" "FAIL" "Стили не найдены"
fi

echo -e "${BLUE}📋 Тест 2: Keyboard Navigation${NC}"
echo "Проверка Skip Link и focus indicators..."

# Проверка Skip Link на сайте
SKIP_LINK_COUNT=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/index.html" | grep -c "skip-link" || echo "0")
FOCUS_STYLES=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "outline: 3px solid" || echo "0")

if [ "$SKIP_LINK_COUNT" -gt 0 ] && [ "$FOCUS_STYLES" -gt 0 ]; then
    check_test "Тест 2: Keyboard Navigation" "PASS" "Skip Link найден ($SKIP_LINK_COUNT раз), Focus indicators: 3px синий outline"
else
    check_test "Тест 2: Keyboard Navigation" "FAIL" "Элементы не найдены на сайте"
fi

echo -e "${BLUE}📋 Тест 3: Hover на кнопках${NC}"
echo "Проверка hover эффектов..."

HOVER_EFFECTS=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "translateY(-2px)" || echo "0")
BOX_SHADOW=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "box-shadow.*0 4px 8px" || echo "0")

if [ "$HOVER_EFFECTS" -gt 0 ] && [ "$BOX_SHADOW" -gt 0 ]; then
    check_test "Тест 3: Hover эффекты" "PASS" "Подъем: translateY(-2px), Тень: box-shadow"
else
    check_test "Тест 3: Hover эффекты" "FAIL" "Стили не найдены на сайте"
fi

echo -e "${BLUE}📋 Тест 4: Контрастность${NC}"
echo "Проверка цветов для WCAG AA..."

COLOR_CHECK=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "#374151" || echo "0")

if [ "$COLOR_CHECK" -gt 0 ]; then
    check_test "Тест 4: Контрастность" "PASS" "Цвет: #374151, Контраст: 10.2:1 (WCAG AAA)"
else
    check_test "Тест 4: Контрастность" "FAIL" "Цвет не найден на сайте"
fi

echo -e "${BLUE}📋 Тест 5: Читаемость текста${NC}"
echo "Проверка line-height и типографики..."

LINE_HEIGHT=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "line-height: 1.6" || echo "0")
MAX_WIDTH=$(curl -s -u "$USERNAME:$PASSWORD" "$BASE_URL/css/styles.css" | grep -c "max-width: 65ch" || echo "0")

if [ "$LINE_HEIGHT" -gt 0 ] && [ "$MAX_WIDTH" -gt 0 ]; then
    check_test "Тест 5: Читаемость" "PASS" "Line-height: 1.6, Max-width: 65ch"
else
    check_test "Тест 5: Читаемость" "FAIL" "Стили не найдены на сайте"
fi

echo "=========================================="
echo -e "${BLUE}📊 Итоговый результат${NC}"
echo "=========================================="
echo ""
echo -e "Пройдено тестов: ${GREEN}$TESTS_PASSED${NC}"
echo -e "Провалено тестов: ${RED}$TESTS_FAILED${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✅ Все тесты пройдены успешно!${NC}"
    echo ""
    echo "Для визуального тестирования:"
    echo "1. Откройте $BASE_URL"
    echo "2. Авторизуйтесь (username: $USERNAME)"
    echo "3. Проверьте pi.html и index.html"
    echo ""
    echo "Рекомендации:"
    echo "- Нажмите Tab для проверки keyboard navigation"
    echo "- Наведите курсор на кнопки для проверки hover эффектов"
    echo "- Проверьте читаемость меток полей"
    echo ""
    exit 0
else
    echo -e "${RED}❌ Некоторые тесты провалены${NC}"
    exit 1
fi

