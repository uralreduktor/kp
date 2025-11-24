#!/usr/bin/env node
/**
 * Node.js скрипт для генерации PDF через Playwright
 * Использование: node generate_pdf_playwright.js <html_file> <pdf_output>
 * 
 * Требования:
 * - npm install playwright
 * - npx playwright install chromium
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

// Получение аргументов командной строки
const args = process.argv.slice(2);

if (args.length < 2) {
    console.error('Usage: node generate_pdf_playwright.js <html_file> <pdf_output>');
    process.exit(1);
}

const htmlFile = args[0];
const pdfOutput = args[1];

// Проверка существования HTML файла
if (!fs.existsSync(htmlFile)) {
    console.error(`Error: HTML file not found: ${htmlFile}`);
    process.exit(1);
}

// Чтение HTML содержимого
const htmlContent = fs.readFileSync(htmlFile, 'utf-8');

// Функция для генерации PDF
async function generatePDF() {
    let browser = null;
    
    try {
        // Запуск браузера в headless режиме
        // Используем chromium, который автоматически использует headless_shell, если доступен
        const launchOptions = {
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu'
            ]
        };
        
        // Если указан путь к браузерам через переменную окружения
        if (process.env.PLAYWRIGHT_BROWSERS_PATH) {
            // Playwright автоматически использует этот путь
            console.log('Using PLAYWRIGHT_BROWSERS_PATH:', process.env.PLAYWRIGHT_BROWSERS_PATH);
        }
        
        browser = await chromium.launch(launchOptions);
        
        // Создание новой страницы
        const page = await browser.newPage();
        
        // Установка содержимого страницы
        // Используем file:// протокол для локальных файлов или data: URL для встроенного HTML
        // Для изображений используем абсолютные пути или base64
        
        // Конвертируем относительные пути изображений в абсолютные
        // Если HTML содержит относительные пути, их нужно обработать
        await page.setContent(htmlContent, {
            waitUntil: 'networkidle0' // Ждем загрузки всех ресурсов
        });
        
        // Ожидание загрузки шрифтов (как в Puppeteer waitForFonts)
        await page.evaluate(() => {
            return document.fonts.ready;
        });
        
        // Дополнительное ожидание для полной загрузки изображений
        await page.waitForLoadState('networkidle');
        
        // Генерация PDF с расширенными опциями (заимствовано из Puppeteer)
        await page.pdf({
            path: pdfOutput,
            format: 'A4',
            margin: {
                top: '10mm',
                right: '10mm',
                bottom: '10mm',
                left: '10mm'
            },
            printBackground: true, // Включаем фоны для корректного отображения
            preferCSSPageSize: false,
            // Дополнительные опции из Puppeteer:
            scale: 1.0, // Масштаб рендеринга (0.1-2), можно использовать для увеличения качества
            displayHeaderFooter: false, // Можно включить для заголовков/подвалов на каждой странице
            // headerTemplate: '', // HTML шаблон для заголовка (если displayHeaderFooter: true)
            // footerTemplate: '', // HTML шаблон для подвала с pageNumber и totalPages
            tagged: true, // Генерация доступного PDF (для screen readers)
            outline: false, // Генерация структуры документа (экспериментально)
            timeout: 30000 // Таймаут в миллисекундах
        });
        
        console.log(`PDF generated successfully: ${pdfOutput}`);
        
    } catch (error) {
        console.error('Error generating PDF:', error.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
}

// Запуск генерации
generatePDF().catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
});

