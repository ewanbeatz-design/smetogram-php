# Сметограм PHP

Сметограм на PHP 8.2+, MySQL/MariaDB, PDO и Bootstrap 5.

## MVP
- современная главная;
- регистрация и вход;
- сессии и CSRF;
- проекты;
- разделы сметы;
- позиции, количество, единица, цена и автоматический итог;
- удаление позиций и проектов;
- GitHub Actions → REG.RU.

## Локальный запуск
1. Создайте базу MySQL/MariaDB.
2. Скопируйте config/config.example.php в config/config.php.
3. Укажите доступы к БД.
4. Импортируйте database/schema.sql.
5. Откройте проект через Apache/PHP 8.2+.

Node.js/npm/pnpm не требуются.