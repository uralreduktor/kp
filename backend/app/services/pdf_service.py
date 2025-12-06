import json
import asyncio
import os
from typing import Dict, Any
from fastapi import HTTPException
from playwright.async_api import async_playwright

class PdfService:
    def __init__(self):
        # Absolute path to the script
        self.php_script_path = "/var/www/kp/api/cli/render_invoice.php"

    async def generate_pdf(self, invoice_data: Dict[str, Any]) -> bytes:
        # 1. Generate HTML using PHP CLI
        try:
            if not os.path.exists(self.php_script_path):
                raise FileNotFoundError(f"PHP render script not found at {self.php_script_path}")

            proc = await asyncio.create_subprocess_exec(
                'php', self.php_script_path,
                stdin=asyncio.subprocess.PIPE,
                stdout=asyncio.subprocess.PIPE,
                stderr=asyncio.subprocess.PIPE
            )
            
            input_json = json.dumps(invoice_data, ensure_ascii=False).encode('utf-8')
            stdout, stderr = await proc.communicate(input=input_json)
            
            if proc.returncode != 0:
                error_msg = stderr.decode() if stderr else "Unknown error"
                raise RuntimeError(f"PHP Render Error (code {proc.returncode}): {error_msg}")
                
            html_content = stdout.decode('utf-8')
            
            # Basic check if HTML is valid
            if not html_content.strip().startswith("<!DOCTYPE html>") and not html_content.strip().startswith("<html"):
                 # Maybe some warning spilled into stdout?
                 pass

        except Exception as e:
             raise HTTPException(status_code=500, detail=f"Failed to render HTML template: {str(e)}")

        # 2. Generate PDF using Playwright
        try:
            async with async_playwright() as p:
                # Launch options same as in JS script
                browser = await p.chromium.launch(
                    headless=True,
                    args=['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
                )
                page = await browser.new_page()
                
                await page.set_content(html_content, wait_until="networkidle")
                
                pdf_bytes = await page.pdf(
                    format="A4",
                    print_background=True,
                    margin={
                        "top": "10mm",
                        "right": "10mm",
                        "bottom": "10mm",
                        "left": "10mm"
                    },
                    scale=1.0,
                    display_header_footer=False
                )
                
                await browser.close()
                return pdf_bytes
                
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Failed to generate PDF with Playwright: {str(e)}")

