from __future__ import annotations

from fastapi import APIRouter, Cookie, Depends, HTTPException, Request, status

from app.core.config import get_settings
from app.dependencies.db import DbSession
from app.schemas.device import DeviceInfo, DeviceListResponse, DeviceRevokeRequest
from app.services.auth import AuthService
from app.services.device import DeviceService

router = APIRouter(tags=["devices"], prefix="/devices")
settings = get_settings()


def get_auth_service(session: DbSession) -> AuthService:
    """Dependency для получения AuthService."""
    return AuthService(session)


def get_device_service(session: DbSession) -> DeviceService:
    """Dependency для получения DeviceService."""
    return DeviceService(session)


@router.get("/", response_model=DeviceListResponse, status_code=status.HTTP_200_OK)
async def list_devices(
    request: Request,
    session_token: str | None = Cookie(None, alias=settings.session_cookie_name),
    auth_service: AuthService = Depends(get_auth_service),
    device_service: DeviceService = Depends(get_device_service),
) -> DeviceListResponse:
    """Получает список доверенных устройств пользователя."""
    if not session_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Session required",
        )

    user_id = await auth_service.get_current_user(session_token)
    devices = await device_service.list_by_user(user_id)

    return DeviceListResponse(
        devices=[
            DeviceInfo(
                id=device.id,
                device_id=device.device_id,
                device_name=device.device_name,
                fingerprint=device.fingerprint,
                first_seen_at=device.first_seen_at,
                last_seen_at=device.last_seen_at,
                last_ip=device.last_ip,
                expires_at=device.expires_at,
                revoked_at=device.revoked_at,
            )
            for device in devices
        ]
    )


@router.post("/revoke", status_code=status.HTTP_200_OK)
async def revoke_device(
    request: Request,
    revoke_data: DeviceRevokeRequest,
    session_token: str | None = Cookie(None, alias=settings.session_cookie_name),
    auth_service: AuthService = Depends(get_auth_service),
    device_service: DeviceService = Depends(get_device_service),
) -> dict[str, str]:
    """Отзывает доверенное устройство."""
    if not session_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Session required",
        )

    user_id = await auth_service.get_current_user(session_token)
    await device_service.revoke(user_id, revoke_data.id)

    return {"message": "Device revoked successfully"}

