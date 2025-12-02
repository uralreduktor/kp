from __future__ import annotations

import uuid
from datetime import datetime
from typing import TYPE_CHECKING

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.session import Session

if TYPE_CHECKING:
    pass


class SessionRepository:
    """Репозиторий для работы с сессиями."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session

    async def get_by_token_hash(self, token_hash: str) -> Session | None:
        """Получает сессию по хэшу токена (deprecated - используйте get_by_token_verify)."""
        stmt = (
            select(Session)
            .where(Session.session_token_hash == token_hash)
            .where(Session.expires_at > datetime.utcnow())
            .where(Session.revoked_at.is_(None))
        )
        result = await self.session.execute(stmt)
        return result.scalar_one_or_none()

    async def get_by_token_verify(self, plain_token: str) -> Session | None:
        """Получает сессию по токену, проверяя через verify_token."""
        from app.utils.tokens import verify_token

        # Получаем все активные сессии
        stmt = (
            select(Session)
            .where(Session.expires_at > datetime.utcnow())
            .where(Session.revoked_at.is_(None))
        )
        result = await self.session.execute(stmt)
        sessions = result.scalars().all()

        # Проверяем токен для каждой сессии
        for session in sessions:
            if verify_token(plain_token, session.session_token_hash):
                return session

        return None

    async def create(
        self,
        user_id: uuid.UUID,
        token_hash: str,
        expires_at: datetime,
        device_id: str | None = None,
        ip_address: str | None = None,
        user_agent: str | None = None,
    ) -> Session:
        """Создаёт новую сессию."""
        session = Session(
            id=uuid.uuid4(),
            user_id=user_id,
            session_token_hash=token_hash,
            device_id=device_id,
            ip_address=ip_address,
            user_agent=user_agent,
            expires_at=expires_at,
            last_seen_at=datetime.utcnow(),
        )
        self.session.add(session)
        await self.session.commit()
        await self.session.refresh(session)
        return session

    async def revoke(self, session_id: uuid.UUID) -> None:
        """Отзывает сессию."""
        session = await self.session.get(Session, session_id)
        if session:
            session.revoked_at = datetime.utcnow()
            await self.session.commit()

    async def revoke_by_user(self, user_id: uuid.UUID) -> None:
        """Отзывает все сессии пользователя."""
        stmt = (
            select(Session)
            .where(Session.user_id == user_id)
            .where(Session.revoked_at.is_(None))
        )
        result = await self.session.execute(stmt)
        sessions = result.scalars().all()
        for session in sessions:
            session.revoked_at = datetime.utcnow()
        await self.session.commit()

    async def update_last_seen(self, session_id: uuid.UUID) -> None:
        """Обновляет время последнего обращения к сессии."""
        session = await self.session.get(Session, session_id)
        if session:
            session.last_seen_at = datetime.utcnow()
            await self.session.commit()

