import logging
import asyncio
import random
from typing import Optional
from playwright.async_api import async_playwright, Browser, Page, Error as PlaywrightError
from fake_useragent import UserAgent
from ..schemas import ParseRequest, ParseResponse
from .base import ParsingEngine

logger = logging.getLogger(__name__)

class PlaywrightEngine(ParsingEngine):
    def __init__(self):
        self.ua = UserAgent()

    async def _apply_stealth(self, page: Page):
        """
        Apply stealth techniques to avoid bot detection.
        """
        # 1. Randomize User-Agent
        try:
            user_agent = self.ua.random
            await page.set_extra_http_headers({"User-Agent": user_agent})
        except Exception:
            pass

        # 2. Remove navigator.webdriver property
        await page.add_init_script("""
            Object.defineProperty(navigator, 'webdriver', {
                get: () => undefined
            });
        """)

        # 3. Mock languages and plugins
        await page.add_init_script("""
            Object.defineProperty(navigator, 'languages', {
                get: () => ['ru-RU', 'ru', 'en-US', 'en']
            });
            Object.defineProperty(navigator, 'plugins', {
                get: () => [1, 2, 3, 4, 5]
            });
        """)

        # 4. Mock WebGL (basic)
        await page.add_init_script("""
            const getParameter = WebGLRenderingContext.prototype.getParameter;
            WebGLRenderingContext.prototype.getParameter = function(parameter) {
                if (parameter === 37445) return 'Intel Inc.';
                if (parameter === 37446) return 'Intel Iris OpenGL Engine';
                return getParameter(parameter);
            };
        """)

    async def _scroll_page(self, page: Page):
        """
        Scroll the page to trigger lazy loading.
        Mimics logic from api/parse_html_playwright_stealth.js
        """
        try:
            scroll_height = await page.evaluate("document.body.scrollHeight")
            viewport_height = await page.evaluate("window.innerHeight")
            
            if scroll_height > viewport_height:
                # Scroll to a random position between 30% and 80% of the page
                target_y = random.randint(int(scroll_height * 0.3), int(scroll_height * 0.8))
                await page.evaluate(f"window.scrollTo(0, {target_y})")
                logger.info(f"Scrolled page to {target_y}px")
                await asyncio.sleep(random.uniform(0.5, 1.5))
        except Exception as e:
            logger.warning(f"Scroll failed: {e}")

    async def parse(self, request: ParseRequest) -> ParseResponse:
        async with async_playwright() as p:
            browser = None
            context = None
            try:
                logger.info(f"Starting Playwright parse for {request.url} (Stealth: {request.use_stealth})")
                
                browser = await p.chromium.launch(
                    headless=True,
                    args=[
                        "--no-sandbox",
                        "--disable-setuid-sandbox",
                        "--disable-blink-features=AutomationControlled",
                    ]
                )
                
                user_agent = None
                if request.use_stealth:
                    try: user_agent = self.ua.random
                    except Exception: pass

                context = await browser.new_context(
                    viewport={"width": 1920, "height": 1080},
                    locale="ru-RU",
                    timezone_id="Europe/Moscow",
                    user_agent=user_agent
                )

                page = await context.new_page()
                
                if request.use_stealth:
                    await self._apply_stealth(page)

                logger.info(f"Navigating to {request.url}")
                
                response = await page.goto(
                    str(request.url), 
                    wait_until="domcontentloaded", 
                    timeout=request.timeout
                )

                # Wait for Selector or Network Idle
                if request.wait_for_selector:
                    try:
                        # Try waiting for the selector, but don't fail hard if not found immediately
                        # We might need to scroll first
                        await page.wait_for_selector(request.wait_for_selector, timeout=request.timeout)
                    except PlaywrightError as e:
                        logger.warning(f"Timeout waiting for selector {request.wait_for_selector}. Will try scrolling.")

                # Perform Scroll to trigger Lazy Load
                if request.render_js:
                    await self._scroll_page(page)
                
                # If we didn't wait successfully before, try network idle now
                try:
                    await page.wait_for_load_state("networkidle", timeout=5000)
                except Exception:
                    pass

                content = await page.content()
                status = response.status if response else 0
                
                headers = {}
                if response:
                    try: headers = await response.all_headers()
                    except Exception: pass
                
                cookies = await context.cookies()

                logger.info(f"Successfully parsed {request.url}. Content length: {len(content)}")

                return ParseResponse(
                    url=str(request.url),
                    content=content,
                    status_code=status,
                    headers=headers,
                    cookies=cookies
                )

            except Exception as e:
                logger.error(f"Error parsing {request.url}: {e}")
                return ParseResponse(
                    url=str(request.url),
                    content="",
                    status_code=500,
                    headers={},
                    cookies=[],
                    error=str(e)
                )
            finally:
                if context: await context.close()
                if browser: await browser.close()
