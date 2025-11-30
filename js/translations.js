/**
 * Переводы для приложения Коммерческое Предложение
 * Только русский язык для ООО "Вектор"
 */

const translations = {
  ru: {
    // Основные
    title: 'Коммерческое Предложение',
    subtitle: 'Коммерческое Предложение',
    
    // Действия
    save: 'Сохранить',
    edit: 'Редактировать',
    downloadPDF: 'На печать',
    backToRegistry: 'Назад к реестру',
    saving: 'Сохранение...',
    saved: 'Сохранено!',
    printView: 'Печатная форма',
    addRow: 'Добавить строку',
    remove: 'Удалить',
    actions: 'Действия',
    
    // Поля документа
    proposalNumber: 'Номер',
    date: 'Дата',
    validUntil: 'Действителен до',
    currency: 'Валюта',
    recipient: 'Плательщик',
    billTo: 'Счет на имя',
    inn: 'ИНН',
    
    // Таблица товаров
    introText: 'В ответ на Ваш запрос, на поставку продукции, готовы предложить следующее:',
    mainTabLabel: 'Коммерческое предложение',
    technicalTabLabel: 'Техническое приложение',
    technicalAnnexTitle: 'Технические характеристики',
    technicalIntro: 'Секция содержит дополнительные технические сведения по позициям предложения.',
    technicalSummaryLabel: 'Общий технический комментарий',
    technicalSpecs: 'Характеристики / описание',
    technicalBindingNote: 'Техническое приложение является неотъемлемой частью основного коммерческого предложения.',
    number: '№',
    type: 'Описание товара',
    hsCode: 'Код ТН ВЭД',
    countryOfOrigin: 'Страна производства',
    name: 'Модель',
    unit: 'Ед.',
    quantity: 'Кол-во',
    pricePerUnit: 'Цена за ед.',
    total: 'Сумма',
    leadTime: 'Срок поставки',
    totalLabel: 'ИТОГО',
    
    // Коммерческие условия
    commercialTerms: 'Коммерческие условия',
    deliveryTerms: 'Условия поставки',
    incoterm: 'Базис поставки',
    deliveryPlace: 'Место доставки',
    deliveryTime: 'Срок поставки',
    paymentTerms: 'Условия оплаты',
    warranty: 'Гарантия',
    validity: 'Действительность',
    remarks: 'Примечания',
    
    // Подпись
    signature: 'Подпись',
    contactPerson: 'Ответственное лицо',
    position: 'Должность',
    
    // Реквизиты
    address: 'Юридический адрес',
    bank: 'Банк',
    bankCode: 'Код банка',
    branchCode: 'Код филиала',
    bankAddress: 'Адрес банка',
    bankingDetails: 'Банковские реквизиты',
    
    // Реестр
    registry: 'Реестр коммерческих предложений',
    createNew: 'Создать новый',
    invoicesList: 'Список Коммерческих Предложений',
    noInvoices: 'Нет сохраненных Коммерческих Предложений',
    loading: 'Загрузка...',
    open: 'Открыть',
    delete: 'Удалить',
    confirmDelete: 'Вы уверены, что хотите удалить это Коммерческое Предложение?',
    savedAt: 'Сохранён',
    
    // Сообщения
    saveSuccess: 'Коммерческое Предложение успешно сохранёно!',
    saveError: 'Ошибка при сохранении Коммерческого Предложения',
    deleteSuccess: 'Коммерческое Предложение успешно удалёно!',
    deleteError: 'Ошибка при удалении Коммерческого Предложения',
    loadError: 'Ошибка при загрузке данных',
    
    // Фильтры и поиск
    search: 'Поиск',
    filter: 'Фильтр',
    clearFilters: 'Очистить фильтры',
    sortBy: 'Сортировать по',
    date: 'Дата',
    number: 'Номер',
    recipient: 'Плательщик',
    total: 'Сумма',
    currency: 'Валюта',
    from: 'От',
    to: 'До',
    minAmount: 'Мин. сумма',
    maxAmount: 'Макс. сумма',
    itemsPerPage: 'Записей на странице',
    page: 'Страница',
    of: 'из',
    noResults: 'Нет результатов',
    showFilters: 'Показать фильтры',
    hideFilters: 'Скрыть фильтры'
  }
};

// Экспорт для использования в модулях
if (typeof module !== 'undefined' && module.exports) {
  module.exports = translations;
}

