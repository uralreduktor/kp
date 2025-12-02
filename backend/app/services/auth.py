from __future__ import annotations

import uuid
from datetime import datetime, timedelta

from fastapi import HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import get_settings
from app.repositories.device import DeviceRepository
from app.repositories.session import SessionRepository
from app.repositories.user import UserRepository
from app.utils.password import verify_password

from app.utils.tokens import (
    generate_device_id,
    generate_token,
    get_expires_at,
    hash_token,
)

settings = get_settings()


class AuthService:
    """Сервис аутентификации."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session
        self.user_repo = UserRepository(session)
        self.session_repo = SessionRepository(session)
        self.device_repo = DeviceRepository(session)

    async def login(
        self,
        email: str,
        password: str,
        remember_device: bool,
        fingerprint: str | None,
        ip_address: str | None = None,
        user_agent: str | None = None,
    ) -> tuple[str, str | None, str | None]:
        """
        Выполняет вход пользователя.

        Returns:
            tuple: (session_token, device_id, device_token)
        """
        user = await self.user_repo.get_by_email(email)
        if not user or not user.is_active:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid email or password",
            )

        if not verify_password(password, user.hashed_password):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid email or password",
            )

        await self.user_repo.update_last_login(user.id)

        session_token = generate_token()
        session_token_hash = hash_token(session_token)
        expires_at = datetime.utcnow() + timedelta(
            minutes=settings.access_token_ttl_minutes
        )

        device_id: str | None = None
        device_token: str | None = None

        if remember_device and fingerprint:
            device_id = generate_device_id()
            device_token = generate_token()
            device_token_hash = hash_token(device_token)

            await self.device_repo.create(
                user_id=user.id,
                device_id=device_id,
                token_hash=device_token_hash,
                fingerprint=fingerprint,
                expires_at=get_expires_at(settings.device_token_ttl_days),
                ip_address=ip_address,
                user_agent=user_agent,
            )

        await self.session_repo.create(
            user_id=user.id,
            token_hash=session_token_hash,
            expires_at=expires_at,
            device_id=device_id,
            ip_address=ip_address,
            user_agent=user_agent,
        )

        return session_token, device_id, device_token

    async def refresh_session(
        self,
        device_id: str,
        device_token: str,
        fingerprint: str | None,
        ip_address: str | None = None,
        user_agent: str | None = None,
    ) -> str:
        """
        Обновляет сессию на основе доверенного устройства.

        Returns:
            str: новый session_token
        """
        device = await self.device_repo.get_by_device_id(device_id)
        if not device:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid device",
            )

        from app.utils.tokens import verify_token

        if not verify_token(device_token, device.device_token_hash):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid device token",
            )

        if fingerprint and device.fingerprint != fingerprint:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Device fingerprint mismatch",
            )

        await self.device_repo.update_last_seen(
            device_id=device_id,
            ip_address=ip_address,
            user_agent=user_agent,
        )

        session_token = generate_token()
        session_token_hash = hash_token(session_token)
        expires_at = datetime.utcnow() + timedelta(
            minutes=settings.access_token_ttl_minutes
        )

        await self.session_repo.create(
            user_id=device.user_id,
            token_hash=session_token_hash,
            expires_at=expires_at,
            device_id=device_id,
            ip_address=ip_address,
            user_agent=user_agent,
        )

        return session_token

    async def logout(self, session_token: str) -> None:
        """Выполняет выход пользователя."""
        session = await self.session_repo.get_by_token_verify(session_token)
        if session:
            await self.session_repo.revoke(session.id)

    async def get_current_user(self, session_token: str) -> uuid.UUID:
        """
        Получает текущего пользователя по токену сессии.

        Returns:
            UUID: user_id
        """
        session = await self.session_repo.get_by_token_verify(session_token)
        if not session:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid session",
            )

        await self.session_repo.update_last_seen(session.id)
        return session.user_id

