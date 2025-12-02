"""CLI утилита для управления пользователями."""

from __future__ import annotations

import asyncio
import sys
from typing import TYPE_CHECKING, Optional

import typer
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.config import get_settings
from app.db.session import AsyncSessionFactory
from app.repositories.user import UserRepository
from app.utils.password import hash_password

if TYPE_CHECKING:
    pass

app = typer.Typer(help="Управление пользователями KP Auth")


@app.command()
def create(
    email: str = typer.Argument(..., help="Email пользователя"),
    password: Optional[str] = typer.Option(None, "--password", "-p", help="Пароль пользователя (если не указан, будет запрошен интерактивно)"),
    superuser: bool = typer.Option(False, "--superuser", help="Сделать суперпользователем"),
) -> None:
    """Создать нового пользователя."""
    if not password:
        password = typer.prompt("Пароль", hide_input=True)
        password_confirm = typer.prompt("Подтвердите пароль", hide_input=True)
        if password != password_confirm:
            typer.echo("❌ Пароли не совпадают!", err=True)
            sys.exit(1)
    # Проверяем наличие флага --superuser в аргументах командной строки
    # Typer может неправильно обрабатывать булевые опции, поэтому проверяем напрямую
    is_superuser_flag = "--superuser" in sys.argv
    # Используем флаг из аргументов или значение параметра
    is_superuser = is_superuser_flag or (superuser if isinstance(superuser, bool) and superuser else False)
    asyncio.run(_create_user(email, password, is_superuser))


async def _create_user(email: str, password: str, is_superuser: bool | str) -> None:
    async with AsyncSessionFactory() as session:
        user_repo = UserRepository(session)

        existing_user = await user_repo.get_by_email(email)
        if existing_user:
            typer.echo(f"❌ Пользователь {email} уже существует!", err=True)
            sys.exit(1)

        # Преобразуем строку в bool, если необходимо
        if isinstance(is_superuser, str):
            is_superuser = is_superuser.lower() in ("true", "1", "yes", "on")

        hashed_password = hash_password(password)
        user = await user_repo.create(
            email=email,
            hashed_password=hashed_password,
            is_superuser=bool(is_superuser),
        )
        typer.echo(f"✅ Создан пользователь: {user.email} (superuser: {user.is_superuser})")


@app.command(name="list")
def list_users() -> None:
    """Список всех пользователей."""
    asyncio.run(_list_users())


async def _list_users() -> None:
    async with AsyncSessionFactory() as session:
        from sqlalchemy import select

        from app.models.user import User

        stmt = select(User).order_by(User.created_at)
        result = await session.execute(stmt)
        users = result.scalars().all()

        if not users:
            typer.echo("Пользователи не найдены")
            return

        typer.echo("\n📋 Список пользователей:\n")
        for user in users:
            status = "✅" if user.is_active else "❌"
            superuser = "👑" if user.is_superuser else "👤"
            typer.echo(f"{status} {superuser} {user.email}")
            typer.echo(f"   ID: {user.id}")
            typer.echo(f"   Создан: {user.created_at}")
            if user.last_login_at:
                typer.echo(f"   Последний вход: {user.last_login_at}")
            typer.echo()


@app.command()
def delete(
    email: str = typer.Argument(..., help="Email пользователя для удаления"),
    confirm: bool = typer.Option(False, "--yes", help="Подтвердить удаление без запроса"),
) -> None:
    """Удалить пользователя."""
    if not confirm:
        if not typer.confirm(f"⚠️  Вы уверены, что хотите удалить пользователя {email}?"):
            typer.echo("Отменено")
            return

    asyncio.run(_delete_user(email))


async def _delete_user(email: str) -> None:
    async with AsyncSessionFactory() as session:
        user_repo = UserRepository(session)

        user = await user_repo.get_by_email(email)
        if not user:
            typer.echo(f"❌ Пользователь {email} не найден!", err=True)
            sys.exit(1)

        await session.delete(user)
        await session.commit()
        typer.echo(f"✅ Пользователь {email} удалён")


@app.command()
def update_password(
    email: str = typer.Argument(..., help="Email пользователя"),
    password: Optional[str] = typer.Option(None, "--password", "-p", help="Новый пароль (если не указан, будет запрошен интерактивно)"),
) -> None:
    """Изменить пароль пользователя."""
    if not password:
        password = typer.prompt("Новый пароль", hide_input=True)
        password_confirm = typer.prompt("Подтвердите пароль", hide_input=True)
        if password != password_confirm:
            typer.echo("❌ Пароли не совпадают!", err=True)
            sys.exit(1)
    asyncio.run(_update_password(email, password))


async def _update_password(email: str, password: str) -> None:
    async with AsyncSessionFactory() as session:
        from datetime import datetime

        user_repo = UserRepository(session)

        user = await user_repo.get_by_email(email)
        if not user:
            typer.echo(f"❌ Пользователь {email} не найден!", err=True)
            sys.exit(1)

        user.hashed_password = hash_password(password)
        user.password_updated_at = datetime.utcnow()
        await session.commit()
        typer.echo(f"✅ Пароль для {email} обновлён")


@app.command()
def toggle_active(
    email: str = typer.Argument(..., help="Email пользователя"),
) -> None:
    """Включить/выключить пользователя."""
    asyncio.run(_toggle_active(email))


async def _toggle_active(email: str) -> None:
    async with AsyncSessionFactory() as session:
        user_repo = UserRepository(session)

        user = await user_repo.get_by_email(email)
        if not user:
            typer.echo(f"❌ Пользователь {email} не найден!", err=True)
            sys.exit(1)

        user.is_active = not user.is_active
        await session.commit()
        status = "активирован" if user.is_active else "деактивирован"
        typer.echo(f"✅ Пользователь {email} {status}")


if __name__ == "__main__":
    app()

