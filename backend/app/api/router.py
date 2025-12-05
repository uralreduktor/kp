from fastapi import APIRouter

from app.api.routes import auth, device, health
from app.parsing.router import router as parsing_router

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(device.router)
api_router.include_router(parsing_router)
