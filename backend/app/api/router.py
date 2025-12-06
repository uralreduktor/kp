from fastapi import APIRouter

from app.api.routes import auth, device, health, invoices, external
from app.parsing.router import router as parsing_router

api_router = APIRouter()
api_router.include_router(health.router)
api_router.include_router(auth.router)
api_router.include_router(device.router)
api_router.include_router(parsing_router)

# New Invoice routes (prefix /api/invoices is automatic because app includes api_router with /api prefix)
# Wait, settings.api_prefix is /api.
# So invoices will be at /api/invoices
api_router.include_router(invoices.router, prefix="/invoices", tags=["invoices"])

# External services (DaData, PDF)
# Routes in external.py are /suggest/... and /pdf/...
# So they will be /api/suggest/... and /api/pdf/... matching new clean structure
api_router.include_router(external.router, tags=["external"])
