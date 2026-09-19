-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 13, 2026 at 07:09 AM
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
(9, 'CSG201', 'Data Structure', 3, 4, '', 'First', '300', 'Active'),
(12, 'CSG302', 'DATA ANALYSIS', 4, 4, '', 'Second', '300', 'Active'),
(13, 'EDT302', 'MEASUREMENT AND EVALUATION', 3, 11, '', 'Second', '300', 'Active'),
(14, 'ADT304', 'ADMINISTRATIVE WORK', 3, 17, '', 'First', '300', 'Active');

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
(1, 12, 2, 1, 1, 'First Semester', '300'),
(2, 9, 5, 1, 1, 'First Semester', '300'),
(3, 9, 5, 1, 1, 'Second Semester', '300'),
(4, 9, 9, 1, 1, 'First Semester', '300'),
(5, 14, 10, 6, 1, 'First Semester', '300');

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

-- --------------------------------------------------------

--
-- Table structure for table `course_topics`
--

CREATE TABLE `course_topics` (
  `topic_id` int(200) NOT NULL,
  `course_id` int(200) NOT NULL,
  `topic_number` int(20) NOT NULL,
  `topic_title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `expected_hours` int(20) NOT NULL,
  `created_at` timestamp(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(17, 'Administrative techniques', 'For administrative work.');

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
(8, 'ub222', 'Arrey', 'arrey@gmail.com', 4, '677543444', 'active'),
(9, 'UB2331', 'Ojong', 'ojong@gmail.com', 4, '675737274', 'active'),
(10, 'UB2211', 'Macelo', 'marcelo@gmail.com', 17, '677342211', 'active');

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
(3, 'IT', 4, 3),
(4, 'ICT', 4, 2),
(5, 'AUTOMOBILE MECHANICS', 9, 3),
(6, 'ADT', 17, 3);

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
(10, 'john', 'john@gmail.com', '$2y$10$j9NJYRCMad4X67kV67hKWeLv9X6nR1i5nhwU8fdVLwsjGKwBmaoya', 1, NULL, 'active', '2026-08-24 13:59:36'),
(15, 'Ojong', 'ojong@gmail.com', '$2y$10$WM5RxsVLr2m07u5hr3XQCuTWGVlxlAFdDKn.H/Vk2PowIVzzQXdtS', 2, NULL, 'active', '2026-08-28 14:15:23'),
(16, 'Madam Vera', 'vera@gmail.com', '$2y$10$5jQ2o2HxNk6T3/Cma.TV4um/SLs4hVKQ/tU6wO7g5OIQtxyQ5h5nu', 1, 17, 'active', '2026-09-12 22:34:44'),
(17, 'Macelo', 'marcelo@gmail.com', '$2y$10$4PbJZpt6z4/zahxKh3XgwOjkNQbhaIZh0mo.LgRAVlIdqt/XHB7ry', 2, NULL, 'active', '2026-09-12 22:39:11');

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
-- Indexes for table `course_topics`
--
ALTER TABLE `course_topics`
  ADD PRIMARY KEY (`topic_id`);

--
-- Indexes for table `cousre_topics`
--
ALTER TABLE `cousre_topics`
  ADD PRIMARY KEY (`topic_id`);

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
  MODIFY `course_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `course_assgnment`
--
ALTER TABLE `course_assgnment`
  MODIFY `assignment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `course_coverage`
--
ALTER TABLE `course_coverage`
  MODIFY `coverage_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_scheme_topics`
--
ALTER TABLE `course_scheme_topics`
  MODIFY `scheme_topic_id` int(200) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_topics`
--
ALTER TABLE `course_topics`
  MODIFY `topic_id` int(200) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cousre_topics`
--
ALTER TABLE `cousre_topics`
  MODIFY `topic_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `lecturers`
--
ALTER TABLE `lecturers`
  MODIFY `lecturer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `program_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  MODIFY `user_id` int(200) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
