from fastapi import APIRouter, Depends, Response, Body, Query
from typing import Dict, Any, List
from app.services.pdf_service import PdfService
from app.services.dadata_service import DaDataService
from app.schemas.invoice import Invoice

router = APIRouter()

def get_pdf_service() -> PdfService:
    return PdfService()

def get_dadata_service() -> DaDataService:
    return DaDataService()

@router.post("/pdf/generate")
async def generate_pdf(
    invoice: Invoice,
    service: PdfService = Depends(get_pdf_service)
):
    """
    Generate PDF for the given invoice data.
    """
    data = invoice.model_dump(by_alias=True, exclude_none=True)
    pdf_bytes = await service.generate_pdf(data)
    
    filename = f"Invoice_{invoice.number}.pdf"
    
    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={"Content-Disposition": f"attachment; filename={filename}"}
    )

# Supports both POST (better) and GET (legacy compatibility)

@router.post("/suggest/party")
async def suggest_party_post(
    query: str = Body(..., embed=True),
    count: int = Body(5, embed=True),
    service: DaDataService = Depends(get_dadata_service)
):
    suggestions = await service.suggest_party(query, count)
    return {"success": True, "suggestions": suggestions}

@router.get("/suggest/party")
async def suggest_party_get(
    query: str = Query(...),
    count: int = Query(5),
    service: DaDataService = Depends(get_dadata_service)
):
    suggestions = await service.suggest_party(query, count)
    return {"success": True, "suggestions": suggestions}

@router.post("/suggest/address")
async def suggest_address_post(
    query: str = Body(..., embed=True),
    count: int = Body(5, embed=True),
    service: DaDataService = Depends(get_dadata_service)
):
    suggestions = await service.suggest_address(query, count)
    return {"success": True, "suggestions": suggestions}

@router.get("/suggest/address")
async def suggest_address_get(
    query: str = Query(...),
    count: int = Query(5),
    service: DaDataService = Depends(get_dadata_service)
):
    suggestions = await service.suggest_address(query, count)
    return {"success": True, "suggestions": suggestions}

