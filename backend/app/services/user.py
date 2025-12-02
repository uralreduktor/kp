from __future__ import annotations

import uuid

from sqlalchemy.ext.asyncio import AsyncSession

from app.models.user import User
from app.repositories.user import UserRepository


class UserService:
    """Сервис для работы с пользователями."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session
        self.user_repo = UserRepository(session)

    async def get_by_id(self, user_id: uuid.UUID) -> User | None:
        """Получает пользователя по ID."""
        return await self.user_repo.get_by_id(user_id)

