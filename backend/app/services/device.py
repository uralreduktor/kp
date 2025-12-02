from __future__ import annotations

import uuid

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
        """Отзывает устройство пользователя."""
        devices = await self.device_repo.list_by_user(user_id)
        for device in devices:
            if device.id == device_id:
                await self.device_repo.revoke(device_id)
                return




