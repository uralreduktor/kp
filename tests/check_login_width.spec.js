const { test, expect } = require('@playwright/test');

test('Проверка ширины формы авторизации', async ({ page }) => {
  // Переходим на страницу
  await page.goto('https://kp.uralreduktor.com/', { waitUntil: 'networkidle' });
  
  // Ждём загрузки формы логина
  await page.waitForSelector('form', { timeout: 10000 });
  
  // Находим контейнер формы (ищем по классу max-w-sm)
  const formContainer = page.locator('.max-w-sm').first();
  
  // Получаем размеры формы
  const boundingBox = await formContainer.boundingBox();
  
  if (boundingBox) {
    console.log('\n📏 Размеры формы авторизации:');
    console.log(`   Ширина: ${Math.round(boundingBox.width)}px`);
    console.log(`   Высота: ${Math.round(boundingBox.height)}px`);
    console.log(`   Позиция X: ${Math.round(boundingBox.x)}px`);
    console.log(`   Позиция Y: ${Math.round(boundingBox.y)}px`);
    
    // Проверяем, что ширина не превышает ожидаемую (max-w-sm = 384px + padding)
    expect(boundingBox.width).toBeLessThanOrEqual(450);
    console.log(`\n✅ Ширина формы: ${Math.round(boundingBox.width)}px (ожидается ≤ 450px)`);
  } else {
    console.log('⚠️  Не удалось получить размеры формы');
    // Пробуем найти форму другим способом
    const form = page.locator('form').first();
    const formBox = await form.boundingBox();
    if (formBox) {
      console.log(`   Ширина формы (через form): ${Math.round(formBox.width)}px`);
    }
  }
  
  // Делаем скриншот для визуальной проверки
  await page.screenshot({ path: 'login-form-width.png', fullPage: true });
  console.log('\n📸 Скриншот сохранён: login-form-width.png');
});

