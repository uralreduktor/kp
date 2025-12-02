from __future__ import annotations

import uuid
from datetime import datetime
from typing import TYPE_CHECKING

from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.trusted_device import TrustedDevice

if TYPE_CHECKING:
    pass


class DeviceRepository:
    """Репозиторий для работы с доверенными устройствами."""

    def __init__(self, session: AsyncSession) -> None:
        self.session = session

    async def get_by_device_id(self, device_id: str) -> TrustedDevice | None:
        """Получает устройство по device_id."""
        stmt = (
            select(TrustedDevice)
            .where(TrustedDevice.device_id == device_id)
            .where(TrustedDevice.revoked_at.is_(None))
            .where(TrustedDevice.expires_at > datetime.utcnow())
        )
        result = await self.session.execute(stmt)
        return result.scalar_one_or_none()

    async def get_by_token_hash(self, token_hash: str) -> TrustedDevice | None:
        """Получает устройство по хэшу токена."""
        stmt = (
            select(TrustedDevice)
            .where(TrustedDevice.device_token_hash == token_hash)
            .where(TrustedDevice.revoked_at.is_(None))
            .where(TrustedDevice.expires_at > datetime.utcnow())
        )
        result = await self.session.execute(stmt)
        return result.scalar_one_or_none()

    async def create(
        self,
        user_id: uuid.UUID,
        device_id: str,
        token_hash: str,
        fingerprint: str,
        expires_at: datetime,
        device_name: str | None = None,
        device_info: dict | None = None,
        ip_address: str | None = None,
        user_agent: str | None = None,
    ) -> TrustedDevice:
        """Создаёт новое доверенное устройство."""
        device = TrustedDevice(
            id=uuid.uuid4(),
            user_id=user_id,
            device_id=device_id,
            device_token_hash=token_hash,
            fingerprint=fingerprint,
            device_name=device_name,
            device_info=device_info,
            last_ip=ip_address,
            last_user_agent=user_agent,
            first_seen_at=datetime.utcnow(),
            last_seen_at=datetime.utcnow(),
            expires_at=expires_at,
        )
        self.session.add(device)
        await self.session.commit()
        await self.session.refresh(device)
        return device

    async def list_by_user(self, user_id: uuid.UUID) -> list[TrustedDevice]:
        """Получает список всех устройств пользователя."""
        stmt = select(TrustedDevice).where(TrustedDevice.user_id == user_id)
        result = await self.session.execute(stmt)
        return list(result.scalars().all())

    async def revoke(self, device_id: uuid.UUID) -> None:
        """Отзывает устройство."""
        device = await self.session.get(TrustedDevice, device_id)
        if device:
            device.revoked_at = datetime.utcnow()
            await self.session.commit()

    async def update_last_seen(
        self,
        device_id: str,
        ip_address: str | None = None,
        user_agent: str | None = None,
    ) -> None:
        """Обновляет время последнего обращения к устройству."""
        device = await self.get_by_device_id(device_id)
        if device:
            device.last_seen_at = datetime.utcnow()
            if ip_address:
                device.last_ip = ip_address
            if user_agent:
                device.last_user_agent = user_agent
            await self.session.commit()




