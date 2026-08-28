<?php

// Tables and columns of the Robust database (from robust.sql)
$ROBUST_TABLES = [
    'AgentPrefs' => ['PrincipalID','AccessPrefs','HoverHeight','Language','LanguageIsPublic','PermEveryone','PermGroup','PermNextOwner'],
    'assets' => ['name','description','assetType','local','temporary','data','id','create_time','access_time','asset_flags','CreatorID'],
    'auth' => ['UUID','passwordHash','passwordSalt','webLoginKey','accountType'],
    'Avatars' => ['PrincipalID','Name','Value'],
    'balances' => ['user','balance','status','type'],
    'classifieds' => ['classifieduuid','creatoruuid','creationdate','expirationdate','category','name','description','parceluuid','parentestate','snapshotuuid','simname','posglobal','parcelname','classifiedflags','priceforlisting'],
    'Friends' => ['PrincipalID','Friend','Flags','Offered'],
    'GridUser' => ['UserID','HomeRegionID','HomePosition','HomeLookAt','LastRegionID','LastPosition','LastLookAt','Online','Login','Logout'],
    'hg_traveling_data' => ['SessionID','UserID','GridExternalName','ServiceToken','ClientIPAddress','MyIPAddress','TMStamp'],
    'im_offline' => ['ID','PrincipalID','FromID','Message','TMStamp'],
    'inventoryfolders' => ['folderName','type','version','folderID','agentID','parentFolderID'],
    'inventoryitems' => ['assetID','assetType','inventoryName','inventoryDescription','inventoryNextPermissions','inventoryCurrentPermissions','invType','creatorID','inventoryBasePermissions','inventoryEveryOnePermissions','salePrice','saleType','creationDate','groupID','groupOwned','flags','inventoryID','avatarID','parentFolderID','inventoryGroupPermissions'],
    'migrations' => ['name','version'],
    'MuteList' => ['AgentID','MuteID','MuteName','MuteType','MuteFlags','Stamp'],
    'os_groups_groups' => ['GroupID','Location','Name','Charter','InsigniaID','FounderID','MembershipFee','OpenEnrollment','ShowInList','AllowPublish','MaturePublish','OwnerRoleID'],
    'os_groups_invites' => ['InviteID','GroupID','RoleID','PrincipalID','TMStamp'],
    'os_groups_membership' => ['GroupID','PrincipalID','SelectedRoleID','Contribution','ListInProfile','AcceptNotices','AccessToken'],
    'os_groups_notices' => ['GroupID','NoticeID','TMStamp','FromName','Subject','Message','HasAttachment','AttachmentType','AttachmentName','AttachmentItemID','AttachmentOwnerID'],
    'os_groups_principals' => ['PrincipalID','ActiveGroupID'],
    'os_groups_rolemembership' => ['GroupID','RoleID','PrincipalID'],
    'os_groups_roles' => ['GroupID','RoleID','Name','Description','Title','Powers'],
    'Presence' => ['UserID','RegionID','SessionID','SecureSessionID','LastSeen'],
    'regions' => ['uuid','regionHandle','regionName','regionRecvKey','regionSendKey','regionSecret','regionDataURI','serverIP','serverPort','serverURI','locX','locY','locZ','eastOverrideHandle','westOverrideHandle','southOverrideHandle','northOverrideHandle','regionAssetURI','regionAssetRecvKey','regionAssetSendKey','regionUserURI','regionUserRecvKey','regionUserSendKey','regionMapTexture','serverHttpPort','serverRemotingPort','owner_uuid','originUUID','access','ScopeID','sizeX','sizeY','flags','last_seen','PrincipalID','Token','parcelMapTexture'],
    'tokens' => ['UUID','token','validity'],
    'totalsales' => ['UUID','user','objectUUID','type','TotalCount','TotalAmount','time'],
    'transactions' => ['UUID','sender','receiver','amount','senderBalance','receiverBalance','objectUUID','objectName','regionHandle','regionUUID','type','time','secure','status','commonName','description'],
    'UserAccounts' => ['PrincipalID','ScopeID','FirstName','LastName','Email','ServiceURLs','Created','UserLevel','UserFlags','UserTitle','active'],
    'userdata' => ['UserId','TagId','DataKey','DataVal'],
    'userinfo' => ['user','simip','avatar','pass','type','class','serverurl'],
    'usernotes' => ['useruuid','targetuuid','notes'],
    'userpicks' => ['pickuuid','creatoruuid','toppick','parceluuid','name','description','snapshotuuid','user','originalname','simname','posglobal','sortorder','enabled','gatekeeper'],
    'userprofile' => ['useruuid','profilePartner','profileAllowPublish','profileMaturePublish','profileURL','profileWantToMask','profileWantToText','profileSkillsMask','profileSkillsText','profileLanguages','profileImage','profileAboutText','profileFirstImage','profileFirstText'],
    'usersettings' => ['useruuid','imviaemail','visible','email'],
];
// Database utility for all tables of the Robust database (OpenSim)
// Reusable in other scripts via require/include

class RobustDB {
    private $pdo;

    /**
     * Create a RobustDB instance.
     * @param string|PDO $host Hostname or PDO object
     * @param string|null $dbname
     * @param string|null $user
     * @param string|null $pass
     */
    public function __construct($host, $dbname = null, $user = null, $pass = null) {
        if ($host instanceof PDO) {
            $this->pdo = $host;
        } else {
            // Check whether datainc.php provides a $pdo variable
            if ($host === 'datainc') {
                require_once __DIR__ . '/datainc.php';
                if (isset($pdo) && $pdo instanceof PDO) {
                    $this->pdo = $pdo;
                    return;
                } else {
                    throw new Exception('No valid PDO connection found in datainc.php.');
                }
            }
            $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            $this->pdo = new PDO($dsn, $user, $pass, $options);
        }
    }

    // List all tables
    public function listTables() {
        $stmt = $this->pdo->query("SHOW TABLES");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Read all entries of a table
    public function getAll($table) {
        $stmt = $this->pdo->query("SELECT * FROM `" . addslashes($table) . "`");
        return $stmt->fetchAll();
    }

    // Read a single entry (by primary key)
    public function getById($table, $idColumn, $idValue) {
        $sql = "SELECT * FROM `" . addslashes($table) . "` WHERE `$idColumn` = :id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $idValue]);
        return $stmt->fetch();
    }

    // Insert an entry
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        $sql = "INSERT INTO `" . addslashes($table) . "` (" . implode(",", $columns) . ") VALUES (" . implode(",", $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);
        return $this->pdo->lastInsertId();
    }

    // Update an entry
    public function update($table, $idColumn, $idValue, $data) {
        $set = [];
        foreach ($data as $col => $val) {
            $set[] = "`$col` = :$col";
        }
        $sql = "UPDATE `" . addslashes($table) . "` SET " . implode(", ", $set) . " WHERE `$idColumn` = :id";
        $data['id'] = $idValue;
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    // Delete an entry
    public function delete($table, $idColumn, $idValue) {
        $sql = "DELETE FROM `" . addslashes($table) . "` WHERE `$idColumn` = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $idValue]);
    }

    // Arbitrary query (e.g. for complex filters)
    public function query($sql, $params = []) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

// Example usage in other scripts:
// require_once 'statistics/data.php';
// $db = new RobustDB($host, $dbname, $user, $pass);
// $result = $db->getAll('UserAccounts');

?>