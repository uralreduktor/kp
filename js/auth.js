/**
 * Модуль авторизации для интеграции с FastAPI auth сервисом
 */

class AuthService {
  constructor() {
    this.apiBase = '/api';
    this.sessionCheckInterval = null;
  }

  /**
   * Генерирует fingerprint устройства на основе безопасных данных
   */
  async generateFingerprint() {
    const data = [
      navigator.userAgent,
      navigator.language,
      screen.width + 'x' + screen.height,
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      navigator.platform,
    ].join('|');

    const encoder = new TextEncoder();
    const dataBuffer = encoder.encode(data);
    const hashBuffer = await crypto.subtle.digest('SHA-256', dataBuffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
  }

  /**
   * Выполняет вход пользователя
   */
  async login(email, password, rememberDevice = false) {
    try {
      const fingerprint = await this.generateFingerprint();
      
      const response = await fetch(`${this.apiBase}/auth/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        credentials: 'include',
        body: JSON.stringify({
          email,
          password,
          rememberDevice,
          fingerprint,
        }),
      });

      if (!response.ok) {
        const error = await response.json().catch(() => ({ detail: 'Ошибка входа' }));
        throw new Error(error.detail || 'Неверный email или пароль');
      }

      const data = await response.json();
      return data;
    } catch (error) {
      console.error('Login error:', error);
      throw error;
    }
  }

  /**
   * Обновляет сессию через доверенное устройство
   */
  async refreshSession() {
    try {
      const fingerprint = await this.generateFingerprint();
      
      const response = await fetch(`${this.apiBase}/auth/refresh`, {
        method: 'POST',
        headers: {
          'X-Device-Fingerprint': fingerprint,
        },
        credentials: 'include',
      });

      if (!response.ok) {
        return null;
      }

      const data = await response.json();
      return data;
    } catch (error) {
      console.error('Refresh error:', error);
      return null;
    }
  }

  /**
   * Получает информацию о текущем пользователе
   */
  async getCurrentUser() {
    try {
      const response = await fetch(`${this.apiBase}/auth/me`, {
        method: 'GET',
        credentials: 'include',
      });

      if (!response.ok) {
        return null;
      }

      const data = await response.json();
      return data;
    } catch (error) {
      console.error('Get current user error:', error);
      return null;
    }
  }

  /**
   * Выполняет выход
   */
  async logout() {
    try {
      await fetch(`${this.apiBase}/auth/logout`, {
        method: 'POST',
        credentials: 'include',
      });
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      // Очищаем локальное состояние
      window.location.reload();
    }
  }

  /**
   * Проверяет наличие активной сессии
   */
  async checkSession() {
    const user = await this.getCurrentUser();
    if (user) {
      return user;
    }

    // Пытаемся обновить через устройство
    const refreshed = await this.refreshSession();
    if (refreshed) {
      return await this.getCurrentUser();
    }

    return null;
  }

  /**
   * Запускает периодическую проверку сессии
   */
  startSessionCheck(intervalMs = 5 * 60 * 1000) {
    if (this.sessionCheckInterval) {
      clearInterval(this.sessionCheckInterval);
    }

    this.sessionCheckInterval = setInterval(async () => {
      const user = await this.checkSession();
      if (!user) {
        // Сессия истекла, перенаправляем на логин
        this.onSessionExpired();
      }
    }, intervalMs);
  }

  /**
   * Останавливает проверку сессии
   */
  stopSessionCheck() {
    if (this.sessionCheckInterval) {
      clearInterval(this.sessionCheckInterval);
      this.sessionCheckInterval = null;
    }
  }

  /**
   * Callback при истечении сессии
   */
  onSessionExpired() {
    this.stopSessionCheck();
    window.location.reload();
  }
}

// Экспортируем глобальный экземпляр
window.authService = new AuthService();




