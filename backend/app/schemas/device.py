from __future__ import annotations

from datetime import datetime
from uuid import UUID

from pydantic import BaseModel


class DeviceInfo(BaseModel):
    """Информация об устройстве."""

    id: UUID
    device_id: str
    device_name: str | None
    fingerprint: str
    first_seen_at: datetime
    last_seen_at: datetime | None
    last_ip: str | None
    expires_at: datetime
    revoked_at: datetime | None


class DeviceListResponse(BaseModel):
    """Список доверенных устройств."""

    devices: list[DeviceInfo]


class DeviceRevokeRequest(BaseModel):
    """Запрос на отзыв устройства."""

    device_id: UUID




