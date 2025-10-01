-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 01, 2025 at 05:24 AM
-- Server version: 8.0.33-cll-lve
-- PHP Version: 8.3.14

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pgritawa_nova`
--

-- --------------------------------------------------------

--
-- Table structure for table `boq_items`
--

CREATE TABLE `boq_items` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `wbs_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `item_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_qty` decimal(18,4) NOT NULL,
  `unit_price` decimal(18,2) NOT NULL,
  `weight` decimal(9,6) DEFAULT NULL,
  `scheduled_start` date DEFAULT NULL,
  `scheduled_end` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_reports`
--

CREATE TABLE `daily_reports` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `report_date` date NOT NULL,
  `weather` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_report_photos`
--

CREATE TABLE `daily_report_photos` (
  `id` bigint UNSIGNED NOT NULL,
  `daily_report_id` bigint UNSIGNED NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `caption` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `expense_date` date NOT NULL,
  `category` enum('material','alat','upah','lainnya') COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vendor` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(18,4) DEFAULT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `unit_price` decimal(18,2) DEFAULT NULL,
  `total` decimal(18,2) GENERATED ALWAYS AS ((ifnull(`qty`,0) * ifnull(`unit_price`,0))) STORED,
  `method` enum('cash','transfer','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `linked_purchase_request_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `progress_entries`
--

CREATE TABLE `progress_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `daily_report_id` bigint UNSIGNED NOT NULL,
  `boq_item_id` bigint UNSIGNED NOT NULL,
  `done_qty` decimal(18,4) NOT NULL,
  `remark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `projects`
--

CREATE TABLE `projects` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('renovasi','ruko','sekolah','lainnya') COLLATE utf8mb4_unicode_ci NOT NULL,
  `client` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `budget` decimal(18,2) DEFAULT NULL,
  `status` enum('draft','ongoing','done','hold') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `project_members`
--

CREATE TABLE `project_members` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `member_role` enum('manager','finance','foreman','worker') COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_requests`
--

CREATE TABLE `purchase_requests` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `request_date` date NOT NULL,
  `requested_by` bigint UNSIGNED NOT NULL,
  `status` enum('draft','submitted','approved','rejected','purchased','billed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_request_items`
--

CREATE TABLE `purchase_request_items` (
  `id` bigint UNSIGNED NOT NULL,
  `purchase_request_id` bigint UNSIGNED NOT NULL,
  `name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `spec` text COLLATE utf8mb4_unicode_ci,
  `category` enum('material','alat','upah','lainnya') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL,
  `unit` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `est_unit_price` decimal(18,2) DEFAULT NULL,
  `actual_unit_price` decimal(18,2) DEFAULT NULL,
  `vendor` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `s_curve_actual`
--

CREATE TABLE `s_curve_actual` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `curve_date` date NOT NULL,
  `actual_cumulative` decimal(5,2) NOT NULL,
  `source` enum('auto','manual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `s_curve_baseline`
--

CREATE TABLE `s_curve_baseline` (
  `id` bigint UNSIGNED NOT NULL,
  `project_id` bigint UNSIGNED NOT NULL,
  `curve_date` date NOT NULL,
  `planned_cumulative` decimal(5,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `full_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('owner','manager','finance','foreman','worker') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'worker',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `phone`, `password_hash`, `role`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'tanto', 'admin', 'admin@mastanto.com', '081234567890', '$2y$10$Idoc9.oR/wD.Ruv1fVLeNe1ERC7Wkrn7PvOP3rqaxEQ0AxdHwvSJq', 'owner', 1, '2025-09-30 14:34:14', '2025-09-30 22:11:33');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_project_expenses_summary`
-- (See below for the actual view)
--
CREATE TABLE `view_project_expenses_summary` (
`project_id` bigint unsigned
,`category` enum('material','alat','upah','lainnya')
,`total_amount` decimal(40,2)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_scurve_planned_vs_actual`
-- (See below for the actual view)
--
CREATE TABLE `view_scurve_planned_vs_actual` (
`project_id` bigint unsigned
,`curve_date` date
,`planned_cumulative` decimal(5,2)
,`actual_cumulative` decimal(5,2)
);

-- --------------------------------------------------------

--
-- Structure for view `view_project_expenses_summary`
--
DROP TABLE IF EXISTS `view_project_expenses_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`pgritawa`@`localhost` SQL SECURITY DEFINER VIEW `view_project_expenses_summary`  AS SELECT `e`.`project_id` AS `project_id`, `e`.`category` AS `category`, sum(`e`.`total`) AS `total_amount` FROM `expenses` AS `e` GROUP BY `e`.`project_id`, `e`.`category` ;

-- --------------------------------------------------------

--
-- Structure for view `view_scurve_planned_vs_actual`
--
DROP TABLE IF EXISTS `view_scurve_planned_vs_actual`;

CREATE ALGORITHM=UNDEFINED DEFINER=`pgritawa`@`localhost` SQL SECURITY DEFINER VIEW `view_scurve_planned_vs_actual`  AS SELECT `p`.`id` AS `project_id`, `d`.`curve_date` AS `curve_date`, `b`.`planned_cumulative` AS `planned_cumulative`, `a`.`actual_cumulative` AS `actual_cumulative` FROM (((`projects` `p` left join (select `s_curve_baseline`.`project_id` AS `project_id`,`s_curve_baseline`.`curve_date` AS `curve_date` from `s_curve_baseline` union select `s_curve_actual`.`project_id` AS `project_id`,`s_curve_actual`.`curve_date` AS `curve_date` from `s_curve_actual`) `d` on((`d`.`project_id` = `p`.`id`))) left join `s_curve_baseline` `b` on(((`b`.`project_id` = `p`.`id`) and (`b`.`curve_date` = `d`.`curve_date`)))) left join `s_curve_actual` `a` on(((`a`.`project_id` = `p`.`id`) and (`a`.`curve_date` = `d`.`curve_date`)))) ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `boq_items`
--
ALTER TABLE `boq_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_boq_project` (`project_id`),
  ADD KEY `idx_boq_scheduled` (`scheduled_start`,`scheduled_end`);

--
-- Indexes for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_daily_proj_date` (`project_id`,`report_date`),
  ADD KEY `idx_daily_created_by` (`created_by`);

--
-- Indexes for table `daily_report_photos`
--
ALTER TABLE `daily_report_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dr_photos_report` (`daily_report_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_expenses_project_date` (`project_id`,`expense_date`),
  ADD KEY `idx_expenses_linked_pr` (`linked_purchase_request_id`),
  ADD KEY `idx_expenses_category` (`category`);

--
-- Indexes for table `progress_entries`
--
ALTER TABLE `progress_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_progress_boq` (`boq_item_id`),
  ADD KEY `idx_progress_daily` (`daily_report_id`),
  ADD KEY `idx_progress_project` (`project_id`);

--
-- Indexes for table `projects`
--
ALTER TABLE `projects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_projects_status` (`status`),
  ADD KEY `fk_projects_created_by` (`created_by`);

--
-- Indexes for table `project_members`
--
ALTER TABLE `project_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_member` (`project_id`,`user_id`),
  ADD KEY `idx_member_user` (`user_id`);

--
-- Indexes for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_project_status` (`project_id`,`status`),
  ADD KEY `fk_pr_user` (`requested_by`);

--
-- Indexes for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pr_items_pr` (`purchase_request_id`);

--
-- Indexes for table `s_curve_actual`
--
ALTER TABLE `s_curve_actual`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_actual_unique` (`project_id`,`curve_date`);

--
-- Indexes for table `s_curve_baseline`
--
ALTER TABLE `s_curve_baseline`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_baseline_unique` (`project_id`,`curve_date`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `boq_items`
--
ALTER TABLE `boq_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_reports`
--
ALTER TABLE `daily_reports`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_report_photos`
--
ALTER TABLE `daily_report_photos`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `progress_entries`
--
ALTER TABLE `progress_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `projects`
--
ALTER TABLE `projects`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `project_members`
--
ALTER TABLE `project_members`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `s_curve_actual`
--
ALTER TABLE `s_curve_actual`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `s_curve_baseline`
--
ALTER TABLE `s_curve_baseline`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `boq_items`
--
ALTER TABLE `boq_items`
  ADD CONSTRAINT `fk_boq_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `daily_reports`
--
ALTER TABLE `daily_reports`
  ADD CONSTRAINT `fk_dr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_dr_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `daily_report_photos`
--
ALTER TABLE `daily_report_photos`
  ADD CONSTRAINT `fk_dr_photos_report` FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `fk_exp_linked_pr` FOREIGN KEY (`linked_purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_exp_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `progress_entries`
--
ALTER TABLE `progress_entries`
  ADD CONSTRAINT `fk_prj_progress` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progress_boq` FOREIGN KEY (`boq_item_id`) REFERENCES `boq_items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_progress_daily` FOREIGN KEY (`daily_report_id`) REFERENCES `daily_reports` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `projects`
--
ALTER TABLE `projects`
  ADD CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `project_members`
--
ALTER TABLE `project_members`
  ADD CONSTRAINT `fk_pm_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pm_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `purchase_requests`
--
ALTER TABLE `purchase_requests`
  ADD CONSTRAINT `fk_pr_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pr_user` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Constraints for table `purchase_request_items`
--
ALTER TABLE `purchase_request_items`
  ADD CONSTRAINT `fk_pr_items_pr` FOREIGN KEY (`purchase_request_id`) REFERENCES `purchase_requests` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `s_curve_actual`
--
ALTER TABLE `s_curve_actual`
  ADD CONSTRAINT `fk_scact_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `s_curve_baseline`
--
ALTER TABLE `s_curve_baseline`
  ADD CONSTRAINT `fk_scbase_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
