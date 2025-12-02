from __future__ import annotations

from datetime import datetime
from uuid import UUID

from pydantic import BaseModel, EmailStr, Field


class LoginRequest(BaseModel):
    """Схема запроса на вход."""

    email: EmailStr
    password: str
    remember_device: bool = Field(default=False, alias="rememberDevice")
    fingerprint: str | None = None


class LoginResponse(BaseModel):
    """Схема ответа на успешный вход."""

    user_id: UUID
    email: str
    is_superuser: bool


class RefreshResponse(BaseModel):
    """Схема ответа на обновление сессии."""

    user_id: UUID
    email: str


class LogoutResponse(BaseModel):
    """Схема ответа на выход."""

    message: str = "Logged out successfully"


class UserResponse(BaseModel):
    """Схема информации о пользователе."""

    id: UUID
    email: str
    is_active: bool
    is_superuser: bool
    last_login_at: datetime | None
    created_at: datetime




