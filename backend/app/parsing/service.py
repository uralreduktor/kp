from .schemas import ParseRequest, ParseResponse
from .engine.playwright import PlaywrightEngine

class ParsingService:
    def __init__(self):
        # In the future, we can inject different engines here or choose based on request
        self.engine = PlaywrightEngine()

    async def parse_url(self, request: ParseRequest) -> ParseResponse:
        """
        Main entry point for parsing a URL.
        Delegates to the appropriate engine.
        """
        # Logic to choose engine could go here.
        # For now, default to Playwright as it's the most robust for our use case.
        return await self.engine.parse(request)

# Global instance
parsing_service = ParsingService()
