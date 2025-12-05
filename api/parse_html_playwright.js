const { chromium } = require('playwright');

const args = process.argv.slice(2);
if (args.length < 1) {
    console.error('Usage: node parse_html_playwright.js <url>');
    process.exit(1);
}

const url = args[0];

(async () => {
    let browser = null;
    try {
        browser = await chromium.launch({
            headless: true,
            args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage']
        });
        
        const page = await browser.newPage();
        
        // Set a realistic user agent
        await page.setExtraHTTPHeaders({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept-Language': 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7'
        });

        // Navigate to the URL
        try {
            await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
            
            // Wait for hydration (Next.js usually adds __NEXT_DATA__ or specific root elements)
            // Wait for body to be non-empty
            await page.waitForSelector('body', { timeout: 10000 });
            
            // Try to wait for something significant if it's a SPA
            try {
                 await page.waitForLoadState('networkidle', { timeout: 10000 });
            } catch (e) {}

            // Special wait for B2B-Center specific elements if possible
            // e.g. table, or .tender-description
            try {
                await Promise.race([
                    page.waitForSelector('table', { timeout: 5000 }),
                    page.waitForSelector('.tender_description', { timeout: 5000 }),
                    page.waitForSelector('#__NEXT_DATA__', { timeout: 5000 })
                ]);
            } catch (e) {}
            
            // Extra sleep to be safe
            await page.waitForTimeout(3000);

        } catch (e) {
            console.error('Navigation error:', e.message);
            // Don't exit, try to return what we have
        }

        const content = await page.content();
        console.log(content);
        
    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    } finally {
        if (browser) {
            await browser.close();
        }
    }
})();

