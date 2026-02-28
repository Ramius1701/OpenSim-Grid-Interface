-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Erstellungszeit: 19. Okt 2025 um 11:02
-- Server-Version: 10.6.22-MariaDB-0ubuntu0.22.04.1
-- PHP-Version: 8.1.2-1ubuntu2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Datenbank: `robust`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `AgentPrefs`
--

CREATE TABLE `AgentPrefs` (
  `PrincipalID` char(36) NOT NULL,
  `AccessPrefs` char(2) NOT NULL DEFAULT 'M',
  `HoverHeight` double(30,27) NOT NULL DEFAULT 0.000000000000000000000000000,
  `Language` char(5) NOT NULL DEFAULT 'en-us',
  `LanguageIsPublic` tinyint(1) NOT NULL DEFAULT 1,
  `PermEveryone` int(6) NOT NULL DEFAULT 0,
  `PermGroup` int(6) NOT NULL DEFAULT 0,
  `PermNextOwner` int(6) NOT NULL DEFAULT 532480
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `assets`
--

CREATE TABLE `assets` (
  `name` varchar(64) NOT NULL,
  `description` varchar(64) NOT NULL,
  `assetType` tinyint(4) NOT NULL,
  `local` tinyint(1) NOT NULL,
  `temporary` tinyint(1) NOT NULL,
  `data` longblob NOT NULL,
  `id` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `create_time` int(11) DEFAULT 0,
  `access_time` int(11) DEFAULT 0,
  `asset_flags` int(11) NOT NULL DEFAULT 0,
  `CreatorID` varchar(128) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `auth`
--

CREATE TABLE `auth` (
  `UUID` char(36) NOT NULL,
  `passwordHash` char(32) NOT NULL DEFAULT '',
  `passwordSalt` char(32) NOT NULL DEFAULT '',
  `webLoginKey` varchar(255) NOT NULL DEFAULT '',
  `accountType` varchar(32) NOT NULL DEFAULT 'UserAccount'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Avatars`
--

CREATE TABLE `Avatars` (
  `PrincipalID` char(36) NOT NULL,
  `Name` varchar(32) NOT NULL,
  `Value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `balances`
--

CREATE TABLE `balances` (
  `user` varchar(36) NOT NULL,
  `balance` int(10) NOT NULL,
  `status` tinyint(2) DEFAULT NULL,
  `type` tinyint(2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.4';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `classifieds`
--

CREATE TABLE `classifieds` (
  `classifieduuid` char(36) NOT NULL,
  `creatoruuid` char(36) NOT NULL,
  `creationdate` int(20) NOT NULL,
  `expirationdate` int(20) NOT NULL,
  `category` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `parceluuid` char(36) NOT NULL,
  `parentestate` int(11) NOT NULL,
  `snapshotuuid` char(36) NOT NULL,
  `simname` varchar(255) NOT NULL,
  `posglobal` varchar(255) NOT NULL,
  `parcelname` varchar(255) NOT NULL,
  `classifiedflags` int(8) NOT NULL,
  `priceforlisting` int(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Friends`
--

CREATE TABLE `Friends` (
  `PrincipalID` varchar(255) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `Friend` varchar(255) NOT NULL,
  `Flags` varchar(16) NOT NULL DEFAULT '0',
  `Offered` varchar(32) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `GridUser`
--

CREATE TABLE `GridUser` (
  `UserID` varchar(255) NOT NULL,
  `HomeRegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `HomePosition` char(64) NOT NULL DEFAULT '<0,0,0>',
  `HomeLookAt` char(64) NOT NULL DEFAULT '<0,0,0>',
  `LastRegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `LastPosition` char(64) NOT NULL DEFAULT '<0,0,0>',
  `LastLookAt` char(64) NOT NULL DEFAULT '<0,0,0>',
  `Online` char(5) NOT NULL DEFAULT 'false',
  `Login` char(16) NOT NULL DEFAULT '0',
  `Logout` char(16) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `hg_traveling_data`
--

CREATE TABLE `hg_traveling_data` (
  `SessionID` varchar(36) NOT NULL,
  `UserID` varchar(36) NOT NULL,
  `GridExternalName` varchar(255) NOT NULL DEFAULT '',
  `ServiceToken` varchar(255) NOT NULL DEFAULT '',
  `ClientIPAddress` varchar(16) NOT NULL DEFAULT '',
  `MyIPAddress` varchar(16) NOT NULL DEFAULT '',
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `im_offline`
--

CREATE TABLE `im_offline` (
  `ID` mediumint(9) NOT NULL,
  `PrincipalID` char(36) NOT NULL DEFAULT '',
  `FromID` char(36) NOT NULL DEFAULT '',
  `Message` text NOT NULL,
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `inventoryfolders`
--

CREATE TABLE `inventoryfolders` (
  `folderName` varchar(64) DEFAULT NULL,
  `type` smallint(6) NOT NULL DEFAULT 0,
  `version` int(11) NOT NULL DEFAULT 0,
  `folderID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `agentID` char(36) DEFAULT NULL,
  `parentFolderID` char(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `inventoryitems`
--

CREATE TABLE `inventoryitems` (
  `assetID` varchar(36) DEFAULT NULL,
  `assetType` int(11) DEFAULT NULL,
  `inventoryName` varchar(64) DEFAULT NULL,
  `inventoryDescription` varchar(128) DEFAULT NULL,
  `inventoryNextPermissions` int(10) UNSIGNED DEFAULT NULL,
  `inventoryCurrentPermissions` int(10) UNSIGNED DEFAULT NULL,
  `invType` int(11) DEFAULT NULL,
  `creatorID` varchar(255) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `inventoryBasePermissions` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `inventoryEveryOnePermissions` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `salePrice` int(11) NOT NULL DEFAULT 0,
  `saleType` tinyint(4) NOT NULL DEFAULT 0,
  `creationDate` int(11) NOT NULL DEFAULT 0,
  `groupID` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `groupOwned` tinyint(4) NOT NULL DEFAULT 0,
  `flags` int(11) UNSIGNED NOT NULL DEFAULT 0,
  `inventoryID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `avatarID` char(36) DEFAULT NULL,
  `parentFolderID` char(36) DEFAULT NULL,
  `inventoryGroupPermissions` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `migrations`
--

CREATE TABLE `migrations` (
  `name` varchar(100) DEFAULT NULL,
  `version` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `MuteList`
--

CREATE TABLE `MuteList` (
  `AgentID` char(36) NOT NULL,
  `MuteID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `MuteName` varchar(64) NOT NULL DEFAULT '',
  `MuteType` int(11) NOT NULL DEFAULT 1,
  `MuteFlags` int(11) NOT NULL DEFAULT 0,
  `Stamp` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_groups`
--

CREATE TABLE `os_groups_groups` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `Location` varchar(255) NOT NULL DEFAULT '',
  `Name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `Charter` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `InsigniaID` char(36) NOT NULL DEFAULT '',
  `FounderID` char(36) NOT NULL DEFAULT '',
  `MembershipFee` int(11) NOT NULL DEFAULT 0,
  `OpenEnrollment` varchar(255) NOT NULL DEFAULT '',
  `ShowInList` int(4) NOT NULL DEFAULT 0,
  `AllowPublish` int(4) NOT NULL DEFAULT 0,
  `MaturePublish` int(4) NOT NULL DEFAULT 0,
  `OwnerRoleID` char(36) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_invites`
--

CREATE TABLE `os_groups_invites` (
  `InviteID` char(36) NOT NULL DEFAULT '',
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `TMStamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_membership`
--

CREATE TABLE `os_groups_membership` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `SelectedRoleID` char(36) NOT NULL DEFAULT '',
  `Contribution` int(11) NOT NULL DEFAULT 0,
  `ListInProfile` int(4) NOT NULL DEFAULT 1,
  `AcceptNotices` int(4) NOT NULL DEFAULT 1,
  `AccessToken` char(36) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_notices`
--

CREATE TABLE `os_groups_notices` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `NoticeID` char(36) NOT NULL DEFAULT '',
  `TMStamp` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `FromName` varchar(255) NOT NULL DEFAULT '',
  `Subject` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `Message` text CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `HasAttachment` int(4) NOT NULL DEFAULT 0,
  `AttachmentType` int(4) NOT NULL DEFAULT 0,
  `AttachmentName` varchar(128) NOT NULL DEFAULT '',
  `AttachmentItemID` char(36) NOT NULL DEFAULT '',
  `AttachmentOwnerID` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_principals`
--

CREATE TABLE `os_groups_principals` (
  `PrincipalID` varchar(255) NOT NULL DEFAULT '',
  `ActiveGroupID` char(36) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_rolemembership`
--

CREATE TABLE `os_groups_rolemembership` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `PrincipalID` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `os_groups_roles`
--

CREATE TABLE `os_groups_roles` (
  `GroupID` char(36) NOT NULL DEFAULT '',
  `RoleID` char(36) NOT NULL DEFAULT '',
  `Name` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `Description` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `Title` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `Powers` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `Presence`
--

CREATE TABLE `Presence` (
  `UserID` varchar(255) NOT NULL,
  `RegionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `SessionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `SecureSessionID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `LastSeen` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `regions`
--

CREATE TABLE `regions` (
  `uuid` varchar(36) NOT NULL,
  `regionHandle` bigint(20) UNSIGNED NOT NULL,
  `regionName` varchar(128) DEFAULT NULL,
  `regionRecvKey` varchar(128) DEFAULT NULL,
  `regionSendKey` varchar(128) DEFAULT NULL,
  `regionSecret` varchar(128) DEFAULT NULL,
  `regionDataURI` varchar(255) DEFAULT NULL,
  `serverIP` varchar(64) DEFAULT NULL,
  `serverPort` int(10) UNSIGNED DEFAULT NULL,
  `serverURI` varchar(255) DEFAULT NULL,
  `locX` int(10) UNSIGNED DEFAULT NULL,
  `locY` int(10) UNSIGNED DEFAULT NULL,
  `locZ` int(10) UNSIGNED DEFAULT NULL,
  `eastOverrideHandle` bigint(20) UNSIGNED DEFAULT NULL,
  `westOverrideHandle` bigint(20) UNSIGNED DEFAULT NULL,
  `southOverrideHandle` bigint(20) UNSIGNED DEFAULT NULL,
  `northOverrideHandle` bigint(20) UNSIGNED DEFAULT NULL,
  `regionAssetURI` varchar(255) DEFAULT NULL,
  `regionAssetRecvKey` varchar(128) DEFAULT NULL,
  `regionAssetSendKey` varchar(128) DEFAULT NULL,
  `regionUserURI` varchar(255) DEFAULT NULL,
  `regionUserRecvKey` varchar(128) DEFAULT NULL,
  `regionUserSendKey` varchar(128) DEFAULT NULL,
  `regionMapTexture` varchar(36) DEFAULT NULL,
  `serverHttpPort` int(10) DEFAULT NULL,
  `serverRemotingPort` int(10) DEFAULT NULL,
  `owner_uuid` varchar(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `originUUID` varchar(36) DEFAULT NULL,
  `access` int(10) UNSIGNED DEFAULT 1,
  `ScopeID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `sizeX` int(11) NOT NULL DEFAULT 0,
  `sizeY` int(11) NOT NULL DEFAULT 0,
  `flags` int(11) NOT NULL DEFAULT 0,
  `last_seen` int(11) NOT NULL DEFAULT 0,
  `PrincipalID` char(36) NOT NULL DEFAULT '00000000-0000-0000-0000-000000000000',
  `Token` varchar(255) NOT NULL,
  `parcelMapTexture` varchar(36) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `tokens`
--

CREATE TABLE `tokens` (
  `UUID` char(36) NOT NULL,
  `token` varchar(255) NOT NULL,
  `validity` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `totalsales`
--

CREATE TABLE `totalsales` (
  `UUID` varchar(36) NOT NULL,
  `user` varchar(36) NOT NULL,
  `objectUUID` varchar(36) NOT NULL,
  `type` int(10) NOT NULL,
  `TotalCount` int(10) NOT NULL DEFAULT 0,
  `TotalAmount` int(10) NOT NULL DEFAULT 0,
  `time` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.3';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `transactions`
--

CREATE TABLE `transactions` (
  `UUID` varchar(36) NOT NULL,
  `sender` varchar(36) NOT NULL,
  `receiver` varchar(36) NOT NULL,
  `amount` int(10) NOT NULL,
  `senderBalance` int(10) NOT NULL DEFAULT -1,
  `receiverBalance` int(10) NOT NULL DEFAULT -1,
  `objectUUID` varchar(36) DEFAULT NULL,
  `objectName` varchar(255) DEFAULT NULL,
  `regionHandle` varchar(36) NOT NULL,
  `regionUUID` varchar(36) NOT NULL,
  `type` int(10) NOT NULL,
  `time` int(11) NOT NULL,
  `secure` varchar(36) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `commonName` varchar(128) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.12';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `UserAccounts`
--

CREATE TABLE `UserAccounts` (
  `PrincipalID` char(36) NOT NULL,
  `ScopeID` char(36) NOT NULL,
  `FirstName` varchar(64) NOT NULL,
  `LastName` varchar(64) NOT NULL,
  `Email` varchar(64) DEFAULT NULL,
  `ServiceURLs` text DEFAULT NULL,
  `Created` int(11) DEFAULT NULL,
  `UserLevel` int(11) NOT NULL DEFAULT 0,
  `UserFlags` int(11) NOT NULL DEFAULT 0,
  `UserTitle` varchar(64) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `userdata`
--

CREATE TABLE `userdata` (
  `UserId` char(36) NOT NULL,
  `TagId` varchar(64) NOT NULL,
  `DataKey` varchar(255) DEFAULT NULL,
  `DataVal` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `userinfo`
--

CREATE TABLE `userinfo` (
  `user` varchar(36) NOT NULL,
  `simip` varchar(64) NOT NULL,
  `avatar` varchar(50) NOT NULL,
  `pass` varchar(36) NOT NULL DEFAULT '',
  `type` tinyint(2) NOT NULL DEFAULT 0,
  `class` tinyint(2) NOT NULL DEFAULT 0,
  `serverurl` varchar(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci COMMENT='Rev.3';

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `usernotes`
--

CREATE TABLE `usernotes` (
  `useruuid` varchar(36) NOT NULL,
  `targetuuid` varchar(36) NOT NULL,
  `notes` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `userpicks`
--

CREATE TABLE `userpicks` (
  `pickuuid` varchar(36) NOT NULL,
  `creatoruuid` varchar(36) NOT NULL,
  `toppick` enum('true','false') NOT NULL,
  `parceluuid` varchar(36) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `snapshotuuid` varchar(36) NOT NULL,
  `user` varchar(255) NOT NULL,
  `originalname` varchar(255) NOT NULL,
  `simname` varchar(255) NOT NULL,
  `posglobal` varchar(255) NOT NULL,
  `sortorder` int(2) NOT NULL,
  `enabled` enum('true','false') NOT NULL,
  `gatekeeper` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `userprofile`
--

CREATE TABLE `userprofile` (
  `useruuid` varchar(36) NOT NULL,
  `profilePartner` varchar(36) NOT NULL,
  `profileAllowPublish` binary(1) NOT NULL,
  `profileMaturePublish` binary(1) NOT NULL,
  `profileURL` varchar(255) NOT NULL,
  `profileWantToMask` int(3) NOT NULL,
  `profileWantToText` text NOT NULL,
  `profileSkillsMask` int(3) NOT NULL,
  `profileSkillsText` text NOT NULL,
  `profileLanguages` text NOT NULL,
  `profileImage` varchar(36) NOT NULL,
  `profileAboutText` text NOT NULL,
  `profileFirstImage` varchar(36) NOT NULL,
  `profileFirstText` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `usersettings`
--

CREATE TABLE `usersettings` (
  `useruuid` varchar(36) NOT NULL,
  `imviaemail` enum('true','false') NOT NULL,
  `visible` enum('true','false') NOT NULL,
  `email` varchar(254) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indizes der exportierten Tabellen
--

--
-- Indizes für die Tabelle `AgentPrefs`
--
ALTER TABLE `AgentPrefs`
  ADD PRIMARY KEY (`PrincipalID`),
  ADD UNIQUE KEY `PrincipalID` (`PrincipalID`);

--
-- Indizes für die Tabelle `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`);

--
-- Indizes für die Tabelle `auth`
--
ALTER TABLE `auth`
  ADD PRIMARY KEY (`UUID`);

--
-- Indizes für die Tabelle `Avatars`
--
ALTER TABLE `Avatars`
  ADD PRIMARY KEY (`PrincipalID`,`Name`),
  ADD KEY `PrincipalID` (`PrincipalID`);

--
-- Indizes für die Tabelle `balances`
--
ALTER TABLE `balances`
  ADD PRIMARY KEY (`user`);

--
-- Indizes für die Tabelle `classifieds`
--
ALTER TABLE `classifieds`
  ADD PRIMARY KEY (`classifieduuid`);

--
-- Indizes für die Tabelle `Friends`
--
ALTER TABLE `Friends`
  ADD PRIMARY KEY (`PrincipalID`(36),`Friend`(36)),
  ADD KEY `PrincipalID` (`PrincipalID`);

--
-- Indizes für die Tabelle `GridUser`
--
ALTER TABLE `GridUser`
  ADD PRIMARY KEY (`UserID`);

--
-- Indizes für die Tabelle `hg_traveling_data`
--
ALTER TABLE `hg_traveling_data`
  ADD PRIMARY KEY (`SessionID`),
  ADD KEY `UserID` (`UserID`);

--
-- Indizes für die Tabelle `im_offline`
--
ALTER TABLE `im_offline`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `PrincipalID` (`PrincipalID`),
  ADD KEY `FromID` (`FromID`);

--
-- Indizes für die Tabelle `inventoryfolders`
--
ALTER TABLE `inventoryfolders`
  ADD PRIMARY KEY (`folderID`),
  ADD KEY `inventoryfolders_agentid` (`agentID`),
  ADD KEY `inventoryfolders_parentFolderid` (`parentFolderID`);

--
-- Indizes für die Tabelle `inventoryitems`
--
ALTER TABLE `inventoryitems`
  ADD PRIMARY KEY (`inventoryID`),
  ADD KEY `inventoryitems_avatarid` (`avatarID`),
  ADD KEY `inventoryitems_parentFolderid` (`parentFolderID`);

--
-- Indizes für die Tabelle `MuteList`
--
ALTER TABLE `MuteList`
  ADD UNIQUE KEY `AgentID_2` (`AgentID`,`MuteID`,`MuteName`),
  ADD KEY `AgentID` (`AgentID`);

--
-- Indizes für die Tabelle `os_groups_groups`
--
ALTER TABLE `os_groups_groups`
  ADD PRIMARY KEY (`GroupID`),
  ADD UNIQUE KEY `Name` (`Name`);
ALTER TABLE `os_groups_groups` ADD FULLTEXT KEY `Name_2` (`Name`);

--
-- Indizes für die Tabelle `os_groups_invites`
--
ALTER TABLE `os_groups_invites`
  ADD PRIMARY KEY (`InviteID`),
  ADD UNIQUE KEY `PrincipalGroup` (`GroupID`,`PrincipalID`);

--
-- Indizes für die Tabelle `os_groups_membership`
--
ALTER TABLE `os_groups_membership`
  ADD PRIMARY KEY (`GroupID`,`PrincipalID`),
  ADD KEY `PrincipalID` (`PrincipalID`);

--
-- Indizes für die Tabelle `os_groups_notices`
--
ALTER TABLE `os_groups_notices`
  ADD PRIMARY KEY (`NoticeID`),
  ADD KEY `GroupID` (`GroupID`),
  ADD KEY `TMStamp` (`TMStamp`);

--
-- Indizes für die Tabelle `os_groups_principals`
--
ALTER TABLE `os_groups_principals`
  ADD PRIMARY KEY (`PrincipalID`);

--
-- Indizes für die Tabelle `os_groups_rolemembership`
--
ALTER TABLE `os_groups_rolemembership`
  ADD PRIMARY KEY (`GroupID`,`RoleID`,`PrincipalID`),
  ADD KEY `PrincipalID` (`PrincipalID`);

--
-- Indizes für die Tabelle `os_groups_roles`
--
ALTER TABLE `os_groups_roles`
  ADD PRIMARY KEY (`GroupID`,`RoleID`),
  ADD KEY `GroupID` (`GroupID`);

--
-- Indizes für die Tabelle `Presence`
--
ALTER TABLE `Presence`
  ADD UNIQUE KEY `SessionID` (`SessionID`),
  ADD KEY `UserID` (`UserID`),
  ADD KEY `RegionID` (`RegionID`);

--
-- Indizes für die Tabelle `regions`
--
ALTER TABLE `regions`
  ADD PRIMARY KEY (`uuid`),
  ADD KEY `regionName` (`regionName`),
  ADD KEY `regionHandle` (`regionHandle`),
  ADD KEY `overrideHandles` (`eastOverrideHandle`,`westOverrideHandle`,`southOverrideHandle`,`northOverrideHandle`),
  ADD KEY `ScopeID` (`ScopeID`),
  ADD KEY `flags` (`flags`);

--
-- Indizes für die Tabelle `tokens`
--
ALTER TABLE `tokens`
  ADD UNIQUE KEY `uuid_token` (`UUID`,`token`),
  ADD KEY `UUID` (`UUID`),
  ADD KEY `token` (`token`),
  ADD KEY `validity` (`validity`);

--
-- Indizes für die Tabelle `totalsales`
--
ALTER TABLE `totalsales`
  ADD PRIMARY KEY (`UUID`);

--
-- Indizes für die Tabelle `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`UUID`);

--
-- Indizes für die Tabelle `UserAccounts`
--
ALTER TABLE `UserAccounts`
  ADD UNIQUE KEY `PrincipalID` (`PrincipalID`),
  ADD KEY `Email` (`Email`),
  ADD KEY `FirstName` (`FirstName`),
  ADD KEY `LastName` (`LastName`),
  ADD KEY `Name` (`FirstName`,`LastName`);

--
-- Indizes für die Tabelle `userdata`
--
ALTER TABLE `userdata`
  ADD PRIMARY KEY (`UserId`,`TagId`);

--
-- Indizes für die Tabelle `userinfo`
--
ALTER TABLE `userinfo`
  ADD PRIMARY KEY (`user`);

--
-- Indizes für die Tabelle `usernotes`
--
ALTER TABLE `usernotes`
  ADD UNIQUE KEY `useruuid` (`useruuid`,`targetuuid`);

--
-- Indizes für die Tabelle `userpicks`
--
ALTER TABLE `userpicks`
  ADD PRIMARY KEY (`pickuuid`);

--
-- Indizes für die Tabelle `userprofile`
--
ALTER TABLE `userprofile`
  ADD PRIMARY KEY (`useruuid`);

--
-- Indizes für die Tabelle `usersettings`
--
ALTER TABLE `usersettings`
  ADD PRIMARY KEY (`useruuid`);

--
-- AUTO_INCREMENT für exportierte Tabellen
--

--
-- AUTO_INCREMENT für Tabelle `im_offline`
--
ALTER TABLE `im_offline`
  MODIFY `ID` mediumint(9) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
