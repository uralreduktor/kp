from __future__ import annotations

from fastapi import APIRouter, Cookie, Depends, Header, HTTPException, Request, Response, status

from app.core.config import get_settings
from app.dependencies.db import DbSession
from app.schemas.auth import LoginRequest, LoginResponse, LogoutResponse, UserResponse
from app.services.auth import AuthService
from app.services.user import UserService

router = APIRouter(tags=["auth"], prefix="/auth")
settings = get_settings()


def get_auth_service(session: DbSession) -> AuthService:
    """Dependency для получения AuthService."""
    return AuthService(session)


def get_user_service(session: DbSession) -> UserService:
    """Dependency для получения UserService."""
    return UserService(session)


@router.post("/login", response_model=LoginResponse, status_code=status.HTTP_200_OK)
async def login(
    request: Request,
    response: Response,
    login_data: LoginRequest,
    service: AuthService = Depends(get_auth_service),
) -> LoginResponse:
    """Endpoint для входа пользователя."""
    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")

    session_token, device_id, device_token = await service.login(
        email=login_data.email,
        password=login_data.password,
        remember_device=login_data.remember_device,
        fingerprint=login_data.fingerprint,
        ip_address=ip_address,
        user_agent=user_agent,
    )

    response.set_cookie(
        key=settings.session_cookie_name,
        value=session_token,
        httponly=True,
        secure=True,
        samesite="lax",
        max_age=settings.access_token_ttl_minutes * 60,
    )

    if device_id and device_token:
        response.set_cookie(
            key=settings.device_cookie_name,
            value=device_id,
            httponly=False,
            secure=True,
            samesite="lax",
            max_age=settings.device_token_ttl_days * 24 * 60 * 60,
        )
        response.set_cookie(
            key=settings.device_token_cookie_name,
            value=device_token,
            httponly=True,
            secure=True,
            samesite="lax",
            max_age=settings.device_token_ttl_days * 24 * 60 * 60,
        )

    user = await service.user_repo.get_by_email(login_data.email)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="User not found after login",
        )

    return LoginResponse(
        user_id=user.id,
        email=user.email,
        is_superuser=user.is_superuser,
    )


@router.post("/refresh", response_model=LoginResponse, status_code=status.HTTP_200_OK)
async def refresh(
    request: Request,
    response: Response,
    device_id: str | None = Cookie(None, alias=settings.device_cookie_name),
    device_token: str | None = Cookie(None, alias=settings.device_token_cookie_name),
    fingerprint: str | None = Header(None, alias="X-Device-Fingerprint"),
    service: AuthService = Depends(get_auth_service),
) -> LoginResponse:
    """Endpoint для обновления сессии через доверенное устройство."""
    if not device_id or not device_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Device credentials required",
        )

    ip_address = request.client.host if request.client else None
    user_agent = request.headers.get("user-agent")

    session_token = await service.refresh_session(
        device_id=device_id,
        device_token=device_token,
        fingerprint=fingerprint,
        ip_address=ip_address,
        user_agent=user_agent,
    )

    response.set_cookie(
        key=settings.session_cookie_name,
        value=session_token,
        httponly=True,
        secure=True,
        samesite="lax",
        max_age=settings.access_token_ttl_minutes * 60,
    )

    device = await service.device_repo.get_by_device_id(device_id)
    if not device:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="Device not found after refresh",
        )

    user = await service.user_repo.get_by_id(device.user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail="User not found",
        )

    return LoginResponse(
        user_id=user.id,
        email=user.email,
        is_superuser=user.is_superuser,
    )


@router.post("/logout", response_model=LogoutResponse, status_code=status.HTTP_200_OK)
async def logout(
    response: Response,
    session_token: str | None = Cookie(None, alias=settings.session_cookie_name),
    service: AuthService = Depends(get_auth_service),
) -> LogoutResponse:
    """Endpoint для выхода пользователя."""
    if session_token:
        await service.logout(session_token)

    response.delete_cookie(
        key=settings.session_cookie_name,
        httponly=True,
        secure=True,
        samesite="lax",
    )
    response.delete_cookie(
        key=settings.device_cookie_name,
        httponly=False,
        secure=True,
        samesite="lax",
    )
    response.delete_cookie(
        key=settings.device_token_cookie_name,
        httponly=True,
        secure=True,
        samesite="lax",
    )

    return LogoutResponse()


@router.get("/me", response_model=UserResponse, status_code=status.HTTP_200_OK)
async def get_current_user(
    session_token: str | None = Cookie(None, alias=settings.session_cookie_name),
    service: AuthService = Depends(get_auth_service),
    user_service: UserService = Depends(get_user_service),
) -> UserResponse:
    """Endpoint для получения информации о текущем пользователе."""
    if not session_token:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Session required",
        )

    user_id = await service.get_current_user(session_token)
    user = await user_service.get_by_id(user_id)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="User not found",
        )

    return UserResponse(
        id=user.id,
        email=user.email,
        is_active=user.is_active,
        is_superuser=user.is_superuser,
        last_login_at=user.last_login_at,
        created_at=user.created_at,
    )

