# КП Реестр - Frontend 2.0

Новая версия фронтенда на базе Vite + React + TypeScript.

## Требования
- Node.js 18+
- npm 9+

## Установка

```bash
cd frontend
npm install
```

## Запуск в режиме разработки

```bash
npm run dev
```
Приложение будет доступно по адресу `http://localhost:5173`.
Запросы к `/api` проксируются на `https://kp.uralreduktor.com` (или настройте в `vite.config.ts`).

## Сборка для продакшена

```bash
npm run build
```
Результат сборки будет в папке `dist`.

## Структура проекта

- `src/components` - Переиспользуемые UI компоненты
- `src/features` - Функциональные модули (auth, registry)
- `src/pages` - Страницы приложения
- `src/services` - API клиенты
- `src/types` - TypeScript интерфейсы
