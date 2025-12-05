from pydantic import BaseModel, HttpUrl, Field
from typing import Any, Dict, Optional, List

class ParseRequest(BaseModel):
    url: HttpUrl
    wait_for_selector: Optional[str] = Field(None, description="CSS selector to wait for before capturing HTML")
    use_stealth: bool = Field(True, description="Use stealth evasion techniques")
    render_js: bool = Field(True, description="Use Playwright (True) or simple HTTP client (False)")
    timeout: int = Field(60000, description="Timeout in ms")

class ParseResponse(BaseModel):
    url: str
    content: str
    status_code: int
    headers: Dict[str, str]
    cookies: List[Dict[str, Any]]
    error: Optional[str] = None
