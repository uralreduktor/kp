from typing import Optional, List, Dict, Any
from pydantic import BaseModel

class TenderItem(BaseModel):
    name: str
    quantity: float

class TenderData(BaseModel):
    tenderNumber: Optional[str] = None
    tenderPlatform: Optional[str] = None
    tenderLink: Optional[str] = None
    recipient: Optional[str] = None
    recipientINN: Optional[str] = None
    recipientAddress: Optional[str] = None
    deliveryAddress: Optional[str] = None
    deliveryIncoterm: Optional[str] = None
    items: List[TenderItem] = []
    
class TenderParseResponse(BaseModel):
    success: bool
    data: Optional[TenderData] = None
    error: Optional[str] = None
