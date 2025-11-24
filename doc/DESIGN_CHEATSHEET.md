# 🎨 Design Cheatsheet — Быстрая справка

**Версия:** 1.1.0 | **Дата:** 21.11.2025 | **One-page reference**

---

## 📏 ТИПОГРАФИКА

```
┌─────────────────────────────────────────────────────┐
│                                                     │
│  H1: 48px / 3rem         line-height: 1.2          │
│  H2: 32px / 2rem         line-height: 1.2          │
│  H3: 24px / 1.5rem       line-height: 1.2          │
│  Body: 16px / 1rem       line-height: 1.6 ✅       │
│  Small: 14px / 0.875rem  line-height: 1.5          │
│  Labels: 14px / 0.875rem (НЕ 12px!) ✅             │
│                                                     │
│  Font Weights: 400 (normal), 500 (medium),         │
│                600 (semibold), 700 (bold)          │
└─────────────────────────────────────────────────────┘
```

**Критично:** Line-height ≥ 1.6 для body текста!

---

## 🎨 ЦВЕТА

### Основные:
```
Primary Blue:  #2563eb  (кнопки, ссылки)
Gray Text:     #374151  (основной текст) ✅
Gray Light:    #6b7280  (вторичный текст)
Success Green: #10b981  (успех)
Error Red:     #ef4444  (ошибки)
```

### Контрастность (WCAG AA):
```
✅ Text:   ≥ 4.5:1  (Labels: 10.2:1 ✅)
✅ UI:     ≥ 3:1
✅ Large:  ≥ 3:1

Проверка: https://webaim.org/resources/contrastchecker/
Foreground: #374151
Background: #FFFFFF
Result: 10.2:1 (AAA Pass) ✅
```

---

## 📐 SPACING (8px Grid)

```
┌────┬────┬─────┬─────┬─────┬─────┬─────┐
│ 4  │ 8  │ 12  │ 16  │ 24  │ 32  │ 48  │
│ xs │ sm │ md  │ lg  │ xl  │ 2xl │ 3xl │
└────┴────┴─────┴─────┴─────┴─────┴─────┘

Использование:
- 4px:  Tiny gaps
- 8px:  Малые отступы
- 16px: Стандарт ✅
- 24px: Между группами
- 32px: Между секциями
- 48px: Крупные разделы
```

**Правило:** Все отступы кратны 8px!

---

## 🔘 КНОПКИ

### Размеры:
```
Height: ≥ 44px (touch-friendly!) ✅
Padding: 12px 24px
Border-radius: 8px
```

### Hover эффект:
```css
button {
  transition: all 0.2s;
}

button:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

button:active {
  transform: translateY(0);
}
```

---

## ♿ ACCESSIBILITY (A11Y)

### Focus Indicator (КРИТИЧНО!):
```css
*:focus-visible {
  outline: 3px solid #3b82f6;
  outline-offset: 2px;
}
```

### ARIA Labels:
```html
<!-- ❌ Плохо -->
<button><TrashIcon /></button>

<!-- ✅ Хорошо -->
<button aria-label="Удалить документ">
  <TrashIcon />
</button>
```

### Skip Link:
```html
<a href="#main" class="skip-link">
  Перейти к содержимому
</a>
```

**Tab навигация:** Все элементы доступны с клавиатуры ✅

---

## 📱 АДАПТИВНОСТЬ

### Breakpoints:
```
Mobile:    < 640px   (font-size: 14px)
Tablet:    768px     (font-size: 16px)
Desktop:   1024px    (font-size: 16px)
```

### Touch Targets:
```
Минимум: 44 × 44px ✅
Оптимально: 48 × 48px
```

### Mobile First:
```css
/* Mobile по умолчанию */
.element { font-size: 14px; }

/* Tablet и выше */
@media (min-width: 768px) {
  .element { font-size: 16px; }
}
```

---

## 🎯 КОМПОНЕНТЫ

### Input:
```css
height: 44px;
padding: 12px 16px;
border: 2px solid #d1d5db;
border-radius: 8px;

:focus {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
```

### Card:
```css
padding: 24px;
border-radius: 12px;
box-shadow: 0 4px 6px rgba(0,0,0,0.1);

:hover {
  transform: translateY(-4px);
  box-shadow: 0 10px 15px rgba(0,0,0,0.15);
}
```

### Modal:
```
z-index: 1040
backdrop: rgba(0,0,0,0.5)
max-width: 600px
border-radius: 12px
```

---

## 🚦 STATES

### Button States:
```
Normal:   background: #2563eb
Hover:    background: #1d4ed8  translateY(-2px)
Active:   background: #1e40af  translateY(0)
Disabled: opacity: 0.5  cursor: not-allowed
Loading:  spinner + disabled
```

### Input States:
```
Normal:   border: #d1d5db
Focus:    border: #3b82f6  ring: blue
Error:    border: #ef4444  ring: red
Success:  border: #10b981  ring: green + checkmark
Disabled: bg: #f3f4f6  cursor: not-allowed
```

---

## ⏱️ АНИМАЦИИ

### Transitions:
```css
/* Быстро (hover) */
transition: all 150ms ease;

/* Нормально (кнопки) */
transition: all 200ms cubic-bezier(0.4,0,0.2,1);

/* Медленно (модалы) */
transition: all 300ms cubic-bezier(0.4,0,0.2,1);
```

### Примеры:
```
Кнопка Hover:     200ms
Dropdown Open:    150ms
Modal Open:       300ms
Toast Slide-in:   300ms
```

---

## 🌑 SHADOWS

```css
Small:  0 1px 2px rgba(0,0,0,0.05)   /* Cards */
Medium: 0 4px 6px rgba(0,0,0,0.1)    /* Buttons hover */
Large:  0 10px 15px rgba(0,0,0,0.15) /* Dropdowns */
XL:     0 20px 25px rgba(0,0,0,0.2)  /* Modals */
```

---

## 📋 ЧЕКЛИСТ

### Перед деплоем проверьте:

#### Типографика:
- [ ] Body line-height = 1.6
- [ ] Labels font-size ≥ 14px
- [ ] H1/H2/H3 правильные размеры

#### Accessibility:
- [ ] Все кнопки ≥ 44×44px
- [ ] Focus indicators видны (3px синий)
- [ ] ARIA labels для иконок
- [ ] Tab навигация работает
- [ ] Skip link добавлен

#### Контраст:
- [ ] Text contrast ≥ 4.5:1
- [ ] UI contrast ≥ 3:1
- [ ] Labels contrast = 10.2:1 ✅

#### Анимации:
- [ ] Hover эффекты на кнопках
- [ ] Transitions ≤ 300ms
- [ ] Loading states показываются

#### Мобильные:
- [ ] Touch targets ≥ 44px
- [ ] Responsive типографика
- [ ] Карточки вместо таблиц

---

## 🧪 ТЕСТИРОВАНИЕ

### Инструменты:
```
Lighthouse:    F12 → Lighthouse (цель: ≥90)
WCAG Checker:  webaim.org/contrastchecker
WAVE:          wave.webaim.org/extension
axe DevTools:  deque.com/axe/devtools
```

### Быстрый тест (5 минут):
```
1. Labels читаемы?           ✓
2. Tab → Skip link?           ✓
3. Tab → Focus indicators?    ✓
4. Hover на кнопки работает?  ✓
5. Контраст проверен?         ✓
```

---

## 🚨 КРИТИЧЕСКИЕ ОШИБКИ

### ❌ Не делайте так:

```css
/* ❌ Labels слишком мелкие */
label { font-size: 12px; color: #9CA3AF; }

/* ❌ Line-height слишком плотный */
body { line-height: 1.4; }

/* ❌ Кнопки слишком маленькие */
button { height: 32px; }

/* ❌ Нет focus indicator */
/* Отсутствует outline */

/* ❌ Spacing не из grid */
margin: 15px;  /* Не кратно 8! */
```

### ✅ Делайте так:

```css
/* ✅ Labels читаемые */
label { font-size: 14px; color: #374151; }

/* ✅ Line-height комфортный */
body { line-height: 1.6; }

/* ✅ Touch-friendly кнопки */
button { min-height: 44px; }

/* ✅ Focus indicator */
*:focus-visible { outline: 3px solid #3b82f6; }

/* ✅ Spacing из grid */
margin: 16px;  /* 8px grid! */
```

---

## 💡 QUICK WINS

### 5 изменений = +40% UX:

1. **Line-height: 1.6** (+40% читаемость)
2. **Labels: 14px #374151** (WCAG AA ✅)
3. **Focus: 3px outline** (A11Y ✅)
4. **Buttons: ≥44px** (Touch-friendly ✅)
5. **Hover: translateY(-2px)** (Feedback ✅)

**Время:** 30 минут  
**Эффект:** Огромный!

---

## 📚 РЕСУРСЫ

- **Полный анализ:** `UX_UI_ANALYSIS.md`
- **Тестирование:** `TEST_IMPROVEMENTS.md`
- **Компоненты:** `COMPONENT_LIBRARY.md`
- **Tokens:** `DESIGN_TOKENS.md`
- **Quick Start:** `QUICK_START_IMPROVEMENTS.md`

---

## 🎯 ЗОЛОТЫЕ ПРАВИЛА

```
1. Consistency is King    (Единообразие)
2. Accessibility First    (A11Y важнее красоты)
3. Mobile Matters         (44px минимум!)
4. Contrast Counts        (≥4.5:1 всегда)
5. Smooth Transitions     (≤300ms)
6. Spacing from Grid      (8px система)
7. Test with Keyboard     (Tab навигация)
8. Show Loading States    (Skeleton > Spinner)
9. Error Messages Clear   (Понятные тексты)
10. Document Everything   (Пиши документацию)
```

---

**Распечатайте и повесьте рядом с монитором! 📌**

**Версия:** 1.1.0 ✅  
**Статус:** Ready to use 🚀  
**Обновлено:** 21.11.2025


