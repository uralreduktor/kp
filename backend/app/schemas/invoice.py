from pydantic import BaseModel, Field, BeforeValidator, ConfigDict
from typing import List, Optional, Any, Annotated, Dict, Union

# Валидатор для превращения пустого массива PHP ([]) в пустой словарь ({})
def empty_list_to_dict(v: Any) -> Any:
    if isinstance(v, list) and len(v) == 0:
        return {}
    return v

CleanDict = Annotated[Dict[str, Any], BeforeValidator(empty_list_to_dict)]

def coerce_number(v: Any) -> float:
    """
    Приводит строковые/числовые значения к float.
    Пустая строка или None -> 0. Некорректные строки вызывают ошибку валидации.
    """
    if v is None:
        return 0
    if isinstance(v, (int, float)):
        return float(v)
    if isinstance(v, str):
        raw = v.strip()
        if raw == "":
            return 0
        raw = raw.replace(",", ".")
        try:
            return float(raw)
        except ValueError:
            raise ValueError(f"Expected numeric value, got '{v}'")
    raise TypeError(f"Expected numeric value, got '{type(v).__name__}'")

CoercedNumber = Annotated[float, BeforeValidator(coerce_number)]

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
    description: Optional[str] = ""
    type: Optional[str] = ""  # Legacy: тип/описание товара
    model: Optional[str] = ""
    name: Optional[str] = ""  # Legacy: название/метка модели
    quantity: CoercedNumber = 0
    price: CoercedNumber = 0
    unit: Optional[str] = ""  # Единица измерения
    countryOfOrigin: Optional[str] = ""  # Страна производства
    country: Optional[str] = ""  # Альтернативное поле для страны
    hsCode: Optional[str] = ""  # Код ТН ВЭД
    leadTime: Optional[str] = ""  # Срок поставки для позиции
    technicalDescription: Optional[str] = ""  # Техническое описание
    reducerSpecs: Optional[Union[CleanDict, ReducerSpecs]] = Field(default_factory=dict)
    
    model_config = ConfigDict(extra='allow')  # Разрешаем дополнительные поля

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
    
    # Legacy: поля условий на верхнем уровне (для обратной совместимости)
    incoterm: Optional[str] = ""
    deliveryPlace: Optional[str] = ""
    deliveryTime: Optional[str] = ""
    paymentTerms: Optional[str] = ""
    warranty: Optional[str] = ""
    remarks: Optional[str] = ""
    
    # Legacy: поля контакта на верхнем уровне
    contactPerson: Optional[str] = ""
    position: Optional[str] = ""
    contactEmail: Optional[str] = ""
    contactPhone: Optional[str] = ""
    
    organizationId: Optional[str] = None
    selectedBankId: Optional[str] = None
    documentType: str = "regular"
    
    tenderId: Optional[str] = ""
    tenderPlatform: Optional[str] = ""
    tenderLink: Optional[str] = ""
    
    technicalSummary: Optional[str] = ""
    
    # Reducer specifications
    reducerSpecs: Optional[CleanDict] = Field(default_factory=dict)
    
    # Metadata fields that might exist in files
    meta_data: Optional[CleanDict] = Field(default=None, alias="_metadata")
    
    model_config = ConfigDict(
        extra='ignore',
        populate_by_name=True,
    )

