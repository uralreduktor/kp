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
            
        # Check if this is a positions page
        if '/positions/' in url or '/positions' in url:
            # Parse items from positions page
            data.items = self._parse_positions_page(tree)
        else:
            # Try to parse items from main tender page (fallback)
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

    def _parse_positions_page(self, tree) -> list[TenderItem]:
        """
        Parse items from B2B-Center positions page.
        Expected table format:
        №	Позиция	Максимальная цена без НДС, ₽	Количество	Единица измерения	...
        """
        items = []
        
        # Find all table rows with position data
        # Try multiple selectors for robustness
        rows = (
            tree.xpath("//table//tr[td[position()>=4]]") or  # Rows with at least 4 cells
            tree.xpath("//tr[contains(@class, 'position-row')]") or
            tree.xpath("//tbody//tr[td]")
        )
        
        logger.info(f"Found {len(rows)} potential position rows")
        
        for row in rows:
            cells = row.xpath(".//td")
            if len(cells) < 2:  # Need at least №, Позиция
                continue
                
            cell_texts = [self._clean_text(c.text_content()) for c in cells]
            
            # Skip header rows
            first_cell_lower = cell_texts[0].lower()
            if any(header in first_cell_lower for header in ['№', 'позиция', 'номер']):
                if 'количество' in ' '.join(cell_texts).lower():
                    logger.debug(f"Skipping header row: {cell_texts[0]}")
                    continue
            
            # Parse position data
            # Format: № | Позиция | Макс.цена | Количество | Ед.изм | ...
            try:
                # Position number (first cell) - validate it's a number
                pos_num = cell_texts[0].strip()
                if not re.match(r'^\d+$', pos_num):
                    continue
                
                # Position name (second cell)
                name = cell_texts[1].strip() if len(cell_texts) > 1 else ''
                if not name or len(name) < 3:
                    continue
                
                # Quantity - try to find it in cells 3-5
                # Usually: cell[3] is quantity
                qty = 1.0  # Default
                for i in range(3, min(len(cell_texts), 6)):
                    qty_str = re.sub(r'[^\d.,]', '', cell_texts[i]).replace(',', '.')
                    if qty_str:
                        try:
                            parsed_qty = float(qty_str)
                            if parsed_qty > 0:
                                qty = parsed_qty
                                break
                        except ValueError:
                            continue
                
                logger.info(f"Parsed item #{pos_num}: {name[:50]}... qty={qty}")
                items.append(TenderItem(name=name, quantity=qty))
                
            except Exception as e:
                logger.warning(f"Failed to parse row: {e}")
                continue
        
        logger.info(f"Successfully parsed {len(items)} items from positions page")
        return items

    def parse_positions_only(self, html_content: str) -> list[TenderItem]:
        """
        Parse only items from positions page without affecting other data.
        """
        tree = html.fromstring(html_content)
        return self._parse_positions_page(tree)

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
