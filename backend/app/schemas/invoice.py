from pydantic import BaseModel, Field, BeforeValidator, ConfigDict
from typing import List, Optional, Any, Annotated, Dict, Union

# Валидатор для превращения пустого массива PHP ([]) в пустой словарь ({})
def empty_list_to_dict(v: Any) -> Any:
    if isinstance(v, list) and len(v) == 0:
        return {}
    return v

CleanDict = Annotated[Dict[str, Any], BeforeValidator(empty_list_to_dict)]

class ReducerSpecs(BaseModel):
    type: Optional[str] = None
    stages: Optional[Union[int, float]] = None
    torqueNm: Optional[Union[int, float]] = None
    ratio: Optional[str] = None
    housingMaterial: Optional[str] = None
    gearMaterial: Optional[str] = None
    bearings: Optional[List[str]] = None
    
    model_config = ConfigDict(extra='allow')

class InvoiceItem(BaseModel):
    id: Optional[str] = None
    description: str = ""
    model: Optional[str] = ""
    quantity: Union[float, int] = 0
    price: Union[float, int] = 0
    reducerSpecs: Optional[Union[CleanDict, ReducerSpecs]] = Field(default_factory=dict)

class CommercialTerms(BaseModel):
    incoterm: Optional[str] = ""
    deliveryPlace: Optional[str] = ""
    deliveryTime: Optional[str] = ""
    paymentTerms: Optional[str] = ""
    warranty: Optional[str] = ""

class Contact(BaseModel):
    person: Optional[str] = ""
    position: Optional[str] = ""
    email: Optional[str] = ""
    phone: Optional[str] = ""

class Invoice(BaseModel):
    filename: Optional[str] = None
    number: str
    date: str
    validUntil: Optional[str] = ""
    
    recipient: str
    recipientINN: Optional[str] = ""
    recipientAddress: Optional[str] = ""
    
    currency: str = "Руб."
    
    items: List[InvoiceItem] = Field(default_factory=list)
    
    # Union[CleanDict, CommercialTerms] позволяет сначала отловить {}, а затем валидировать модель
    # Но BeforeValidator уже делает работу по очистке.
    # Используем BeforeValidator для полей, которые должны быть моделями
    
    commercialTerms: Annotated[CommercialTerms, BeforeValidator(empty_list_to_dict)] = Field(default_factory=CommercialTerms)
    contact: Annotated[Contact, BeforeValidator(empty_list_to_dict)] = Field(default_factory=Contact)
    
    organizationId: Optional[str] = None
    selectedBankId: Optional[str] = None
    documentType: str = "regular"
    
    tenderId: Optional[str] = ""
    tenderPlatform: Optional[str] = ""
    tenderLink: Optional[str] = ""
    
    technicalSummary: Optional[str] = ""
    
    # Metadata fields that might exist in files
    meta_data: Optional[Dict[str, Any]] = Field(default=None, alias="_metadata")
    
    model_config = ConfigDict(extra='ignore')

