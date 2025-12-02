from fastapi import APIRouter

router = APIRouter(tags=["health"], prefix="/health")


@router.get("/ping", summary="Простой health-check")
async def ping() -> dict[str, str]:
    return {"status": "ok"}
