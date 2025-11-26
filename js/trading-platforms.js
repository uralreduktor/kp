/**
 * Справочник торговых площадок
 * Используется в torgikp.html для выбора торговой площадки
 */

const tradingPlatforms = [
  {
    id: 'b2b-center',
    name: 'B2B-Center',
    url: 'https://www.b2b-center.ru/',
    urlPattern: /b2b-center\.ru/i,
    description: 'Электронная торговая площадка B2B-Center'
  },
  {
    id: 'tender-pro',
    name: 'Tender.Pro',
    url: 'https://www.tender.pro/',
    urlPattern: /tender\.pro/i,
    description: 'Электронная торговая площадка Tender.Pro'
  },
  {
    id: 'sberbank-ast',
    name: 'Сбербанк-АСТ',
    url: 'https://www.sberbank-ast.ru/',
    urlPattern: /sberbank-ast\.ru/i,
    description: 'Электронная торговая площадка Сбербанк-АСТ'
  },
  {
    id: 'rts-tender',
    name: 'РТС-Тендер',
    url: 'https://www.rts-tender.ru/',
    urlPattern: /rts-tender\.ru/i,
    description: 'Электронная торговая площадка РТС-Тендер'
  },
  {
    id: 'eetp',
    name: 'ЕЭТП',
    url: 'https://www.roseltorg.ru/',
    urlPattern: /(roseltorg|eetp)\.ru/i,
    description: 'Единая электронная торговая площадка'
  },
  {
    id: 'zakazrf',
    name: 'Заказ РФ',
    url: 'https://www.zakazrf.ru/',
    urlPattern: /zakazrf\.ru/i,
    description: 'Электронная торговая площадка Заказ РФ'
  },
  {
    id: 'fabrikant',
    name: 'Фабрикант',
    url: 'https://www.fabrikant.ru/',
    urlPattern: /fabrikant\.ru/i,
    description: 'Электронная торговая площадка Фабрикант'
  },
  {
    id: 'other',
    name: 'Другая площадка',
    url: '',
    urlPattern: null,
    description: 'Другая торговая площадка'
  }
];

/**
 * Определяет торговую площадку по URL
 * @param {string} url - URL ссылки на торги
 * @returns {object|null} Объект торговой площадки или null
 */
function detectTradingPlatform(url) {
  if (!url) return null;
  
  for (const platform of tradingPlatforms) {
    if (platform.urlPattern && platform.urlPattern.test(url)) {
      return platform;
    }
  }
  
  return tradingPlatforms.find(p => p.id === 'other') || null;
}

