-- Подготовка существующей базы Сметограма к PHP-версии.
-- Выполнить ОДИН РАЗ в базе u1027561_smetogram через phpMyAdmin.
-- Данные существующих таблиц не удаляются.
SET @has_password_hash := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'password_hash');
SET @sql := IF(@has_password_hash = 0, 'ALTER TABLE users ADD COLUMN password_hash VARCHAR(255) NULL AFTER email', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- PHP-регистрация будет заполнять эти поля.
-- Существующие пользователи без password_hash смогут войти только после установки
-- нового пароля через будущий раздел восстановления доступа.
