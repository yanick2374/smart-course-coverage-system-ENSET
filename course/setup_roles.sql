USE course_coverage_management_system;

INSERT INTO roles (role_name) VALUES
('Admin'),
('HOD'),
('Lecturer')
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- The login system expects passwords to be stored with PHP password_hash().
-- Create the first Admin account from PHP rather than storing a plain-text password.
