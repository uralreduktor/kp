from fastapi import APIRouter, Depends

from app.api.routes import auth, device, health, invoices, external
from app.parsing.router import router as parsing_router
from app.dependencies.auth import session_guard

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(device.router)
api_router.include_router(parsing_router)

# Версионированный API
api_v1_router = APIRouter(prefix="/v1")
api_v1_router.include_router(
    invoices.router,
    prefix="/invoices",
    tags=["invoices"],
    dependencies=[Depends(session_guard)],
)
api_v1_router.include_router(
    external.router,
    tags=["external"],
    dependencies=[Depends(session_guard)],
)
api_v1_router.include_router(
    parsing_router,
    prefix="/parsing",
    tags=["parsing"],
)

# Совместимость без версии (до полного перехода фронтенда)
api_router.include_router(
    invoices.router,
    prefix="/invoices",
    tags=["invoices"],
    dependencies=[Depends(session_guard)],
)
api_router.include_router(
    external.router,
    tags=["external"],
    dependencies=[Depends(session_guard)],
)
api_router.include_router(
    parsing_router,
    prefix="/parsing",
    tags=["parsing"],
)
api_router.include_router(api_v1_router)
