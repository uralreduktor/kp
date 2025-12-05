import asyncio
import json
import re
import logging
import sys
from playwright.async_api import async_playwright
from lxml import html

# Configure logging to stdout
logging.basicConfig(level=logging.INFO, format='%(message)s')
logger = logging.getLogger(__name__)

# --- Minimal Parser Implementation (copied from updated code) ---
class DebugParser:
    def parse(self, html_content: str, url: str):
        print(f"Analyzing HTML (length: {len(html_content)})")
        
        # Check for __NEXT_DATA__
        match = re.search(r'<script id="__NEXT_DATA__" type="application/json">(.*?)</script>', html_content, re.DOTALL)
        if match:
            print("SUCCESS: Found __NEXT_DATA__ script tag!")
            try:
                json_data = json.loads(match.group(1))
                print("SUCCESS: Parsed JSON data")
                
                # Debug: print structure hints
                if isinstance(json_data, dict):
                    print(f"Top level keys: {list(json_data.keys())}")
                    if 'props' in json_data:
                        print(f"Props keys: {list(json_data['props'].keys())}")
                        if 'pageProps' in json_data['props']:
                             print(f"pageProps keys: {list(json_data['props']['pageProps'].keys())}")
                
                result = {}
                self._find_recursive(json_data, result)
                print(f"\n--- EXTRACTION RESULT ---\n{json.dumps(result, indent=2, ensure_ascii=False)}")
                
            except Exception as e:
                print(f"ERROR parsing JSON: {e}")
        else:
            print("FAIL: __NEXT_DATA__ script tag NOT found in HTML")
            
            # Check for other markers
            if 'organizer-information' in html_content:
                print("Found 'organizer-information' class")
            else:
                print("Class 'organizer-information' NOT found")

    def _find_recursive(self, data, result):
        if result.get('inn') and result.get('name'):
            return

        if isinstance(data, dict):
            for k, v in data.items():
                k_lower = k.lower()
                
                if k_lower in ['inn', 'инн', 'taxpayerid', 'taxpayer_id']:
                    s_v = str(v).strip()
                    if re.match(r'^\d{10,12}$', s_v):
                         result['inn'] = s_v
                         print(f"FOUND INN at key '{k}': {s_v}")
                
                if k_lower in ['short_title', 'full_title', 'org_name', 'company_name', 'name', 'organization', 'customer', 'organizer', 'title'] and isinstance(v, str):
                     if any(word in v for word in ['ООО', 'АО', 'ЗАО', 'ИП', 'ОАО', 'ПАО', 'КАО']):
                         if not result.get('name'):
                             result['name'] = v
                             print(f"FOUND NAME at key '{k}': {v}")
   
                if isinstance(v, (dict, list)):
                    self._find_recursive(v, result)
                    
        elif isinstance(data, list):
            for item in data:
                self._find_recursive(item, result)

async def main():
    url = "https://www.b2b-center.ru/app/market/postavka-valov/tender-4256022/"
    print(f"Fetching: {url}")
    
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(
            user_agent="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36"
        )
        page = await context.new_page()
        
        try:
            await page.goto(url, wait_until="domcontentloaded", timeout=30000)
            # Wait for the critical element
            try:
                await page.wait_for_selector('#__NEXT_DATA__', timeout=10000)
                print("Selector #__NEXT_DATA__ appeared")
            except:
                print("Timeout waiting for selector")
                
            content = await page.content()
            parser = DebugParser()
            parser.parse(content, url)
            
        except Exception as e:
            print(f"Browser error: {e}")
        finally:
            await browser.close()

if __name__ == "__main__":
    asyncio.run(main())
