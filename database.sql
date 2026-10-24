-- ==========================================================
-- Komfo Anokye Teaching Hospital (KATH) - IT Task Management System
-- Database Schema and Sample Data
-- Database Name: kath_it_task_management
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `kath_it_task_management` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `kath_it_task_management`;

-- 1. Departments Table
CREATE TABLE IF NOT EXISTS `departments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `department_name` VARCHAR(120) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. IT Categories Table
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_name` VARCHAR(120) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Users Table
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `full_name` VARCHAR(150) NOT NULL,
    `username` VARCHAR(60) NOT NULL UNIQUE,
    `email` VARCHAR(150) NOT NULL UNIQUE,
    `phone` VARCHAR(30) NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
    `department_id` INT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tasks Table
CREATE TABLE IF NOT EXISTS `tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_number` VARCHAR(30) NOT NULL UNIQUE,
    `user_id` INT NOT NULL,
    `department_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `problem_description` TEXT NOT NULL,
    `work_done` TEXT NOT NULL,
    `priority` ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL DEFAULT 'Medium',
    `status` ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `date_started` DATETIME NOT NULL,
    `date_completed` DATETIME NULL,
    `remarks` TEXT NULL,
    `attachment` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_department` FOREIGN KEY (`department_id`) 
        REFERENCES `departments`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_tasks_category` FOREIGN KEY (`category_id`) 
        REFERENCES `categories`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_tasks_task_number` (`task_number`),
    INDEX `idx_tasks_status` (`status`),
    INDEX `idx_tasks_priority` (`priority`),
    INDEX `idx_tasks_date_started` (`date_started`),
    INDEX `idx_tasks_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Task Updates / History Table
CREATE TABLE IF NOT EXISTS `task_updates` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `task_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `update_message` TEXT NOT NULL,
    `status` ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_updates_task` FOREIGN KEY (`task_id`) 
        REFERENCES `tasks`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_updates_user` FOREIGN KEY (`user_id`) 
        REFERENCES `users`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_updates_task_id` (`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. System Settings Table
CREATE TABLE IF NOT EXISTS `system_settings` (
    `setting_key` VARCHAR(60) PRIMARY KEY,
    `setting_value` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- INSERT INITIAL SEED DATA
-- ==========================================================

-- Insert System Settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('hospital_name', 'Komfo Anokye Teaching Hospital (KATH)'),
('system_title', 'IT Task Management and Reporting System'),
('hospital_location', 'Kumasi, Ashanti Region, Ghana'),
('it_support_email', 'itsupport@kath.gov.gh'),
('it_support_phone', '+233 (0)32 202 2301')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Insert 11 KATH Departments
INSERT INTO `departments` (`id`, `department_name`, `description`, `status`) VALUES
(1, 'ENT', 'Ear, Nose, and Throat Directorate and Specialist Clinics', 'active'),
(2, 'Pharmacy', 'Main Pharmacy, Satellite Pharmacies, and Medical Dispensaries', 'active'),
(3, 'Eye Centre', 'KATH Eye Care Centre, Surgical Theatres, and Outpatient Clinic', 'active'),
(4, 'Breast Care Centre', 'Breast Screening, Oncology, and Specialized Clinical Unit', 'active'),
(5, 'Laboratory', 'Central Diagnostic Lab, Pathology, and Hematology Testing', 'active'),
(6, 'Administration', 'Executive Management, Directorate, and Secretarial Offices', 'active'),
(7, 'Outpatient Department', 'General OPD triage, consultation rooms, and records', 'active'),
(8, 'Radiology', 'X-Ray, CT Scan, MRI, and Medical Ultrasound Imaging', 'active'),
(9, 'Human Resource', 'Staffing, Personnel Records, and Administrative HR', 'active'),
(10, 'Finance', 'Revenue, Cashiers, Billing, Accounts, and Auditing Offices', 'active'),
(11, 'Other', 'Other support units, logistics, and residential quarters', 'active')
ON DUPLICATE KEY UPDATE `department_name` = VALUES(`department_name`);

-- Insert 15 IT Categories
INSERT INTO `categories` (`id`, `category_name`, `description`, `status`) VALUES
(1, 'Networking', 'LAN/WAN switches, routers, VLAN setup, and link troubleshooting', 'active'),
(2, 'GHIMS Network', 'Ghana Health Information Management System connectivity & servers', 'active'),
(3, 'CCTV', 'Security surveillance cameras, NVR/DVR storage, and monitoring feed', 'active'),
(4, 'Computer Hardware', 'Desktop PCs, Motherboards, RAM, Power supplies, and Peripherals', 'active'),
(5, 'Software Installation', 'Clinical software, Office suites, Antivirus, and PDF tools', 'active'),
(6, 'Windows/Operating System', 'OS installation, Windows updates, corruption, and profile repairs', 'active'),
(7, 'Printer Support', 'Label printers, LaserJet, multifunctional scanners, and spoolers', 'active'),
(8, 'DNS/IP Configuration', 'Static IP reservations, DHCP conflicts, Gateway, and Subnets', 'active'),
(9, 'User Account/Domain', 'Active Directory accounts, password resets, and domain joining', 'active'),
(10, 'Telephone/Communication', 'Intercom lines, PABX systems, IP phones, and hotline cables', 'active'),
(11, 'Network Cabling', 'RJ45 crimping, faceplates, patch panel punching, and conduit trunking', 'active'),
(12, 'Access Point/Wi-Fi', 'Wireless access point setup, SSID coverage, and signal dropouts', 'active'),
(13, 'IT Security', 'Malware remediation, unauthorized access alerts, and firewalls', 'active'),
(14, 'General IT Support', 'User training, workstation relocation, and general technical guidance', 'active'),
(15, 'Other', 'Uncategorized IT technical interventions and physical installations', 'active')
ON DUPLICATE KEY UPDATE `category_name` = VALUES(`category_name`);

-- Insert Initial Users (Passwords: Admin@123 and Staff@123)
-- Admin: admin / Admin@123 -> $2y$10$abb5kfh3yp/r2c4eLrZcN.PLI6KclTLXoe0xOXsEqvwcwcYBQSy6K
-- Staff1: staff1 / Staff@123 -> $2y$10$gUe1OTPY7LO1VNyjGvIk2u97oW3P8T1DDkv6ELrEb9R419QGPivsy
-- Staff2: kwame_it / Staff@123
-- Staff3: akua_it / Staff@123
INSERT INTO `users` (`id`, `full_name`, `username`, `email`, `phone`, `password`, `role`, `department_id`, `status`) VALUES
(1, 'System Administrator (IT Head)', 'admin', 'admin@kath.gov.gh', '+233 24 100 0001', '$2y$10$abb5kfh3yp/r2c4eLrZcN.PLI6KclTLXoe0xOXsEqvwcwcYBQSy6K', 'admin', 6, 'active'),
(2, 'Kofi Mensah (Support Tech)', 'staff1', 'staff1@kath.gov.gh', '+233 24 200 0002', '$2y$10$gUe1OTPY7LO1VNyjGvIk2u97oW3P8T1DDkv6ELrEb9R419QGPivsy', 'staff', 6, 'active'),
(3, 'Kwame Osei (Network Specialist)', 'kwame_it', 'kwame.osei@kath.gov.gh', '+233 24 300 0003', '$2y$10$gUe1OTPY7LO1VNyjGvIk2u97oW3P8T1DDkv6ELrEb9R419QGPivsy', 'staff', 6, 'active'),
(4, 'Akua Boateng (Systems Analyst)', 'akua_it', 'akua.boateng@kath.gov.gh', '+233 24 400 0004', '$2y$10$gUe1OTPY7LO1VNyjGvIk2u97oW3P8T1DDkv6ELrEb9R419QGPivsy', 'staff', 6, 'active')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);

-- Insert Sample Tasks reflecting realistic KATH IT attachment scenarios
INSERT INTO `tasks` (`id`, `task_number`, `user_id`, `department_id`, `category_id`, `title`, `problem_description`, `work_done`, `priority`, `status`, `date_started`, `date_completed`, `remarks`, `created_at`) VALUES
(1, 'KATH-IT-000001', 2, 1, 1, 'Network connection problem in ENT consulting room', 'The desktop computer in ENT Consultation Room 2 was displaying network unplugged error, cutting off clinical consultation records.', 'Inspected the CAT6 patch cable and discovered a damaged RJ45 clip. Re-terminated the cable using new RJ45 connector, verified link on Cisco switch port 14, and renewed IP via DHCP.', 'High', 'Completed', '2026-09-10 08:30:00', '2026-09-10 09:20:00', 'Tested connection speed and confirmed GHIMS access is fully restored.', '2026-09-10 09:25:00'),

(2, 'KATH-IT-000002', 3, 2, 2, 'GHIMS server unreachable at Main Pharmacy dispensary', 'Pharmacists unable to dispense medication as GHIMS interface timed out with gateway timeout error 504.', 'Traced VLAN routing between Pharmacy subnet 192.168.12.0/24 and core GHIMS cluster. Flushed ARP table and reset the distribution switch port trunk. Verified connection latency < 2ms.', 'Critical', 'Completed', '2026-09-12 10:15:00', '2026-09-12 11:30:00', 'Issue resolved promptly to prevent patient queuing at pharmacy counter.', '2026-09-12 11:35:00'),

(3, 'KATH-IT-000003', 2, 3, 3, 'CCTV camera video feed blackout in Eye Centre Theatre entrance', 'CCTV monitoring room reported Channel 8 (Eye Centre corridor) showed video loss signal.', 'Tested 12V DC power adapter and PoE switch injector. Found PoE injector burned out. Replaced with genuine Hikvision 30W PoE injector and repositioned camera angle.', 'High', 'Completed', '2026-09-14 11:00:00', '2026-09-14 12:45:00', 'Camera video feed restored and recording verified on central NVR.', '2026-09-14 12:50:00'),

(4, 'KATH-IT-000004', 4, 4, 4, 'Laboratory hematology analyzer computer failing to boot', 'Dell OptiPlex desktop connected to Beckman Coulter analyzer made repeated 3-beep alarm and showed black screen.', 'Cleaned dust from motherboard slots, tested RAM modules on test bench, found faulty 8GB DDR4 RAM stick. Installed replacement RAM, ran BIOS diagnostics (passed), and booted into laboratory analysis software.', 'Critical', 'Completed', '2026-09-16 09:00:00', '2026-09-16 10:30:00', 'All lab tests running smoothly. Advised quarterly preventive maintenance.', '2026-09-16 10:40:00'),

(5, 'KATH-IT-000005', 2, 7, 7, 'Outpatient Department prescription label printer paper jam and driver error', 'Zebra thermal label printer printing illegible barcodes and continuously red-blinking paper jam indicator.', 'Cleared thermal head roller residue using 99% isopropyl alcohol, re-calibrated sensor gap, and re-installed latest Zebra ZD220 driver on triage PC.', 'Medium', 'Completed', '2026-09-18 14:00:00', '2026-09-18 15:10:00', 'Printed 10 sample test patient ID wristbands and prescription labels successfully.', '2026-09-18 15:15:00'),

(6, 'KATH-IT-000006', 3, 8, 8, 'IP address conflict on Radiology PACS ultrasound workstation', 'Sonographer workstation showed Windows IP Conflict balloon notification and lost picture archive transfer.', 'Configured static IP reservation 192.168.10.45 in Windows TCP/IPv4 properties and mapped MAC address in KATH Core DHCP server reservation table.', 'High', 'Completed', '2026-09-20 09:45:00', '2026-09-20 10:40:00', 'Confirmed ultrasound scans transferring directly to central PACS storage.', '2026-09-20 10:45:00'),

(7, 'KATH-IT-000007', 4, 9, 9, 'Active Directory account lockout for Human Resource director', 'HR Officer unable to log in after weekend, account locked due to repeated incorrect password attempts.', 'Verified user identity through HR ID card. Unlocked account in Windows Server 2022 Active Directory Users and Computers (ADUC) and enforced password change at next logon.', 'Medium', 'Completed', '2026-09-21 08:00:00', '2026-09-21 08:25:00', 'Officer logged in successfully and set new secure password.', '2026-09-21 08:30:00'),

(8, 'KATH-IT-000008', 2, 10, 10, 'Telephone intercom line crackling and no dial tone at Breast Care Centre', 'Intercom station 321 in Breast Care clinic has static noise and intermittent disconnect during calls to triage.', 'Tested line voltage from MDF room to distribution frame. Found rusted punch terminal on Krone block. Punched to clean terminal pair and tested handset audio clarity.', 'Low', 'Completed', '2026-09-22 13:00:00', '2026-09-22 14:15:00', 'Dial tone clear and two-way audio confirmed with switchboard.', '2026-09-22 14:20:00'),

(9, 'KATH-IT-000009', 3, 11, 11, 'Network cable trunking ripped off during renovation in Finance billing hall', 'Electricians dislodged network conduit, exposing and slicing 3 patch cables feeding cashier computers.', 'Ran 40 meters of new LSZH Cat6 cable through heavy-duty PVC trunking, punched into patch panel ports 18-20, and crimped RJ45 connectors with boots. Certified with cable tester.', 'High', 'Completed', '2026-09-23 10:00:00', '2026-09-23 13:30:00', 'All 3 cashier stations online and processing hospital payment receipts.', '2026-09-23 13:40:00'),

(10, 'KATH-IT-000010', 4, 12, 12, 'Wi-Fi Access Point dropping mobile nursing tablets in Emergency Ward', 'Ubiquiti UniFi AP in Emergency Hall experiencing high packet loss and intermittent disconnects for mobile tablets.', 'Updated UniFi AP firmware to v6.6.65, adjusted 2.4GHz channel from 6 to non-overlapping channel 1, and set 5GHz channel width to VHT40 for better hospital density.', 'Medium', 'Completed', '2026-09-24 15:00:00', '2026-09-24 16:30:00', 'Signal strength improved from -78dBm to -55dBm across the emergency ward.', '2026-09-24 16:45:00'),

(11, 'KATH-IT-000011', 2, 2, 2, 'GHIMS synchronization slow during morning peak hours at OPD', 'Triage nurses reporting 30-40 second delays when submitting patient vitals into the GHIMS web portal.', 'Investigating bandwidth bottlenecks and local switch CPU utilization. Applied QoS prioritization for hospital GHIMS traffic on OPD edge router.', 'High', 'In Progress', '2026-09-28 08:30:00', NULL, 'Monitoring packet traffic logs with network team.', '2026-09-28 08:35:00'),

(12, 'KATH-IT-000012', 3, 3, 3, 'CCTV camera at Main Gate Pharmacy entrance showing intermittent flicker', 'Security supervisor reports periodic blue screen flashes on camera 12 covering pharmacy loading dock.', 'Replaced BNC connector and tested coaxial impedance. Still observing occasional voltage drops.', 'Medium', 'In Progress', '2026-09-29 11:00:00', NULL, 'Waiting for high-ladder equipment to inspect external cabling run.', '2026-09-29 11:20:00'),

(13, 'KATH-IT-000013', 4, 6, 6, 'Windows Blue Screen (BSOD) loop on Administration Accountant laptop', 'HP ProBook experiencing MEMORY_MANAGEMENT BSOD immediately after Windows login.', 'Booted into Windows Safe Mode, rolled back recent faulty display driver update, and initiated sfc /scannow command.', 'High', 'In Progress', '2026-09-29 14:00:00', NULL, 'System files repair in progress.', '2026-09-29 14:15:00'),

(14, 'KATH-IT-000014', 2, 4, 4, 'Hard drive clicking noise and freezing on Laboratory Blood Bank PC', 'Blood bank technician noticed repetitive mechanical clicking sound and prolonged freezing when querying blood inventory.', 'Identified imminent mechanical HDD failure. Scheduled immediate drive clone to 512GB Kingston NVMe SSD and full data backup.', 'Critical', 'Pending', '2026-09-30 08:00:00', NULL, 'Work pending technician availability and new SSD requisition from procurement.', '2026-09-30 08:15:00'),

(15, 'KATH-IT-000015', 3, 1, 1, 'Network switch power supply failure in Radiology Annex', 'All 8 networked workstations and CT scanner reporting PC in Radiology Annex have lost LAN connectivity.', 'Discovered core 24-port PoE switch has power supply fault (amber fault LED blinking).', 'Critical', 'Pending', '2026-09-30 08:45:00', NULL, 'Replacement Cisco Catalyst switch dispatched from central IT server store.', '2026-09-30 09:00:00')
ON DUPLICATE KEY UPDATE `task_number` = VALUES(`task_number`);

-- Insert Initial Task Updates / Chronological Timeline
INSERT INTO `task_updates` (`task_id`, `user_id`, `update_message`, `status`, `created_at`) VALUES
(1, 2, 'Dispatched to ENT Consulting Room 2 after phone dispatch from clinical coordinator.', 'In Progress', '2026-09-10 08:35:00'),
(1, 2, 'Tested physical link with Fluke cable tester. Identified open pin 3 on RJ45 head.', 'In Progress', '2026-09-10 08:55:00'),
(1, 2, 'Re-crimped RJ45 jack, rebooted workstation network interface, confirmed GHIMS access active. Task completed.', 'Completed', '2026-09-10 09:20:00'),

(2, 3, 'Urgent ticket received: Pharmacy staff unable to dispense medication due to GHIMS unreachable error.', 'In Progress', '2026-09-12 10:20:00'),
(2, 3, 'Diagnosed VLAN broadcast storm causing pharmacy access switch to throttle port trunking.', 'In Progress', '2026-09-12 11:05:00'),
(2, 3, 'Isolated faulty network loop on counter 4, cleared STP blocking state. GHIMS operational.', 'Completed', '2026-09-12 11:30:00'),

(11, 2, 'Received slow GHIMS complaint from OPD head nurse. Logging network telemetry.', 'In Progress', '2026-09-28 08:40:00'),
(11, 2, 'Configured QoS bandwidth guarantee on OPD sub-interface. Continuing monitoring through lunch peak.', 'In Progress', '2026-09-28 11:15:00'),

(14, 2, 'Inspected Blood Bank PC. Hard disk smart data confirms bad sectors and head actuator clicking. Recommended SSD replacement.', 'Pending', '2026-09-30 08:20:00'),

(15, 3, 'Emergency notice: Radiology Annex switch is dead. Technicians preparing backup switch with pre-configured VLAN 10 and 20.', 'Pending', '2026-09-30 09:05:00');
