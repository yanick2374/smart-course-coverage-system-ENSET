-- Run this once in phpMyAdmin after selecting course_coverage_management_system.
-- It allows each HOD account to be tied to a department.

ALTER TABLE users
  ADD COLUMN department_id INT(11) NULL AFTER role_id;

ALTER TABLE users
  ADD INDEX idx_users_department_id (department_id);

ALTER TABLE users
  ADD CONSTRAINT fk_users_department
  FOREIGN KEY (department_id) REFERENCES departments(department_id)
  ON UPDATE CASCADE
  ON DELETE SET NULL;
