# Course Coverage Management System — HOD Dashboard

This package recreates the supplied HOD dashboard design using PHP, HTML and CSS and connects the dashboard to the supplied MariaDB/MySQL database `course_coverage_management_system`.

## Requirements
- XAMPP/WAMP/Laragon
- PHP 8.0+
- MySQL/MariaDB
- phpMyAdmin

## Install
1. Create a database named `course_coverage_management_system`.
2. Import your supplied `course_coverage_management_system.sql`.
3. Copy this folder into your web root, e.g.:
   `C:\xampp\htdocs\course_coverage_management_system\`
4. Open `config/database.php` and set:
   - host
   - database
   - username
   - password
5. Start Apache and MySQL in XAMPP.
6. Visit:
   `http://localhost/course_coverage_management_system/`

## Important
The SQL supplied with the project defines the tables but does not include sample INSERT data. Therefore, KPI values will reflect the actual records in your database. If there are no records, the dashboard will show zero/empty states.

The schema also does not contain a direct HOD-to-department relationship, so this first dashboard version uses the whole database for the HOD view. When the authentication and department assignment are implemented, the queries can be scoped to the logged-in HOD's department.

## Database logic
- Total lecturers: `lecturers`
- Total courses: `courses`
- Assigned courses: `course_assgnment`, filtered by academic session and semester
- Coverage: total `course_coverage.hours_taught` / total `cousre_topics.expected_hours` for each assigned course
- Coverage status: Excellent >=75%, Good 50–74%, Average 25–49%, Poor <25%
- Recent submissions: latest `course_coverage` records
- Low coverage alerts: assigned courses below 50%

The supplied schema names `course_assgnment` and `cousre_topics` are intentionally used exactly as they appear in the SQL.
