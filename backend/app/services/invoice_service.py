import json
import os
import re
from datetime import datetime
from glob import glob
from typing import List, Optional, Dict

from fastapi import HTTPException, status
from pydantic import ValidationError

from app.core.config import get_settings
from app.schemas.invoice import Invoice

settings = get_settings()

class InvoiceService:
    def __init__(self):
        self.storage_path = settings.invoice_storage_path
        self._ensure_storage_access()

    def _ensure_storage_access(self):
        if not os.path.exists(self.storage_path):
            try:
                os.makedirs(self.storage_path, mode=0o777, exist_ok=True)
            except OSError as e:
                raise RuntimeError(f"Failed to create storage directory {self.storage_path}: {e}")
        
        if not os.access(self.storage_path, os.W_OK):
             # Try to fix permissions if we own it? No, unsafe. Just warn/fail.
             # In docker, this might be tricky. We assume permissions are set correctly by ops.
             # Just logging for now might be better than crashing app startup?
             # But for a service that NEEDS to write, crashing is better.
             raise RuntimeError(f"Storage directory {self.storage_path} is not writable by current user {os.getuid()}")

    def list_invoices(self) -> List[Dict]:
        """
        Lists all invoices in the storage directory.
        Returns basic metadata for the list view.
        """
        invoices = []
        
        try:
            # Sort by modification time, newest first
            files = sorted(glob(os.path.join(self.storage_path, "*.json")), key=os.path.getmtime, reverse=True)
        except OSError as e:
             raise HTTPException(status_code=500, detail=f"Failed to list invoices: {e}")

        for file_path in files:
            try:
                with open(file_path, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    
                    # Basic validation/extraction for list view
                    invoices.append({
                        "filename": os.path.basename(file_path),
                        "number": data.get("number", "N/A"),
                        "date": data.get("date", "N/A"),
                        "recipient": data.get("recipient", "N/A"),
                        "total": self._calculate_total(data),
                        "currency": data.get("currency", "Руб."),
                        "documentType": data.get("documentType", "regular"),
                        "saved_at": data.get("_metadata", {}).get("saved_at", ""),
                        "organizationId": data.get("organizationId", None)
                    })
            except (json.JSONDecodeError, OSError):
                continue # Skip broken files

        return invoices

    def _calculate_total(self, data: dict) -> float:
        total = 0.0
        items = data.get("items", [])
        if isinstance(items, list):
            for item in items:
                if isinstance(item, dict):
                    try:
                        q = float(item.get("quantity", 0))
                        p = float(item.get("price", 0))
                        total += q * p
                    except (ValueError, TypeError):
                        pass
        return total

    def get_invoice(self, filename: str) -> Invoice:
        safe_filename = os.path.basename(filename)
        file_path = os.path.join(self.storage_path, safe_filename)
        
        # DEBUG
        print(f"DEBUG: accessing file path: '{file_path}'")
        
        if not os.path.exists(file_path):
            print(f"DEBUG: File not found: '{file_path}'")
            try:
                print(f"DEBUG: Dir listing: {os.listdir(self.storage_path)[:5]}")
            except Exception as e:
                print(f"DEBUG: List dir error: {e}")
            
            raise HTTPException(status_code=404, detail="Invoice not found")
            
        try:
            with open(file_path, 'r', encoding='utf-8') as f:
                data = json.load(f)
                
            # Inject filename if missing so we know which file we edited
            if "filename" not in data or not data["filename"]:
                data["filename"] = safe_filename
                
            return Invoice.model_validate(data)
        except json.JSONDecodeError:
             raise HTTPException(status_code=500, detail="Invalid JSON file")
        except ValidationError as e:
             # Return detailed error for debugging
             raise HTTPException(status_code=422, detail=f"Validation error: {e}")

    def save_invoice(self, invoice: Invoice) -> Dict:
        data = invoice.model_dump(by_alias=True, exclude_none=True)
        
        # Handle metadata
        if "_metadata" not in data or not data["_metadata"]:
             data["_metadata"] = {}
             
        data["_metadata"].update({
            "saved_at": datetime.now().isoformat(),
            "version": "2.0 (FastAPI)",
            "updated_by": "fastapi_service"
        })

        filename = invoice.filename
        if not filename:
            # If no filename, generate one based on number + timestamp
            safe_number = re.sub(r'[^a-zA-Z0-9-_]', '_', invoice.number)
            timestamp = datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
            filename = f"{safe_number}_{timestamp}.json"
            data["filename"] = filename

        safe_filename = os.path.basename(filename)
        file_path = os.path.join(self.storage_path, safe_filename)
        
        # Atomic write
        temp_path = f"{file_path}.tmp"
        try:
            with open(temp_path, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            # Set permissions to 666 so PHP/Others can read/write if needed
            os.chmod(temp_path, 0o666)
            os.replace(temp_path, file_path)
        except OSError as e:
            if os.path.exists(temp_path):
                os.remove(temp_path)
            raise HTTPException(status_code=500, detail=f"Failed to save file: {e}")
            
        return {"success": True, "filename": safe_filename, "filepath": file_path}

    def get_next_number(self, date_str: str = None) -> int:
        """
        Scans all JSON files to find the next sequence number for the given date.
        Format: DDMMYY-NN (e.g. 251118-01)
        """
        if not date_str:
            date_str = datetime.now().strftime("%d%m%y")
        else:
            # Try to convert YYYY-MM-DD to DDMMYY
            try:
                 dt = datetime.strptime(date_str, "%Y-%m-%d")
                 date_str = dt.strftime("%d%m%y")
            except ValueError:
                 pass 
        
        # Regex: ^DDMMYY-(\d+)$
        pattern = re.compile(rf"^{re.escape(date_str)}-(\d+)$", re.IGNORECASE)
        
        max_number = 0
        
        try:
            files = os.listdir(self.storage_path)
        except OSError:
            return 1
            
        for filename in files:
            if not filename.endswith(".json"):
                continue
                
            try:
                with open(os.path.join(self.storage_path, filename), 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    invoice_number = str(data.get("number", "")).strip()
                    
                    match = pattern.match(invoice_number)
                    if match:
                        num = int(match.group(1))
                        if num > max_number:
                            max_number = num
            except (json.JSONDecodeError, OSError):
                continue
                
        return max_number + 1

