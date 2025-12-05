from fastapi import APIRouter, HTTPException, Query
from typing import Any
from .schemas import ParseRequest, ParseResponse
from .service import parsing_service
from .tender_schemas import TenderParseResponse, TenderData
from .parsers.b2b_center import B2BCenterParser
import logging

logger = logging.getLogger(__name__)

router = APIRouter(prefix="/parsing", tags=["parsing"])

@router.post("/parse", response_model=ParseResponse)
async def parse_url(request: ParseRequest):
    try:
        result = await parsing_service.parse_url(request)
        return result
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))

@router.get("/tender", response_model=TenderParseResponse)
async def parse_tender(url: str = Query(..., description="Tender URL")):
    logger.info(f"Received tender parse request for URL: {url}")
    try:
        # 1. Load Main Page
        req = ParseRequest(
            url=url, 
            use_stealth=True, 
            render_js=True, 
            wait_for_selector='#__NEXT_DATA__, .organizer-information, .customer-information' if 'b2b-center.ru' in url else None
        )
        
        page_result = await parsing_service.parse_url(req)
        if page_result.status_code != 200 or not page_result.content:
             return TenderParseResponse(success=False, error=f"Failed to load page: {page_result.status_code}")

        # 2. Parse Main Page
        parser = B2BCenterParser() if 'b2b-center.ru' in url else None
        if not parser: return TenderParseResponse(success=False, error="Unsupported platform")
             
        data = parser.parse(page_result.content, url)

        # 3. Follow Link Logic
        debug_steps = [f"MainHTML:{len(page_result.content)}"]
        
        if hasattr(parser, 'found_organizer_link') and parser.found_organizer_link:
            link = parser.found_organizer_link
            debug_steps.append(f"FoundLink:{link}")
            
            if not data.recipientINN:
                profile_url = link if link.startswith('http') else f"https://www.b2b-center.ru{link}"
                
                try:
                    profile_req = ParseRequest(url=profile_url, use_stealth=True, render_js=True)
                    profile_result = await parsing_service.parse_url(profile_req)
                    
                    if profile_result.status_code == 200 and profile_result.content:
                        debug_steps.append(f"ProfileHTML:{len(profile_result.content)}")
                        inn = parser.parse_company_profile(profile_result.content)
                        if inn:
                            data.recipientINN = inn
                            debug_steps.append(f"ProfileINN:{inn}")
                        else:
                            debug_steps.append("ProfileINN:NOT_FOUND")
                    else:
                        debug_steps.append(f"ProfileFetchFail:{profile_result.status_code}")
                except Exception as e:
                    debug_steps.append(f"ProfileErr:{str(e)}")
        else:
            debug_steps.append("NoLinkFound")

        # Inject debug info if failed
        if not data.recipientINN:
            debug_str = " | ".join(debug_steps)
            if data.recipient: data.recipient += f" [{debug_str}]"
            else: data.recipient = f"[{debug_str}]"
        
        return TenderParseResponse(success=True, data=data)

    except Exception as e:
        logger.error(f"Tender parse error: {e}", exc_info=True)
        return TenderParseResponse(success=False, error=str(e))
