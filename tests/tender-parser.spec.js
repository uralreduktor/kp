const { test, expect } = require('@playwright/test');

/**
 * Тест парсера данных торгов B2B-Center
 * 
 * ПРИМЕЧАНИЕ: Для работы тестов требуется авторизация на веб-сервере.
 * Убедитесь, что вы авторизованы в браузере перед запуском тестов.
 */

test.describe('Парсер данных торгов B2B-Center', () => {
  const TENDER_URL = 'https://www.b2b-center.ru/market/priobretenie-reduktora-konveiera-ch-5a028-0-sztm-dlia-ao-mikhailovskii/tender-4242870/';
  const EXPECTED_ITEM_NAME = 'Редуктор 5А028-0 СЗТМ';
  const EXPECTED_QUANTITY = 1;
  const EXPECTED_TENDER_NUMBER = '4242870';
  
  // Используем контекст с сохранением состояния авторизации
  test.use({
    // Если требуется авторизация, можно использовать storageState
    // storageState: 'tests/.auth/user.json',
  });

  test('должен заполнить данные из ссылки на торги B2B-Center', async ({ page }) => {
    // Открываем страницу torgikp.html
    await page.goto('/torgikp.html');
    
    // Ждем загрузки страницы
    await page.waitForLoadState('networkidle');
    
    // Проверяем, что страница загрузилась
    await expect(page.locator('h1')).toContainText('Коммерческое Предложение');
    
    // Находим поле "Ссылка на торги" и вводим URL
    const tenderLinkInput = page.locator('input[name="tenderLink"], input[placeholder*="https://"]').first();
    await tenderLinkInput.fill(TENDER_URL);
    
    // Находим кнопку "Загрузить данные из ссылки" и нажимаем
    const loadButton = page.locator('button:has-text("Загрузить данные из ссылки")');
    await expect(loadButton).toBeVisible();
    await loadButton.click();
    
    // Ждем завершения загрузки (кнопка должна показать "Загрузка..." и вернуться обратно)
    await page.waitForTimeout(3000); // Даем время на парсинг
    
    // Проверяем, что данные заполнились:
    
    // 1. Номер торгов
    const tenderNumberInput = page.locator('input[name="tenderNumber"]').first();
    const tenderNumberValue = await tenderNumberInput.inputValue();
    console.log('Номер торгов:', tenderNumberValue);
    expect(tenderNumberValue).toBeTruthy();
    
    // 2. Торговая площадка должна быть выбрана
    const tradingPlatformSelect = page.locator('select[name="tradingPlatform"]').first();
    const tradingPlatformValue = await tradingPlatformSelect.inputValue();
    console.log('Торговая площадка:', tradingPlatformValue);
    expect(tradingPlatformValue).toBe('b2b-center');
    
    // 3. Проверяем таблицу товаров - первая строка должна содержать "Редуктор 5А028-0 СЗТМ"
    const firstItemTypeInput = page.locator('table tbody tr:first-child td:nth-child(2) input').first();
    await expect(firstItemTypeInput).toBeVisible({ timeout: 5000 });
    
    const itemTypeValue = await firstItemTypeInput.inputValue();
    console.log('Описание товара:', itemTypeValue);
    
    // Проверяем, что поле заполнено и содержит ожидаемое значение
    expect(itemTypeValue).toBeTruthy();
    expect(itemTypeValue).not.toBe('Описание товара'); // Не должно быть дефолтным значением
    expect(itemTypeValue).toContain('Редуктор');
    
    // 4. Проверяем количество
    const firstItemQuantityInput = page.locator('table tbody tr:first-child td:nth-child(4) input').first();
    const itemQuantityValue = await firstItemQuantityInput.inputValue();
    console.log('Количество:', itemQuantityValue);
    expect(itemQuantityValue).toBe(String(EXPECTED_QUANTITY));
    
    // Делаем скриншот для отладки
    await page.screenshot({ path: 'tests/screenshots/tender-parser-filled.png', fullPage: true });
  });

  test('API парсера должен возвращать правильные данные', async ({ request }) => {
    const apiUrl = `/api/parse-tender-data.php?url=${encodeURIComponent(TENDER_URL)}`;
    
    const response = await request.get(apiUrl);
    expect(response.ok()).toBeTruthy();
    
    const data = await response.json();
    console.log('Ответ API:', JSON.stringify(data, null, 2));
    
    expect(data.success).toBe(true);
    expect(data.data).toBeDefined();
    
    // Проверяем наличие позиций
    if (data.data.items && data.data.items.length > 0) {
      const firstItem = data.data.items[0];
      console.log('Первая позиция:', firstItem);
      
      expect(firstItem.name).toBeTruthy();
      expect(firstItem.name).toContain('Редуктор');
      expect(firstItem.quantity).toBe(EXPECTED_QUANTITY);
    } else {
      // Если items нет, проверяем старый формат
      expect(data.data.itemName).toBeTruthy();
      expect(data.data.itemName).toContain('Редуктор');
      expect(data.data.quantity).toBe(EXPECTED_QUANTITY);
    }
    
    // Проверяем номер торгов
    if (data.data.tenderNumber) {
      expect(data.data.tenderNumber).toBe(EXPECTED_TENDER_NUMBER);
    }
    
    // Проверяем платформу
    expect(data.platform).toBe('b2b-center');
  });

  test('должен сохранить данные после заполнения', async ({ page }) => {
    // Открываем страницу
    await page.goto('/torgikp.html');
    await page.waitForLoadState('networkidle');
    
    // Заполняем ссылку на торги
    const tenderLinkInput = page.locator('input[name="tenderLink"], input[placeholder*="https://"]').first();
    await tenderLinkInput.fill(TENDER_URL);
    
    // Загружаем данные
    const loadButton = page.locator('button:has-text("Загрузить данные из ссылки")');
    await loadButton.click();
    await page.waitForTimeout(3000);
    
    // Заполняем обязательные поля для сохранения
    const recipientInput = page.locator('textarea[name="recipient"]').first();
    await recipientInput.fill('Тестовая организация');
    
    // Сохраняем документ
    const saveButton = page.locator('button:has-text("Сохранить")');
    await saveButton.click();
    
    // Ждем сохранения
    await page.waitForTimeout(2000);
    
    // Проверяем, что появилось сообщение об успешном сохранении
    const savedMessage = page.locator('button:has-text("✓ Сохранено"), button:has-text("Сохранено")');
    await expect(savedMessage).toBeVisible({ timeout: 5000 });
    
    // Проверяем, что данные остались в форме после сохранения
    const firstItemTypeInput = page.locator('table tbody tr:first-child td:nth-child(2) input').first();
    const itemTypeValue = await firstItemTypeInput.inputValue();
    
    expect(itemTypeValue).toBeTruthy();
    expect(itemTypeValue).not.toBe('Описание товара');
    expect(itemTypeValue).toContain('Редуктор');
  });
});

