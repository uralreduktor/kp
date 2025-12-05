import re
import logging
import json
from typing import Optional, Dict, Any
from lxml import html
from ..tender_schemas import TenderData, TenderItem

logger = logging.getLogger(__name__)

class B2BCenterParser:
    def parse(self, html_content: str, url: str) -> TenderData:
        tree = html.fromstring(html_content)
        data = TenderData(tenderLink=url, tenderPlatform="b2b-center")
        
        match = re.search(r'tender-(\d+)', url) or re.search(r'id=(\d+)', url)
        if match: data.tenderNumber = match.group(1)
            
        # Items
        rows = tree.xpath("//tr[contains(@class, 'c2')] | //tr[@class='c2']")
        if not rows: rows = tree.xpath("//table//tr[td[position()>1]]")
        
        for row in rows:
            cells = row.xpath(".//td")
            cell_texts = [self._clean_text(c.text_content()) for c in cells]
            name, qty = '', 0.0
            if len(cell_texts) >= 5:
                if cell_texts[3]: name = cell_texts[3]
                elif cell_texts[1]: name = cell_texts[1]
                if cell_texts[4]:
                    q_str = re.sub(r'[^\d.,]', '', cell_texts[4]).replace(',', '.')
                    try: qty = float(q_str)
                    except ValueError: pass
            if name and qty > 0:
                data.items.append(TenderItem(name=name, quantity=qty))

        # Organizer
        org_info = self._parse_organizer(tree)
        
        # NextData
        next_data_info = self._parse_next_data(html_content)
        if next_data_info.get('name'): org_info['name'] = next_data_info['name']
        if next_data_info.get('inn'): org_info['inn'] = next_data_info['inn']

        if org_info.get('name'): data.recipient = org_info['name']
        if org_info.get('inn'): data.recipientINN = org_info['inn']
        
        # Save link for router
        self.found_organizer_link = org_info.get('link')
        
        return data

    def parse_company_profile(self, html_content: str) -> Optional[str]:
        tree = html.fromstring(html_content)
        # Look for INN in table cells or text
        elements = tree.xpath("//*[contains(text(), 'ИНН')]")
        for el in elements:
            # Sibling cell
            next_el = el.getnext()
            if next_el is not None:
                text = self._clean_text(next_el.text_content())
                match = re.search(r'(\d{10,12})', text)
                if match: return match.group(1)
            # Parent text
            parent = el.getparent()
            if parent is not None:
                text = self._clean_text(parent.text_content())
                match = re.search(r'ИНН.*?(\d{10,12})', text)
                if match: return match.group(1)
        return None

    def _clean_text(self, text: str) -> str:
        if not text: return ""
        return " ".join(text.split())

    def _parse_organizer(self, tree) -> Dict[str, Any]:
        labels = ["Организатор", "Заказчик", "Покупатель"]
        result = {'name': '', 'inn': '', 'link': ''}
        
        for label in labels:
            elements = tree.xpath(f"//*[contains(text(), '{label}')]")
            for el in elements:
                # Traverse up to find container
                parent = el.getparent()
                steps = 0
                while parent is not None and steps < 3:
                    # Look for links
                    links = parent.xpath(".//a")
                    for link in links:
                        href = link.get('href')
                        if href and ('/firms/' in href or 'action=company' in href):
                            if not result['name']:
                                result['name'] = self._clean_text(link.text_content())
                                result['link'] = href
                    
                    if result['link']: break
                    parent = parent.getparent()
                    steps += 1
                if result['link']: break
            if result['link']: break
            
        return result

    def _parse_next_data(self, html_content: str) -> Dict[str, Any]:
        result = {}
        try:
            match = re.search(r'<script[^>]*id=["\']__NEXT_DATA__["\'][^>]*>(.*?)</script>', html_content, re.DOTALL)
            if match:
                json_data = json.loads(match.group(1))
                self._find_recursive(json_data, result)
        except Exception: pass
        return result

    def _find_recursive(self, data: Any, result: Dict[str, Any]):
        if result.get('inn') and result.get('name'): return
        if isinstance(data, dict):
            for k, v in data.items():
                k_lower = k.lower()
                if k_lower in ['inn', 'инн', 'taxpayerid', 'taxpayer_id']:
                    s_v = str(v).strip()
                    if re.match(r'^\d{10,12}$', s_v): result['inn'] = s_v
                if k_lower in ['short_title', 'full_title', 'org_name', 'company_name', 'name', 'organization', 'customer', 'organizer', 'title']:
                     if isinstance(v, str) and len(v) > 3:
                         if any(w in v for w in ['ООО', 'АО', 'ЗАО', 'ИП', 'ОАО', 'ПАО', 'КАО']) or k_lower in ['org_name', 'company_name', 'customer', 'organizer']:
                             if not result.get('name'): result['name'] = v
                if isinstance(v, (dict, list)): self._find_recursive(v, result)
        elif isinstance(data, list):
            for item in data: self._find_recursive(item, result)
