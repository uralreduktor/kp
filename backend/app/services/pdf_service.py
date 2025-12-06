import json
import os
import base64
import re
from datetime import datetime
from typing import Dict, Any, List, Optional
from pathlib import Path

from fastapi import HTTPException
from jinja2 import Environment, FileSystemLoader
from playwright.async_api import async_playwright


class PdfService:
    def __init__(self):
        # Пути к ресурсам
        self.base_dir = Path("/var/www/kp")
        self.org_file = self.base_dir / "js" / "organizations.js"
        
        # Jinja2 шаблоны (будем использовать inline для простоты)
        self.env = Environment(autoescape=True)

    def _parse_organization_data(self, org_id: str) -> Dict[str, str]:
        """Парсит данные организации из organizations.js"""
        try:
            with open(self.org_file, 'r', encoding='utf-8') as f:
                content = f.read()
        except FileNotFoundError:
            return self._get_default_org_data()

        org_data = {
            'name': '',
            'address': '',
            'phone': '',
            'email': '',
            'INN_vektor': '',
            'logo': '',
            'stamp': '',
            'signature': '',
            'code': ''
        }

        # Ищем блок организации
        pattern = rf"'{org_id}':\s*\{{([^}}]+(?:\{{[^}}]*\}}[^}}]*)*)\}}"
        match = re.search(pattern, content, re.DOTALL)
        
        if match:
            org_block = match.group(1)
            
            # Извлекаем поля
            fields = ['name', 'address', 'phone', 'email', 'INN_vektor', 'logo', 'stamp', 'signature', 'code']
            for field in fields:
                field_pattern = rf"(?:'{field}'|{field}):\s*'([^']+)'"
                field_match = re.search(field_pattern, org_block)
                if field_match:
                    org_data[field] = field_match.group(1)

        return org_data

    def _parse_banking_details(self, org_id: str) -> Dict[str, str]:
        """Парсит банковские реквизиты из organizations.js"""
        try:
            with open(self.org_file, 'r', encoding='utf-8') as f:
                content = f.read()
        except FileNotFoundError:
            return {}

        banking_details = {
            'bankName': '',
            'bankAddress': '',
            'account': '',
            'bik': '',
            'correspondentAccount': '',
            'beneficiary': ''
        }

        pattern = rf"'{org_id}':\s*\{{([^}}]+(?:\{{[^}}]*\}}[^}}]*)*)\}}"
        match = re.search(pattern, content, re.DOTALL)
        
        if match:
            org_block = match.group(1)
            
            fields = ['bankName', 'bankAddress', 'account', 'bik', 'correspondentAccount', 'beneficiary']
            for field in fields:
                field_pattern = rf"(?:'{field}'|{field}):\s*'([^']+)'"
                field_match = re.search(field_pattern, org_block)
                if field_match:
                    banking_details[field] = field_match.group(1)

        return banking_details

    def _get_default_org_data(self) -> Dict[str, str]:
        """Дефолтные данные организации"""
        return {
            'name': 'ООО "Вектор"',
            'address': '620143, Свердловская обл., г. Екатеринбург, ул. Машиностроителей, 19, оф.687',
            'phone': '+7 (343) 236-44-44',
            'email': 'sales@uralreduktor.ru',
            'INN_vektor': '6679016273',
            'logo': 'LOGO_URALREDUKTOR.png',
            'stamp': 'vektor_2.png',
            'signature': 'sign.png',
            'code': 'VEC'
        }

    def _image_to_base64(self, filename: str) -> str:
        """Конвертирует изображение в base64 data URI"""
        if not filename:
            return ''
        
        file_path = self.base_dir / filename
        
        if not file_path.exists():
            return ''
        
        extension = file_path.suffix.lower().lstrip('.')
        
        mime_types = {
            'svg': 'image/svg+xml',
            'png': 'image/png',
            'jpg': 'image/jpeg',
            'jpeg': 'image/jpeg',
            'gif': 'image/gif',
            'webp': 'image/webp'
        }
        
        mime_type = mime_types.get(extension, 'image/png')
        
        try:
            with open(file_path, 'rb') as f:
                image_data = f.read()
            
            # Для SVG упрощаем (убираем CSS переменные)
            if extension == 'svg':
                svg_content = image_data.decode('utf-8')
                svg_content = self._simplify_svg_for_pdf(svg_content)
                image_data = svg_content.encode('utf-8')
            
            return f"data:{mime_type};base64,{base64.b64encode(image_data).decode('utf-8')}"
        except Exception:
            return ''

    def _simplify_svg_for_pdf(self, svg_content: str) -> str:
        """Упрощает SVG для PDF (убирает CSS переменные)"""
        # Убираем класс logo-shp
        svg_content = re.sub(r'<svg([^>]*)\s+class=["\']logo-shp["\']([^>]*)>', r'<svg\1\2>', svg_content, flags=re.I)
        
        # Удаляем media queries
        svg_content = re.sub(r'@media\s+[^{]*\{[^}]*\}', '', svg_content, flags=re.DOTALL)
        
        # Заменяем CSS переменные
        replacements = [
            (r'fill:\s*var\(--logo-primary[^)]*\)', 'fill: #124981'),
            (r'fill:\s*var\(--logo-secondary[^)]*\)', 'fill: #FEFEFE'),
            (r'fill:\s*var\(--logo-gradient[^)]*\)', 'fill: url(#gradient_light)'),
            (r'var\(--logo-primary[^)]*\)', '#124981'),
            (r'var\(--logo-secondary[^)]*\)', '#FEFEFE'),
            (r'var\(--logo-gradient[^)]*\)', 'url(#gradient_light)'),
        ]
        
        for pattern, replacement in replacements:
            svg_content = re.sub(pattern, replacement, svg_content, flags=re.I)
        
        # Добавляем fill атрибуты к path элементам с классами
        svg_content = re.sub(
            r'(<path[^>]*\s+class=["\'][^"\']*logo-primary[^"\']*["\'][^>]*)>',
            r'\1 fill="#124981">',
            svg_content,
            flags=re.I
        )
        svg_content = re.sub(
            r'(<path[^>]*\s+class=["\'][^"\']*logo-secondary[^"\']*["\'][^>]*)>',
            r'\1 fill="#FEFEFE">',
            svg_content,
            flags=re.I
        )
        
        # Удаляем определения CSS переменных и пустые style теги
        svg_content = re.sub(r'--logo-[^:]*:[^;]*;', '', svg_content)
        svg_content = re.sub(r'<style[^>]*>\s*<!--[^>]*-->\s*</style>', '', svg_content, flags=re.I)
        svg_content = re.sub(r'<style[^>]*>\s*</style>', '', svg_content, flags=re.I)
        
        return svg_content

    def _format_number(self, num: float, decimals: int = 2) -> str:
        """Форматирует число с разделителями тысяч и запятой для дробной части"""
        if decimals == 0:
            # Целое число
            formatted = f"{int(num):,}".replace(',', ' ')
        else:
            # С дробной частью
            formatted = f"{num:,.{decimals}f}".replace(',', ' ').replace('.', ',')
        return formatted

    def _format_date(self, date_str: str) -> str:
        """Форматирует дату в русский формат"""
        if not date_str:
            return 'N/A'
        
        try:
            date = datetime.fromisoformat(date_str.replace('/', '-'))
            months = {
                1: 'января', 2: 'февраля', 3: 'марта', 4: 'апреля',
                5: 'мая', 6: 'июня', 7: 'июля', 8: 'августа',
                9: 'сентября', 10: 'октября', 11: 'ноября', 12: 'декабря'
            }
            return f"{date.day} {months[date.month]} {date.year}"
        except Exception:
            return date_str

    def _format_valid_until_date(self, date_str: str) -> str:
        """Форматирует дату действительности"""
        if not date_str:
            return 'N/A'
        
        # Если это уже текст, возвращаем как есть
        if '-' not in date_str and not re.match(r'^\d{4}-\d{2}-\d{2}', date_str):
            return date_str
        
        try:
            date = datetime.fromisoformat(date_str.replace('/', '-'))
            months = {
                1: 'января', 2: 'февраля', 3: 'марта', 4: 'апреля',
                5: 'мая', 6: 'июня', 7: 'июля', 8: 'августа',
                9: 'сентября', 10: 'октября', 11: 'ноября', 12: 'декабря'
            }
            return f"{date.day} {months[date.month]} {date.year} г."
        except Exception:
            return date_str

    def _normalize_invoice(self, data: Dict[str, Any]) -> Dict[str, Any]:
        """Нормализует данные инвойса для шаблона"""
        
        # Обработка commercialTerms
        if 'commercialTerms' in data and isinstance(data['commercialTerms'], dict):
            terms = data['commercialTerms']
            # Берём значение из вложенного объекта, только если оно не пустое
            if terms.get('incoterm'):
                data['incoterm'] = terms['incoterm']
            if terms.get('deliveryPlace'):
                data['deliveryPlace'] = terms['deliveryPlace']
            if terms.get('deliveryTime'):
                data['deliveryTime'] = terms['deliveryTime']
            if terms.get('paymentTerms'):
                data['paymentTerms'] = terms['paymentTerms']
            if terms.get('warranty'):
                data['warranty'] = terms['warranty']

        # Обработка contact
        if 'contact' in data and isinstance(data['contact'], dict):
            contact = data['contact']
            if contact.get('person'):
                data['contactPerson'] = contact['person']
            if contact.get('position'):
                data['position'] = contact['position']

        org_id = data.get('organizationId', 'vector')
        org_data = self._parse_organization_data(org_id)
        banking_details = self._parse_banking_details(org_id)

        # Конвертируем изображения
        logo_path = self._image_to_base64(org_data['logo'])
        stamp_path = self._image_to_base64(org_data['stamp'])
        signature_path = self._image_to_base64(org_data['signature'])

        # Расчет итогов и форматирование items
        items_raw = data.get('items', [])
        items = []
        total = 0
        
        for item in items_raw:
            quantity = item.get('quantity', 0)
            price = item.get('price', 0)
            unit = (item.get('unit') or '').lower()
            amount = quantity * price
            total += amount
            
            # Форматируем количество: целое для "шт", с дробной частью для остальных
            if unit in ['шт', 'шт.', 'штук', 'штука']:
                quantity_formatted = self._format_number(quantity, decimals=0)
            else:
                quantity_formatted = self._format_number(quantity, decimals=2)
            
            items.append({
                **item,  # Копируем все поля
                'quantity_formatted': quantity_formatted,
                'price_formatted': self._format_number(price, decimals=2),
                'amount_formatted': self._format_number(amount, decimals=2),
            })
        
        vat = total / 6  # НДС 20%
        total_formatted = self._format_number(total, decimals=2)
        vat_formatted = self._format_number(vat, decimals=2)

        # Определяем label для срока
        incoterm = (data.get('incoterm') or '').strip()
        delivery_label = 'Срок поставки'
        if incoterm in ['САМОВЫВОЗ', 'ФРАНКО-СКЛАД ПРОДАВЦА', 'ДО ТРАНСПОРТА ПОКУПАТЕЛЯ']:
            delivery_label = 'Срок готовности'

        # Настройки подписи в зависимости от количества позиций
        item_count = len(items)
        signature_margin_top = 15
        signature_text_offset = 10
        signature_text_top_offset = 0
        
        if item_count == 3:
            signature_margin_top = 0
        elif item_count >= 4:
            signature_margin_top = -28
            signature_text_offset = 35
            signature_text_top_offset = 30

        result = {
            'number': data.get('number', ''),
            'date': self._format_date(data.get('date', '')),
            'valid_until': self._format_valid_until_date(data.get('validUntil', '')),
            'recipient': data.get('recipient', ''),
            'recipient_inn': data.get('recipientINN', ''),
            'recipient_address': data.get('recipientAddress', ''),
            'currency': data.get('currency', 'RUB'),
            'items': items,
            'total': total_formatted,
            'vat': vat_formatted,
            'incoterm': data.get('incoterm', ''),
            'delivery_place': data.get('deliveryPlace', ''),
            'delivery_time': data.get('deliveryTime', ''),
            'payment_terms': data.get('paymentTerms', ''),
            'warranty': data.get('warranty', ''),
            'remarks': data.get('remarks', ''),
            'contact_person': data.get('contactPerson', ''),
            'position': data.get('position', ''),
            'delivery_label': delivery_label,
            'signature_margin_top': signature_margin_top,
            'signature_text_offset': signature_text_offset,
            'signature_text_top_offset': signature_text_top_offset,
            'org': org_data,
            'banking': banking_details,
            'logo_path': logo_path,
            'stamp_path': stamp_path,
            'signature_path': signature_path,
        }
        
        return result

    def _get_playwright_template(self) -> str:
        """Возвращает HTML шаблон для Playwright (полная копия PHP версии)"""
        return '''<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Коммерческое Предложение</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt; 
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }
        .container { 
            max-width: 210mm; 
            margin: 0 auto; 
            padding: 10mm 10mm 2px 10mm;
        }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            margin-bottom: 20px; 
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 15px;
        }
        .logo-section { 
            flex-shrink: 0;
            display: flex;
            align-items: center;
        }
        .logo { 
            max-height: 80px; 
            width: auto;
            object-fit: contain;
        }
        .company-info { 
            text-align: right; 
            font-size: 9pt; 
            color: #1F2937;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: center;
        }
        .company-info a {
            color: inherit;
            text-decoration: none;
        }
        .company-name { 
            font-weight: bold; 
            font-size: 11pt; 
            margin-bottom: 5px;
        }
        .title {
            text-align: center;
            font-size: 18pt;
            font-weight: bold;
            margin: 10px 0;
            color: #1e40af;
        }
        .document-info {
            display: flex;
            text-align: left;
            margin-bottom: 5px;
        }
        .bill-to {
            flex: 3;
        }
        .invoice-details {
            flex: 1;
            text-align: left;
            margin-left: 140px;
        }
        .info-label {
            font-weight: bold;
            font-size: 9pt;
            color: #374151;
            margin-top: 10px;
        }
        .info-value {
            font-size: 10pt;
            margin-top: 3px;
        }
        .info-row {
            margin-top: 10px;
        }
        .info-row .info-label {
            margin-top: 0;
            margin-right: 5px;
        }
        .info-row .info-value {
            margin-top: 0;
        }
        .intro-text {
            font-size: 10pt;
            color: #1F2937;
            margin: 10px 0;
            line-height: 1.6;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: none;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
            font-size: 9pt;
        }
        td {
            font-size: 9pt;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .total-row {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .vat-row {
            background-color: #f9fafb;
            font-size: 9pt;
        }
        .terms-section {
            margin-top: 20px;
            font-size: 9pt;
            line-height: 1.6;
            display: flex;
            justify-content: space-between;
            gap: 40px;
        }
        .terms-left, .terms-right {
            flex: 1;
        }
        .terms-section p {
            margin: 5px 0;
        }
        .remarks-section {
            margin-top: 20px;
            font-size: 9pt;
            line-height: 1.1;
            width: 100%;
        }
        .remarks-section p {
            margin: 0;
        }
        .banking-section {
            margin-top: 15px;
            padding: 10px;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            overflow: visible;
            page-break-inside: avoid;
            break-inside: avoid-page;
            page-break-before: avoid;
            page-break-after: auto;
            position: relative;
            z-index: 1;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        .banking-section h3 {
            font-size: 11pt;
            margin-bottom: 10px;
            color: #1e40af;
        }
        .banking-details-list {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.5;
        }
        .banking-detail-item {
            margin-bottom: 8px;
        }
        .banking-detail-label {
            font-weight: bold;
            color: #1F2937;
        }
        .banking-detail-value {
            color: #000;
        }
        .banking-detail-row {
            display: flex;
            gap: 30px;
            margin-bottom: 8px;
        }
        .banking-detail-col {
            flex: 1;
        }
        .signature {
            margin-top: {{ signature_margin_top }}px;
            padding-top: 20px;
            display: flex;
            align-items: flex-start;
            justify-content: flex-start;
            position: relative;
            gap: 50px;
            margin-left: 250px;
            page-break-inside: avoid !important;
            break-inside: avoid-page !important;
            page-break-before: avoid !important;
            page-break-after: avoid !important;
            z-index: 2;
            margin-bottom: 5px;
        }
        @page {
            margin-bottom: 5px;
        }
        .signature-center {
            flex: 0 0 auto;
            display: flex;
            align-items: flex-start;
            margin-right: 5px;
        }
        .signature-right {
            flex: 0 0 auto;
        }
        .signature-images {
            position: relative;
            display: inline-block;
            margin-top: -10px;
        }
        .signature-stamp {
            max-width: 150px;
            height: auto;
            position: relative;
            z-index: 1;
        }
        .signature-sign {
            max-width: 120px;
            height: auto;
            position: absolute;
            top: 30px;
            left: 20px;
            z-index: 2;
        }
        .signature-text {
            text-align: center;
            position: relative;
            z-index: 3;
            text-align: left;
        }
        @media print {
            body { padding: 0; }
            .container { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo-section">
                {% if logo_path %}<img src="{{ logo_path }}" alt="{{ org.name }}" class="logo" />{% endif %}
            </div>
            <div class="company-info">
                <div class="company-name">{{ org.name }}</div>
                {% if org.INN_vektor %}<div>ИНН: {{ org.INN_vektor }}</div>{% endif %}
                <div>{{ org.address }}</div>
                <div><a href="tel:{{ org.phone|replace(' ', '')|replace('(', '')|replace(')', '')|replace('-', '') }}">{{ org.phone }}</a></div>
                <div><a href="mailto:{{ org.email }}">{{ org.email }}</a></div>
            </div>
        </div>
        
        <div class="title">Коммерческое Предложение</div>
        
        <div class="document-info">
            <div class="bill-to">
                <div class="info-label">Плательщик:</div>
                <div class="info-value">{{ recipient }}</div>
                <div style="font-size: 10pt; color: #374151;">{{ recipient_address }}</div>
                {% if recipient_inn %}<div style="font-size: 10pt; color: #374151; margin-top: 3px;">ИНН: {{ recipient_inn }}</div>{% endif %}
            </div>
            <div class="invoice-details">
                <div class="info-row"><span class="info-label">Номер:</span> <span class="info-value">{{ number }}</span></div>
                <div class="info-row"><span class="info-label">Дата:</span> <span class="info-value">{{ date }}</span></div>
                <div class="info-row"><span class="info-label">Валюта:</span> <span class="info-value">{{ currency }}</span></div>
            </div>
        </div>
        
        <div class="intro-text">В ответ на Ваш запрос, на поставку продукции, готовы предложить следующее:</div>
        
        <table>
            <thead>
                <tr>
                    <th style="width: 5%;">No.</th>
                    <th style="min-width: 200px;">Описание Товара</th>
                    <th style="width: 15%;" class="text-center">Страна<br>Производства</th>
                    <th style="width: 8%;" class="text-center">Кол-во</th>
                    <th style="width: 7%;" class="text-center">Ед.</th>
                    <th style="width: 11%;" class="text-center">Цена за<br>ед.</th>
                    <th style="width: 12%;" class="text-right">Сумма</th>
                </tr>
            </thead>
            <tbody>
            {% for item in items %}
                <tr>
                    <td class="text-center">{{ loop.index }}</td>
                    <td>
                        <div style="font-weight: bold;">{{ item.get('description') or item.get('type') or '' }}</div>
                        {% if item.get('model') %}<div style="font-size: 0.9em; color: #555;">{{ item.get('model') }}</div>{% endif %}
                        {% if item.get('name') %}<div style="font-size: 0.9em; color: #333;">{{ item.get('name') }}</div>{% endif %}
                    </td>
                    <td class="text-center">{{ item.get('countryOfOrigin') or item.get('country') or '' }}</td>
                    <td class="text-center">{{ item.get('quantity_formatted') }}</td>
                    <td class="text-center">{{ item.get('unit') or '' }}</td>
                    <td class="text-right">{{ item.get('price_formatted') }}</td>
                    <td class="text-right">{{ item.get('amount_formatted') }}</td>
                </tr>
            {% endfor %}
                <tr class="total-row">
                    <td colspan="6" class="text-right"><strong>Сумма в {{ currency }}:</strong></td>
                    <td class="text-right"><strong>{{ total }}</strong></td>
                </tr>
                <tr class="vat-row">
                    <td colspan="6" class="text-right">В том числе НДС - 20%:</td>
                    <td class="text-right">{{ vat }}</td>
                </tr>
            </tbody>
        </table>
        
        <div class="terms-section">
            <div class="terms-left">
                <p><strong>Условия поставки:</strong> {{ incoterm }} {{ delivery_place }}</p>
                <p><strong>Условия оплаты:</strong> {{ payment_terms }}</p>
                <p><strong>{{ delivery_label }}:</strong> {{ delivery_time }}</p>
            </div>
            <div class="terms-right">
                <p><strong>Гарантия:</strong> {{ warranty }}</p>
                <p><strong>Коммерческое Предложение действительно до:<br></strong> {{ valid_until }}</p>
            </div>
        </div>
        
        {% if remarks %}
        <div class="remarks-section">
            <p><strong>Примечания:</strong> {{ remarks|replace('\\n', '<br>')|safe }}</p>
        </div>
        {% endif %}
        
        {% if banking.bankName or banking.account %}
        <div class="banking-section">
            <h3>Банковские реквизиты</h3>
            <div class="banking-details-list">
                {% if banking.bankName %}
                <div class="banking-detail-item">
                    <span class="banking-detail-label">Банк:</span>
                    <span class="banking-detail-value">{{ banking.bankName }}</span>
                </div>
                {% endif %}
                {% if banking.bankAddress %}
                <div class="banking-detail-item">
                    <span class="banking-detail-label">Адрес банка:</span>
                    <span class="banking-detail-value">{{ banking.bankAddress }}</span>
                </div>
                {% endif %}
                {% if banking.bik or banking.correspondentAccount %}
                <div class="banking-detail-item banking-detail-row">
                    {% if banking.bik %}
                    <div class="banking-detail-col">
                        <span class="banking-detail-label">БИК:</span>
                        <span class="banking-detail-value">{{ banking.bik }}</span>
                    </div>
                    {% endif %}
                    {% if banking.correspondentAccount %}
                    <div class="banking-detail-col">
                        <span class="banking-detail-label">Корр. счет:</span>
                        <span class="banking-detail-value">{{ banking.correspondentAccount }}</span>
                    </div>
                    {% endif %}
                </div>
                {% endif %}
                {% if banking.beneficiary or banking.account %}
                <div class="banking-detail-item banking-detail-row">
                    {% if banking.beneficiary %}
                    <div class="banking-detail-col">
                        <span class="banking-detail-label">Бенефициар:</span>
                        <span class="banking-detail-value">{{ banking.beneficiary }}</span>
                    </div>
                    {% endif %}
                    {% if banking.account %}
                    <div class="banking-detail-col">
                        <span class="banking-detail-label">Счет:</span>
                        <span class="banking-detail-value">{{ banking.account }}</span>
                    </div>
                    {% endif %}
                </div>
                {% endif %}
            </div>
        </div>
        {% endif %}
        
        <div class="signature" style="margin-top: {{ signature_margin_top }}px;">
            <div class="signature-center">
                <div class="signature-text" style="margin-left: {{ signature_text_offset }}px; margin-top: {{ signature_text_top_offset }}px;">
                    <div style="margin-bottom: 5px; font-weight: bold;">{{ contact_person }}</div>
                    <div style="font-size: 9pt; color: #374151;">{{ position }}</div>
                </div>
            </div>
            <div class="signature-right">
                {% if stamp_path or signature_path %}
                <div class="signature-images">
                    {% if stamp_path %}<img src="{{ stamp_path }}" alt="Company Stamp" class="signature-stamp" />{% endif %}
                    {% if signature_path %}<img src="{{ signature_path }}" alt="Authorized Signature" class="signature-sign" />{% endif %}
                </div>
                {% endif %}
            </div>
        </div>
    </div>
</body>
</html>'''

    async def generate_pdf(self, invoice_data: Dict[str, Any]) -> bytes:
        """Генерирует PDF из данных инвойса"""
        try:
            # Нормализуем данные
            ctx = self._normalize_invoice(invoice_data)
            
            # Рендерим HTML через Jinja2
            template = self.env.from_string(self._get_playwright_template())
            html_content = template.render(**ctx)

        except Exception as e:
             raise HTTPException(status_code=500, detail=f"Failed to render HTML template: {str(e)}")

        # Генерация PDF через Playwright
        try:
            async with async_playwright() as p:
                browser = await p.chromium.launch(
                    headless=True,
                    args=['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage', '--disable-gpu']
                )
                page = await browser.new_page()
                await page.set_content(html_content, wait_until="networkidle")
                
                pdf_bytes = await page.pdf(
                    format="A4",
                    print_background=True,
                    margin={
                        "top": "10mm",
                        "right": "10mm",
                        "bottom": "10mm",
                        "left": "10mm"
                    },
                    scale=1.0,
                    display_header_footer=False
                )
                
                await browser.close()
                return pdf_bytes
                
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Failed to generate PDF with Playwright: {str(e)}")
