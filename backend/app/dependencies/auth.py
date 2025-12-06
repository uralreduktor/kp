from __future__ import annotations

import uuid
from fastapi import Cookie, Depends, Header, HTTPException, Request, Response, status

from app.core.config import get_settings
from app.dependencies.db import DbSession
from app.services.auth import AuthService

settings = get_settings()


def get_auth_service(session: DbSession) -> AuthService:
    """Dependency для получения AuthService."""
    return AuthService(session)


async def session_guard(
    request: Request,
    response: Response,
    session_token: str | None = Cookie(None, alias=settings.session_cookie_name),
    device_id: str | None = Cookie(None, alias=settings.device_cookie_name),
    device_token: str | None = Cookie(None, alias=settings.device_token_cookie_name),
    fingerprint: str | None = Header(None, alias="X-Device-Fingerprint"),
    auth_service: AuthService = Depends(get_auth_service),
) -> uuid.UUID:
    """
    Проверка сессии. Если session_token отсутствует, но есть device cookies — пытаемся авто-обновить.
    """
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")

    if session_token:
        return await auth_service.get_current_user(session_token)

    if device_id and device_token:
        new_session_token = await auth_service.refresh_session(
            device_id=device_id,
            device_token=device_token,
            fingerprint=fingerprint,
            ip_address=ip_address,
            user_agent=user_agent,
        )
        is_secure = settings.environment != "local"
        response.set_cookie(
            key=settings.session_cookie_name,
            value=new_session_token,
            httponly=True,
            secure=is_secure,
            samesite="lax",
            max_age=settings.access_token_ttl_minutes * 60,
        )
        return await auth_service.get_current_user(new_session_token)

    raise HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Authentication required",
    )

