from __future__ import annotations

import uuid

from fastapi import HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.repositories.device import DeviceRepository


class DeviceService:
    """Сервис для работы с устройствами."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session
        self.device_repo = DeviceRepository(session)

    async def list_by_user(self, user_id: uuid.UUID) -> list:
        """Получает список устройств пользователя."""
        return await self.device_repo.list_by_user(user_id)

    async def revoke(self, user_id: uuid.UUID, device_id: uuid.UUID) -> None:
        """Отзывает устройство пользователя.
        
        Args:
            user_id: UUID пользователя
            device_id: UUID первичного ключа устройства (TrustedDevice.id)
            
        Raises:
            HTTPException: Если устройство не найдено или не принадлежит пользователю
        """
        try:
            # Репозиторий проверит принадлежность устройства пользователю
            await self.device_repo.revoke(device_id, user_id=user_id)
        except ValueError as e:
            # Устройство не принадлежит пользователю или не найдено
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Device not found or does not belong to user",
            ) from e




