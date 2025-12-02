from __future__ import annotations

import secrets
from datetime import datetime, timedelta

from passlib.context import CryptContext

token_context = CryptContext(schemes=["bcrypt"], deprecated="auto")


def generate_token() -> str:
    """Генерирует криптографически стойкий токен."""
    return secrets.token_urlsafe(32)


def hash_token(token: str) -> str:
    """Хэширует токен для хранения в БД."""
    return token_context.hash(token)


def verify_token(plain_token: str, hashed_token: str) -> bool:
    """Проверяет токен против хэша."""
    return token_context.verify(plain_token, hashed_token)


def generate_device_id() -> str:
    """Генерирует уникальный идентификатор устройства."""
    return secrets.token_urlsafe(16)


def get_expires_at(days: int) -> datetime:
    """Возвращает дату истечения через указанное количество дней."""
    return datetime.utcnow() + timedelta(days=days)




