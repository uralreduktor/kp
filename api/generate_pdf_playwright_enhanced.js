#!/usr/bin/env node
/**
 * Улучшенный Node.js скрипт для генерации PDF через Playwright
 * Заимствует полезные функции из Puppeteer
 * 
 * Использование: node generate_pdf_playwright_enhanced.js <html_file> <pdf_output> [options]
 * 
 * Опции (через переменные окружения):
 * - PDF_SCALE=1.0 - масштаб рендеринга (0.1-2)
 * - PDF_DISPLAY_HEADER_FOOTER=false - показывать заголовки/подвалы
 * - PDF_HEADER_TEMPLATE - HTML шаблон для заголовка
 * - PDF_FOOTER_TEMPLATE - HTML шаблон для подвала
 * - PDF_TAGGED=true - генерация доступного PDF
 * - PDF_TIMEOUT=30000 - таймаут в миллисекундах
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
    console.error('Usage: node generate_pdf_playwright_enhanced.js <html_file> <pdf_output>');
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

// Получение опций из переменных окружения (заимствовано из практик Puppeteer)
const getEnvOption = (key, defaultValue) => {
    const value = process.env[key];
    if (value === undefined) return defaultValue;
    if (value === 'true') return true;
    if (value === 'false') return false;
    if (!isNaN(value)) return parseFloat(value);
    return value;
};

// Функция для генерации PDF с расширенными опциями
async function generatePDF() {
    let browser = null;
    
    try {
        // Запуск браузера в headless режиме
        browser = await chromium.launch({
            headless: true,
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage', // Уменьшает использование памяти
                '--disable-gpu' // Для серверов без GPU
            ]
        });
        
        // Создание новой страницы
        const page = await browser.newPage();
        
        // Установка viewport для консистентного рендеринга
        await page.setViewportSize({ width: 1920, height: 1080 });
        
        // Установка содержимого страницы
        await page.setContent(htmlContent, {
            waitUntil: 'networkidle0' // Ждем загрузки всех ресурсов
        });
        
        // Ожидание загрузки шрифтов (заимствовано из Puppeteer waitForFonts)
        // Это гарантирует, что все шрифты загружены перед генерацией PDF
        try {
            await page.evaluate(() => {
                return document.fonts.ready;
            });
        } catch (e) {
            console.warn('Warning: Could not wait for fonts:', e.message);
        }
        
        // Дополнительное ожидание для полной загрузки изображений
        await page.waitForLoadState('networkidle');
        
        // Небольшая задержка для завершения всех анимаций и рендеринга
        await page.waitForTimeout(500);
        
        // Получение опций из переменных окружения
        const scale = getEnvOption('PDF_SCALE', 1.0);
        const displayHeaderFooter = getEnvOption('PDF_DISPLAY_HEADER_FOOTER', false);
        const headerTemplate = getEnvOption('PDF_HEADER_TEMPLATE', '');
        const footerTemplate = getEnvOption('PDF_FOOTER_TEMPLATE', '');
        const tagged = getEnvOption('PDF_TAGGED', true);
        const timeout = getEnvOption('PDF_TIMEOUT', 30000);
        
        // Генерация PDF с расширенными опциями (заимствовано из Puppeteer)
        const pdfOptions = {
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
            scale: scale, // Масштаб рендеринга (0.1-2)
            displayHeaderFooter: displayHeaderFooter, // Показывать заголовки/подвалы
            tagged: tagged, // Генерация доступного PDF (для screen readers)
            outline: false, // Генерация структуры документа (экспериментально)
            timeout: timeout // Таймаут в миллисекундах
        };
        
        // Добавляем шаблоны заголовка и подвала, если они указаны
        if (displayHeaderFooter) {
            if (headerTemplate) {
                pdfOptions.headerTemplate = headerTemplate;
            }
            if (footerTemplate) {
                // Пример footerTemplate с номерами страниц (как в Puppeteer):
                // '<div style="font-size: 10px; text-align: center; width: 100%;">
                //   Page <span class="pageNumber"></span> of <span class="totalPages"></span>
                // </div>'
                pdfOptions.footerTemplate = footerTemplate || 
                    '<div style="font-size: 10px; text-align: center; width: 100%;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>';
            }
        }
        
        await page.pdf(pdfOptions);
        
        console.log(`PDF generated successfully: ${pdfOutput}`);
        console.log(`Options used: scale=${scale}, tagged=${tagged}, displayHeaderFooter=${displayHeaderFooter}`);
        
    } catch (error) {
        console.error('Error generating PDF:', error.message);
        if (error.stack) {
            console.error('Stack trace:', error.stack);
        }
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

