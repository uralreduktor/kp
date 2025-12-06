import httpx
from typing import List, Dict, Any
from app.core.config import get_settings

settings = get_settings()

class DaDataService:
    BASE_URL = "https://suggestions.dadata.ru/suggestions/api/4_1/rs/suggest"

    def __init__(self):
        self.token = settings.dadata_api_key
        self.headers = {
            "Content-Type": "application/json",
            "Accept": "application/json",
            "Authorization": f"Token {self.token}"
        }

    async def suggest_party(self, query: str, count: int = 5) -> List[Dict[str, Any]]:
        if len(query) < 3:
            return []
            
        async with httpx.AsyncClient() as client:
            try:
                response = await client.post(
                    f"{self.BASE_URL}/party",
                    json={"query": query, "count": min(count, 10), "status": ["ACTIVE"]},
                    headers=self.headers,
                    timeout=10.0
                )
                response.raise_for_status()
                data = response.json()
                
                suggestions = []
                for item in data.get("suggestions", []):
                    d = item.get("data", {})
                    suggestions.append({
                        "value": item.get("value"),
                        "inn": d.get("inn"),
                        "kpp": d.get("kpp"),
                        "ogrn": d.get("ogrn"),
                        "name": d.get("name", {}).get("full_with_opf") or d.get("name", {}).get("full") or item.get("value"),
                        "name_short": d.get("name", {}).get("short_with_opf") or d.get("name", {}).get("short"),
                        "address": d.get("address", {}).get("unrestricted_value") or d.get("address", {}).get("value"),
                        "management": d.get("management", {}).get("name"),
                        "type": d.get("type"),
                        "status": d.get("state", {}).get("status")
                    })
                return suggestions
            except httpx.HTTPError as e:
                # In production use proper logger
                print(f"DaData API Error: {e}")
                return []

    async def suggest_address(self, query: str, count: int = 5) -> List[Dict[str, Any]]:
        if len(query) < 3:
            return []
            
        async with httpx.AsyncClient() as client:
            try:
                response = await client.post(
                    f"{self.BASE_URL}/address",
                    json={
                        "query": query, 
                        "count": min(count, 20),
                        "locations": [{"country": "Россия"}]
                    },
                    headers=self.headers,
                    timeout=10.0
                )
                response.raise_for_status()
                data = response.json()
                
                suggestions = []
                for item in data.get("suggestions", []):
                    d = item.get("data", {})
                    suggestions.append({
                        "value": item.get("value"),
                        "unrestricted_value": item.get("unrestricted_value") or item.get("value"),
                        "postal_code": d.get("postal_code"),
                        "country": d.get("country") or "Россия",
                        "region": d.get("region_with_type"),
                        "city": d.get("city_with_type") or d.get("settlement_with_type"),
                        "street": d.get("street_with_type"),
                        "house": d.get("house"),
                        "fias_id": d.get("fias_id"),
                        "fias_level": d.get("fias_level"),
                        "geo_lat": d.get("geo_lat"),
                        "geo_lon": d.get("geo_lon")
                    })
                return suggestions
            except httpx.HTTPError as e:
                print(f"DaData API Error: {e}")
                return []

