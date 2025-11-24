/**
 * Данные организации ООО "Вектор"
 */

const organizations = {
  'vector': {
    id: 'vector',
    name: 'ООО "Вектор"',
    shortName: 'ВЕКТОР',
    code: 'VEC',
    logo: 'LOGO_URALREDUKTOR.png', // Нужно будет добавить логотип
    stamp: 'vektor_2.png', // Нужно будет добавить печать
    signature: 'sign.png', // Нужно будет добавить подпись
    INN_vektor: '6679016273',
    address: '620143, Свердловская обл., г. Екатеринбург, ул. Машиностроителей, 19, оф.687', // Требуется уточнение
    phone: '+7 (343) 236-44-44', // Требуется уточнение
    email: 'sales@uralreduktor.ru', // Требуется уточнение
    bankName: 'ООО «Банк Точка»',
    bankAddress: '109044, Г. МОСКВА, ВН.ТЕР.Г. МУНИЦИПАЛЬНЫЙ ОКРУГ ЮЖНОПОРТОВЫЙ, ПЕР. 3-Й КРУТИЦКИЙ, Д. 11, ПОМЕЩ. 7Н',
    account: '40702810602500012474',
    bik: '044525104',
    correspondentAccount: '30101810745374525104',
    beneficiary: 'ООО "Вектор"'
  }
};

// Функция получения организации по умолчанию
const getDefaultOrganization = () => {
  return organizations.vector;
};

// Функция сохранения выбранной организации
const saveSelectedOrganization = (orgId) => {
  localStorage.setItem('selectedOrganization', orgId);
};

