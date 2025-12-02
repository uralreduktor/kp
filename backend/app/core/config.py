from __future__ import annotations

from functools import lru_cache
from typing import List

from pydantic import AnyHttpUrl, Field, field_validator
from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    """Глобальные настройки приложения."""

    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        case_sensitive=False,
        extra="ignore",
    )

    app_name: str = "KP Auth Service"
    api_prefix: str = "/api"
    environment: str = Field("local", alias="ENVIRONMENT")

    database_url: str = Field(
        default="postgresql+asyncpg://postgres:postgres@localhost:5432/kp_auth",
        alias="DATABASE_URL",
    )
    alembic_database_url: str | None = Field(default=None, alias="ALEMBIC_DATABASE_URL")

    redis_url: str = Field(
        default="redis://localhost:6379/0",
        alias="REDIS_URL",
    )

    session_secret: str = Field(default="dev-secret", alias="SESSION_SECRET")
    csrf_secret: str = Field(default="dev-csrf-secret", alias="CSRF_SECRET")

    cors_origins: List[AnyHttpUrl] | str | None = Field(default_factory=list)
    allowed_hosts: List[str] = Field(
        default_factory=lambda: ["kp.uralreduktor.com", "localhost", "127.0.0.1"],
    )

    session_cookie_name: str = "session"
    device_cookie_name: str = "device_id"
    device_token_cookie_name: str = "device_token"

    access_token_ttl_minutes: int = 15
    device_token_ttl_days: int = 30

    sqlalchemy_echo: bool = False

    @field_validator("cors_origins", mode="before")
    @classmethod
    def split_cors_origins(cls, value: str | list[AnyHttpUrl] | None) -> list[str]:
        if value is None:
            return []
        if isinstance(value, list):
            return value
        return [origin.strip() for origin in value.split(",") if origin.strip()]


@lru_cache
def get_settings() -> Settings:
    return Settings()
