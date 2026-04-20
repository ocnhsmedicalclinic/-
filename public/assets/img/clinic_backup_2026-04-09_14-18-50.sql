-- Medical Clinic System Backup
-- Generated: 2026-04-09 14:18:50
-- Host: localhost via TCP/IP

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Table structure for table `activity_logs`
DROP TABLE IF EXISTS `activity_logs`;


CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user` varchar(100) DEFAULT NULL,
  `student_name` varchar(150) DEFAULT NULL,
  `lrn` varchar(50) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `activity_logs`
INSERT INTO `activity_logs` VALUES("1","SUPERADMIN","Juan Dela Cruz","123456789012","Added Student","2026-02-03 16:07:20");
INSERT INTO `activity_logs` VALUES("2","SUPERADMIN","Maria Santos","987654321098","Updated Student Record","2026-02-03 16:07:20");


-- Table structure for table `backup_log`
DROP TABLE IF EXISTS `backup_log`;


CREATE TABLE `backup_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `backup_date` datetime NOT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `backup_log`
INSERT INTO `backup_log` VALUES("1","2026-02-06 16:19:44","admin","2026-02-06 16:19:44");
INSERT INTO `backup_log` VALUES("2","2026-03-09 14:14:47","admin","2026-03-09 14:14:47");


-- Table structure for table `dismissed_schedules`
DROP TABLE IF EXISTS `dismissed_schedules`;


CREATE TABLE `dismissed_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `sched_id` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`sched_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `dismissed_schedules`
INSERT INTO `dismissed_schedules` VALUES("1","2","2_1770638940","2026-02-09 14:07:57");
INSERT INTO `dismissed_schedules` VALUES("2","19","2_1770638940","2026-02-09 14:08:16");
INSERT INTO `dismissed_schedules` VALUES("3","2","2_1770674400","2026-02-09 14:09:09");


-- Table structure for table `notifications`
DROP TABLE IF EXISTS `notifications`;


CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `notifications`
INSERT INTO `notifications` VALUES("1","registration","New user registered: hers (Doctor)","users.php","1","2026-02-06 21:44:59");
INSERT INTO `notifications` VALUES("2","registration","New user registered: Test User (Medical Staff)","users.php","1","2026-02-06 21:48:35");
INSERT INTO `notifications` VALUES("3","registration","New user registered: hers (Doctor)","users.php","1","2026-02-06 21:54:59");


-- Table structure for table `password_resets`
DROP TABLE IF EXISTS `password_resets`;


CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `password_resets`
INSERT INTO `password_resets` VALUES("15","bsit120301@gmail.com","5faf7dab75438d5400efd7f49a292d86be6dc1150bd3e26175de5be598d559a5","2026-02-05 11:56:48","2026-02-05 17:56:48");
INSERT INTO `password_resets` VALUES("16","bsit120301@gmail.com","e2ed42eec515c13db2025b50bdccdb97e2a21009d7b6db90ff142fe5915a404d","2026-02-05 11:58:40","2026-02-05 17:58:40");
INSERT INTO `password_resets` VALUES("17","admin@gmail.com","d289ac4aef5899ce9b1ad0582a4058414876bdaa32b7b0bebbb76d2ef0a0de11","2026-02-05 12:00:04","2026-02-05 18:00:04");
INSERT INTO `password_resets` VALUES("18","bsit120301@gmail.com","948cbb4bac7b95348d4c76baa6d057413a1da82ea5d8e522de8dfff6eee66492","2026-02-06 03:12:55","2026-02-06 09:12:55");
INSERT INTO `password_resets` VALUES("19","bsit120301@gmail.com","072bf0232602f34f90dbcb176a69e09f84281a0cfe14dc39b2fb001832e32ed9","2026-02-06 03:16:38","2026-02-06 09:16:38");
INSERT INTO `password_resets` VALUES("20","bsit120301@gmail.com","61700ec19cae05a0d848e5e7fb70edc95a12c39d24b3ab75c68e71e4b1306fb2","2026-02-06 03:18:31","2026-02-06 09:18:31");
INSERT INTO `password_resets` VALUES("21","bsit120301@gmail.com","cfe1341731bc4ea5cc576ff0d52456500f5184244c3f2470bf5a830b0854caac","2026-02-06 03:18:41","2026-02-06 09:18:41");
INSERT INTO `password_resets` VALUES("22","markherald3@gmail.com","661e7c0c6a7735b1b8b051b6f5047e1103e125572035a5afd4517c0a96a31660","2026-02-06 03:20:32","2026-02-06 09:20:32");
INSERT INTO `password_resets` VALUES("23","markherald3@gmail.com","90b6b0e048fe17b9083994d2f524582e463da2e6e54bc0406fd5d97440d3f65a","2026-02-06 03:21:11","2026-02-06 09:21:11");
INSERT INTO `password_resets` VALUES("24","markherald3@gmail.com","bb37c3da93e9b6d5dc2e60391851756d65f689eb1997366807af9750ed21eba4","2026-02-06 03:21:22","2026-02-06 09:21:22");
INSERT INTO `password_resets` VALUES("25","bsit120301@gmail.com","7705e05e4589e5b78a63b3491cc6b7e9e1a6d5a83ac0f77f6d951123ae5c50b0","2026-02-06 03:21:53","2026-02-06 09:21:53");
INSERT INTO `password_resets` VALUES("26","bsit120301@gmail.com","490f4cbb75d1ae02e9cda9166e00ea3185cf2e951a055caa80cbbaee0bb977d4","2026-02-06 03:22:21","2026-02-06 09:22:21");
INSERT INTO `password_resets` VALUES("27","bsit120301@gmail.com","e3fb5e8a603233d422138f7468b9b916b55998e3759c3b5e5f2eb810998ad2cc","2026-02-06 03:22:30","2026-02-06 09:22:30");
INSERT INTO `password_resets` VALUES("28","bsit120301@gmail.com","648b1b2e868dd6184571ff180ed6f0d6e6f15c29ac0dfe2f666e7e71cd18284b","2026-02-06 03:24:39","2026-02-06 09:24:39");
INSERT INTO `password_resets` VALUES("29","bsit120301@gmail.com","930d6c08fa4f149ef5b4b2dfea29ead3f5654180503a4c81c2318e033852c903","2026-02-06 03:25:04","2026-02-06 09:25:04");
INSERT INTO `password_resets` VALUES("30","bsit120301@gmail.com","fcf9e32757407addbe14e863488a4d177fc5fb68c272e5742375b83fdd1e8537","2026-02-06 03:26:50","2026-02-06 09:26:50");
INSERT INTO `password_resets` VALUES("31","bsit120301@gmail.com","6ca7a6bfe637da4dcb6dd79ccbdfee389e714628b79382e58fade50d98e22b6f","2026-02-06 03:27:15","2026-02-06 09:27:15");
INSERT INTO `password_resets` VALUES("32","bsit120301@gmail.com","8c6e4be6c176576e3ef711fed679cf81f3fc5530ff906d1f84d01ccd9416b0e3","2026-02-06 03:27:42","2026-02-06 09:27:42");
INSERT INTO `password_resets` VALUES("33","markherald3@gmail.com","33c61b6bd8854d86381ac3a642065ace432f74019bb896556553bdd6e74bf475","2026-02-06 04:55:00","2026-02-06 10:55:00");
INSERT INTO `password_resets` VALUES("34","markherald3@gmail.com","4dd22630db4e630271eae2ccd74d75aace8b131747251a659945a4e5cd2cc074","2026-02-06 04:55:21","2026-02-06 10:55:21");
INSERT INTO `password_resets` VALUES("35","markherald3@gmail.com","086bd3b5447f3d0c698eb24d632670db7206c07c88cbdfea2786c5e04cfe5820","2026-02-06 04:56:28","2026-02-06 10:56:28");
INSERT INTO `password_resets` VALUES("36","markherald3@gmail.com","2f28386d45c7510976fa4970231c1306d2983114b845b550e6096914320ae915","2026-02-06 04:57:13","2026-02-06 10:57:13");
INSERT INTO `password_resets` VALUES("37","markherald3@gmail.com","fe3bb369b80b5d4ad6e6cec91884818a7eef41980914dfb5e5b3b783edcdc6c3","2026-02-06 04:59:30","2026-02-06 10:59:30");
INSERT INTO `password_resets` VALUES("38","bsit120301@gmail.com","208484e18808e40866d70fa5b5563a61a22f5af281bad278e2d005f90a363147","2026-02-06 14:14:22","2026-02-06 20:14:22");
INSERT INTO `password_resets` VALUES("39","bsit120301@gmail.com","2d2eb8fa8b96e3e984c8046333278c4157471f89d279693f80cfe4615a5e0d98","2026-02-06 14:18:06","2026-02-06 20:18:06");
INSERT INTO `password_resets` VALUES("40","bsit120301@gmail.com","99ef8118406cec49c770810808dede74d095b83d3ac412d792eb501a184275eb","2026-02-06 14:18:12","2026-02-06 20:18:12");
INSERT INTO `password_resets` VALUES("41","bsit120301@gmail.com","7652ab3e78a42ddc9e55502cb912426ae0288583ad268ce27c45d99381aa5b47","2026-02-06 14:21:58","2026-02-06 20:21:58");
INSERT INTO `password_resets` VALUES("42","bsit120301@gmail.com","7b41cf7bded78a87ae2eb6cb5096801f4f29404a1481cc59cf66ccfd9dcd7137","2026-02-06 14:25:28","2026-02-06 20:25:28");
INSERT INTO `password_resets` VALUES("43","bsit120301@gmail.com","c8372e37667bdde04372f1711346ae4157a3b1f6fcfce62ac6e0161fec763a71","2026-02-06 14:25:39","2026-02-06 20:25:39");
INSERT INTO `password_resets` VALUES("44","bsit120301@gmail.com","a913e0cf3c3020aeee9037055b0982948bd20b9f36868b0b661cef02d7a4b42e","2026-02-06 14:26:15","2026-02-06 20:26:15");


-- Table structure for table `student_files`
DROP TABLE IF EXISTS `student_files`;


CREATE TABLE `student_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `file_type` varchar(50) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL,
  `uploaded_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `student_files`


-- Table structure for table `students`
DROP TABLE IF EXISTS `students`;


CREATE TABLE `students` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `lrn` varchar(20) DEFAULT NULL,
  `curriculum` varchar(50) DEFAULT NULL,
  `address` varchar(100) DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `birthplace` varchar(100) DEFAULT NULL,
  `religion` varchar(100) NOT NULL,
  `guardian` varchar(100) DEFAULT NULL,
  `contact` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'active',
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_at` datetime DEFAULT NULL,
  `health_exam_json` longtext DEFAULT NULL,
  `treatment_logs_json` longtext DEFAULT NULL,
  `consent_data_json` longtext DEFAULT NULL,
  `consent_front_file` varchar(255) DEFAULT NULL,
  `consent_back_file` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `students`
INSERT INTO `students` VALUES("2","PIGAO, ROYCE M","12345678910","BEP","Caste","Male","2004-05-05","Olongapo City","INC","PRINCE TALUA","09987654321","2026-01-30 13:42:43","active","0","0000-00-00 00:00:00","{\"date_7\":\"\",\"date_8\":\"\",\"date_9\":\"\",\"date_10\":\"\",\"date_11\":\"\",\"date_12\":\"\",\"temperature_7\":\"\",\"temperature_8\":\"\",\"temperature_9\":\"\",\"temperature_10\":\"\",\"temperature_11\":\"\",\"temperature_12\":\"\",\"blood_pressure_7\":\"\",\"blood_pressure_8\":\"\",\"blood_pressure_9\":\"\",\"blood_pressure_10\":\"\",\"blood_pressure_11\":\"\",\"blood_pressure_12\":\"\",\"cardiac_pulse_rate_7\":\"\",\"cardiac_pulse_rate_8\":\"\",\"cardiac_pulse_rate_9\":\"\",\"cardiac_pulse_rate_10\":\"\",\"cardiac_pulse_rate_11\":\"\",\"cardiac_pulse_rate_12\":\"\",\"respiratory_rate_7\":\"\",\"respiratory_rate_8\":\"\",\"respiratory_rate_9\":\"\",\"respiratory_rate_10\":\"\",\"respiratory_rate_11\":\"\",\"respiratory_rate_12\":\"\",\"height_7\":\"180cm\",\"height_8\":\"\",\"height_9\":\"\",\"height_10\":\"\",\"height_11\":\"\",\"height_12\":\"\",\"weight_7\":\"70klg\",\"weight_8\":\"\",\"weight_9\":\"\",\"weight_10\":\"\",\"weight_11\":\"\",\"weight_12\":\"\",\"bmi_weight_7\":\"\",\"bmi_weight_8\":\"\",\"bmi_weight_9\":\"\",\"bmi_weight_10\":\"\",\"bmi_weight_11\":\"\",\"bmi_weight_12\":\"\",\"bmi_height_7\":\"\",\"bmi_height_8\":\"\",\"bmi_height_9\":\"\",\"bmi_height_10\":\"\",\"bmi_height_11\":\"\",\"bmi_height_12\":\"\",\"snellen_7\":\"\",\"snellen_8\":\"\",\"snellen_9\":\"\",\"snellen_10\":\"\",\"snellen_11\":\"\",\"snellen_12\":\"\",\"eye_chart__near__7\":\"\",\"eye_chart__near__8\":\"\",\"eye_chart__near__9\":\"\",\"eye_chart__near__10\":\"\",\"eye_chart__near__11\":\"\",\"eye_chart__near__12\":\"\",\"ishihara_chart_7\":\"\",\"ishihara_chart_8\":\"\",\"ishihara_chart_9\":\"\",\"ishihara_chart_10\":\"\",\"ishihara_chart_11\":\"\",\"ishihara_chart_12\":\"\",\"auditory_7\":\"\",\"auditory_8\":\"\",\"auditory_9\":\"\",\"auditory_10\":\"\",\"auditory_11\":\"\",\"auditory_12\":\"\",\"skin_scalp_7\":\"\",\"skin_scalp_8\":\"\",\"skin_scalp_9\":\"\",\"skin_scalp_10\":\"\",\"skin_scalp_11\":\"\",\"skin_scalp_12\":\"\",\"eyes_ears_nose_7\":\"\",\"eyes_ears_nose_8\":\"\",\"eyes_ears_nose_9\":\"\",\"eyes_ears_nose_10\":\"\",\"eyes_ears_nose_11\":\"\",\"eyes_ears_nose_12\":\"\",\"mouth_neck_throat_7\":\"\",\"mouth_neck_throat_8\":\"\",\"mouth_neck_throat_9\":\"\",\"mouth_neck_throat_10\":\"\",\"mouth_neck_throat_11\":\"\",\"mouth_neck_throat_12\":\"\",\"lungs_heart_7\":\"\",\"lungs_heart_8\":\"\",\"lungs_heart_9\":\"\",\"lungs_heart_10\":\"\",\"lungs_heart_11\":\"\",\"lungs_heart_12\":\"\",\"abdomen_genitalia_7\":\"\",\"abdomen_genitalia_8\":\"\",\"abdomen_genitalia_9\":\"\",\"abdomen_genitalia_10\":\"\",\"abdomen_genitalia_11\":\"\",\"abdomen_genitalia_12\":\"\",\"spine_extremities_7\":\"\",\"spine_extremities_8\":\"\",\"spine_extremities_9\":\"\",\"spine_extremities_10\":\"\",\"spine_extremities_11\":\"\",\"spine_extremities_12\":\"\",\"iron-folic_acid_supplementation__v_o_x__7\":\"\",\"iron-folic_acid_supplementation__v_o_x__8\":\"\",\"iron-folic_acid_supplementation__v_o_x__9\":\"\",\"iron-folic_acid_supplementation__v_o_x__10\":\"\",\"iron-folic_acid_supplementation__v_o_x__11\":\"\",\"iron-folic_acid_supplementation__v_o_x__12\":\"\",\"deworming__v_o_x__7\":\"\",\"deworming__v_o_x__8\":\"\",\"deworming__v_o_x__9\":\"\",\"deworming__v_o_x__10\":\"\",\"deworming__v_o_x__11\":\"\",\"deworming__v_o_x__12\":\"\",\"immunization__specify__7\":\"\",\"immunization__specify__8\":\"\",\"immunization__specify__9\":\"\",\"immunization__specify__10\":\"\",\"immunization__specify__11\":\"\",\"immunization__specify__12\":\"\",\"sbfp_beneficiary__v_o_x__7\":\"\",\"sbfp_beneficiary__v_o_x__8\":\"\",\"sbfp_beneficiary__v_o_x__9\":\"\",\"sbfp_beneficiary__v_o_x__10\":\"\",\"sbfp_beneficiary__v_o_x__11\":\"\",\"sbfp_beneficiary__v_o_x__12\":\"\",\"4ps_beneficiary__v_o_x__7\":\"\",\"4ps_beneficiary__v_o_x__8\":\"\",\"4ps_beneficiary__v_o_x__9\":\"\",\"4ps_beneficiary__v_o_x__10\":\"\",\"4ps_beneficiary__v_o_x__11\":\"\",\"4ps_beneficiary__v_o_x__12\":\"\",\"menarche_7\":\"\",\"menarche_8\":\"\",\"menarche_9\":\"\",\"menarche_10\":\"\",\"menarche_11\":\"\",\"menarche_12\":\"\",\"others__specify_7\":\"\",\"others__specify_8\":\"\",\"others__specify_9\":\"\",\"others__specify_10\":\"\",\"others__specify_11\":\"\",\"others__specify_12\":\"\",\"examiner_7\":\"\",\"examiner_8\":\"\",\"examiner_9\":\"\",\"examiner_10\":\"\",\"examiner_11\":\"\",\"examiner_12\":\"\"}","[{\"grade\":\"8\",\"date\":\"2026-02-05\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"int_pharm\":\"1\",\"next_visit\":\"2026-02-09T13:09\",\"email\":\"markherald3@gmail.com\",\"email_sent\":\"1\"},{\"grade\":\"8\",\"date\":\"2026-02-05\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"int_trad\":\"1\",\"next_visit\":\"2026-02-07T23:00\",\"email\":\"\",\"email_sent\":\"\"},{\"grade\":\"8\",\"date\":\"2026-02-05\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"int_trad\":\"1\",\"next_visit\":\"2026-02-09T14:14\",\"email\":\"\",\"email_sent\":\"\"},{\"grade\":\"8\",\"date\":\"2026-02-09\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"int_pharm\":\"1\",\"next_visit\":\"2026-02-09T23:00\",\"email\":\"\",\"email_sent\":\"\"}]","","","");
INSERT INTO `students` VALUES("3","guiyab, ROYCE b.","12345678910","BEP","Lincoln","Male","2026-01-08","Olongapo City","Catholic","RON","09123456789","2026-01-30 13:53:32","active","0","0000-00-00 00:00:00","{\"date_7\":\"\",\"date_8\":\"\",\"date_9\":\"\",\"date_10\":\"\",\"date_11\":\"\",\"date_12\":\"\",\"temperature_7\":\"\",\"temperature_8\":\"\",\"temperature_9\":\"\",\"temperature_10\":\"\",\"temperature_11\":\"\",\"temperature_12\":\"\",\"blood_pressure_7\":\"\",\"blood_pressure_8\":\"\",\"blood_pressure_9\":\"\",\"blood_pressure_10\":\"\",\"blood_pressure_11\":\"\",\"blood_pressure_12\":\"\",\"cardiac_pulse_rate_7\":\"\",\"cardiac_pulse_rate_8\":\"\",\"cardiac_pulse_rate_9\":\"\",\"cardiac_pulse_rate_10\":\"\",\"cardiac_pulse_rate_11\":\"\",\"cardiac_pulse_rate_12\":\"\",\"respiratory_rate_7\":\"\",\"respiratory_rate_8\":\"\",\"respiratory_rate_9\":\"\",\"respiratory_rate_10\":\"\",\"respiratory_rate_11\":\"\",\"respiratory_rate_12\":\"\",\"height_7\":\"165cm\",\"height_8\":\"\",\"height_9\":\"\",\"height_10\":\"\",\"height_11\":\"\",\"height_12\":\"\",\"weight_7\":\"60klg\",\"weight_8\":\"\",\"weight_9\":\"\",\"weight_10\":\"\",\"weight_11\":\"\",\"weight_12\":\"\",\"bmi_weight_7\":\"\",\"bmi_weight_8\":\"\",\"bmi_weight_9\":\"\",\"bmi_weight_10\":\"\",\"bmi_weight_11\":\"\",\"bmi_weight_12\":\"\",\"bmi_height_7\":\"\",\"bmi_height_8\":\"\",\"bmi_height_9\":\"\",\"bmi_height_10\":\"\",\"bmi_height_11\":\"\",\"bmi_height_12\":\"\",\"snellen_7\":\"\",\"snellen_8\":\"\",\"snellen_9\":\"\",\"snellen_10\":\"\",\"snellen_11\":\"\",\"snellen_12\":\"\",\"eye_chart__near__7\":\"\",\"eye_chart__near__8\":\"\",\"eye_chart__near__9\":\"\",\"eye_chart__near__10\":\"\",\"eye_chart__near__11\":\"\",\"eye_chart__near__12\":\"\",\"ishihara_chart_7\":\"\",\"ishihara_chart_8\":\"\",\"ishihara_chart_9\":\"\",\"ishihara_chart_10\":\"\",\"ishihara_chart_11\":\"\",\"ishihara_chart_12\":\"\",\"auditory_7\":\"\",\"auditory_8\":\"\",\"auditory_9\":\"\",\"auditory_10\":\"\",\"auditory_11\":\"\",\"auditory_12\":\"\",\"skin_scalp_7\":\"\",\"skin_scalp_8\":\"\",\"skin_scalp_9\":\"\",\"skin_scalp_10\":\"\",\"skin_scalp_11\":\"\",\"skin_scalp_12\":\"\",\"eyes_ears_nose_7\":\"\",\"eyes_ears_nose_8\":\"\",\"eyes_ears_nose_9\":\"\",\"eyes_ears_nose_10\":\"\",\"eyes_ears_nose_11\":\"\",\"eyes_ears_nose_12\":\"\",\"mouth_neck_throat_7\":\"\",\"mouth_neck_throat_8\":\"\",\"mouth_neck_throat_9\":\"\",\"mouth_neck_throat_10\":\"\",\"mouth_neck_throat_11\":\"\",\"mouth_neck_throat_12\":\"\",\"lungs_heart_7\":\"\",\"lungs_heart_8\":\"\",\"lungs_heart_9\":\"\",\"lungs_heart_10\":\"\",\"lungs_heart_11\":\"\",\"lungs_heart_12\":\"\",\"abdomen_genitalia_7\":\"\",\"abdomen_genitalia_8\":\"\",\"abdomen_genitalia_9\":\"\",\"abdomen_genitalia_10\":\"\",\"abdomen_genitalia_11\":\"\",\"abdomen_genitalia_12\":\"\",\"spine_extremities_7\":\"\",\"spine_extremities_8\":\"\",\"spine_extremities_9\":\"\",\"spine_extremities_10\":\"\",\"spine_extremities_11\":\"\",\"spine_extremities_12\":\"\",\"iron-folic_acid_supplementation__v_o_x__7\":\"\",\"iron-folic_acid_supplementation__v_o_x__8\":\"\",\"iron-folic_acid_supplementation__v_o_x__9\":\"\",\"iron-folic_acid_supplementation__v_o_x__10\":\"\",\"iron-folic_acid_supplementation__v_o_x__11\":\"\",\"iron-folic_acid_supplementation__v_o_x__12\":\"\",\"deworming__v_o_x__7\":\"\",\"deworming__v_o_x__8\":\"\",\"deworming__v_o_x__9\":\"\",\"deworming__v_o_x__10\":\"\",\"deworming__v_o_x__11\":\"\",\"deworming__v_o_x__12\":\"\",\"immunization__specify__7\":\"\",\"immunization__specify__8\":\"\",\"immunization__specify__9\":\"\",\"immunization__specify__10\":\"\",\"immunization__specify__11\":\"\",\"immunization__specify__12\":\"\",\"sbfp_beneficiary__v_o_x__7\":\"\",\"sbfp_beneficiary__v_o_x__8\":\"\",\"sbfp_beneficiary__v_o_x__9\":\"\",\"sbfp_beneficiary__v_o_x__10\":\"\",\"sbfp_beneficiary__v_o_x__11\":\"\",\"sbfp_beneficiary__v_o_x__12\":\"\",\"4ps_beneficiary__v_o_x__7\":\"\",\"4ps_beneficiary__v_o_x__8\":\"\",\"4ps_beneficiary__v_o_x__9\":\"\",\"4ps_beneficiary__v_o_x__10\":\"\",\"4ps_beneficiary__v_o_x__11\":\"\",\"4ps_beneficiary__v_o_x__12\":\"\",\"menarche_7\":\"\",\"menarche_8\":\"\",\"menarche_9\":\"\",\"menarche_10\":\"\",\"menarche_11\":\"\",\"menarche_12\":\"\",\"others__specify_7\":\"\",\"others__specify_8\":\"\",\"others__specify_9\":\"\",\"others__specify_10\":\"\",\"others__specify_11\":\"\",\"others__specify_12\":\"\",\"examiner_7\":\"\",\"examiner_8\":\"\",\"examiner_9\":\"\",\"examiner_10\":\"\",\"examiner_11\":\"\",\"examiner_12\":\"\"}","[{\"grade\":\"9\",\"date\":\"2026-01-05\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"next_visit\":\"\",\"email\":\"\",\"email_sent\":\"\"},{\"grade\":\"9\",\"date\":\"2026-02-05\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"next_visit\":\"\",\"email\":\"\",\"email_sent\":\"\"},{\"grade\":\"9\",\"date\":\"\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"next_visit\":\"\",\"email\":\"\",\"email_sent\":\"\"}]","","","");
INSERT INTO `students` VALUES("4","dela cruz, juan d.","01234567489","STE","Phase 1 block 24 lot 11 13 lincoln heights san pablo dinalupihan bataan","Male","2021-01-06","Olongapo City","Roman Catholic","Ronald Batongbakal","0987654322","2026-02-04 15:20:45","active","0","0000-00-00 00:00:00","{\"date_7\":\"\",\"date_8\":\"\",\"date_9\":\"\",\"date_10\":\"\",\"date_11\":\"\",\"date_12\":\"\",\"temperature_7\":\"\",\"temperature_8\":\"\",\"temperature_9\":\"\",\"temperature_10\":\"\",\"temperature_11\":\"\",\"temperature_12\":\"\",\"blood_pressure_7\":\"\",\"blood_pressure_8\":\"\",\"blood_pressure_9\":\"\",\"blood_pressure_10\":\"\",\"blood_pressure_11\":\"\",\"blood_pressure_12\":\"\",\"cardiac_pulse_rate_7\":\"\",\"cardiac_pulse_rate_8\":\"\",\"cardiac_pulse_rate_9\":\"\",\"cardiac_pulse_rate_10\":\"\",\"cardiac_pulse_rate_11\":\"\",\"cardiac_pulse_rate_12\":\"\",\"respiratory_rate_7\":\"\",\"respiratory_rate_8\":\"\",\"respiratory_rate_9\":\"\",\"respiratory_rate_10\":\"\",\"respiratory_rate_11\":\"\",\"respiratory_rate_12\":\"\",\"height_7\":\"\",\"height_8\":\"\",\"height_9\":\"\",\"height_10\":\"\",\"height_11\":\"\",\"height_12\":\"\",\"weight_7\":\"70klg\",\"weight_8\":\"\",\"weight_9\":\"\",\"weight_10\":\"\",\"weight_11\":\"\",\"weight_12\":\"\",\"bmi_weight_7\":\"\",\"bmi_weight_8\":\"\",\"bmi_weight_9\":\"\",\"bmi_weight_10\":\"\",\"bmi_weight_11\":\"\",\"bmi_weight_12\":\"\",\"bmi_height_7\":\"\",\"bmi_height_8\":\"\",\"bmi_height_9\":\"\",\"bmi_height_10\":\"\",\"bmi_height_11\":\"\",\"bmi_height_12\":\"\",\"snellen_7\":\"\",\"snellen_8\":\"\",\"snellen_9\":\"\",\"snellen_10\":\"\",\"snellen_11\":\"\",\"snellen_12\":\"\",\"eye_chart__near__7\":\"\",\"eye_chart__near__8\":\"\",\"eye_chart__near__9\":\"\",\"eye_chart__near__10\":\"\",\"eye_chart__near__11\":\"\",\"eye_chart__near__12\":\"\",\"ishihara_chart_7\":\"\",\"ishihara_chart_8\":\"\",\"ishihara_chart_9\":\"\",\"ishihara_chart_10\":\"\",\"ishihara_chart_11\":\"\",\"ishihara_chart_12\":\"\",\"auditory_7\":\"\",\"auditory_8\":\"\",\"auditory_9\":\"\",\"auditory_10\":\"\",\"auditory_11\":\"\",\"auditory_12\":\"\",\"skin_scalp_7\":\"\",\"skin_scalp_8\":\"\",\"skin_scalp_9\":\"\",\"skin_scalp_10\":\"\",\"skin_scalp_11\":\"\",\"skin_scalp_12\":\"\",\"eyes_ears_nose_7\":\"\",\"eyes_ears_nose_8\":\"\",\"eyes_ears_nose_9\":\"\",\"eyes_ears_nose_10\":\"\",\"eyes_ears_nose_11\":\"\",\"eyes_ears_nose_12\":\"\",\"mouth_neck_throat_7\":\"\",\"mouth_neck_throat_8\":\"\",\"mouth_neck_throat_9\":\"\",\"mouth_neck_throat_10\":\"\",\"mouth_neck_throat_11\":\"\",\"mouth_neck_throat_12\":\"\",\"lungs_heart_7\":\"\",\"lungs_heart_8\":\"\",\"lungs_heart_9\":\"\",\"lungs_heart_10\":\"\",\"lungs_heart_11\":\"\",\"lungs_heart_12\":\"\",\"abdomen_genitalia_7\":\"\",\"abdomen_genitalia_8\":\"\",\"abdomen_genitalia_9\":\"\",\"abdomen_genitalia_10\":\"\",\"abdomen_genitalia_11\":\"\",\"abdomen_genitalia_12\":\"\",\"spine_extremities_7\":\"\",\"spine_extremities_8\":\"\",\"spine_extremities_9\":\"\",\"spine_extremities_10\":\"\",\"spine_extremities_11\":\"\",\"spine_extremities_12\":\"\",\"iron-folic_acid_supplementation__v_o_x__7\":\"\",\"iron-folic_acid_supplementation__v_o_x__8\":\"\",\"iron-folic_acid_supplementation__v_o_x__9\":\"\",\"iron-folic_acid_supplementation__v_o_x__10\":\"\",\"iron-folic_acid_supplementation__v_o_x__11\":\"\",\"iron-folic_acid_supplementation__v_o_x__12\":\"\",\"deworming__v_o_x__7\":\"\",\"deworming__v_o_x__8\":\"\",\"deworming__v_o_x__9\":\"\",\"deworming__v_o_x__10\":\"\",\"deworming__v_o_x__11\":\"\",\"deworming__v_o_x__12\":\"\",\"immunization__specify__7\":\"\",\"immunization__specify__8\":\"\",\"immunization__specify__9\":\"\",\"immunization__specify__10\":\"\",\"immunization__specify__11\":\"\",\"immunization__specify__12\":\"\",\"sbfp_beneficiary__v_o_x__7\":\"\",\"sbfp_beneficiary__v_o_x__8\":\"\",\"sbfp_beneficiary__v_o_x__9\":\"\",\"sbfp_beneficiary__v_o_x__10\":\"\",\"sbfp_beneficiary__v_o_x__11\":\"\",\"sbfp_beneficiary__v_o_x__12\":\"\",\"4ps_beneficiary__v_o_x__7\":\"\",\"4ps_beneficiary__v_o_x__8\":\"\",\"4ps_beneficiary__v_o_x__9\":\"\",\"4ps_beneficiary__v_o_x__10\":\"\",\"4ps_beneficiary__v_o_x__11\":\"\",\"4ps_beneficiary__v_o_x__12\":\"\",\"menarche_7\":\"\",\"menarche_8\":\"\",\"menarche_9\":\"\",\"menarche_10\":\"\",\"menarche_11\":\"\",\"menarche_12\":\"\",\"others__specify_7\":\"\",\"others__specify_8\":\"\",\"others__specify_9\":\"\",\"others__specify_10\":\"\",\"others__specify_11\":\"\",\"others__specify_12\":\"\",\"examiner_7\":\"\",\"examiner_8\":\"\",\"examiner_9\":\"\",\"examiner_10\":\"\",\"examiner_11\":\"\",\"examiner_12\":\"\"}","[{\"grade\":\"8\",\"date\":\"2026-02-07\",\"complaint\":\"headache\",\"treatment\":\"paracetamol\",\"attended\":\"doc\",\"next_visit\":\"2026-02-07T18:00\",\"email\":\"\",\"email_sent\":\"\"}]","","","");


-- Table structure for table `treatment_records`
DROP TABLE IF EXISTS `treatment_records`;


CREATE TABLE `treatment_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `student_id` int(11) NOT NULL,
  `date_of_visit` date DEFAULT NULL,
  `complaint` varchar(255) DEFAULT NULL,
  `findings` varchar(255) DEFAULT NULL,
  `treatment_given` varchar(255) DEFAULT NULL,
  `medication` varchar(255) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `attended_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `student_id` (`student_id`),
  CONSTRAINT `treatment_records_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `treatment_records`


-- Table structure for table `users`
DROP TABLE IF EXISTS `users`;


CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `role` enum('admin','staff','viewer') DEFAULT 'staff',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `session_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_username` (`username`),
  KEY `idx_user_active` (`is_active`),
  KEY `idx_user_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `users`
INSERT INTO `users` VALUES("2","admin","$2y$12$oED.MNQFmc8HLXyREt1M7.SjVqgn/ItcYqia0X/v1qve8E3q1ffNm","admin@gmail.com","admin","2026-02-04 11:00:44","2026-02-09 14:09:17","2026-02-06 08:24:15","1","787f031f83ac6dcde278e7e424e27f025aaf5b0c946fedc1e7acd667bfa4e51e");
INSERT INTO `users` VALUES("3","medical","$2y$12$EJy6z6BNs326NeDfBtD4/OGpOZT4bzO2mUcihzHb1WgKZmrHOOhma","medical@gmail.com","admin","2026-02-05 16:43:37","2026-02-06 20:59:24","2026-01-28 09:31:15","1","");
INSERT INTO `users` VALUES("7","adminss","$2y$12$fkR/IXPuM7DknKdcuvT4f.zixb7HV34BmCOQNh2TEh.HKg87WogWu","bsit12033301@gmail.com","","2026-02-06 11:55:00","2026-02-06 21:50:45","2026-01-16 19:02:15","0","");
INSERT INTO `users` VALUES("9","superadmin","$2y$12$2KpGIygdMTlka7dyhJyKY.Pok/2D8.BnY2nk81wvmsxoA67fjbnTC","ocnhsmedicalclinic@gmail.com","admin","2026-02-06 20:35:37","2026-02-06 20:35:37","","1","");
INSERT INTO `users` VALUES("15","sdasdasd","$2y$12$rBVFCDJ/8AD3Nma0T3XnuOy0JKJiox/o4Yok9gN9b8AWr3F5KyJZC","admssin@gmail.com","","2026-02-06 21:32:55","2026-02-06 21:32:55","","0","");
INSERT INTO `users` VALUES("19","hers","$2y$12$YZiUB5axA5SpoyoMjpbJdOXiLLRNaF.79sl8yaHMHIgVBHmUSUy/e","markherald3@gmail.com","","2026-02-06 21:54:59","2026-02-09 14:08:13","","1","fd18ce9246aa621adb18018df7c26902eab6d4f6f13aa14f5c9a79b58956a777");
INSERT INTO `users` VALUES("20","admins","$2y$12$oXM511KytvELhYWdVa9/3OkPs96vfGh.f/by4KmNSvJQHSaN.j7CS","adminsss@gmail.com","admin","2026-02-09 11:21:25","2026-02-09 11:21:25","","1","");


SET FOREIGN_KEY_CHECKS=1;
