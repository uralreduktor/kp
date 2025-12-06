Я провел анализ вашего проекта, чтобы составить план отказа от PHP в пользу Python (FastAPI).

### Текущее состояние (As-Is)
Проект находится в "гибридном" состоянии:
1.  **Frontend**: React (Vite), строго типизирован, использует Zod.
2.  **Backend (Legacy)**: PHP-скрипты (`api/*.php`). Отвечают за CRUD операции с файлами (сохранение/чтение JSON), генерацию номеров, PDF и проксирование запросов (DaData).
    *   *Проблема*: Отсутствие строгой типизации, ручное управление файлами, конфликты типов данных (массив `[]` vs объект `{}`).
3.  **Backend (Modern)**: Python FastAPI (`backend/app`). Отвечает за авторизацию (Postgres) и парсинг (Playwright).
    *   *Потенциал*: Уже настроен, типизирован (Pydantic), работает асинхронно.

### Целевое состояние (To-Be)
Единый бэкенд на FastAPI. PHP полностью исключен из цепочки обработки данных.
*   **Данные**: Инвойсы по-прежнему хранятся в JSON файлах (для обратной совместимости на первом этапе), но управляются через Python.
*   **API**: RESTful эндпоинты `/api/v1/*`.

---

### План миграции (Скорректированный)

Я разбил план на 5 этапов, добавив критические проверки безопасности и совместимости.

#### Этап 1: Описание данных (Schemas) с валидацией Legacy
Нужно создать Pydantic-схемы, которые умеют "чистить" старые данные от PHP (превращать `[]` в `{}`).

**Задача:** Создать файл `backend/app/schemas/invoice.py`.

```python
from pydantic import BaseModel, Field, BeforeValidator
from typing import List, Optional, Any, Annotated, Dict

# Валидатор для превращения пустого массива PHP ([]) в пустой словарь ({})
def empty_list_to_dict(v: Any) -> Any:
    if v == []:
        return {}
    return v

CleanDict = Annotated[Dict[str, Any], BeforeValidator(empty_list_to_dict)]

class CommercialTerms(BaseModel):
    incoterm: Optional[str] = ""
    # ...

class Invoice(BaseModel):
    number: str
    date: str
    # Pydantic автоматически исправит [] на {} при чтении старых файлов
    commercialTerms: CleanDict | CommercialTerms = Field(default_factory=dict)
    items: List[InvoiceItem] = Field(default_factory=list)
    # ...
```

#### Этап 2: Логика работы с файлами (Service Layer) и Права
Нужно переписать логику `save.php`, `load.php` и `list.php` на Python, учитывая права доступа.

**Задача:** Создать сервис `backend/app/services/invoice_service.py`.
*   **Config**: Путь к `@archiv 2025` берется из `.env`.
*   **Permissions**: При старте сервис проверяет права на запись (`os.access(path, os.W_OK)`). Python процесс должен иметь права (быть в группе `www-data` или иметь доступ ACL).
*   **Save**: Конвертирует Pydantic модель -> dict -> сохраняет JSON (с atomic write для безопасности).
*   **Load**: Читает файл -> прогоняет через `CleanDict` -> отдает валидную модель.
*   **Backup**: Перед первым деплоем сделать бэкап папки `@archiv 2025`.

#### Этап 3: Перенос сопутствующих сервисов (DaData, PDF)
PHP также отвечает за подсказки и PDF. Их нельзя просто отключить.

**Задача:**
1.  **DaData**: Создать `backend/app/services/dadata.py` (или использовать библиотеку `dadata`). Эндпоинт `/api/v1/suggest/...`.
2.  **PDF**: Перенести логику генерации PDF. Вместо вызова `node` скриптов из PHP, использовать `playwright-python` напрямую внутри FastAPI (у нас он уже есть для парсинга).

#### Этап 4: Роутинг и Nginx
Создаем маршруты в `backend/app/api/routes/invoices.py` и настраиваем веб-сервер.

*   `GET /api/v1/invoices`
*   `GET /api/v1/invoices/{filename}`
*   `POST /api/v1/invoices`
*   `POST /api/v1/pdf/generate`
*   `GET /api/v1/suggest/address`

**Конфигурация Nginx:**
Добавить location для API v1, чтобы запросы шли на FastAPI (порт 8001):
```nginx
location /api/v1/ {
    proxy_pass http://127.0.0.1:8001;
    # ...
}
```

#### Этап 5: Интеграция на Фронтенде
Меняем URL в `frontend/src/features/invoice/service.ts`.

*   Убираем `z.preprocess` из схем Zod (теперь бэкенд гарантирует типы).
*   Переключаем энпоинты на `/api/v1/...`.

---

### Преимущества updated плана

1.  **Совместимость**: Мы не сломаем открытие старых файлов благодаря `BeforeValidator`.
2.  **Надежность**: Проверка прав доступа предотвратит ошибки "Permission denied" при смене бэкенда.
3.  **Полнота**: Мы не забудем про PDF и DaData, без которых работа невозможна.
4.  **Безопасность**: Атомарная запись файлов и изоляция от web-root.
