"""Скрипт для создания начальных пользователей."""

from __future__ import annotations

import asyncio

from app.core.config import get_settings
from app.db.session import AsyncSessionFactory
from app.repositories.user import UserRepository
from app.utils.password import hash_password

settings = get_settings()

# Пользователи из документации PASSWORD_PROTECTION.md
USERS = [
    {
        "email": "admin@kp.uralreduktor.com",
        "username": "admin",
        "password": "@kp2025#@",
        "is_superuser": True,
    },
    {
        "email": "SidorkinV@kp.uralreduktor.com",
        "username": "SidorkinV",
        "password": "Svl@kp2025",
        "is_superuser": False,
    },
    {
        "email": "LebedevA@kp.uralreduktor.com",
        "username": "LebedevA",
        "password": "LA@kp2025",
        "is_superuser": False,
    },
    {
        "email": "Suevalova_A@kp.uralreduktor.com",
        "username": "Suevalova_A",
        "password": "S_A@kp2025",
        "is_superuser": False,
    },
    {
        "email": "KKA@kp.uralreduktor.com",
        "username": "KKA",
        "password": "KKA@kp2025",
        "is_superuser": False,
    },
]


async def create_users(force: bool = False) -> None:
    """Создаёт пользователей в БД."""
    async with AsyncSessionFactory() as session:
        user_repo = UserRepository(session)

        for user_data in USERS:
            existing_user = await user_repo.get_by_email(user_data["email"])
            if existing_user:
                if force:
                    # Удаляем существующего пользователя
                    await session.delete(existing_user)
                    await session.commit()
                    print(f"🗑️  Удалён существующий пользователь {user_data['email']}")
                else:
                    print(f"⚠️  Пользователь {user_data['email']} уже существует, пропускаем")
                    continue

            hashed_password = hash_password(user_data["password"])
            user = await user_repo.create(
                email=user_data["email"],
                hashed_password=hashed_password,
                is_superuser=user_data["is_superuser"],
            )
            print(f"✅ Создан пользователь: {user.email} (superuser: {user.is_superuser})")

        print("\n✨ Все пользователи созданы успешно!")


if __name__ == "__main__":
    import sys

    force = "--force" in sys.argv or "-f" in sys.argv
    asyncio.run(create_users(force=force))

