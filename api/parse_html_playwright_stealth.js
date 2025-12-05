// Улучшенная версия парсера с Playwright Stealth
// Файл: api/parse_html_playwright_stealth.js
//
// Установка зависимостей:
// npm install playwright-extra puppeteer-extra-plugin-stealth

const { chromium } = require('playwright-extra');
const stealth = require('puppeteer-extra-plugin-stealth')();

// Применяем stealth плагин
chromium.use(stealth);

const args = process.argv.slice(2);
if (args.length < 1) {
    console.error('Usage: node parse_html_playwright_stealth.js <url>');
    process.exit(1);
}

const url = args[0];

// Массив User-Agent для ротации
const USER_AGENTS = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:121.0) Gecko/20100101 Firefox/121.0'
];

// Случайный выбор User-Agent
function getRandomUserAgent() {
    return USER_AGENTS[Math.floor(Math.random() * USER_AGENTS.length)];
}

// Случайная задержка (эмуляция поведения человека)
function randomDelay(min = 500, max = 2000) {
    return new Promise(resolve => {
        const delay = Math.floor(Math.random() * (max - min + 1)) + min;
        setTimeout(resolve, delay);
    });
}

(async () => {
    let browser = null;
    const startTime = Date.now();
    
    try {
        // Запуск браузера с оптимизированными параметрами
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-blink-features=AutomationControlled', // Скрыть автоматизацию
                '--disable-features=IsolateOrigins,site-per-process'
            ]
        });
        
        // Создание контекста с реалистичными параметрами
        const context = await browser.newContext({
            viewport: { 
                width: 1920, 
                height: 1080 
            },
            userAgent: getRandomUserAgent(),
            locale: 'ru-RU',
            timezoneId: 'Europe/Moscow',
            // Эмуляция прав браузера
            permissions: ['geolocation'],
            geolocation: { latitude: 55.7558, longitude: 37.6173 }, // Москва
            // Дополнительные HTTP заголовки
            extraHTTPHeaders: {
                'Accept-Language': 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding': 'gzip, deflate, br',
                'DNT': '1',
                'Connection': 'keep-alive',
                'Upgrade-Insecure-Requests': '1'
            }
        });
        
        const page = await context.newPage();
        
        // Дополнительная маскировка (на случай если stealth плагин не покрыл)
        await page.addInitScript(() => {
            // Убираем webdriver флаг
            Object.defineProperty(navigator, 'webdriver', {
                get: () => false,
            });
            
            // Добавляем chrome объект (есть в настоящих браузерах)
            window.chrome = {
                runtime: {},
                loadTimes: function() {},
                csi: function() {},
                app: {}
            };
            
            // Маскируем plugins
            Object.defineProperty(navigator, 'plugins', {
                get: () => [
                    {
                        0: {type: "application/x-google-chrome-pdf", suffixes: "pdf", description: "Portable Document Format"},
                        description: "Portable Document Format",
                        filename: "internal-pdf-viewer",
                        length: 1,
                        name: "Chrome PDF Plugin"
                    }
                ],
            });
            
            // Переопределяем permissions
            const originalQuery = window.navigator.permissions.query;
            window.navigator.permissions.query = (parameters) => (
                parameters.name === 'notifications' ?
                    Promise.resolve({ state: Notification.permission }) :
                    originalQuery(parameters)
            );
        });
        
        // Навигация с обработкой ошибок
        console.error(`[DEBUG] Navigating to ${url}`);
        
        await page.goto(url, { 
            waitUntil: 'domcontentloaded', 
            timeout: 60000 
        });
        
        console.error('[DEBUG] Page loaded, waiting for stability');
        
        // Случайная задержка для эмуляции пользователя
        await randomDelay(1000, 2000);
        
        // Ожидание загрузки сети (с таймаутом)
        try {
            await page.waitForLoadState('networkidle', { timeout: 5000 });
            console.error('[DEBUG] Network idle achieved');
        } catch (e) {
            console.error('[DEBUG] Network idle timeout (not critical)');
        }
        
        // Случайная прокрутка страницы (эмуляция просмотра)
        try {
            const scrollHeight = await page.evaluate(() => document.body.scrollHeight);
            const viewportHeight = await page.evaluate(() => window.innerHeight);
            
            if (scrollHeight > viewportHeight) {
                const scrollTo = Math.floor(Math.random() * (scrollHeight - viewportHeight));
                await page.evaluate((y) => window.scrollTo(0, y), scrollTo);
                await randomDelay(300, 800);
                console.error(`[DEBUG] Scrolled to ${scrollTo}px`);
            }
        } catch (e) {
            console.error('[DEBUG] Scroll failed (not critical)');
        }
        
        // Получение контента
        const content = await page.content();
        const duration = Date.now() - startTime;
        
        console.error(`[DEBUG] Content fetched successfully in ${duration}ms (size: ${content.length} bytes)`);
        
        // Вывод HTML в stdout (для PHP)
        console.log(content);
        
    } catch (error) {
        const duration = Date.now() - startTime;
        console.error(`[ERROR] Failed after ${duration}ms: ${error.message}`);
        
        // Детальная информация об ошибке
        if (error.stack) {
            console.error('[STACK]', error.stack.split('\n').slice(0, 3).join('\n'));
        }
        
        process.exit(1);
        
    } finally {
        if (browser) {
            await browser.close();
            console.error('[DEBUG] Browser closed');
        }
    }
})();
