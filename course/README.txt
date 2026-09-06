COURSE COVERAGE MANAGEMENT SYSTEM - LOGIN

IMPORTANT FIX
==============
The original login package required create_admin.php to be run before the supplied credentials could work. This version includes setup.php, which creates/repairs the Admin role and Admin account and resets the test Admin password to the supplied credentials.

1. Put this folder in C:\xampp\htdocs\
2. Start Apache and MySQL.
3. Import your course_coverage_management_system.sql into phpMyAdmin.
4. Check config/database.php. Default XAMPP values are:
   host = 127.0.0.1
   database = course_coverage_management_system
   username = root
   password = empty
5. Open:
   http://localhost/course_coverage_login/setup.php
6. After successful setup, open:
   http://localhost/course_coverage_login/
7. Login:
   Email: admin@ubuea.cm
   Password: Admin@12345
8. Delete setup.php after setup.

ONE LOGIN PAGE / ROLE REDIRECTION
==================================
Admin    -> admin/dashboard.php
HOD      -> hod/dashboard.php
Lecturer -> lecturer/dashboard.php

The login checks users.email, users.password, users.status and roles.role_name.
Passwords must be stored using PHP password_hash().
