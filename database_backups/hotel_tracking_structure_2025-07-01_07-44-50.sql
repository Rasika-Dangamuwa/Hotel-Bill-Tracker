-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: hotel_tracking_system
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Temporary table structure for view `active_propagandists`
--

DROP TABLE IF EXISTS `active_propagandists`;
/*!50001 DROP VIEW IF EXISTS `active_propagandists`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `active_propagandists` AS SELECT
 1 AS `id`,
  1 AS `name`,
  1 AS `nic`,
  1 AS `phone`,
  1 AS `department`,
  1 AS `propagandist_since`,
  1 AS `notes` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `table_name` varchar(50) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('INSERT','UPDATE','DELETE','LOGIN','LOGOUT') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_table_record` (`table_name`,`record_id`),
  KEY `idx_user_action` (`user_id`,`action`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `bill_employees`
--

DROP TABLE IF EXISTS `bill_employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `stay_date` date NOT NULL,
  `room_number` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_date` (`employee_id`,`stay_date`),
  KEY `idx_bill_employee` (`bill_id`,`employee_id`),
  KEY `idx_employee_date` (`employee_id`,`stay_date`),
  CONSTRAINT `bill_employees_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bill_employees_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=136 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER validate_stay_date_range
    BEFORE INSERT ON bill_employees
    FOR EACH ROW
BEGIN
    DECLARE bill_check_in DATE;
    DECLARE bill_check_out DATE;
    
    SELECT check_in, check_out INTO bill_check_in, bill_check_out
    FROM bills WHERE id = NEW.bill_id;
    
    IF NEW.stay_date < bill_check_in OR NEW.stay_date >= bill_check_out THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Stay date must be within the bill date range';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER validate_stay_date_range_update
    BEFORE UPDATE ON bill_employees
    FOR EACH ROW
BEGIN
    DECLARE bill_check_in DATE;
    DECLARE bill_check_out DATE;
    
    SELECT check_in, check_out INTO bill_check_in, bill_check_out
    FROM bills WHERE id = NEW.bill_id;
    
    IF NEW.stay_date < bill_check_in OR NEW.stay_date >= bill_check_out THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Stay date must be within the bill date range';
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `bill_files`
--

DROP TABLE IF EXISTS `bill_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `file_number` varchar(50) NOT NULL COMMENT 'Format: RSK/25/B/04',
  `description` text DEFAULT NULL COMMENT 'Optional description of the file',
  `submitted_date` date NOT NULL COMMENT 'Date account assistant created this file',
  `status` enum('pending','submitted') DEFAULT 'pending' COMMENT 'pending=adding bills, submitted=sent to finance',
  `submitted_to_finance_date` date DEFAULT NULL COMMENT 'Date when file was submitted to finance dept',
  `total_bills` int(11) DEFAULT 0 COMMENT 'Number of bills in this file',
  `total_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Total amount of all bills in file',
  `created_by` int(11) NOT NULL COMMENT 'Account assistant who created the file',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `file_number` (`file_number`),
  KEY `created_by` (`created_by`),
  KEY `idx_file_status` (`status`),
  KEY `idx_file_number` (`file_number`),
  KEY `idx_file_date` (`submitted_date`),
  CONSTRAINT `bill_files_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `bill_summary`
--

DROP TABLE IF EXISTS `bill_summary`;
/*!50001 DROP VIEW IF EXISTS `bill_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `bill_summary` AS SELECT
 1 AS `id`,
  1 AS `invoice_number`,
  1 AS `hotel_name`,
  1 AS `location`,
  1 AS `check_in`,
  1 AS `check_out`,
  1 AS `total_nights`,
  1 AS `room_count`,
  1 AS `total_amount`,
  1 AS `status`,
  1 AS `submitted_by_name`,
  1 AS `propagandist_name`,
  1 AS `file_number`,
  1 AS `file_status`,
  1 AS `employee_count`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `hotel_id` int(11) NOT NULL,
  `rate_id` int(11) NOT NULL,
  `check_in` date NOT NULL,
  `check_out` date NOT NULL,
  `total_nights` int(11) NOT NULL,
  `room_count` int(11) NOT NULL,
  `base_amount` decimal(10,2) NOT NULL,
  `water_charge` decimal(10,2) DEFAULT 0.00,
  `washing_charge` decimal(10,2) DEFAULT 0.00,
  `service_charge` decimal(10,2) DEFAULT 0.00,
  `misc_charge` decimal(10,2) DEFAULT 0.00,
  `misc_description` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `submitted_by` int(11) NOT NULL,
  `propagandist_id` int(11) DEFAULT NULL COMMENT 'Employee who originally submitted these bills',
  `bill_file_id` int(11) DEFAULT NULL COMMENT 'Which file this bill belongs to',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `rate_id` (`rate_id`),
  KEY `idx_invoice_number` (`invoice_number`),
  KEY `idx_hotel_date` (`hotel_id`,`check_in`,`check_out`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_bills_date_range` (`check_in`,`check_out`),
  KEY `idx_bills_propagandist` (`propagandist_id`),
  KEY `idx_bills_file` (`bill_file_id`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`),
  CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`rate_id`) REFERENCES `hotel_rates` (`id`),
  CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_bills_file` FOREIGN KEY (`bill_file_id`) REFERENCES `bill_files` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bills_propagandist` FOREIGN KEY (`propagandist_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER calculate_bill_nights
    BEFORE INSERT ON bills
    FOR EACH ROW
BEGIN
    SET NEW.total_nights = DATEDIFF(NEW.check_out, NEW.check_in);
    SET NEW.base_amount = NEW.total_nights * NEW.room_count * (
        SELECT rate FROM hotel_rates WHERE id = NEW.rate_id
    );
    SET NEW.total_amount = NEW.base_amount + NEW.water_charge + NEW.washing_charge + NEW.service_charge + NEW.misc_charge;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_file_totals_insert
    AFTER INSERT ON bills
    FOR EACH ROW
BEGIN
    IF NEW.bill_file_id IS NOT NULL THEN
        UPDATE bill_files 
        SET 
            total_bills = (SELECT COUNT(*) FROM bills WHERE bill_file_id = NEW.bill_file_id),
            total_amount = (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE bill_file_id = NEW.bill_file_id)
        WHERE id = NEW.bill_file_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_bill_totals
    BEFORE UPDATE ON bills
    FOR EACH ROW
BEGIN
    SET NEW.total_nights = DATEDIFF(NEW.check_out, NEW.check_in);
    SET NEW.base_amount = NEW.total_nights * NEW.room_count * (
        SELECT rate FROM hotel_rates WHERE id = NEW.rate_id
    );
    SET NEW.total_amount = NEW.base_amount + NEW.water_charge + NEW.washing_charge + NEW.service_charge + NEW.misc_charge;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_file_totals_update
    AFTER UPDATE ON bills
    FOR EACH ROW
BEGIN
    -- Update old file totals
    IF OLD.bill_file_id IS NOT NULL THEN
        UPDATE bill_files 
        SET 
            total_bills = (SELECT COUNT(*) FROM bills WHERE bill_file_id = OLD.bill_file_id),
            total_amount = (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE bill_file_id = OLD.bill_file_id)
        WHERE id = OLD.bill_file_id;
    END IF;
    
    -- Update new file totals
    IF NEW.bill_file_id IS NOT NULL THEN
        UPDATE bill_files 
        SET 
            total_bills = (SELECT COUNT(*) FROM bills WHERE bill_file_id = NEW.bill_file_id),
            total_amount = (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE bill_file_id = NEW.bill_file_id)
        WHERE id = NEW.bill_file_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER update_file_totals_delete
    AFTER DELETE ON bills
    FOR EACH ROW
BEGIN
    IF OLD.bill_file_id IS NOT NULL THEN
        UPDATE bill_files 
        SET 
            total_bills = (SELECT COUNT(*) FROM bills WHERE bill_file_id = OLD.bill_file_id),
            total_amount = (SELECT COALESCE(SUM(total_amount), 0) FROM bills WHERE bill_file_id = OLD.bill_file_id)
        WHERE id = OLD.bill_file_id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Temporary table structure for view `current_hotel_rates`
--

DROP TABLE IF EXISTS `current_hotel_rates`;
/*!50001 DROP VIEW IF EXISTS `current_hotel_rates`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `current_hotel_rates` AS SELECT
 1 AS `rate_id`,
  1 AS `hotel_id`,
  1 AS `hotel_name`,
  1 AS `location`,
  1 AS `rate`,
  1 AS `effective_date`,
  1 AS `created_by_name` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `employee_positions`
--

DROP TABLE IF EXISTS `employee_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employee_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `employee_id` int(11) NOT NULL,
  `position` varchar(100) NOT NULL COMMENT 'Propagandist, Crew Member, etc.',
  `effective_date` date NOT NULL COMMENT 'When this position started',
  `end_date` date DEFAULT NULL COMMENT 'When position ended (NULL if current)',
  `is_current` tinyint(1) DEFAULT 1 COMMENT 'Is this the current position',
  `notes` text DEFAULT NULL COMMENT 'Optional notes about the position',
  `created_by` int(11) NOT NULL COMMENT 'User who assigned this position',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_employee_current` (`employee_id`,`is_current`),
  KEY `idx_position_current` (`position`,`is_current`),
  KEY `idx_effective_date` (`effective_date`),
  CONSTRAINT `employee_positions_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_positions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER ensure_one_current_position_insert
    BEFORE INSERT ON employee_positions
    FOR EACH ROW
BEGIN
    IF NEW.is_current = TRUE THEN
        UPDATE employee_positions 
        SET is_current = FALSE, end_date = CURDATE() 
        WHERE employee_id = NEW.employee_id AND is_current = TRUE;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER ensure_one_current_position_update
    BEFORE UPDATE ON employee_positions
    FOR EACH ROW
BEGIN
    IF NEW.is_current = TRUE AND OLD.is_current = FALSE THEN
        UPDATE employee_positions 
        SET is_current = FALSE, end_date = CURDATE() 
        WHERE employee_id = NEW.employee_id AND is_current = TRUE AND id != NEW.id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `nic` varchar(12) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `added_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nic` (`nic`),
  KEY `added_by` (`added_by`),
  KEY `idx_nic` (`nic`),
  KEY `idx_employees_active` (`is_active`,`name`),
  CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hotel_rates`
--

DROP TABLE IF EXISTS `hotel_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hotel_rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hotel_id` int(11) NOT NULL,
  `rate` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_current` tinyint(1) DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  KEY `idx_hotel_effective_date` (`hotel_id`,`effective_date`),
  KEY `idx_current_rate` (`hotel_id`,`is_current`),
  KEY `idx_hotel_rates_date` (`hotel_id`,`effective_date`,`is_current`),
  CONSTRAINT `hotel_rates_ibfk_1` FOREIGN KEY (`hotel_id`) REFERENCES `hotels` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hotel_rates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `hotels`
--

DROP TABLE IF EXISTS `hotels`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hotels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hotel_name` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `hotels_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `pending_bill_files`
--

DROP TABLE IF EXISTS `pending_bill_files`;
/*!50001 DROP VIEW IF EXISTS `pending_bill_files`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `pending_bill_files` AS SELECT
 1 AS `id`,
  1 AS `file_number`,
  1 AS `description`,
  1 AS `submitted_date`,
  1 AS `total_bills`,
  1 AS `total_amount`,
  1 AS `created_by_name`,
  1 AS `created_at` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','account_assistant') DEFAULT 'account_assistant',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `idx_users_login` (`email`,`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'hotel_tracking_system'
--

--
-- Final view structure for view `active_propagandists`
--

/*!50001 DROP VIEW IF EXISTS `active_propagandists`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `active_propagandists` AS select `e`.`id` AS `id`,`e`.`name` AS `name`,`e`.`nic` AS `nic`,`e`.`phone` AS `phone`,`e`.`department` AS `department`,`ep`.`effective_date` AS `propagandist_since`,`ep`.`notes` AS `notes` from (`employees` `e` join `employee_positions` `ep` on(`e`.`id` = `ep`.`employee_id`)) where `ep`.`position` = 'Propagandist' and `ep`.`is_current` = 1 and `e`.`is_active` = 1 order by `e`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `bill_summary`
--

/*!50001 DROP VIEW IF EXISTS `bill_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `bill_summary` AS select `b`.`id` AS `id`,`b`.`invoice_number` AS `invoice_number`,`h`.`hotel_name` AS `hotel_name`,`h`.`location` AS `location`,`b`.`check_in` AS `check_in`,`b`.`check_out` AS `check_out`,`b`.`total_nights` AS `total_nights`,`b`.`room_count` AS `room_count`,`b`.`total_amount` AS `total_amount`,`b`.`status` AS `status`,`u`.`name` AS `submitted_by_name`,`p`.`name` AS `propagandist_name`,`bf`.`file_number` AS `file_number`,`bf`.`status` AS `file_status`,(select count(0) from `bill_employees` `be` where `be`.`bill_id` = `b`.`id`) AS `employee_count`,`b`.`created_at` AS `created_at` from ((((`bills` `b` join `hotels` `h` on(`b`.`hotel_id` = `h`.`id`)) join `users` `u` on(`b`.`submitted_by` = `u`.`id`)) left join `employees` `p` on(`b`.`propagandist_id` = `p`.`id`)) left join `bill_files` `bf` on(`b`.`bill_file_id` = `bf`.`id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `current_hotel_rates`
--

/*!50001 DROP VIEW IF EXISTS `current_hotel_rates`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `current_hotel_rates` AS select `hr`.`id` AS `rate_id`,`h`.`id` AS `hotel_id`,`h`.`hotel_name` AS `hotel_name`,`h`.`location` AS `location`,`hr`.`rate` AS `rate`,`hr`.`effective_date` AS `effective_date`,`u`.`name` AS `created_by_name` from ((`hotel_rates` `hr` join `hotels` `h` on(`hr`.`hotel_id` = `h`.`id`)) join `users` `u` on(`hr`.`created_by` = `u`.`id`)) where `hr`.`is_current` = 1 and `h`.`is_active` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `pending_bill_files`
--

/*!50001 DROP VIEW IF EXISTS `pending_bill_files`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `pending_bill_files` AS select `bf`.`id` AS `id`,`bf`.`file_number` AS `file_number`,`bf`.`description` AS `description`,`bf`.`submitted_date` AS `submitted_date`,`bf`.`total_bills` AS `total_bills`,`bf`.`total_amount` AS `total_amount`,`u`.`name` AS `created_by_name`,`bf`.`created_at` AS `created_at` from (`bill_files` `bf` join `users` `u` on(`bf`.`created_by` = `u`.`id`)) where `bf`.`status` = 'pending' order by `bf`.`submitted_date` desc,`bf`.`file_number` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-07-01  7:44:51
