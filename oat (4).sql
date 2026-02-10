-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 10, 2026 at 08:48 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `oat`
--

-- --------------------------------------------------------

--
-- Table structure for table `ojt_records`
--

CREATE TABLE `ojt_records` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time NOT NULL,
  `ot_hours` decimal(4,2) DEFAULT 0.00,
  `remarks` varchar(255) DEFAULT NULL,
  `time_in_policy` time DEFAULT NULL,
  `time_out_policy` time DEFAULT NULL,
  `lunch_start` time DEFAULT NULL,
  `lunch_end` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_records`
--

INSERT INTO `ojt_records` (`id`, `user_id`, `date`, `time_in`, `time_out`, `ot_hours`, `remarks`, `time_in_policy`, `time_out_policy`, `lunch_start`, `lunch_end`) VALUES
(28, 4, '2026-02-07', '06:26:16', '20:00:00', 0.00, 'late', '07:00:00', '17:00:00', '12:00:00', '14:00:00'),
(29, 4, '2026-02-08', '08:00:00', '00:00:00', 0.00, 'late', '07:00:00', '17:00:00', '11:00:00', '12:00:00'),
(33, 4, '2026-02-09', '13:14:36', '00:00:00', 0.00, 'late', '07:00:00', '17:00:00', '11:00:00', '12:00:00'),
(35, 4, '2026-02-10', '12:18:00', '00:00:00', 0.00, 'late', '07:00:00', '17:00:00', '11:00:00', '12:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `ojt_requirements`
--

CREATE TABLE `ojt_requirements` (
  `user_id` int(11) NOT NULL,
  `required_hours` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ojt_requirements`
--

INSERT INTO `ojt_requirements` (`user_id`, `required_hours`) VALUES
(4, 486.00),
(5, 111.00);

-- --------------------------------------------------------

--
-- Table structure for table `ot_reports`
--

CREATE TABLE `ot_reports` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `ot_hours` int(11) NOT NULL,
  `ot_date` date NOT NULL,
  `ot_reason` text NOT NULL,
  `recipient` varchar(20) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved` tinyint(1) DEFAULT 0,
  `decline_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ot_reports`
--

INSERT INTO `ot_reports` (`id`, `student_id`, `ot_hours`, `ot_date`, `ot_reason`, `recipient`, `submitted_at`, `approved`, `decline_reason`) VALUES
(1, 4, 2, '2026-02-07', 'asdasdasd', '', '2026-02-05 02:20:40', 0, NULL),
(2, 4, 1, '2026-02-05', 'asdasdasdasdasdasdasxzvxzcv', '', '2026-02-05 04:33:44', 1, NULL),
(3, 4, 1, '2026-02-05', 'Ueueujeudur', '', '2026-02-05 04:39:32', 0, NULL),
(4, 4, 2, '2026-02-07', 'sadasdadsdsshoulldasd', '', '2026-02-07 08:27:24', 1, NULL),
(5, 4, 491808, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:05:14', 0, NULL),
(6, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:06:00', 0, NULL),
(7, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:07:54', 0, NULL),
(8, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:19:22', 0, NULL),
(9, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:22:13', 0, NULL),
(10, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:24:00', 0, NULL),
(11, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:24:54', 0, NULL),
(12, 4, 0, '2026-02-08', 'sdfcbsdcbasdasdAcwcaasd', '', '2026-02-08 07:27:30', 0, 'no');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `password_resets`
--

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`) VALUES
(24, 4, 'e0edab72c31d1a10bbadde45c7ec0d3d26438de80d9473a32b8333fa3ae3e02a', '2026-02-03 16:09:23'),
(25, 4, '53d234f3dc6d0a7b486403799040282eb159f6a3ff62ce13c1bff83627009b22', '2026-02-03 16:09:37'),
(26, 4, '352a14426d982da825b800b88c2e9e1c94b44005f84a39ca2f8a3e5140e5655e', '2026-02-03 16:09:41'),
(27, 4, '175dacd9f7de1b74470cb3770eb34f80627f530fa6c04d298cfaab8a46f884d4', '2026-02-03 16:10:18'),
(28, 4, '4898dc0ffb77efc1a1a51ad37d39b90318a2333661746695031a47751433d36a', '2026-02-03 16:10:22'),
(29, 4, 'c2b030914b61d18ee89baffdabee68c4825cb5a242d5bccc7ce410f2453f793a', '2026-02-03 16:11:45'),
(30, 4, '32a936391c3407b07d9774f776c67f351dba98f9d5ffcc9a776cf8edf43501b0', '2026-02-03 16:11:50'),
(31, 4, 'd0700aa7f744e408f484c58fc9182ee38f1db595ed9c202d8a37ea37a6edb7ae', '2026-02-04 12:26:40'),
(32, 4, '63fb8396da8d24b79506e45c462d35a599754fe8b5436288037494eff667f844', '2026-02-04 12:28:22'),
(33, 4, 'fc01d939bce868a9ffeec2049d202e0429ac4e804d333495e5a3388bd104923b', '2026-02-04 12:29:39'),
(34, 4, '1a8413e09c88b04e7ec51475dc7fbbfc877f75335db537e69d898e5af5044738', '2026-02-04 12:29:44'),
(35, 4, '6f7b6cfd87ff094b69748c91abaeb96cca51be26f0432a27054611b7ed569d3f', '2026-02-04 12:32:33'),
(36, 4, '2464b2591dcae5c3d1eac28d4e527eddb4daa3e25e9d000801ff016b70062e69', '2026-02-04 12:32:38'),
(37, 4, '3f5280c3afdd34a22d93bf04b96a5367640ec1db8cea85b06bc0b9d9aad1b149', '2026-02-04 12:33:43'),
(38, 4, '853039dda83fa6e31d6ae1b7266be44bb95780b4343c269f8be3f2e4648336d9', '2026-02-04 12:34:36'),
(39, 4, '5fc8bc704b360702192495c16486dbb129b2e11c17c7eabe90a4765cf7f88cc2', '2026-02-04 12:36:43'),
(40, 4, '9cdf63b222fc7181865706025db18edfbeab3176ae887aaf7cb37a4673a087df', '2026-02-04 12:36:44'),
(41, 4, '822ea632f86317586ce42385960154f70390f7f65273c63b332caecdfeca792a', '2026-02-04 12:37:11'),
(42, 4, 'e7dc79311c37ff21daaff45e0daac273280a80cc6472228e337496c541b86095', '2026-02-04 12:37:17'),
(43, 4, '83aa14662ad3bf904154e41d37dd8114e015669f7d04dcd4b34bafaf9ecfe909', '2026-02-04 12:38:25'),
(44, 4, 'ae3f660a77adb7281f32f1bb00d1a86d7c8605f2f1fa78ada4ee2a465bd79ee2', '2026-02-04 12:38:33'),
(45, 4, 'a0873034192c57c19b6f9a54ded0744b0be8202313a21d152756a71f48504e96', '2026-02-04 12:40:30');

-- --------------------------------------------------------

--
-- Table structure for table `qr_codes`
--

CREATE TABLE `qr_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `type` varchar(10) DEFAULT 'time_in',
  `session_id` varchar(36) DEFAULT NULL,
  `date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qr_codes`
--

INSERT INTO `qr_codes` (`id`, `code`, `expires_at`, `created_at`, `type`, `session_id`, `date`) VALUES
(17390, '7229dc033df76edac31216df97cd98cb8ad8ec844198b5fdfaa94cd8d39d0cb8', '2026-02-10 15:48:58', '2026-02-10 15:48:56', 'time_in', '698ae2e80e3421.86871781', '2026-02-10'),
(17391, '7e85953c9f15d42e5c9b39dac52801b0f6aefa5bccb48b9e46978a958369fa79', '2026-02-10 15:48:58', '2026-02-10 15:48:56', 'time_out', '698ae2e80e3417.07828487', '2026-02-10');

-- --------------------------------------------------------

--
-- Table structure for table `site_settings`
--

CREATE TABLE `site_settings` (
  `id` int(11) NOT NULL,
  `default_time_in` time NOT NULL DEFAULT '08:00:00',
  `default_time_out` time NOT NULL DEFAULT '17:00:00',
  `lunch_start` time DEFAULT '12:00:00',
  `lunch_end` time DEFAULT '13:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `site_settings`
--

INSERT INTO `site_settings` (`id`, `default_time_in`, `default_time_out`, `lunch_start`, `lunch_end`) VALUES
(1, '07:00:00', '17:00:00', '12:00:00', '15:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `time_groups`
--

CREATE TABLE `time_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `time_in` time NOT NULL,
  `time_out` time NOT NULL,
  `lunch_start` time DEFAULT '12:00:00',
  `lunch_end` time DEFAULT '13:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `time_groups`
--

INSERT INTO `time_groups` (`id`, `name`, `time_in`, `time_out`, `lunch_start`, `lunch_end`) VALUES
(4, 'Default', '08:00:00', '17:00:00', '12:00:00', '13:00:00'),
(5, 'EAC', '07:00:00', '22:00:00', '11:00:00', '12:00:00'),
(6, 'NCST', '07:00:00', '18:00:00', '12:00:00', '13:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','ojt') NOT NULL,
  `fname` varchar(50) DEFAULT NULL,
  `mname` varchar(225) DEFAULT NULL,
  `lname` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `mobile` varchar(15) DEFAULT NULL,
  `school_org` varchar(100) DEFAULT NULL,
  `full_name` varchar(100) DEFAULT NULL,
  `bio` varchar(255) DEFAULT NULL,
  `profile_img` varchar(255) DEFAULT NULL,
  `adviser_id` int(11) DEFAULT NULL,
  `status` enum('active','restricted') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `fname`, `mname`, `lname`, `position`, `age`, `mobile`, `school_org`, `full_name`, `bio`, `profile_img`, `adviser_id`, `status`) VALUES
(4, 'eman', 'emmanuellouisearceo@gmail.com', '$2y$10$QBCfhgnlaGnlVqL2q4yz4O4l3VfTLS8ouHWFfLAOGulMPpc65v6cK', 'ojt', 'Emmanuel', 'V', 'Arceo', 'IT Business Analyst', 22, '09923123123', NULL, NULL, 'Test Bio', 'uploads/profile_4_1770169547.jpg', 6, 'active'),
(5, 'admin', 'Ikki015137@gmail.com', '$2y$10$IlcjELq6OFydJUIh11q6A.eVxDt8y389Bsuajbwbt3pfauFP7aGce', 'super_admin', 'Test', NULL, 'Seuperadmin', NULL, 22, '09923123111', NULL, NULL, NULL, 'uploads/profile_images/698ad932330ac_626009105_2308175632997973_6981034424676502484_n.jpg', NULL, 'active'),
(6, 'SSV', 'markrobin335@gmail.com', '$2y$10$8sE3LPS1Huwvp6q/K.PwXOIXVYLQPLdR0SlouIcprezwsB2VTdGKS', 'admin', 'super', 'S', 'visor', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/profile_images/698ad98f8bac0_sssssssssssssssssssssssssssssssssssssssssssssss.PNG', NULL, 'active'),
(7, 'admin2', 'asdasdasd@gmail.com', '$2y$10$4XtJ7x/tkba9hbP/SP8U/u5.DH.lXJo4KreZY.sEGVaxYzgYiHEoq', 'admin', '2nd', 'A', 'admin', NULL, NULL, NULL, NULL, NULL, NULL, 'uploads/profile_images/698adceb17858_0d6473eb-488a-4ea1-b240-7351793b8cb2.jpg', NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `user_time_groups`
--

CREATE TABLE `user_time_groups` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_time_groups`
--

INSERT INTO `user_time_groups` (`id`, `user_id`, `group_id`) VALUES
(14, 4, 5);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `ojt_records`
--
ALTER TABLE `ojt_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `ojt_requirements`
--
ALTER TABLE `ojt_requirements`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `ot_reports`
--
ALTER TABLE `ot_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `qr_codes`
--
ALTER TABLE `qr_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `site_settings`
--
ALTER TABLE `site_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `time_groups`
--
ALTER TABLE `time_groups`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_adviser_id` (`adviser_id`);

--
-- Indexes for table `user_time_groups`
--
ALTER TABLE `user_time_groups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`,`group_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `ojt_records`
--
ALTER TABLE `ojt_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `ot_reports`
--
ALTER TABLE `ot_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `qr_codes`
--
ALTER TABLE `qr_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17392;

--
-- AUTO_INCREMENT for table `site_settings`
--
ALTER TABLE `site_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `time_groups`
--
ALTER TABLE `time_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `user_time_groups`
--
ALTER TABLE `user_time_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `ojt_records`
--
ALTER TABLE `ojt_records`
  ADD CONSTRAINT `ojt_records_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `ojt_requirements`
--
ALTER TABLE `ojt_requirements`
  ADD CONSTRAINT `ojt_requirements_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ot_reports`
--
ALTER TABLE `ot_reports`
  ADD CONSTRAINT `ot_reports_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_adviser_id` FOREIGN KEY (`adviser_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
