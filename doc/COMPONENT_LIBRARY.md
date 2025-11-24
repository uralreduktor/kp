# 🧩 Библиотека компонентов

**Готовые React компоненты для копирования**  
**Версия:** 1.1.0  
**Дата:** 21 ноября 2025

---

## 📚 Содержание

1. [Кнопки](#кнопки)
2. [Toast Notifications](#toast-notifications)
3. [Skeleton Loaders](#skeleton-loaders)
4. [Формы и Inputs](#формы-и-inputs)
5. [Модальные окна](#модальные-окна)
6. [Карточки](#карточки)
7. [Dropdown меню](#dropdown-меню)
8. [Tabs (Вкладки)](#tabs-вкладки)
9. [Progress Bar](#progress-bar)
10. [Empty States](#empty-states)

---

## 🔘 Кнопки

### Primary Button (Основная кнопка)

```jsx
const PrimaryButton = ({ children, onClick, disabled, loading }) => {
  return (
    <button
      onClick={onClick}
      disabled={disabled || loading}
      className="
        px-6 py-3
        bg-blue-600 hover:bg-blue-700 active:bg-blue-800
        text-white font-medium
        rounded-lg
        shadow-sm hover:shadow-md
        hover:-translate-y-0.5
        active:translate-y-0
        disabled:opacity-50 disabled:cursor-not-allowed
        transition-all duration-200
        min-h-[44px]
        flex items-center justify-center gap-2
      "
      style={{
        transform: loading ? 'none' : undefined
      }}
    >
      {loading && (
        <div className="animate-spin rounded-full h-4 w-4 border-2 border-white border-t-transparent" />
      )}
      {children}
    </button>
  );
};

// Использование:
<PrimaryButton onClick={() => console.log('Clicked')}>
  Сохранить
</PrimaryButton>

<PrimaryButton loading={true}>
  Сохранение...
</PrimaryButton>
```

### Secondary Button (Вторичная кнопка)

```jsx
const SecondaryButton = ({ children, onClick, disabled }) => {
  return (
    <button
      onClick={onClick}
      disabled={disabled}
      className="
        px-6 py-3
        bg-white hover:bg-gray-50 active:bg-gray-100
        text-blue-600
        font-medium
        rounded-lg
        border-2 border-blue-600
        hover:shadow-md
        hover:-translate-y-0.5
        active:translate-y-0
        disabled:opacity-50
        transition-all duration-200
        min-h-[44px]
      "
    >
      {children}
    </button>
  );
};
```

### Icon Button (Кнопка с иконкой)

```jsx
const IconButton = ({ icon, label, onClick, variant = 'default' }) => {
  const variants = {
    default: 'text-gray-600 hover:text-gray-900 hover:bg-gray-100',
    danger: 'text-red-600 hover:text-red-900 hover:bg-red-50',
    success: 'text-green-600 hover:text-green-900 hover:bg-green-50'
  };

  return (
    <button
      onClick={onClick}
      aria-label={label}
      title={label}
      className={`
        p-2 rounded-lg
        transition-all duration-200
        min-w-[44px] min-h-[44px]
        flex items-center justify-center
        ${variants[variant]}
      `}
    >
      {icon}
    </button>
  );
};

// Использование:
<IconButton 
  icon={<TrashIcon className="w-5 h-5" />}
  label="Удалить документ"
  variant="danger"
  onClick={() => handleDelete()}
/>
```

---

## 🔔 Toast Notifications

### Toast Component

```jsx
const Toast = ({ message, type = 'success', onClose }) => {
  const icons = {
    success: '✓',
    error: '✗',
    warning: '⚠',
    info: 'ℹ'
  };

  const colors = {
    success: 'border-green-500 bg-green-50 text-green-900',
    error: 'border-red-500 bg-red-50 text-red-900',
    warning: 'border-yellow-500 bg-yellow-50 text-yellow-900',
    info: 'border-blue-500 bg-blue-50 text-blue-900'
  };

  useEffect(() => {
    const timer = setTimeout(onClose, 3000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <div
      className={`
        fixed top-6 right-6 z-50
        min-w-[300px] max-w-md
        p-4
        rounded-lg border-l-4
        shadow-lg
        flex items-center gap-3
        animate-slide-in
        ${colors[type]}
      `}
    >
      <span className="text-2xl">{icons[type]}</span>
      <div className="flex-1">
        <p className="font-medium">{message}</p>
      </div>
      <button
        onClick={onClose}
        className="text-gray-400 hover:text-gray-600"
        aria-label="Закрыть"
      >
        ✕
      </button>
    </div>
  );
};

// CSS для анимации:
<style jsx>{`
  @keyframes slide-in {
    from {
      transform: translateX(100%);
      opacity: 0;
    }
    to {
      transform: translateX(0);
      opacity: 1;
    }
  }
  .animate-slide-in {
    animation: slide-in 0.3s ease-out;
  }
`}</style>

// Использование:
const [showToast, setShowToast] = useState(false);

<Toast 
  message="Документ успешно сохранён!"
  type="success"
  onClose={() => setShowToast(false)}
/>
```

### Toast Manager (управление несколькими уведомлениями)

```jsx
const ToastManager = () => {
  const [toasts, setToasts] = useState([]);

  const addToast = (message, type = 'success') => {
    const id = Date.now();
    setToasts(prev => [...prev, { id, message, type }]);
  };

  const removeToast = (id) => {
    setToasts(prev => prev.filter(t => t.id !== id));
  };

  return (
    <div className="fixed top-6 right-6 z-50 flex flex-col gap-3">
      {toasts.map(toast => (
        <Toast
          key={toast.id}
          message={toast.message}
          type={toast.type}
          onClose={() => removeToast(toast.id)}
        />
      ))}
    </div>
  );
};

// Глобальная функция для показа toast:
window.showToast = (message, type) => {
  // Вызов из любого места приложения
  addToast(message, type);
};
```

---

## ⏳ Skeleton Loaders

### Skeleton Text

```jsx
const SkeletonText = ({ lines = 3, width = '100%' }) => {
  return (
    <div className="space-y-2">
      {[...Array(lines)].map((_, i) => (
        <div
          key={i}
          className="h-4 bg-gray-200 rounded animate-pulse"
          style={{
            width: i === lines - 1 ? '70%' : width
          }}
        />
      ))}
    </div>
  );
};
```

### Skeleton Card

```jsx
const SkeletonCard = () => {
  return (
    <div className="bg-white p-6 rounded-lg shadow-md">
      {/* Заголовок */}
      <div className="h-6 bg-gray-200 rounded w-3/4 mb-4 animate-pulse" />
      
      {/* Текст */}
      <div className="space-y-2">
        <div className="h-4 bg-gray-200 rounded animate-pulse" />
        <div className="h-4 bg-gray-200 rounded w-5/6 animate-pulse" />
      </div>
      
      {/* Кнопки */}
      <div className="flex gap-2 mt-4">
        <div className="h-10 bg-gray-200 rounded w-24 animate-pulse" />
        <div className="h-10 bg-gray-200 rounded w-24 animate-pulse" />
      </div>
    </div>
  );
};
```

### Skeleton Table

```jsx
const SkeletonTable = ({ rows = 5, columns = 4 }) => {
  return (
    <div className="bg-white rounded-lg shadow-md overflow-hidden">
      <table className="w-full">
        <thead className="bg-gray-50">
          <tr>
            {[...Array(columns)].map((_, i) => (
              <th key={i} className="px-4 py-3">
                <div className="h-4 bg-gray-200 rounded animate-pulse" />
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {[...Array(rows)].map((_, rowIndex) => (
            <tr key={rowIndex} className="border-t">
              {[...Array(columns)].map((_, colIndex) => (
                <td key={colIndex} className="px-4 py-4">
                  <div className="h-4 bg-gray-200 rounded animate-pulse" />
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

// Использование:
{loading ? (
  <SkeletonTable rows={10} columns={5} />
) : (
  <RealTable data={data} />
)}
```

---

## 📝 Формы и Inputs

### Enhanced Input

```jsx
const Input = ({ 
  label, 
  error, 
  success, 
  required,
  icon,
  ...props 
}) => {
  return (
    <div className="space-y-1">
      {label && (
        <label className="block text-sm font-medium text-gray-700">
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}
      
      <div className="relative">
        {icon && (
          <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
            {icon}
          </div>
        )}
        
        <input
          {...props}
          className={`
            w-full px-3 py-2
            ${icon ? 'pl-10' : ''}
            border-2 rounded-lg
            transition-all duration-200
            ${error 
              ? 'border-red-500 focus:border-red-500 focus:ring-red-200' 
              : success
              ? 'border-green-500 focus:border-green-500 focus:ring-green-200'
              : 'border-gray-300 focus:border-blue-500 focus:ring-blue-200'
            }
            focus:ring-4 focus:outline-none
            disabled:bg-gray-100 disabled:cursor-not-allowed
          `}
        />
        
        {success && (
          <div className="absolute right-3 top-1/2 -translate-y-1/2 text-green-500">
            ✓
          </div>
        )}
      </div>
      
      {error && (
        <p className="text-sm text-red-600 flex items-center gap-1">
          <span>⚠</span> {error}
        </p>
      )}
      
      {success && (
        <p className="text-sm text-green-600">
          ✓ {success}
        </p>
      )}
    </div>
  );
};

// Использование:
<Input
  label="Email"
  type="email"
  required
  error={emailError}
  success={emailValid && "Email подтверждён"}
  icon={<MailIcon className="w-5 h-5" />}
  placeholder="your@email.com"
/>
```

---

## 🪟 Модальные окна

### Modal Component

```jsx
const Modal = ({ isOpen, onClose, title, children, footer }) => {
  if (!isOpen) return null;

  return (
    <div
      className="fixed inset-0 z-50 flex items-center justify-center"
      onClick={onClose}
    >
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black bg-opacity-50" />
      
      {/* Modal */}
      <div
        className="
          relative bg-white rounded-lg shadow-xl
          max-w-2xl w-full mx-4
          max-h-[90vh] overflow-auto
          animate-scale-in
        "
        onClick={e => e.stopPropagation()}
      >
        {/* Header */}
        <div className="flex items-center justify-between p-6 border-b">
          <h2 className="text-2xl font-bold text-gray-900">{title}</h2>
          <button
            onClick={onClose}
            className="
              text-gray-400 hover:text-gray-600
              p-2 rounded-lg hover:bg-gray-100
              transition-colors
            "
            aria-label="Закрыть"
          >
            ✕
          </button>
        </div>
        
        {/* Content */}
        <div className="p-6">
          {children}
        </div>
        
        {/* Footer */}
        {footer && (
          <div className="flex justify-end gap-3 p-6 border-t bg-gray-50">
            {footer}
          </div>
        )}
      </div>
    </div>
  );
};

// CSS:
<style jsx>{`
  @keyframes scale-in {
    from {
      transform: scale(0.95);
      opacity: 0;
    }
    to {
      transform: scale(1);
      opacity: 1;
    }
  }
  .animate-scale-in {
    animation: scale-in 0.2s ease-out;
  }
`}</style>

// Использование:
<Modal
  isOpen={isModalOpen}
  onClose={() => setIsModalOpen(false)}
  title="Подтверждение действия"
  footer={
    <>
      <SecondaryButton onClick={() => setIsModalOpen(false)}>
        Отмена
      </SecondaryButton>
      <PrimaryButton onClick={handleConfirm}>
        Подтвердить
      </PrimaryButton>
    </>
  }
>
  <p>Вы уверены, что хотите удалить этот документ?</p>
</Modal>
```

---

## 🎴 Карточки

### Card Component

```jsx
const Card = ({ title, children, actions, hover = true }) => {
  return (
    <div
      className={`
        bg-white rounded-lg shadow-md p-6
        transition-all duration-200
        ${hover ? 'hover:shadow-lg hover:-translate-y-1' : ''}
      `}
    >
      {title && (
        <h3 className="text-xl font-bold text-gray-900 mb-4">
          {title}
        </h3>
      )}
      
      <div className="text-gray-700">
        {children}
      </div>
      
      {actions && (
        <div className="flex gap-2 mt-4 pt-4 border-t">
          {actions}
        </div>
      )}
    </div>
  );
};

// Использование:
<Card
  title="Коммерческое предложение"
  actions={
    <>
      <PrimaryButton>Открыть</PrimaryButton>
      <SecondaryButton>Удалить</SecondaryButton>
    </>
  }
>
  <p>КП-21112025-01</p>
  <p className="text-sm text-gray-500">Дата: 21.11.2025</p>
</Card>
```

---

## 📊 Progress Bar

```jsx
const ProgressBar = ({ value, max = 100, label, showPercent = true }) => {
  const percent = Math.round((value / max) * 100);
  
  return (
    <div className="space-y-2">
      {label && (
        <div className="flex justify-between text-sm">
          <span className="font-medium text-gray-700">{label}</span>
          {showPercent && (
            <span className="text-gray-500">{percent}%</span>
          )}
        </div>
      )}
      
      <div className="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
        <div
          className="h-full bg-blue-600 rounded-full transition-all duration-300"
          style={{ width: `${percent}%` }}
        />
      </div>
    </div>
  );
};

// Использование:
<ProgressBar value={65} label="Генерация PDF" />
```

---

## 🚫 Empty States

```jsx
const EmptyState = ({ 
  icon, 
  title, 
  description, 
  action 
}) => {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-4 text-center">
      {/* Icon */}
      <div className="text-gray-300 mb-4">
        {icon || (
          <svg className="w-24 h-24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" 
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" 
            />
          </svg>
        )}
      </div>
      
      {/* Title */}
      <h3 className="text-xl font-semibold text-gray-900 mb-2">
        {title}
      </h3>
      
      {/* Description */}
      {description && (
        <p className="text-gray-500 mb-6 max-w-sm">
          {description}
        </p>
      )}
      
      {/* Action */}
      {action && (
        <div>{action}</div>
      )}
    </div>
  );
};

// Использование:
<EmptyState
  title="Нет документов"
  description="Создайте первый коммерческий предложение"
  action={
    <PrimaryButton onClick={() => navigate('/create')}>
      ➕ Создать документ
    </PrimaryButton>
  }
/>
```

---

## ✅ Чеклист использования

При использовании компонентов проверьте:

- [ ] **Accessibility:** Все кнопки имеют `aria-label`
- [ ] **Touch-friendly:** Минимум 44x44px
- [ ] **Focus:** Видимый focus indicator
- [ ] **Transitions:** Плавные анимации
- [ ] **Responsive:** Работает на мобильных
- [ ] **Loading states:** Показывается при загрузке
- [ ] **Error states:** Показываются ошибки
- [ ] **Empty states:** Есть placeholder для пустого состояния

---

## 🚀 Как добавить в проект

```jsx
// 1. Скопируйте нужный компонент
// 2. Создайте файл компонента:
// components/Button.jsx

// 3. Импортируйте и используйте:
import { PrimaryButton } from './components/Button';

function MyPage() {
  return (
    <PrimaryButton onClick={handleSave}>
      Сохранить
    </PrimaryButton>
  );
}
```

---

**Версия:** 1.1.0  
**Статус:** ✅ Готово к использованию  
**Обновлено:** 21 ноября 2025

**Копируйте, настраивайте, используйте! 🧩**


