SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `Users`;
CREATE TABLE `Users` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `Username` varchar(50) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `IsAdmin` tinyint(1) NOT NULL DEFAULT '0',
  `IsActive` tinyint(1) NOT NULL DEFAULT '1',
  `DateCreated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `DateUpdated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Username` (`Username`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


INSERT INTO `Users` (`ID`, `FirstName`, `LastName`, `Username`, `Password`, `IsAdmin`, `IsActive`, `DateCreated`, `DateUpdated`) VALUES
(1,'Carlos','Rincon','crincon','plswork123',0,1,'2026-09-10 01:35:51','2026-09-10 01:35:51'),
(2,'Application','Administrator','root','$2b$12$GNnVvvcPEkik/pDmbLV4d.EKCgqqQi/0AoYhGakR/gVVznKhNdiLO',1,1,'2026-09-26 00:00:00','2026-09-26 00:00:00');
 

DROP TABLE IF EXISTS `Contacts`;
CREATE TABLE `Contacts` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `FirstName` varchar(50) NOT NULL,
  `LastName` varchar(50) NOT NULL,
  `Email` varchar(100) DEFAULT NULL,
  `PhoneNumber` varchar(20) DEFAULT NULL,
  `DateCreated` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `DateUpdated` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `UserID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `UserID` (`UserID`),
  KEY `idx_contact_search` (`LastName`,`FirstName`,`Email`),
  CONSTRAINT `Contacts_ibfk_1` FOREIGN KEY (`UserID`) REFERENCES `Users` (`ID`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


INSERT INTO `Contacts` (`ID`, `FirstName`, `LastName`, `Email`, `PhoneNumber`, `DateCreated`, `DateUpdated`, `UserID`) VALUES
(1,'John','Aedo','john.aedo@gmail.com','561-720-6720','2026-09-10 01:36:52','2026-09-10 01:36:52',1);

SET FOREIGN_KEY_CHECKS = 1;

