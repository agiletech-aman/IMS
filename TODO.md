# Task: Remove Categories from Permission System

Steps:
- [x] Remove `categories` module from `app/Models/RolePermission.php` MODULES const
- [x] Remove `categories` from migration `$modules` array and role `$defaults`
- [x] Remove `categories` route mapping from `routes/web.php` asset-management group
