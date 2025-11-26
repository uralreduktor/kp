/**
 * Простой тест API парсера через fetch
 * Запуск: node tests/test-api-simple.js
 */

const testUrl = 'https://www.b2b-center.ru/market/priobretenie-reduktora-konveiera-ch-5a028-0-sztm-dlia-ao-mikhailovskii/tender-4242870/';
const apiUrl = `http://localhost/api/parse-tender-data.php?url=${encodeURIComponent(testUrl)}`;

// Для авторизации используйте переменные окружения или передайте credentials
const username = process.env.AUTH_USER || '';
const password = process.env.AUTH_PASSWORD || '';

console.log('=== Тест API парсера ===\n');
console.log('URL торгов:', testUrl);
console.log('API URL:', apiUrl);
console.log('\nОтправка запроса...\n');

const headers = {
  'Content-Type': 'application/json',
};

// Если указаны учетные данные, добавляем Basic Auth
if (username && password) {
  const auth = Buffer.from(`${username}:${password}`).toString('base64');
  headers['Authorization'] = `Basic ${auth}`;
  console.log('Используется Basic Auth для пользователя:', username);
} else {
  console.log('⚠ Авторизация не указана. Если сервер требует авторизацию, установите AUTH_USER и AUTH_PASSWORD');
}

fetch(apiUrl, { headers })
  .then(response => {
    console.log('HTTP статус:', response.status, response.statusText);
    return response.json();
  })
  .then(data => {
    console.log('\nОтвет API:');
    console.log(JSON.stringify(data, null, 2));
    
    console.log('\n=== Анализ ответа ===\n');
    
    if (data.success) {
      console.log('✓ Успешный ответ');
      
      if (data.data) {
        if (data.data.items && data.data.items.length > 0) {
          console.log(`✓ Найдено позиций: ${data.data.items.length}`);
          data.data.items.forEach((item, index) => {
            console.log(`\nПозиция ${index + 1}:`);
            console.log(`  Наименование: "${item.name}"`);
            console.log(`  Количество: ${item.quantity}`);
          });
        } else if (data.data.itemName) {
          console.log(`✓ Найдена позиция (старый формат):`);
          console.log(`  Наименование: "${data.data.itemName}"`);
          console.log(`  Количество: ${data.data.quantity}`);
        } else {
          console.log('⚠ Позиции не найдены в ответе');
        }
        
        if (data.data.tenderNumber) {
          console.log(`\n✓ Номер торгов: ${data.data.tenderNumber}`);
        }
      }
      
      if (data.platform) {
        console.log(`✓ Платформа: ${data.platform}`);
      }
    } else {
      console.log('✗ Ошибка:', data.error || 'Неизвестная ошибка');
    }
  })
  .catch(error => {
    console.error('\n✗ Ошибка запроса:', error.message);
    console.error('\nУбедитесь, что:');
    console.error('1. Веб-сервер запущен на http://localhost');
    console.error('2. Файл api/parse-tender-data.php доступен');
    console.error('3. PHP настроен правильно');
  });

