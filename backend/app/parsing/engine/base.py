from abc import ABC, abstractmethod
from typing import Optional
from ..schemas import ParseRequest, ParseResponse

class ParsingEngine(ABC):
    @abstractmethod
    async def parse(self, request: ParseRequest) -> ParseResponse:
        """
        Parse the given URL and return the response.
        """
        pass
