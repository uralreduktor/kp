from fastapi import APIRouter, Depends, HTTPException, status, Response
from typing import List, Dict
from app.services.invoice_service import InvoiceService
from app.services.pdf_service import PdfService
from app.schemas.invoice import Invoice

router = APIRouter()

def get_invoice_service() -> InvoiceService:
    return InvoiceService()

def get_pdf_service() -> PdfService:
    return PdfService()

@router.get("/", response_model=List[Dict])
async def list_invoices(
    service: InvoiceService = Depends(get_invoice_service)
):
    """
    Get list of all invoices with basic metadata.
    """
    return service.list_invoices()

@router.get("/next-number")
async def get_next_number(
    date: str = None,
    service: InvoiceService = Depends(get_invoice_service)
):
    """
    Generate next sequence number for the given date.
    """
    number = service.get_next_number(date)
    return {"number": number}

@router.get("/{filename}/pdf")
async def get_invoice_pdf(
    filename: str,
    service: InvoiceService = Depends(get_invoice_service),
    pdf_service: PdfService = Depends(get_pdf_service)
):
    """
    Generate and download PDF for an existing invoice.
    """
    invoice = service.get_invoice(filename)
    data = invoice.model_dump(by_alias=True, exclude_none=False)  # Не исключаем None/пустые строки
    pdf_bytes = await pdf_service.generate_pdf(data)
    
    download_filename = f"Invoice_{invoice.number}.pdf"
    
    return Response(
        content=pdf_bytes,
        media_type="application/pdf",
        headers={"Content-Disposition": f"attachment; filename={download_filename}"}
    )

@router.get("/{filename}", response_model=Invoice)
async def get_invoice(
    filename: str,
    service: InvoiceService = Depends(get_invoice_service)
):
    """
    Get full invoice data by filename.
    """
    return service.get_invoice(filename)

@router.post("/", response_model=Dict)
async def save_invoice(
    invoice: Invoice,
    service: InvoiceService = Depends(get_invoice_service)
):
    """
    Save or update an invoice.
    """
    return service.save_invoice(invoice)


@router.delete("/{filename}", response_model=Dict)
async def delete_invoice(
    filename: str,
    service: InvoiceService = Depends(get_invoice_service)
):
    """
    Delete an invoice by filename.
    """
    return service.delete_invoice(filename)
