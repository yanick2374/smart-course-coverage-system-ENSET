-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 07, 2026 at 11:40 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `course_coverage_management_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_session`
--

CREATE TABLE `academic_session` (
  `session_id` int(11) NOT NULL,
  `session_name` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `academic_session`
--

INSERT INTO `academic_session` (`session_id`, `session_name`, `start_date`, `end_date`, `status`) VALUES
(1, '2026/2027', '2026-08-03', '2026-08-18', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `class_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(20) NOT NULL,
  `remarks` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` int(11) NOT NULL,
  `course_code` varchar(30) NOT NULL,
  `course_name` varchar(100) NOT NULL,
  `credit_value` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `semester` varchar(200) NOT NULL,
  `level` varchar(200) NOT NULL,
  `status` enum('Active','Inactive') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `course_code`, `course_name`, `credit_value`, `department_id`, `description`, `semester`, `level`, `status`) VALUES
(14, 'ITE417', 'DESIGN AND ANALYSISOF LINEAR SYSTEM', 4, 4, '', 'First', '400', 'Active'),
(15, 'CSG405', 'ALGORITHM', 3, 4, '', 'Second', '400', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `course_assgnment`
--

CREATE TABLE `course_assgnment` (
  `assignment_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `session_id` int(11) NOT NULL,
  `semester` varchar(20) NOT NULL,
  `level` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_assgnment`
--

INSERT INTO `course_assgnment` (`assignment_id`, `course_id`, `lecturer_id`, `program_id`, `session_id`, `semester`, `level`) VALUES
(5, 14, 10, 6, 1, 'First Semester', '400'),
(6, 15, 11, 6, 1, 'First Semester', '400'),
(7, 14, 12, 7, 1, 'Second Semester', '600');

-- --------------------------------------------------------

--
-- Table structure for table `course_coverage`
--

CREATE TABLE `course_coverage` (
  `coverage_id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `topic_id` int(11) NOT NULL,
  `date_taught` date NOT NULL,
  `scheme_topic_id` int(200) NOT NULL,
  `hours_taught` decimal(4,2) NOT NULL,
  `coverage_status` varchar(20) NOT NULL,
  `remarks` text NOT NULL,
  `updated_by` int(11) NOT NULL,
  `updated_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_coverage`
--

INSERT INTO `course_coverage` (`coverage_id`, `assignment_id`, `topic_id`, `date_taught`, `scheme_topic_id`, `hours_taught`, `coverage_status`, `remarks`, `updated_by`, `updated_at`) VALUES
(1, 5, 1, '2026-09-17', 1, 3.00, 'In Progress', 'for good', 17, '2026-09-19 05:25:48'),
(2, 5, 2, '2026-09-07', 2, 3.00, 'Partially Covered', 'Almost', 17, '2026-09-19 05:34:29'),
(3, 6, 3, '2026-09-16', 3, 3.00, 'In Progress', 'to be continued', 18, '2026-09-19 07:31:03'),
(4, 7, 2, '2026-11-07', 4, 2.00, 'In Progress', 'In progress', 19, '2026-11-07 08:02:31');

-- --------------------------------------------------------

--
-- Table structure for table `course_scheme_topics`
--

CREATE TABLE `course_scheme_topics` (
  `scheme_topic_id` int(200) NOT NULL,
  `assignment_id` int(200) NOT NULL,
  `topic` varchar(255) NOT NULL,
  `week_number` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `course_scheme_topics`
--

INSERT INTO `course_scheme_topics` (`scheme_topic_id`, `assignment_id`, `topic`, `week_number`, `status`, `created_at`) VALUES
(1, 5, 'INTRODUCTION TO FEEDBACK CONTROL SYSTEM', '1', 'Approved', '2026-09-19 11:25:48.000000'),
(2, 5, 'BLOCK DIAGRAM', '2', 'Approved', '2026-09-19 11:34:29.000000'),
(3, 6, 'FLOWCHART', '3', 'Approved', '2026-09-19 13:31:02.000000'),
(4, 7, 'BLOCK DIAGRAM', '2', 'Approved', '2026-11-07 15:02:31.000000');

-- --------------------------------------------------------

--
-- Table structure for table `cousre_topics`
--

CREATE TABLE `cousre_topics` (
  `topic_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `topic_number` int(11) NOT NULL,
  `topic_title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `expected_hours` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `cousre_topics`
--

INSERT INTO `cousre_topics` (`topic_id`, `course_id`, `topic_number`, `topic_title`, `description`, `expected_hours`) VALUES
(1, 14, 1, 'INTRODUCTION TO FEEDBACK CONTROL SYSTEM', 'This is to know how things work.', 3),
(2, 14, 2, 'BLOCK DIAGRAM', 'To operate blocks', 3),
(3, 15, 1, 'FLOWCHART', 'It solve a problem more easier', 3);

-- --------------------------------------------------------

--
-- Table structure for table `delegates_assignments`
--

CREATE TABLE `delegates_assignments` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `matricle` varchar(30) NOT NULL,
  `email` varchar(120) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `program_id` int(11) NOT NULL,
  `level` int(20) NOT NULL,
  `academic_session_id` int(11) NOT NULL,
  `status` enum('active','inactive') NOT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `department_name` varchar(200) NOT NULL,
  `description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `departments`
--

INSERT INTO `departments` (`department_id`, `department_name`, `description`) VALUES
(1, 'Administrative Techniques', 'Training in administrative,information management, communication, and related office technologies.'),
(2, 'Agriculcure', 'Training in agricultural education, practices, and technologies for technical and vocational education.'),
(3, 'Civil Engineering and Forestry Techniques', 'Training in building construction, civil engineering, forestry, and wood science.'),
(4, 'Computer Science', 'Training in computer science, information technology, and computing-related technologies.'),
(5, 'Electrical and Power Engineering', 'Training in electrical engineering, electronics, electrotechnics, and power systems. '),
(6, 'Guidance Counselling', 'Training in guidance and counselling theories, practices, and educational support services.'),
(7, 'Law', 'Training in legal studies and the application of legal principles in professional and educational environments.'),
(8, 'Management Sciences', 'Training in accountancy, management, marketing, and related business disciplines.'),
(9, 'Mechanical Engineering', 'Training in automobile mechanics, mechanical manufacturing, and mechanical design.'),
(10, 'Renewable Energy', 'Training in renewable energies technologies and sustaianable energy systems.'),
(11, 'Science of Education', 'Training in educational theories, teaching mehtods, curriculum, and professional pedagogy.'),
(12, 'Social Economy and Family Management', 'Training in home economics, fashion, clothing, textiles, and family-related manaagement.'),
(13, 'Topography and Real Estate Management', 'Training in topography, surveying, land management, and real estate-related practices.'),
(14, 'Tourism and Hospitality Management', 'Training in tourism, hotel management, hospitality, and related services.');

-- --------------------------------------------------------

--
-- Table structure for table `lecturers`
--

CREATE TABLE `lecturers` (
  `lecturer_id` int(11) NOT NULL,
  `staff_no` varchar(30) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `department_id` int(11) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lecturers`
--

INSERT INTO `lecturers` (`lecturer_id`, `staff_no`, `full_name`, `email`, `department_id`, `phone`, `status`) VALUES
(10, 'UB2001', 'Mr KOMETA DENIS MBOWO', 'kometa@gmail.com', 4, '651633012', 'active'),
(11, 'UB2002', 'Dr FORWA KINGSLEY NJEM', 'forwa@gmail.com', 4, '651633012', 'active'),
(12, 'ub/lec/23', 'Dr Ekah Kunde', 'ehah@gmail.com', 4, '674223244', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` int(11) NOT NULL,
  `message` text NOT NULL,
  `notification_type` varchar(30) NOT NULL,
  `is_read` tinyint(1) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `notification_type`, `is_read`, `created_at`) VALUES
(1, 17, 0, 'You have been assigned ITE417 for First Semester.', 'assignment', 0, '2026-09-19 02:12:43'),
(2, 17, 0, 'An approved topic was added to ITE417.', 'topic', 0, '2026-09-19 02:15:47'),
(3, 17, 0, 'An approved topic was added to ITE417.', 'topic', 0, '2026-09-19 05:06:56'),
(4, 16, 0, 'Mr KOMETA DENIS MBOWO submitted progress for ITE417 - INTRODUCTION TO FEEDBACK CONTROL SYSTEM', 'coverage', 0, '2026-09-19 05:25:49'),
(5, 16, 0, 'Mr KOMETA DENIS MBOWO submitted progress for ITE417 - BLOCK DIAGRAM', 'coverage', 0, '2026-09-19 05:34:29'),
(6, 18, 0, 'You have been assigned CSG405 for First Semester.', 'assignment', 0, '2026-09-19 07:27:23'),
(7, 18, 0, 'An approved topic was added to CSG405.', 'topic', 0, '2026-09-19 07:28:57'),
(8, 16, 0, 'Dr FORWA KINGSLEY NJEM submitted progress for CSG405 - FLOWCHART', 'coverage', 0, '2026-09-19 07:31:03'),
(9, 19, 0, 'You have been assigned ITE417 for Second Semester.', 'assignment', 0, '2026-11-07 07:59:20'),
(10, 16, 0, 'Dr Ekah Kunde submitted progress for ITE417 - BLOCK DIAGRAM', 'coverage', 0, '2026-11-07 08:02:31');

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `program_id` int(11) NOT NULL,
  `program_name` varchar(200) NOT NULL,
  `department_id` int(200) NOT NULL,
  `duration` int(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `programs`
--

INSERT INTO `programs` (`program_id`, `program_name`, `department_id`, `duration`) VALUES
(6, 'IT', 4, 3),
(7, 'ICT', 4, 2);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`) VALUES
(3, 'Admin'),
(1, 'HOD'),
(2, 'Lecturer');

-- --------------------------------------------------------

--
-- Table structure for table `semesters`
--

CREATE TABLE `semesters` (
  `semester_id` int(11) NOT NULL,
  `semester_name` varchar(50) NOT NULL,
  `semester_code` varchar(20) NOT NULL,
  `session_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `semesters`
--

INSERT INTO `semesters` (`semester_id`, `semester_name`, `semester_code`, `session_id`, `start_date`, `end_date`, `status`) VALUES
(1, 'Second Semester', 'CSG303', 1, '2026-08-31', '2026-08-31', 'Active'),
(2, 'First Semester', 'CSG221', 1, '2026-08-31', '2026-08-31', 'Active');

-- --------------------------------------------------------

--
-- Table structure for table `time_table`
--

CREATE TABLE `time_table` (
  `id` int(11) NOT NULL,
  `lecturer_id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `academic_session_id` int(11) NOT NULL,
  `day` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(100) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(200) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `email`, `password`, `role_id`, `department_id`, `status`, `created_at`) VALUES
(1, 'System Administrator', 'admin@ubuea.cm', '$2y$10$etbipPuIfU9Df6CE5p/M/.QMKKJfBSxlFsB0tKeADru8YaI9aTh1G', 3, NULL, 'Active', '2026-08-20 11:44:50'),
(16, 'Prof KINFACK JUETSA AUBIN', 'kinfack@gmail.com', '$2y$10$GURHzw42CT7FzbIPuvo1gOKz1HYe09TfccD4zhJ1yNyyxa2cWq20G', 1, 4, 'active', '2026-09-18 16:18:19'),
(17, 'Mr KOMETA DENIS MBOWO', 'kometa@gmail.com', '$2y$10$kRc884COvNai7qFoTOtQYeocwRR62LyMExjnyNdbR54Ax9WkxhaYK', 2, NULL, 'active', '2026-09-18 16:22:49'),
(18, 'Dr FORWA KINGSLEY NJEM', 'forwa@gmail.com', '$2y$10$diwp3Eupv7qHNru5/aYF7ObAmDhj4Yt80foa0cqcK2nBCUuSwq2tm', 2, NULL, 'active', '2026-09-19 07:23:11'),
(19, 'Dr Ekah Kunde', 'ehah@gmail.com', '$2y$10$HrabdXiPrx4qTw0TAy2NFeyinTq4RuPEx.Od7O.xKrMQ0pQwVMNAO', 2, NULL, 'active', '2026-11-07 07:57:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_session`
--
ALTER TABLE `academic_session`
  ADD PRIMARY KEY (`session_id`),
  ADD UNIQUE KEY `session_name` (`session_name`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`),
  ADD UNIQUE KEY `course_code` (`course_code`);

--
-- Indexes for table `course_assgnment`
--
ALTER TABLE `course_assgnment`
  ADD PRIMARY KEY (`assignment_id`);

--
-- Indexes for table `course_coverage`
--
ALTER TABLE `course_coverage`
  ADD PRIMARY KEY (`coverage_id`),
  ADD KEY `scheme_topic_id` (`scheme_topic_id`);

--
-- Indexes for table `course_scheme_topics`
--
ALTER TABLE `course_scheme_topics`
  ADD PRIMARY KEY (`scheme_topic_id`);

--
-- Indexes for table `cousre_topics`
--
ALTER TABLE `cousre_topics`
  ADD PRIMARY KEY (`topic_id`);

--
-- Indexes for table `delegates_assignments`
--
ALTER TABLE `delegates_assignments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`),
  ADD UNIQUE KEY `department_name` (`department_name`);

--
-- Indexes for table `lecturers`
--
ALTER TABLE `lecturers`
  ADD PRIMARY KEY (`lecturer_id`),
  ADD UNIQUE KEY `lecturer_id` (`lecturer_id`,`staff_no`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`program_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`semester_id`),
  ADD UNIQUE KEY `unique_semester_code_session` (`semester_code`,`session_id`),
  ADD KEY `fk_semester_session` (`session_id`);

--
-- Indexes for table `time_table`
--
ALTER TABLE `time_table`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_department_id` (`department_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_session`
--
ALTER TABLE `academic_session`
  MODIFY `session_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `course_assgnment`
--
ALTER TABLE `course_assgnment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `course_coverage`
--
ALTER TABLE `course_coverage`
  MODIFY `coverage_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `course_scheme_topics`
--
ALTER TABLE `course_scheme_topics`
  MODIFY `scheme_topic_id` int(200) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cousre_topics`
--
ALTER TABLE `cousre_topics`
  MODIFY `topic_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `delegates_assignments`
--
ALTER TABLE `delegates_assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `lecturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `semesters`
--
ALTER TABLE `semesters`
  MODIFY `semester_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `time_table`
--
ALTER TABLE `time_table`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(200) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `course_coverage`
--
ALTER TABLE `course_coverage`
  ADD CONSTRAINT `course_coverage_ibfk_1` FOREIGN KEY (`scheme_topic_id`) REFERENCES `course_scheme_topics` (`scheme_topic_id`);

--
-- Constraints for table `semesters`
--
ALTER TABLE `semesters`
  ADD CONSTRAINT `fk_semester_session` FOREIGN KEY (`session_id`) REFERENCES `academic_session` (`session_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`department_id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
