-- A separate schema for the test suite so `php artisan test` never wipes the
-- data developers have been clicking through in the browser.
CREATE DATABASE IF NOT EXISTS `isproject_testing`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `isproject_testing`.* TO 'isproject'@'%';
FLUSH PRIVILEGES;
