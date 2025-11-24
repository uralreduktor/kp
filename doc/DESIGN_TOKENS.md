# 🎨 Design Tokens — Система дизайна

**Версия:** 1.1.0  
**Дата обновления:** 21 ноября 2025

---

## 📐 Что такое Design Tokens?

**Design Tokens** — это переменные дизайн-системы, которые хранят визуальные атрибуты (цвета, размеры, отступы). Это единый источник правды для всего UI.

---

## 🎨 Цветовая палитра

### Primary (Основной синий)

```css
/* CSS Variables */
--color-primary-50:  #eff6ff;  /* Очень светлый */
--color-primary-100: #dbeafe;  /* Светлый фон */
--color-primary-200: #bfdbfe;  /* Светлый border */
--color-primary-500: #3b82f6;  /* Основной */
--color-primary-600: #2563eb;  /* Hover */
--color-primary-700: #1d4ed8;  /* Active */
--color-primary-900: #1e3a8a;  /* Очень темный */
```

**Использование:**
```html
<!-- Кнопка -->
<button style="background: var(--color-primary-600)">
  Сохранить
</button>

<!-- Фон карточки -->
<div style="background: var(--color-primary-50)">
  Контент
</div>
```

### Neutral (Серый)

```css
--color-gray-50:  #f9fafb;  /* Фон страницы */
--color-gray-100: #f3f4f6;  /* Фон карточек */
--color-gray-200: #e5e7eb;  /* Borders light */
--color-gray-300: #d1d5db;  /* Borders */
--color-gray-400: #9ca3af;  /* Disabled text */
--color-gray-500: #6b7280;  /* Вторичный текст */
--color-gray-600: #4b5563;  /* Основной текст light */
--color-gray-700: #374151;  /* Основной текст */
--color-gray-800: #1f2937;  /* Заголовки */
--color-gray-900: #111827;  /* Очень темный */
```

### Success (Успех)

```css
--color-success-50:  #f0fdf4;  /* Фон уведомлений */
--color-success-500: #10b981;  /* Основной */
--color-success-600: #059669;  /* Hover */
```

**Примеры:**
```html
<!-- Уведомление об успехе -->
<div style="background: var(--color-success-50); 
            border-left: 4px solid var(--color-success-500)">
  ✓ Документ сохранён!
</div>

<!-- Кнопка успеха -->
<button style="background: var(--color-success-500)">
  Подтвердить
</button>
```

### Warning (Предупреждение)

```css
--color-warning-50:  #fffbeb;
--color-warning-500: #f59e0b;
--color-warning-600: #d97706;
```

### Error (Ошибка)

```css
--color-error-50:  #fef2f2;
--color-error-500: #ef4444;
--color-error-600: #dc2626;
```

---

## 📝 Типографика

### Размеры шрифтов

```css
/* Модульная шкала (Major Third 1.25) */
--font-size-xs:   0.75rem;   /* 12px - Tiny */
--font-size-sm:   0.875rem;  /* 14px - Small */
--font-size-base: 1rem;      /* 16px - Base */
--font-size-lg:   1.25rem;   /* 20px - Large */
--font-size-xl:   1.5rem;    /* 24px - XL */
--font-size-2xl:  2rem;      /* 32px - 2XL */
--font-size-3xl:  3rem;      /* 48px - 3XL */
```

**Таблица применения:**

| Token | Размер | Использование |
|-------|--------|---------------|
| `xs` | 12px | Подписи, метки (deprecated) |
| `sm` | 14px | Labels, вторичный текст |
| `base` | 16px | Основной текст, body |
| `lg` | 20px | H4, крупный текст |
| `xl` | 24px | H3 |
| `2xl` | 32px | H2 |
| `3xl` | 48px | H1, Hero заголовки |

**Примеры:**
```html
<h1 style="font-size: var(--font-size-3xl)">Главный заголовок</h1>
<h2 style="font-size: var(--font-size-2xl)">Подзаголовок</h2>
<p style="font-size: var(--font-size-base)">Основной текст</p>
<small style="font-size: var(--font-size-sm)">Вспомогательный текст</small>
```

### Line-heights

```css
--line-height-tight:   1.2;   /* Заголовки */
--line-height-normal:  1.6;   /* Основной текст */
--line-height-relaxed: 1.8;   /* Длинные тексты */
```

**Применение:**
```css
h1, h2, h3 { line-height: var(--line-height-tight); }
p, div     { line-height: var(--line-height-normal); }
article    { line-height: var(--line-height-relaxed); }
```

### Font Weights

```css
--font-weight-normal:   400;  /* Обычный текст */
--font-weight-medium:   500;  /* Акценты */
--font-weight-semibold: 600;  /* Labels, кнопки */
--font-weight-bold:     700;  /* Заголовки */
```

---

## 📏 Spacing (8px Grid System)

```css
--space-0:  0;          /* 0px */
--space-1:  0.25rem;    /* 4px */
--space-2:  0.5rem;     /* 8px */
--space-3:  0.75rem;    /* 12px */
--space-4:  1rem;       /* 16px */
--space-5:  1.25rem;    /* 20px */
--space-6:  1.5rem;     /* 24px */
--space-8:  2rem;       /* 32px */
--space-10: 2.5rem;     /* 40px */
--space-12: 3rem;       /* 48px */
--space-16: 4rem;       /* 64px */
--space-20: 5rem;       /* 80px */
```

### Рекомендации по применению:

| Token | Размер | Использование |
|-------|--------|---------------|
| `space-1` | 4px | Внутри кнопок, tiny gaps |
| `space-2` | 8px | Малые отступы между элементами |
| `space-3` | 12px | Padding в маленьких элементах |
| `space-4` | 16px | Стандартный padding/margin |
| `space-6` | 24px | Отступы между группами |
| `space-8` | 32px | Отступы между секциями |
| `space-12` | 48px | Крупные секции |
| `space-16` | 64px | Основные разделы страницы |

**Примеры:**
```css
/* Кнопка */
.button {
  padding: var(--space-3) var(--space-6);  /* 12px 24px */
}

/* Карточка */
.card {
  padding: var(--space-6);        /* 24px */
  margin-bottom: var(--space-4);  /* 16px */
}

/* Секция */
.section {
  padding: var(--space-12) 0;     /* 48px 0 */
}
```

---

## 🔲 Border Radius

```css
--radius-sm:  0.25rem;  /* 4px - Small */
--radius-md:  0.5rem;   /* 8px - Medium */
--radius-lg:  0.75rem;  /* 12px - Large */
--radius-xl:  1rem;     /* 16px - XL */
--radius-full: 9999px;  /* Полностью круглый */
```

**Применение:**
- `sm` (4px): Badges, tags
- `md` (8px): Кнопки, inputs, cards
- `lg` (12px): Модальные окна
- `xl` (16px): Крупные карточки
- `full`: Аватары, rounded badges

---

## 🌑 Shadows (Тени)

```css
--shadow-sm:  0 1px 2px rgba(0, 0, 0, 0.05);
--shadow-md:  0 4px 6px rgba(0, 0, 0, 0.1);
--shadow-lg:  0 10px 15px rgba(0, 0, 0, 0.15);
--shadow-xl:  0 20px 25px rgba(0, 0, 0, 0.2);
```

**Примеры:**
```css
/* Карточка */
.card {
  box-shadow: var(--shadow-md);
}

/* Модальное окно */
.modal {
  box-shadow: var(--shadow-xl);
}

/* Dropdown */
.dropdown {
  box-shadow: var(--shadow-lg);
}
```

---

## ⏱️ Transitions (Анимации)

```css
--transition-fast:   150ms ease;
--transition-normal: 200ms cubic-bezier(0.4, 0, 0.2, 1);
--transition-slow:   300ms cubic-bezier(0.4, 0, 0.2, 1);
```

**Применение:**
```css
/* Кнопки */
button {
  transition: all var(--transition-normal);
}

/* Hover эффекты */
.card:hover {
  transition: transform var(--transition-fast);
}

/* Модальные окна */
.modal {
  transition: opacity var(--transition-slow);
}
```

---

## 📱 Breakpoints (Responsive)

```css
/* Mobile first подход */
--breakpoint-sm:  640px;   /* Phones */
--breakpoint-md:  768px;   /* Tablets */
--breakpoint-lg:  1024px;  /* Laptops */
--breakpoint-xl:  1280px;  /* Desktops */
--breakpoint-2xl: 1536px;  /* Large screens */
```

**Использование:**
```css
/* Mobile first */
.element {
  font-size: 14px;
}

/* Tablet и выше */
@media (min-width: 768px) {
  .element {
    font-size: 16px;
  }
}

/* Desktop */
@media (min-width: 1024px) {
  .element {
    font-size: 18px;
  }
}
```

---

## 🎯 Z-Index Scale

```css
--z-dropdown:  1000;
--z-sticky:    1020;
--z-fixed:     1030;
--z-modal:     1040;
--z-popover:   1050;
--z-tooltip:   1060;
```

**Иерархия:**
1. Base layer: 0-10
2. Content: 10-100
3. Dropdown: 1000
4. Modal: 1040
5. Tooltip: 1060 (самый верх)

---

## 🖼️ Component Sizes

### Buttons

```css
--button-height-sm:  32px;   /* Маленькая */
--button-height-md:  44px;   /* Средняя (стандарт) */
--button-height-lg:  56px;   /* Крупная */

--button-padding-sm: 0.5rem 1rem;
--button-padding-md: 0.75rem 1.5rem;
--button-padding-lg: 1rem 2rem;
```

### Inputs

```css
--input-height-sm:  36px;
--input-height-md:  44px;
--input-height-lg:  52px;
```

---

## 💡 Практические примеры

### Кнопка с tokens:

```html
<button style="
  background: var(--color-primary-600);
  color: white;
  padding: var(--space-3) var(--space-6);
  border-radius: var(--radius-md);
  font-size: var(--font-size-base);
  font-weight: var(--font-weight-medium);
  box-shadow: var(--shadow-sm);
  transition: all var(--transition-normal);
  min-height: var(--button-height-md);
">
  Сохранить
</button>

<style>
button:hover {
  background: var(--color-primary-700);
  box-shadow: var(--shadow-md);
  transform: translateY(-2px);
}
</style>
```

### Карточка с tokens:

```html
<div class="card" style="
  background: white;
  padding: var(--space-6);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  margin-bottom: var(--space-4);
">
  <h3 style="
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    color: var(--color-gray-900);
    margin-bottom: var(--space-4);
  ">
    Заголовок карточки
  </h3>
  <p style="
    font-size: var(--font-size-base);
    line-height: var(--line-height-normal);
    color: var(--color-gray-700);
  ">
    Содержимое карточки
  </p>
</div>
```

---

## 🔧 Как использовать в проекте

### В CSS:

```css
/* Определение tokens */
:root {
  --color-primary-600: #2563eb;
  --space-4: 1rem;
  --font-size-base: 1rem;
}

/* Использование */
.button {
  background: var(--color-primary-600);
  padding: var(--space-4);
  font-size: var(--font-size-base);
}
```

### В JavaScript (React):

```jsx
const theme = {
  colors: {
    primary: '#2563eb',
    gray: {
      700: '#374151'
    }
  },
  spacing: {
    4: '1rem'
  }
};

// Использование
<button style={{ 
  background: theme.colors.primary,
  padding: theme.spacing[4]
}}>
  Кнопка
</button>
```

---

## 📋 Чеклист применения Design Tokens

### При создании нового компонента:

- [ ] Используйте цвета из палитры (не хардкод)
- [ ] Используйте spacing из 8px grid
- [ ] Используйте типографическую шкалу
- [ ] Добавьте transitions для hover
- [ ] Используйте стандартные shadows
- [ ] Следуйте z-index иерархии
- [ ] Touch-friendly размеры (≥44px)

### Частые ошибки:

❌ `color: #374151;` — хардкод  
✅ `color: var(--color-gray-700);` — token

❌ `padding: 15px;` — не из grid  
✅ `padding: var(--space-4);` — 16px из 8px grid

❌ `font-size: 15px;` — нет в шкале  
✅ `font-size: var(--font-size-base);` — 16px из шкалы

---

## 🎨 Темная тема (будущее)

Design tokens позволяют легко добавить темную тему:

```css
/* Светлая тема (по умолчанию) */
:root {
  --color-text-primary: #111827;
  --color-background: #ffffff;
}

/* Темная тема */
@media (prefers-color-scheme: dark) {
  :root {
    --color-text-primary: #f9fafb;
    --color-background: #111827;
  }
}

/* Использование */
body {
  color: var(--color-text-primary);
  background: var(--color-background);
}
```

---

## 📚 Ресурсы

- **Design Tokens Community Group:** https://design-tokens.github.io/community-group/
- **Material Design System:** https://material.io/design/color/
- **Tailwind CSS Palette:** https://tailwindcss.com/docs/customizing-colors
- **8px Grid System:** https://spec.fm/specifics/8-pt-grid

---

## ✅ Выводы

### Преимущества Design Tokens:

1. ✅ **Консистентность** — один стиль во всем приложении
2. ✅ **Масштабируемость** — легко добавлять новые компоненты
3. ✅ **Поддержка** — изменение в одном месте = изменение везде
4. ✅ **Темизация** — легко добавить темную тему
5. ✅ **Документация** — понятные названия переменных

### Как начать:

1. Скопируйте tokens из этого файла в ваш CSS
2. Постепенно заменяйте хардкод на variables
3. Создавайте новые компоненты с tokens
4. Документируйте новые tokens

---

**Версия:** 1.1.0  
**Статус:** ✅ Готово к использованию  
**Обновлено:** 21 ноября 2025

**Используйте Design Tokens для создания консистентного UI! 🎨**


