import pytest
from httpx import AsyncClient

from app.main import app


@pytest.mark.asyncio
async def test_health_ping() -> None:
    async with AsyncClient(app=app, base_url="http://testserver") as ac:
        response = await ac.get("/api/health/ping")
    assert response.status_code == 200
    assert response.json() == {"status": "ok"}
