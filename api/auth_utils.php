<?php
/**
 * Утилиты для работы с аутентификацией и правами доступа
 */

/**
 * Получает имя текущего пользователя из HTTP Basic Auth
 * @return string|null Имя пользователя или null
 */
function getCurrentUser() {
    // Nginx передает имя пользователя в заголовке REMOTE_USER
    if (isset($_SERVER['REMOTE_USER'])) {
        return $_SERVER['REMOTE_USER'];
    }
    
    // Альтернативные варианты (для Apache или других серверов)
    if (isset($_SERVER['PHP_AUTH_USER'])) {
        return $_SERVER['PHP_AUTH_USER'];
    }
    
    if (isset($_SERVER['AUTH_USER'])) {
        return $_SERVER['AUTH_USER'];
    }
    
    return null;
}

/**
 * Загружает права пользователей из файла
 * @return array Массив с правами пользователей
 */
function loadPermissions() {
    $permissionsFile = __DIR__ . '/permissions.json';
    
    if (!file_exists($permissionsFile)) {
        // Возвращаем права по умолчанию (без доступа)
        return [
            'users' => [],
            'default' => [
                'canCreate' => false,
                'canEdit' => false,
                'canDelete' => false,
                'description' => 'Пользователь по умолчанию - без прав'
            ]
        ];
    }
    
    $content = file_get_contents($permissionsFile);
    $permissions = json_decode($content, true);
    
    if (!$permissions || !isset($permissions['users'])) {
        return [
            'users' => [],
            'default' => [
                'canCreate' => false,
                'canEdit' => false,
                'canDelete' => false,
                'description' => 'Пользователь по умолчанию - без прав'
            ]
        ];
    }
    
    return $permissions;
}

/**
 * Получает права доступа для текущего пользователя
 * @return array Массив с правами: ['canCreate' => bool, 'canEdit' => bool, 'canDelete' => bool, 'description' => string]
 */
function getUserPermissions() {
    $username = getCurrentUser();
    $permissions = loadPermissions();
    
    // Если пользователь не авторизован, возвращаем права по умолчанию
    if (!$username) {
        return $permissions['default'] ?? [
            'canCreate' => false,
            'canEdit' => false,
            'canDelete' => false,
            'description' => 'Пользователь не авторизован'
        ];
    }
    
    // Проверяем права пользователя
    if (isset($permissions['users'][$username])) {
        return $permissions['users'][$username];
    }
    
    // Если пользователь не найден в списке, возвращаем права по умолчанию
    return $permissions['default'] ?? [
        'canCreate' => false,
        'canEdit' => false,
        'canDelete' => false,
        'description' => 'Пользователь не найден в системе прав'
    ];
}

/**
 * Проверяет, может ли текущий пользователь создавать документы
 * @return bool
 */
function canCreate() {
    $perms = getUserPermissions();
    return $perms['canCreate'] ?? false;
}

/**
 * Проверяет, может ли текущий пользователь редактировать документы
 * @return bool
 */
function canEdit() {
    $perms = getUserPermissions();
    return $perms['canEdit'] ?? false;
}

/**
 * Проверяет, может ли текущий пользователь удалять документы
 * @return bool
 */
function canDelete() {
    $perms = getUserPermissions();
    return $perms['canDelete'] ?? false;
}

/**
 * Проверяет права доступа и возвращает ошибку, если доступ запрещен
 * @param string $action Действие: 'create', 'edit', 'delete'
 * @return array|null null если доступ разрешен, массив с ошибкой если запрещен
 */
function checkPermission($action) {
    $username = getCurrentUser();
    
    if (!$username) {
        return [
            'success' => false,
            'error' => 'Unauthorized',
            'message' => 'Требуется авторизация'
        ];
    }
    
    $allowed = false;
    switch ($action) {
        case 'create':
            $allowed = canCreate();
            break;
        case 'edit':
            $allowed = canEdit();
            break;
        case 'delete':
            $allowed = canDelete();
            break;
        default:
            return [
                'success' => false,
                'error' => 'Invalid action',
                'message' => 'Неизвестное действие'
            ];
    }
    
    if (!$allowed) {
        return [
            'success' => false,
            'error' => 'Forbidden',
            'message' => 'У вас нет прав на выполнение этого действия'
        ];
    }
    
    return null; // Доступ разрешен
}

