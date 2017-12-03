<?php
/**
 * Created by PhpStorm.
 * User: suraj
 * Date: 15/11/16
 * Time: 11:08 AM
 */
/**
 * @version     2.0 +
 * @package       Open Source Excellence Security Suite
 * @subpackage    Centrora Security Firewall
 * @subpackage    Open Source Excellence WordPress Firewall
 * @author        Open Source Excellence {@link http://www.opensource-excellence.com}
 * @author        Created on 01-Jun-2013
 * @license GNU/GPL http://www.gnu.org/copyleft/gpl.html
 *
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *  @Copyright Copyright (C) 2008 - 2012- ... Open Source Excellence
 */
if (!defined('OSE_FRAMEWORK') && !defined('OSE_ADMINPATH') && !defined('_JEXEC'))
{
    die('Direct Access Not Allowed');
}
if(TEST_ENV)
{
    if (function_exists("ini_set")) {
        ini_set("display_errors", "on");
    }
}
oseFirewall::callLibClass('gitBackup', 'GitSetupL');
class GitSetupsuite extends GitSetupL
{

    private $db;
    private $accountDb;
    private $orderBy = null;
    private $limitStm = null;
    private $where = null;
    private $files = null;
    private $installer = null;
    private $account_db_config = null;

    //List of all the file path to pefrom all the git operations
    private $dbTempFilePath = null;
    private $dbTempFolder = null;
    private $centroraBackupFolder = null;
    private $gitbackupFolder = null;
    private $gitfolder = null;
    private $pluginDir = null;
    private $zipBackupFolder = null;
    private $folder_List = null;
    private $publicKey_path = null;
    private $privateKey_path = null;
    private $keyBakup_Gitignore = null;
    private $keyBackup_Folder = null;
    private $move_publicKeyPath = null;
    private $move_PrivateKeyPath = null;
    private $centroraBackupFolder_gitignore = null;
    private $centroraBackup_ZipFile = null;
    private $backupfiles_ExcludePath = null;
    private $backupZipFolder_ignorePath = null;
    private $zipDownload_URL = null;
    private $protectedDir = null;
    private $dataDir = null;
    private $insertData = null;
    private $createTables = null;
    private $alterTables = null;
    private $tablesList = null;
    private $gitLogTable_backup = null;

    //setup db parameters
    private $dbname = null;
    private $username = null;
    private $pswd = null;
    private $hostname = null;
    private $prefix = null;
    private $cms = null;
    private $remote = false;


    private $dbconfiggit_table = '#__osefirewall_dbconfiggit';

    public function __construct($qatest = false, $accountname = false, $accountpath = false, $initalstep = true,$remote =false)
    {
        parent::__construct($qatest,$remote);
        $this->db = oseFirewall::getDBO();
        $this->optimisePHP();
        $this->remote = $remote;
        if($remote == true)
        {
            $this->toggleBackupLog_suite(1);
        }
        date_default_timezone_set ( 'Australia/Melbourne' );
        //complete he db and the path setup only if you have the accountname and path
        if ($initalstep) {
            $accountpath = $this->formatAccountPath($accountpath);
            $this->initalSetup($accountname, $accountpath);
        }
    }
    private function optimisePHP()
    {
        if (function_exists('ini_set'))
        {
            $this->setMaxExecutionTime (0);
            $this->setMemLimit('1024M');
            ini_set('upload_max_filesize', '100M');
            ini_set('post_max_size', '100M');
        }
    }
    private function setMaxExecutionTime ($seconds) {
        if (function_exists('ini_set')) {
            ini_set('max_execution_time', (int)$seconds);
        }
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
        }
    }

    private function setMemLimit ($mem) {
        if (function_exists('ini_set')) {
            ini_set('memory_limit', $mem);
        }
    }

    private function initalSetup($accountname, $accountpath)
    {
        $this->account_db_config = $dbConfig = $this->getDatabaseConfig($accountname);
        $this->setupDbVariables();
        if ($dbConfig['status'] == 1) {
            $dbSetup = $this->setupAccountDbConnection($dbConfig);
            if ($dbSetup['status'] == 1) {
                if (count($dbConfig['info']) == 1) {
                    $this->prepareAccountFilePath($dbConfig['info'][0]->cms, $accountpath, $accountname);
                    $this->createGitLogTable();
                } else {
                    //TODO : ELSE PART  : MAKE SURE THE WEBSITE HAS THE SAME CMS
                    return oseFirewallBase::prepareErrorMessage('More than one database detected ');
                }
            } else {
                return $dbSetup;
                //TODO IMPROVE ERROR HANDLING
            }
        } else {
            return $dbConfig;
        }
    }

    public function setupDbVariables()
    {
        if (!empty($this->account_db_config)) {
            $this->username = $this->account_db_config['info'][0]->DB_USER;
            $this->pswd = $this->account_db_config['info'][0]->DB_PASSWORD;
            $this->dbname = $this->account_db_config['info'][0]->DB_NAME;
            $this->hostname = $this->account_db_config['info'][0]->DB_HOST;
            $this->cms = $this->account_db_config['info'][0]->cms;
            $this->prefix = $this->account_db_config['info'][0]->TABLE_PREFIX;
        }
    }

    //get the databse config for a specific user
    public function getDatabaseConfig($accountname = false, $accountpath = false)
    {
        if (empty($accountpath) && !empty($accountname)) {
            $query = "SELECT `dbconfig` FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE `accountname`=" . $this->db->quoteValue($accountname);
        } else if (empty($accountname) && !empty($accountpath)) {
            $query = "SELECT `dbconfig` FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE `accountpath`=" . $this->db->quoteValue($accountpath);
        } else {
            $this->logErrorBackup_suite("getDatabaseConfig - the accountname and accountpath are empty ; accountanme : $accountname", $accountpath);
            $this->backupLog_suite("getDatabaseConfig - the accountname and accountpath are empty ; accountanme : $accountname", $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage("The accountname and accountpath are blank ", "medium");
        }
        $this->db->setQuery($query);
        $result = $this->db->loadResult();
        if (empty($result)) {
            $this->logErrorBackup_suite("getDatabaseConfig - No Db config exists for accountanme : $accountname", $accountpath);
            $this->backupLog_suite("getDatabaseConfig -No Db config exists for accountanme ; accountanme : $accountname", $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage('No DB config exists for : ' . $accountname, "medium");
        } else {
            $temp = json_decode($result['dbconfig']);
            $this->backupLog_suite("getDatabaseConfig - Db Config exists for account : $accountname", $accountpath);
            return oseFirewallBase::prepareSuccessMessage($temp);
        }
    }

    //set up the data base object to access the account's database
    public function setupAccountDbConnection($dbConfig)
    {
        if ($dbConfig['status'] == 1) {
            foreach ($dbConfig['info'] as $dbconfigRecord) {
                require_once(OSE_FRAMEWORKDIR . ODS . 'oseframework' . ODS . 'db' . ODS . 'suite.php');
                $this->accountDb = new oseDB2Suite($dbconfigRecord->DB_HOST, $dbconfigRecord->DB_NAME, $dbconfigRecord->DB_USER, $dbconfigRecord->DB_PASSWORD, $dbconfigRecord->TABLE_PREFIX);
                $this->prefix = $dbconfigRecord->TABLE_PREFIX;
                return oseFirewallBase::prepareSuccessMessage('The Database Object has been Initialised');
            }
        } else {
            //ERROR IN RETRIEVING THE DATABASE CONFIGURATION
            return $dbConfig;
        }
    }

    protected function createGitLogTable()
    {
        $gitLog = $this->accountDb->isTableExists('#__osefirewall_gitlog');
        if (!$gitLog) {
            $query = "CREATE TABLE `#__osefirewall_gitlog` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                  `commit_id` varchar(50) NOT NULL,
                  `commit_time` varchar(100) NOT NULL,
                  `commit_message` varchar(200) NOT NULL,
                  `is_head` boolean NOT NULL,
                  PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;";
            $this->accountDb->setQuery($query);
            $this->accountDb->loadResult();
        }
    }

    public function formatAccountPath($accountpath)
    {
        if(self::endsWith($accountpath,"/"))
        {
            $accountpath = rtrim($accountpath,"/");
        }
        if(!self::startsWith($accountpath,"/"))
        {
            $accountpath = "/".$accountpath;
        }
        return $accountpath;
    }

    //PREPARE ALL  THE FILE PATHS FOR THE ACCOUNT TO PEFROM GIT OPERATIONS
    public function prepareAccountFilePath($cms, $accountpath, $accountname)
    {
        if(self::endsWith($accountpath,"/"))
        {
            $accountpath = rtrim($accountpath,"/");
        }
        if(!self::startsWith($accountpath,"/"))
        {
            $accountpath = "/".$accountpath;
        }
        if ($cms == 'wp') {

            $this->pluginDir = $accountpath . ODS . 'wp-content' . ODS . 'plugins' . ODS . 'ose-firewall';
            $this->protectedDir = $this->pluginDir . ODS . 'protected';
            $this->dataDir = $this->protectedDir . ODS . 'data';
            $this->dbTempFolder = $this->dataDir . ODS . "backup";
            $this->dbTempFilePath = $this->dbTempFolder . ODS . "dbtables.php";
            $this->centroraBackupFolder = $accountpath . ODS . 'wp-content' . ODS . 'CentroraBackup';
            $this->gitbackupFolder = $this->centroraBackupFolder . ODS . 'gitbackup';
            $this->zipBackupFolder = $this->centroraBackupFolder . ODS . 'BackupFiles';
            $this->folder_List = $this->centroraBackupFolder . ODS . 'folderlist.php';
            $this->publicKey_path = $this->centroraBackupFolder . ODS . 'centrorakey.pub';
            $this->privateKey_path = $this->centroraBackupFolder . ODS . 'centrorakey';
            $this->keyBackup_Folder = $this->centroraBackupFolder . ODS . 'keybackup';
            $this->keyBakup_Gitignore = 'wp-content' . ODS . 'CentroraBackup' . ODS . 'keybackup' . ODS . '*';
            $this->move_publicKeyPath = $this->centroraBackupFolder . ODS . 'keybackup' . ODS . 'centrorakey.pub';
            $this->move_PrivateKeyPath = $this->centroraBackupFolder . ODS . 'keybackup' . ODS . 'centrorakey';
            $this->centroraBackupFolder_gitignore = 'wp-content' . ODS . 'CentroraBackup' . ODS . '*';
            $this->centroraBackup_ZipFile = $accountpath . ODS . 'wp-content' . ODS . 'CentroraBackup' . ODS . 'Backup.zip';
            $this->backupZipFolder_ignorePath = 'wp-content' . ODS . 'CentroraBackup' . ODS . 'BackupFiles';
            $this->backupfiles_ExcludePath = basename($accountpath) . ODS . $this->backupZipFolder_ignorePath;
            $this->zipDownload_URL = '?option=com_ose_firewall&view=gitbackupsuite&task=downloadzip&action=downloadzip&controller=gitbackupsuite&accountpath=' . $accountpath . '&accountname=' . $accountname;
            $this->insertData = "wp-content/CentroraBackup/gitbackup/insertData.sql";
            $this->createTables = "wp-content/CentroraBackup/gitbackup/createTables.sql";
            $this->alterTables = "wp-content/CentroraBackup/gitbackup/alterTables.sql";
            $this->tablesList = "wp-content/CentroraBackup/gitbackup/tablesList.sql";
            $this->gitLogTable_backup = "wp-content/CentroraBackup/gitbackup/gitLog.sql";
        } else if ($cms == 'jm') {
            $this->pluginDir = $accountpath . ODS . 'administrator' . ODS . 'components' . ODS . 'com_ose_firewall';
            $this->protectedDir = $this->pluginDir . ODS . 'protected';
            $this->dataDir = $this->protectedDir . ODS . 'data';
            $this->dbTempFolder = $this->dataDir . ODS . "backup";
            $this->dbTempFilePath = $this->dbTempFolder . ODS . "dbtables.php";
            $this->centroraBackupFolder = $accountpath . ODS . 'media' . ODS . 'CentroraBackup';
            $this->gitbackupFolder = $this->centroraBackupFolder . ODS . 'gitbackup';
            $this->zipBackupFolder = $this->centroraBackupFolder . ODS . 'BackupFiles';
            $this->folder_List = $this->centroraBackupFolder . ODS . 'folderlist.php';
            $this->publicKey_path = $this->centroraBackupFolder . ODS . 'centrorakey.pub';
            $this->privateKey_path = $this->centroraBackupFolder . ODS . 'centrorakey';
            $this->keyBakup_Gitignore = 'media' . ODS . 'CentroraBackup' . ODS . 'keybackup' . ODS . '*';
            $this->keyBackup_Folder = $this->centroraBackupFolder . ODS . 'keybackup';
            $this->move_publicKeyPath = $this->centroraBackupFolder . ODS . 'keybackup' . ODS . 'centrorakey.pub';
            $this->move_PrivateKeyPath = $this->centroraBackupFolder . ODS . 'keybackup' . ODS . 'centrorakey';
            $this->centroraBackupFolder_gitignore = 'media' . ODS . 'CentroraBackup' . ODS . '*';
            $this->centroraBackup_ZipFile = $accountpath . ODS . 'media' . ODS . 'CentroraBackup' . ODS . 'Backup.zip';
            $this->backupZipFolder_ignorePath = 'media' . ODS . 'CentroraBackup' . ODS . 'BackupFiles';
            $this->backupfiles_ExcludePath = basename($accountpath) . ODS . $this->backupZipFolder_ignorePath;
            $this->zipDownload_URL = '?option=com_ose_firewall&view=gitbackupsuite&task=downloadzip&action=downloadzip&controller=gitbackupsuite&accountpath=' . $accountpath . '&accountname=' . $accountname;;
            $this->insertData = "media/CentroraBackup/gitbackup/insertData.sql";
            $this->createTables = "media/CentroraBackup/gitbackup/createTables.sql";
            $this->alterTables = "media/CentroraBackup/gitbackup/alterTables.sql";
            $this->tablesList = "media/CentroraBackup/gitbackup/tablesList.sql";
            $this->gitLogTable_backup = "media/CentroraBackup/gitbackup/gitLog.sql";
        }
        $this->gitfolder = $accountpath . ODS . '.git';
        $folderList = array($this->pluginDir, $this->protectedDir, $this->dataDir, $this->dbTempFolder, $this->centroraBackupFolder, $this->gitbackupFolder, $this->zipBackupFolder, $this->keyBackup_Folder); //order is important
        $folderlist_result = $this->prepareFoldersList($folderList);
        if ($folderlist_result['status'] == 0) {
            $this->logErrorBackup_suite("Cannot create folder for ".$folderlist_result['info'],false);
        }
    }

    //Make foilders which are essential for git backup
    public function prepareFoldersList($foldersList)
    {
        foreach ($foldersList as $folder) {
            if (!file_exists($folder)) {
                $result = mkdir($folder);
                if (!$result) {
                    return oseFirewallBase::prepareErrorMessage("There was some problem in creating the folder : ".$folder);
                }
            }
        }
        return oseFirewallBase::prepareSuccessMessage('All the folders have been created successfully ');
    }

    private function setUpDatabseObject($accountname)
    {
        $dbConfig = $this->getDatabaseConfig($accountname);
        if ($dbConfig['status'] == 1) {
            $dbSetup = $this->setupAccountDbConnection($dbConfig);
        } else {
            return $dbConfig;
        }
    }

    private function getRootPath()
    {
        if (class_exists('SConfig')) {
            if (is_readable('/home/centrora')) {
                $rootpath = dirname('/home/centrora');
            } elseif (is_readable(dirname(OSE_ABSPATH))) {
                $rootpath = dirname(dirname(OSE_ABSPATH));
            } else {
                $rootpath = dirname(OSE_ABSPATH);
            }
        } else {
            $rootpath = OSE_ABSPATH;
        }
        return $rootpath;
    }

    //returns the list of accounts in the suite
    public function getAccountsList()
    {
        $directory = array();
        $accountInfo = array();
        $rootpath = $this->getRootPath();
        if (empty($rootpath)|| !is_readable($rootpath)) {
            return oseFirewallBase::prepareErrorMessage('There was some problem in accessing the root path of the suite' . CONTACT_SUPPORT);
        }
        // Create recursive dir iterator which skips dot folders and Flatten the recursive iterator

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootpath),
            RecursiveIteratorIterator::SELF_FIRST);
        $iterator->setMaxDepth(1);
        if (empty($iterator)) {
            return oseFirewallBase::prepareErrorMessage('There was some problem in getting the list of the folders ' . CONTACT_SUPPORT);
        }
        $i = 0;
        //for each folder path ,check if it contains public_html
        foreach ($iterator as $file) {
            if ($file->isDir()) {
                $pattern = '/public_html$/im';
                if (preg_match($pattern, $file->getRealpath())) {
                    $directory[$i] = ($file->getRealpath());
                    $i++;
                }
            }
        }
        if (empty($directory)) {
            return oseFirewallBase::prepareErrorMessage('No Accounts were found');
        }
        $directory = array_unique($directory);
        foreach ($directory as $dir) {
            $userInfo = posix_getpwuid(fileowner($dir));
            if (isset($userInfo['name']) && $userInfo['dir']) {
                $accountInfo[$userInfo['name']] = $userInfo['dir'] . '/public_html';
            } else {
                return oseFirewallBase::prepareErrorMessage("There was some problem in accessing the account name for directory : $dir" . CONTACT_SUPPORT);
            }
        }
        if (empty($accountInfo)) {
            return oseFirewallBase::prepareErrorMessage('There was some problem in accessing the information related to the users ' . CONTACT_SUPPORT);
        } else {
            return oseFirewallBase::prepareSuccessMessage($accountInfo);
        }
    }

    public function getAccountListTable()
    {
        $columns = oRequest::getVar('columns', null);
        $length = oRequest::getInt('length', 15);
        $start = oRequest::getInt('start', 0);
        $search = oRequest::getVar('search', null);
        $orderArr = oRequest::getVar('order', null);
        //SORTING OPTIONS
        $sortby = $columns[$orderArr[0]['column']]['data'];
        if ($orderArr[0]['dir'] == 'asc') {
            $orderDir = 'SORT_ASC';
        } else {
            $orderDir = 'SORT_DESC';
        }
        $complte_accountstable = $this->getAccountListCompleteTable();
        $filteredRecords = $this->getFilteredAccountTable($complte_accountstable, $sortby, $orderDir, $search['value'], $start, $length);
        if (empty($filteredRecords['data'])) {
            $data['data'] = '';
            $data['recordsTotal'] = 0;
            $data['recordsFiltered'] = 0;
            return $data;
        } else {
            $data['data'] = $filteredRecords['data'];
            $data['recordsTotal'] = count($complte_accountstable);
            $data['recordsFiltered'] = $filteredRecords['count'];
        }
        return $data;
    }

    //return the records based on the paramters provided by the user
    //search, sort and view certain amount of elements
    public function getFilteredAccountTable($complte_accountstable, $sortby, $orderDir, $search, $start, $length)
    {
        if (empty($complte_accountstable)) {
            return $complte_accountstable;
        } else {
            if (!empty($search)) {
                $matched_records = $this->searchRecord($complte_accountstable, $search);
            } else {
                $matched_records = $complte_accountstable;
            }

            if (empty($matched_records)) {
                //if the pattern does not match the records return null
                return false;
            } else {
                //continue if the serach matches the records
                $sorted_records = $this->array_sort($matched_records, $sortby, $orderDir);
                $filteredTable['count'] = count($sorted_records);
                $spliced_array = $this->getLimitedRecords($start, $length, $sorted_records);
                $filteredTable['data'] = $spliced_array;
                return $filteredTable;
            }
        }
    }

    public function getLimitedRecords($start, $length, $array)
    {
        $spliced_array = array_splice($array, $start, $length);
        return $spliced_array;
    }

    public function searchRecord($accountsTable, $searchValue)
    {
        $match = array();
        $i = 0;
        $pattern = '/' . $searchValue . '/';
        foreach ($accountsTable as $record) {
            if (preg_match($pattern, $record['name'])) {
                $match[$i] = $record;
                $i++;
            }
        }
        return $match;
    }

    private function array_sort($array, $on, $order)
    {
        $sortArray = array();
        foreach ($array as $record) {
            foreach ($record as $key => $value) {
                if (!isset($sortArray[$key])) {
                    $sortArray[$key] = array();
                }
                $sortArray[$key][] = $value;
            }
        }
        if ($order == 'SORT_DESC') {
            array_multisort($sortArray[$on], SORT_DESC, $array);

        } else {
            array_multisort($sortArray[$on], SORT_ASC, $array);
        }
        return $array;
    }


    public function getAccountListCompleteTable()
    {
        $accountsTable = array();
        $accountList = $this->getAccountsList();
        if ($accountList['status'] == 0) {
            //ERROR IN GETTING THE ACCOUNTS LIST
            return $accountList; //TODO ERROR HANDLING FOR GET ACCOUNT TABLE LIST
        } else {
            //SUCCESS IN GETTING THE ACCOUNTS LIST
            $i = 0;
            foreach ($accountList['info'] as $accountName => $accountPath) {
                $accountsTable[$i]['id'] = ($i + 1);
//                $accountsTable[$i]['name'] = '<a href="javascript:void(0);" onclick="checkAccountstatus(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Go to the account"  > ' . $accountName . '</a>';
                $accountsTable[$i]['name'] = '<button class="btn-new result-btn-set ipmanage-btn-set text-danger" type="button" onClick="checkAccountstatus(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Go to the account"  > Go to Account <i class="glyphicon glyphicon glyphicon-play" style="color:#D3D3D3"></i></button>'.$accountName;
                $dbconfigExists = $this->checkifDbConfigExists($accountName);  ///TODO check db connections also
                if ($dbconfigExists['status'] == 0) {
                    $accountsTable[$i]['name'] = '<button class="btn-new result-btn-set ipmanage-btn-set text-danger" type="button" onClick="checkAccountstatus(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Go to the account"  > Initiate <i class="glyphicon glyphicon glyphicon-refresh" style="color:#D3D3D3"></i></button>'.$accountName;
                    $accountsTable[$i]['path'] = $accountPath; //
                    $accountsTable[$i]['latestbackup'] = "NULL";
                    $accountsTable[$i]['backupnow'] = '<i disabled class="text-success glyphicon glyphicon-exclamation-sign" style="cursor:pointer; font-size:20px; float:left; color:#D3D3D3;" href="javascript:void(0);" title = "No Database Configurations found"></i>';
                    $accountsTable[$i]['download'] = '<i disabled class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3;"   title = "Download the complete website in zip format"></i>';
                    $accountsTable[$i]['upload'] = '<i disabled class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Download the complete website in zip format"></i>';
                    $accountsTable[$i]['uninstall'] = '<i disabled class="glyphicon glyphicon glyphicon-remove" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3 "  title = "Uninstall Git"></i>';
                } else {
                    //if user db details are chnaged
                    //check the db conenction again
                    $dbconnectivity = $this->checkDbConnectivity($dbconfigExists);
                    if ($dbconnectivity == false) {
                        $this->clearAccountDetails($accountName);
                        $accountsTable[$i]['name'] = '<button class="btn-new result-btn-set ipmanage-btn-set text-danger" type="button" onClick="checkAccountstatus(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Go to the account"  > <i class="glyphicon glyphicon glyphicon-refresh" style="color:#D3D3D3"></i>Initiate</button>'.$accountName;
                        $accountsTable[$i]['path'] = $accountPath; //
                        $accountsTable[$i]['latestbackup'] = "NULL";
                        $accountsTable[$i]['backupnow'] = '<i disabled class="text-success glyphicon glyphicon-exclamation-sign" style="cursor:pointer; font-size:20px; float:left; color:#D3D3D3;" href="javascript:void(0);" title = "No Database Configurations found"></i>';
                        $accountsTable[$i]['download'] = '<i disabled class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3;"   title = "Download the complete website in zip format"></i>';
                        $accountsTable[$i]['upload'] = '<i disabled class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Download the complete website in zip format"></i>';
                        $accountsTable[$i]['uninstall'] = '<i disabled class="glyphicon glyphicon glyphicon-remove" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3 "  title = "Uninstall Git"></i>';
                    } else {
                        $accountsTable[$i]['path'] = $accountPath; //
                        $accountsTable[$i]['latestbackup'] = $this->getLastBackupDateTime($accountName, $accountPath);
                        $flag = $this->checkGitBackupPreRequisite();
                        $message = $flag['info'];
                        if ($flag['status'] == 0) {
                            $accountsTable[$i]['backupnow'] = '<i class="text-success glyphicon glyphicon-floppy-disk" style="cursor:pointer; font-size:20px; float:left; color:#ff69b4;" href="javascript:void(0);" onclick="display_PrerequisiteInfo()" title = "Check Pre-requisites"></i>';
                            $accountsTable[$i]['download'] = '<i disabled class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"   title = "Download the complete website in zip format"></i>';
                            $accountsTable[$i]['upload'] = '<i disabled class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Download the complete website in zip format"></i>';
                            $accountsTable[$i]['uninstall'] = '<i disabled class="glyphicon glyphicon glyphicon-remove" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Uninstall Git"></i>';
                        } else {
                            $isinit = $this->isinit_suite($accountPath);
                            if ($isinit['status'] == 1) {
                                //GIT HAS BEEN INITIALIZED
                                $accountsTable[$i]['backupnow'] = '<i class="text-success glyphicon glyphicon-floppy-disk" style="cursor:pointer; font-size:20px; float:left; color:#1cab94;" href="javascript:void(0);" onclick="createBackupAllFiles_accountstable(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Backup Files Now"></i>';
                                $accountsTable[$i]['download'] = '<i class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#1cab94" href="javascript:void(0);" onclick="findChanges_accountstable(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Download the complete website in zip format"></i>';
                                if(oseFirewallBase::checkSubscriptionStatus(false))
                                {
                                    $accountsTable[$i]['upload'] = '<i class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#1cab94;" href="javascript:void(0);" onclick="gitCloudBackup_accountspage(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Upload the Backup to the Cloud"></i>';
                                }else{
                                    $accountsTable[$i]['upload'] = '<i disabled class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Download the complete website in zip format"></i>';
                                }
                                $accountsTable[$i]['uninstall'] = '<i class="glyphicon glyphicon glyphicon-remove" style="font-size:25px; padding-left:20px; cursor:pointer; color:#ff0000" onclick="uninstallgit_confirm(\'' . $accountName . '\',\'' . $accountPath . '\')"  title = "Uninstall Git"></i>';
                            } else {
                                //GIT NOT INITIALIZED
                                $accountsTable[$i]['backupnow'] = '<i class="text-success glyphicon glyphicon-exclamation-sign" style="cursor:pointer; font-size:20px; float:left; color:#1cab94;" href="javascript:void(0);" onclick="enablegitbackup_accountstable(\'' . $accountName . '\',\'' . $accountPath . '\')" title = "Enable Git Backup"></i>';
                                $accountsTable[$i]['download'] = '<i disabled class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"   title = "Download the complete website in zip format"></i>';
                                $accountsTable[$i]['upload'] = '<i disabled class="glyphicon glyphicon-cloud-upload" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Download the complete website in zip format"></i>';
                                $accountsTable[$i]['uninstall'] = '<i disabled class="glyphicon glyphicon glyphicon-remove" style="font-size:25px; padding-left:20px; cursor:pointer; color:#D3D3D3"  title = "Uninstall Git"></i>';
                            }
                        }
                    }
                }
                $i++;
            }
            return $accountsTable;
        }
    }

    //get the highest for a specific users
    public function getLastBackupDateTime($accountName, $accountpath)
    {
        $this->initalSetup($accountName, $accountpath);
        $result = $this->getHead();
        if ($result[0] != null) {
            $temp = $result[0]['commit_time'];
            $result = strstr($temp, '+', true);
            if ($result == false) {
                $result = strstr($temp, '-', true);
            }
        } else {
            $result = 'NONE';  //return null if there is any error
        }
        return $result;
    }

    //FUNCTION TO CHECK IF THE USER ACCOUNT IS GIT INITALIZED
    public function isAccountGitInitialized($accountPath)
    {
        $gitcmd = "cd $accountPath ; git rev-parse --is-inside-work-tree";
        $output = $this->runShellCommand($gitcmd);
        $result = $output['stdout'];
        if ($result == true)
            return true;
        else
            return false;
    }

    //returns the website
    public function getWebsiteInfo($accountPath)
    {
        oseFirewall::callLibClass('vsscanner', 'cfscanner');
        $scanner = new cfScanner ();
        $result = $scanner->suitePathDetect($accountPath);
        if (empty($result) || (isset($result['cms']) && $result['cms'] == false)) {
            $websiteCount = $this->hasMultipleWebsites($accountPath);
            if ($websiteCount['wp'] > 1 || $websiteCount['jm'] > 1) {
                if ($websiteCount['wp'] > 1 && $websiteCount['jm'] > 1) {
                    return oseFirewallBase::prepareCustomMessage(2, 'The account has ' . $websiteCount['wp'] . ' wordpress website(s) and ' . $websiteCount['jm'] . " Joomla website(s) <br/> Git Backup Does not support this structure <br/>" . CONTACT_SUPPORT);
                } else if ($websiteCount['wp'] > 1 && $websiteCount['jm'] == 0) {
                    return oseFirewallBase::prepareCustomMessage(2, 'The account has ' . $websiteCount['wp'] . " wordpress website(s) <br/> Git Backup Does not support this structure <br/>" . CONTACT_SUPPORT);
                } else if ($websiteCount['wp'] == 0 && $websiteCount['jm'] > 1) {
                    return oseFirewallBase::prepareCustomMessage(2, 'The account has ' . $websiteCount['jm'] . " Joomla website(s) <br/> Git Backup Does not support this structure <br/>" . CONTACT_SUPPORT);
                } else {
                    return oseFirewallBase::prepareCustomMessage(2, 'The account has ' . $websiteCount['wp'] . ' wordpress website(s) and ' . $websiteCount['jm'] . " Joomla website(s) <br/> Git Backup Does not support this structure <br/>" . CONTACT_SUPPORT);
                }
            } else {
                return oseFirewallBase::prepareCustomMessage(2, "There was some problem in accessing the details related to the website <br/>The website folder structure is not supported by the plugin <br/>" . CONTACT_SUPPORT);
            }
        } else {
            if (isset($result['cms'])) {
                if ($result['cms'] == 'jm') {
                    $dbConfig = $this->getDatabaseDetails($result['cms'], $accountPath);
                    return $dbConfig;
                } else if ($result['cms'] == 'wp') {
                    $dbConfig = $this->getDatabaseDetails($result['cms'], $accountPath);
                    return $dbConfig;
                }
            } else {
                return oseFirewallBase::prepareErrorMessage("There was some problem in accessing the platform of the website \n" . CONTACT_SUPPORT);
            }
        }
    }

    public function getDatabaseDetails($cms, $accountPath)
    {
        $result = array();
        $configFilePath = $this->getConfigFilePath($accountPath, $cms);
        if ($cms == 'jm') {
            if (file_exists($configFilePath) && is_readable($configFilePath)) {
                $configFileContent = file_get_contents($configFilePath);
                if (!empty($configFileContent)) {
                    $result['DB_NAME'] = $this->getTextBetweenTags($configFileContent, 'db');
                    $result['TABLE_PREFIX'] = $this->getTextBetweenTags($configFileContent, 'dbprefix');
                    $result['DB_USER'] = $this->getTextBetweenTags($configFileContent, 'user');
                    $result['DB_PASSWORD'] = $this->getTextBetweenTags($configFileContent, 'password');
                    $result['DB_HOST'] = $this->getTextBetweenTags($configFileContent, 'host');
                    $temp = $this->checkDBConfigParameters($result, $cms);
                    return $temp;
                } else {
                    return oseFirewallBase::prepareErrorMessage('The config file content is empty for the Joomla Website');
                }
            } else {
                return oseFirewallBase::prepareErrorMessage('The configuration file is not available to read for the Joomla Website');
            }
        } else if ($cms == 'wp') {
            if (file_exists($configFilePath) && is_readable($configFilePath)) {
                $configFileContent = file_get_contents($configFilePath, TOKEN_PARSE);
                if (!empty($configFileContent)) {
                    $result = $this->getDbInfoWordpress($configFileContent);
                    $temp = $this->checkDBConfigParameters($result, $cms);
                    return $temp;
                } else {
                    return oseFirewallBase::prepareErrorMessage('The config file content is empty for the Wordpress Website');
                }

            } else {
                return oseFirewallBase::prepareErrorMessage('The configuration file is not available to read for the Wordpress Website');
            }
        }
    }

    public function checkDBConfigParameters($params, $cms)
    {
        if ($cms == 'jm') {
            $websitetype = 'Joomla';
        } else {
            $websitetype = 'Wordpress';
        }
        if (empty($params['DB_NAME'])) {
            return oseFirewallBase::prepareErrorMessage('The database name cannot be accessed for the ' . $websitetype . ' Website');
        } else if (empty($params['TABLE_PREFIX'])) {
            return oseFirewallBase::prepareErrorMessage('The database prefix cannot be accessed for the ' . $websitetype . ' Website');
        } else if (empty($params['DB_USER'])) {
            return oseFirewallBase::prepareErrorMessage('The user name cannot be accessed for the ' . $websitetype . ' Website');
        } else if (empty($params['DB_PASSWORD'])) {
            return oseFirewallBase::prepareErrorMessage('The password cannot be accessed for the ' . $websitetype . ' Website');
        } else if (empty($params['DB_HOST'])) {
            return oseFirewallBase::prepareErrorMessage('The host cannot be accessed for the ' . $websitetype . ' Website');
        } else {
            $params['cms'] = $cms;
            return oseFirewallBase::prepareSuccessMessage($params);
        }
    }

    public function getConfigFilePath($accountPath, $cms)
    {
        if ($cms == 'jm') {
            $configFile = '/configuration\.php$/';
        } else {
            $configFile = '/wp-config\.php$/';
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($accountPath),
            RecursiveIteratorIterator::SELF_FIRST);
        $iterator->setMaxDepth(1);
        foreach ($iterator as $file) {
            if (preg_match($configFile, $file->getRealpath())) {
                $configFilePath = $file->getRealpath();
                if (file_exists($configFilePath)) {
                    return $configFilePath;
                }
            }
        }
        return false;
    }

    //coce to detect if the account has multiple websites
    public function hasMultipleWebsites($accountPath)
    {
        $joomla = array();
        $wordpress = array();
        $result = array();
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($accountPath),
            RecursiveIteratorIterator::SELF_FIRST);
        $iterator->setMaxDepth(1);
        $configFile_jm = "/configuration\.php$/im";
        $configFile_wp = "/wp-config\.php$/im";
        foreach ($iterator as $file) {
            //detect joomla config files and store the file path
            $configFilePath = $file->getRealpath();
            if (!empty($configFilePath)) {
                if (preg_match($configFile_jm, $configFilePath)) {
                    if (file_exists($configFilePath)) {
                        array_push($joomla, $configFilePath);
                    }
                }
                //same thing for wordpress
                if (preg_match($configFile_wp, $configFilePath)) {
                    if (file_exists($configFilePath)) {
                        array_push($wordpress, $configFilePath);
                    }
                }
            }
        }
        $result['wp'] = count($wordpress);
        $result['jm'] = count($joomla);
        return $result;
    }

    //regex to get the db variables from Joomla config file
    private function getTextBetweenTags($string, $variable)
    {
        $pattern = "/\s*public\s*[$]$variable\s*=\s*[\'\"](.*)[\'\"];/";
        preg_match($pattern, $string, $matches);
        return $matches[1];
    }

    public function getDbInfoWordpress($configFileContent)
    {
        $file = $configFileContent;
        $tokens = token_get_all($file);
        $token = reset($tokens);
        $defines = array();
        $state = 0;
        $key = '';
        $value = '';
        if (!empty($tokens) && !empty($token)) {
            while ($token) {
                if (is_array($token)) {
                    if ($token[0] == T_WHITESPACE || $token[0] == T_COMMENT || $token[0] == T_DOC_COMMENT) {
                        // do nothing
                    } else if ($token[0] == T_STRING && strtolower($token[1]) == 'define') {
                        $state = 1;
                    } else if ($state == 2 && $this->is_constant($token[0])) {
                        $key = $token[1];
                        $state = 3;
                    } else if ($state == 4 && $this->is_constant($token[0])) {
                        $value = $token[1];
                        $state = 5;
                    }
                } else {
                    $symbol = trim($token);
                    if ($symbol == '(' && $state == 1) {
                        $state = 2;
                    } else if ($symbol == ',' && $state == 3) {
                        $state = 4;
                    } else if ($symbol == ')' && $state == 5) {
                        $defines[$this->strip($key)] = $this->strip($value);
                        $state = 0;
                    }
                }
                $token = next($tokens);
            }
            $params = $this->getWordpressDBParamters($defines, $configFileContent);
            return $params;
        } else {
            //problem in retrieving config variables for the wordpress
            return false;
        }
    }

    public function getWordpressDBParamters($defines, $configFileContent)
    {
        $wpDbInfo = array();
        $required_params = array('DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_HOST');
        foreach ($defines as $key => $value) {
            if (in_array($key, $required_params)) {
                $wpDbInfo[$key] = $value;
            }
        }
        $wpDbInfo['TABLE_PREFIX'] = $this->getWordpressTablePrefix($configFileContent);
        return $wpDbInfo;
    }


    public function is_constant($token)
    {
        return $token == T_CONSTANT_ENCAPSED_STRING || $token == T_STRING ||
        $token == T_LNUMBER || $token == T_DNUMBER;
    }

    private function strip($value)
    {
        return preg_replace('!^([\'"])(.*)\1$!', '$2', $value);
    }

    //to get the table prefix from the wordpress configuration file
    public function getWordpressTablePrefix($string)
    {
        $pattern = "/\s*[$]table_prefix\s*=\s*[\'\"](.*)[\'\"];/";
        preg_match($pattern, $string, $matches);
        return $matches[1];
    }

    //CHECK DATABASE CONNECTION USING THE PARAMETERS
    public function testDbConnection_suite($dbConfig)
    {
        $this->backupLog_suite("testDbConnection - testing db connection ");
        $host = $dbConfig['DB_HOST'];
        $dbname = $dbConfig['DB_NAME'];
        try {
            $dbh = new pdo("mysql:host=$host;dbname=$dbname",
                $dbConfig['DB_USER'],
                $dbConfig['DB_PASSWORD'],
                array(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT));   // array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $this->backupLog_suite("testDbConnection - DB CONNECTION SUCCESSFUL");
            return (array('status' => true));
        } catch (PDOException $ex) {
            $this->backupLog_suite("testDbConnection - FAILED TO CONNECT TO THE DATABASE, exiting with error");
            $this->logErrorBackup_suite("testDbConnection - FAILED TO CONNECT TO THE DATABASE,exiting with error");
            return oseFirewallBase::prepareErrorMessage($ex->getMessage());
//            return ((array('status' => false, 'info' => 'Unable to connect')));
        }
    }

    public function addDataBaseConfig($data, $accountname, $accountpath)
    {
        $check = $this->checkAccountNamePath($accountname, $accountpath);
        if ($check['status'] == 0) {
            return $check;
        }
        $cleanedData = $this->cleanUserInput($data);
        if ($cleanedData['status'] == 0) {
            return $cleanedData;
        } else {
            $result = $this->testDbConnection_suite($cleanedData['info']);
            if ($result['status'] == true) {
                //add the details to the db
                if ((!isset($data['cms']) || empty($data['cms']))) {
                    oseFirewall::callLibClass('vsscanner', 'cfscanner');
                    $scanner = new cfScanner ();
                    $result = $scanner->suitePathDetect($accountpath);
                    if (isset($result['cms'])) {
                        $data['cms'] = $result['cms'];
                    }
                }
                $temp = $this->addDbConfig($accountname, $accountpath, $data);
                return $temp;
            } else {
                //failed to connect to the database
                //ask user to insert the details again
                return oseFirewallBase::prepareErrorMessage('Failed to connect to the Database ,Please check the details');
            }
        }

    }

    public function checkAccountNamePath($accountname, $accountpath)
    {
        if (empty($accountname)) {
            return oseFirewallBase::prepareErrorMessage('Account name is not set');
        }
        if (empty($accountpath)) {
            return oseFirewallBase::prepareErrorMessage('Acccount path is not set ');
        }
        return oseFirewallBase::prepareSuccessMessage('Account name and path is not empty');
    }

    public function cleanUserInput($data)
    {
        $sanitized_data = array();
        foreach ($data as $key => $value) {
            $value = oseFirewallBase::cleanupVar($value);
            if (empty($value)) {
                return oseFirewallBase::prepareErrorMessage($key . 'cannot be empty');
            } else {
                $sanitized_data[$key] = $value;
            }
        }
        return oseFirewallBase::prepareSuccessMessage($sanitized_data);
    }

    public function addDbConfig($accountname, $accountpath, $dbconfig)
    {
        $dbconfig_existing = $this->checkifDbConfigExists($accountname);
        if ($dbconfig_existing['status'] == 0) {
            //add the data
            $varValues = array(
                'accountname' => $accountname,//cleanupVar
                'accountpath' => $accountpath,
                'dbconfig' => json_encode(array($dbconfig)),
            );
            $result = $this->db->addData('insert', $this->dbconfiggit_table, '', '', $varValues);
            if (empty($result)) {
                return oseFirewallBase::prepareErrorMessage('There was some problem in updating the database,Please try again');
            } else {
                return oseFirewallBase::prepareSuccessMessage('The Database Configuration details have saved Successfully');
            }
        } else {
            return oseFirewallBase::prepareErrorMessage('The db config is not empty ');
        }
    }

    //check if the db config exists for an user
    //if yes append the details
    public function checkifDbConfigExists($accountname)
    {
        $query = "SELECT * FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE `accountname`=" . $this->db->quoteValue($accountname);
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if (empty($result)) {
            return oseFirewallBase::prepareErrorMessage('DB Config does not exists for the account ' . $accountname);
        } else {
            return oseFirewallBase::prepareSuccessMessage($result);
        }
    }

    public function clearAccountDetails($accountname = false, $accountpath = false)
    {
        if ($accountname == false && $accountpath == false) {
            return false;
        }
        if ($accountname == false || empty($accountname)) {
            $condition = "WHERE `accountpath` = " . $this->db->quoteValue($accountpath);
        } else {
            $condition = "WHERE `accountname` = " . $this->db->quoteValue($accountname);
        }
        $query = "DELETE FROM " . $this->db->quoteTable($this->dbconfiggit_table) . $condition;
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        return $result;
    }

    public function getGitLogfromDB_suite($accountname, $accountpath)
    {
        $limit = oRequest::getInt('length', 15);
        $start = oRequest::getInt('start', 0);
        $accountname = oseFirewallBase::cleanupVar($accountname);
        if (!isset($this->accountDb)) {
            $this->setUpDatabseObject($accountname);
        }
        if (!empty($limit)) {
            $this->getLimitStm($start, $limit);
        }
        $query = "SELECT * FROM `#__osefirewall_gitlog` WHERE 1 ORDER BY `id` DESC". " " . $this->limitStm;;
        $this->accountDb->setQuery($query);
        $result = $this->accountDb->loadResultList();
        $results = $this->gitLogConversion($result);
        return $results;

    }

    public function gitLogConversion($result)
    {
        $data = array();
        $array = array();
        if (!empty($result)) {
            $tmps = ($result);
            foreach ($tmps as $tmp) {
                if (!empty($tmp['commit_id'])) {
                    $array['id'] = $tmp['id'];
                    $array['commitID'] = $tmp['commit_id'];
                    $array['commitMsg'] = $tmp['commit_message'] . " " . '<i class="text-success glyphicon glyphicon-info-sign" style="cursor:pointer; font-size:20px; float:right; color:#1cab94;" href="javascript:void(0);" onclick="viewChangeHistory(\'' . $tmp['commit_id'] . '\')" title = "View list of files changed in this backup"></i>';
                    $array['commitTime'] = $tmp['commit_time'];
                    $array['isHead'] = ($tmp['is_head'] == 1) ? '<i class="text-success glyphicon glyphicon-ok-sign" style="font-size:20px; padding-left: 30px; color:#1cab94; opacity: 0.8;" title="You are currently on this backup"></i>' : '';
                    if (preg_match('/initial\s*local\s*backup/im', $array['commitMsg'])) {
                        if (preg_match('/Rest\s*of\s*the\s*files/im', $array['commitMsg'])) {
                            $array['rollback'] = ($tmp['is_head'] == 0) ? '<a href="javascript:void(0);" onclick="confirmRollback(\'' . $tmp['commit_id'] . '\')" title = "Revert to this backup"  ><i class="text-block glyphicon glyphicon-repeat" style="font-size:21px"></i></a>'
                                : '<a href="javascript:void(0);" onclick="confirmRollback(\'' . $tmp['commit_id'] . '\')" title = "Discard all the unsaved changes ">
                                 <i class="text-success glyphicon glyphicon-ok-sign" style="font-size:21px;color:#1cab94; opacity: 0.8"></i></a>';
                        } else {
                            $array['rollback'] = '';
                        }
                    } else {
                        $array['rollback'] = ($tmp['is_head'] == 0) ? '<a href="javascript:void(0);" onclick="confirmRollback(\'' . $tmp['commit_id'] . '\')" title = "Revert to this backup"  ><i class="text-block glyphicon glyphicon-repeat" style="font-size:21px"></i></a>'
                            : '<a href="javascript:void(0);" onclick="confirmRollback(\'' . $tmp['commit_id'] . '\')" title = "Discard all the unsaved changes ">
                             <i class="text-success glyphicon glyphicon-ok-sign" style="font-size:21px;color:#1cab94; opacity: 0.8"></i></a>';
                    }
                    $array['zipDownload'] = ($tmp['is_head'] == 1) ? '<i class="fa  fa-file-zip-o" style="font-size:25px; padding-left:20px; cursor:pointer; color:#333333" href="javascript:void(0);" onclick="findChanges()" title = "Download the complete website in zip format"></i>' : '';
                    $data['data'][] = $array;
                }
            }
        } else {
            $data['data'] = "";
        }
        $data['recordsTotal'] = $this->getAllLogRecordCount();
        $data['recordsFiltered'] = $data['recordsTotal'] ;
        return $data;
    }

    public function getAllLogRecordCount()
    {
        // Get total count
        $sql = "SELECT * FROM `#__osefirewall_gitlog` WHERE 1 ";
        $this->accountDb->setQuery($sql);
        $result = $this->accountDb->loadResultList();
        return count($result);
    }

    protected function getLimitStm($start, $limit)
    {
        if (!empty($limit)) {
            $this->limitStm = " LIMIT " . (int)$start . ", " . (int)$limit;
        }
    }




    //chnage the working directory
    public function changetoAccountDir($accountpath)
    {
        if (file_exists($accountpath)) {
            $gitcmd = "cd $accountpath";
            $output = $this->runShellCommand($gitcmd);
            if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || !empty($output['stderr'])) {
                $result['status'] = 0;
                $result['info'] = "There was some problems in changing the directory <br/> ERROR:<br/>" . $output['stderr'];
                return $result;
            } else {

                $temp = oseFirewallBase::prepareSuccessMessage('The directory has been chnaged to ' . $accountpath);
                return $temp;
            }
        } else {
            return oseFirewallBase::prepareErrorMessage('The file ' . $accountpath . "does not exists ");
        }
    }

    //get the full path for the current working directory
    public function getCurrentDirectory()
    {
        $gitcmd = "pwd";
        $output = $this->runShellCommand($gitcmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || !empty($output['stderr'])) {
            $result['status'] = 0;
            $result['info'] = "There was some problems in getting the current directory <br/> ERROR:<br/>" . $output['stderr'];
        } else {
            return oseFirewallBase::prepareSuccessMessage("Current directory is" . $output['stdout']);
        }
    }

    public function isinit_suite($accountpath)
    {
        /*
         *  0 => error
         *  1 => initalise
         *  2 => not initalised
         */
        $this->backupLog_suite("isint - checking if git is initialised for the account : $accountpath", $accountpath);
        $fileExists = $this->checkIfFileExists($accountpath);
        if ($fileExists['status'] = 1) {
            $flag = $this->runShellCommand(" cd $accountpath ; git rev-parse --is-inside-work-tree");
            $result = $flag['stdout'];
            if ($result == true) {
                $this->backupLog_suite("GIT HAS BEEN INITALISED FOR THE ACCOUNT ", $accountpath);
                return oseFirewallBase::prepareSuccessMessage('The git has been initialised');
            } else {
                $this->backupLog_suite("GIT HAS NOT BEEN INITALISED " . $flag['stderr'], $accountpath);
                return oseFirewallBase::prepareCustomMessage(2, $flag['stderr']);
            }
        } else {
            //ERROR IN CHANGING DIRECTORY
            return $fileExists;
        }
    }

    public function checkIfFileExists($accounpath)
    {
        $this->backupLog_suite("checkIfFileExists()  - checking if the file exists for $accounpath", $accounpath);
        if (file_exists($accounpath)) {
            $this->backupLog_suite("checkIfFileExists() - file exists , exiting with success", $accounpath);
            return oseFirewallBase::prepareSuccessMessage("The file $accounpath exists ");
        } else {
            $this->backupLog_suite("checkIfFileExists() -The file $accounpath does not exists ,exiting function with error ", $accounpath);
            $this->logErrorBackup_suite("The file $accounpath does not exists ", $accounpath);
            return oseFirewallBase::prepareErrorMessage("The file $accounpath does not exists ");
        }
    }

    /*
     * Setup the connection with the users Database
     * and get the list ofdatabse tables and write them to a file
     */
    public function backupDB($remote = false)
    {
        $accountname = oRequest::getVar('accountname', NULL);
        $accountpath = oRequest::getVar('accountpath', NULL);
        $dbConfig = $this->getDatabaseConfig($accountname);
        $tables = $this->accountDb->getTableList();
        if (file_exists($this->dbTempFilePath)) {
            unlink($this->dbTempFilePath);
        }
        $write_result = $this->writeTablesList($tables, array());
        if ($write_result['status'] == 0) {
            return $write_result;
        }
        return $this->writeSQL($remote);
    }


    protected function writeTablesList($tables, $backeduplist)
    {
        $newTableArray = array_reverse($tables);
        $content = "<?php\n" . '$tables = array("tables"=>' . var_export($newTableArray, true) . ', "backeduplist" =>' . var_export($backeduplist, true) . ");";
        if (isset($this->dbTempFilePath)) {
            $writeFile_result = $this->writeFile($this->dbTempFilePath, $content);
            if ($writeFile_result) {
                return oseFirewallBase::prepareSuccessMessage('File write was successfull');
            } else {
                return oseFirewallBase::prepareErrorMessage('There was some problem in writing the DB Table file contents');
            }
        } else {
            return oseFirewallBase::prepareErrorMessage('The DB Temp File Path is not defined');
        }
    }


    public function writeSQL($remote = false)
    {
        // Get All request variables;
        oseFirewall::loadRequest();
        $key = oRequest::getVar('key', NULL);
        $type = oRequest::getVar('type', NULL);
        // Get all tables and write all tables;
        $this->tables = $this->getBackupDbtables();
        if (file_exists($this->dbTempFilePath) && !empty($this->tables['tables']))  //TODO : CHNAGE TO ACCPUNT/PROTECTED/DATA
        {
            $this->currentTable = array_pop($this->tables['tables']);
            array_push($this->tables['backeduplist'], $this->currentTable);
            $this->writeTableSQL();
            $this->writeTablesList($this->tables['tables'], $this->tables['backeduplist']);
        }
        $result = $this->checkTaskComplete($key, $type, $remote);
        return $result;
    }

    //get list of the tables in the backupdb file
    public function getBackupDbtables()
    {
        $tables = array();
        $tableFile = $this->dbTempFilePath;
        if (file_exists($tableFile)) {
            require($tableFile);
        }
        return $tables;
    }

    protected function writeTableSQL()
    {
        // Get All Create Table Queries
        $sql = '';
        $createTableQuery = $this->getCreateTable($this->currentTable);
        if (!empty ($createTableQuery)) {
            $sql .= $createTableQuery;
            $allRows = $this->getAllRows($this->currentTable);
            if (!empty ($allRows)) {
                $sql .= $this->getInsertTable($this->currentTable, $allRows);
            }
        }
        $file = $this->centroraBackupFolder . ODS . 'gitbackup' . ODS . $this->currentTable . ".sql";
        $this->writeFile($file, $sql);
    }

    public function getCreateTable($table)
    {
        $query = $this->getCreateTableFromDB($table);
        $viewPattern = $this->getViewPattern();
        $constraintPattern = $this->getConstraintPattern();
        if (preg_match($viewPattern, $query, $matches) > 0) {
            return null;
        } else {
            if (preg_match($constraintPattern, $query, $matches) > 0) {
                $query = preg_replace($constraintPattern, "", $query);
            }
            $return = "--\n";
            $return .= "-- Table structure for " . $this->db->QuoteKey($table) . "\n";
            $return .= "--\n\n";
            $return .= $query;
            $return .= ";\n\n";
            return $return;
        }
    }

    private function getCreateTableFromDB($table)
    {
        $sql = 'SHOW CREATE TABLE ' . $this->accountDb->quoteKey($table);
        $this->accountDb->setQuery($sql);
        $result = $this->accountDb->loadResult();
        $tmp = array_values($result);
        return $tmp [1];
    }

    private function getViewPattern()
    {
        return "/CREATE\s*ALGORITHM\=UNDEFINED\s*[\w|\=|\`|\@|\s]*.*?VIEW\s/ims";
    }

    private function getConstraintPattern()
    {
        return "/\,[CONSTRAINT|\s|\`|\w]+FOREIGN\s*KEY[\s|\`|\w|\(|\)]+ON\s*[UPDATE|DELETE]+\s*[RESTRICT|NO\s*ACTION|CASCADE|SET\s*NULL]+/ims";
    }

    protected function getAllRows($table)
    {
        $query = 'SELECT * FROM ' . $this->accountDb->quoteKey($table);
        $this->accountDb->setQuery($query);
        $results = $this->accountDb->loadResultList();
        return $results;
    }

    protected function getInsertTable($table, $allRows)
    {
        $sql = "--\n";
        $sql .= "-- Dumping data for table " . $this->accountDb->QuoteKey($table) . "\n";
        $sql .= "--\n\n";
        $sql .= "INSERT INTO " . $this->accountDb->QuoteKey($table);
        $sql .= $this->getColumns($allRows [0]);
        $sql .= $this->getValues($allRows);
        $sql .= "\n\n";
        return $sql;
    }

    private function getColumns($row)
    {
        $k = array();
        $i = 0;
        foreach ($row as $key => $value) {
            $k [$i] = $this->accountDb->QuoteKey($key);
            $i++;
        }
        $return = " (" . implode(", ", $k) . ") ";
        return $return;
    }

    private function getValues($rows)
    {
        $varray = array();
        foreach ($rows as $row) {
            $v = array();
            $i = 0;
            foreach ($row as $key => $value) {
                if (is_null($value)) {
                    $v [$i] = 'NULL';
                } else {
                    if (is_numeric($value)) {
                        $v [$i] = ( int )$value;
                    } else {
                        $v [$i] = $this->accountDb->QuoteValue($value);
                    }
                }
                $i++;
            }
            $varray [] = "(" . implode(", ", $v) . ")";
        }
        $return = " VALUES \n" . implode(",\n", $varray) . ";";
        return $return;
    }

    public function init_suite($accountpath)
    {
        $this->backupLog_suite("init - trying to initalise the git for $accountpath", $accountpath);
        $result = $this->runShellCommand("cd $accountpath ; git init");
        if ((strpos($result['stderr'], 'fatal') !== false) || (strpos($result['stderr'], 'error') !== false) || (!file_exists($this->gitfolder))) {
            //ERROR : if there is a fatal error
            $output['status'] = 0;
            $output['info'] = "There were some problem in initialising the git <br/>ERROR: <br/>" . $result['stderr'];
            $this->logErrorBackup_suite("There were some problem in initialising the git <br/>ERROR: <br/>" . $result['stderr'], $accountpath);
            $this->backupLog_suite("There were some problem in initialising the git <br/>ERROR: <br/>" . $result['stderr'], $accountpath);

        } else {
            //IF THE COMMAND AND THE FOLDER WERE CREATED SUCCESSFULLY
            $output['status'] = 1;
            $output['info'] = "The git has been initialised successfully ";
            $this->backupLog_suite("The git has been initialised successfully .$accountpath", $accountpath);
        }
        return $output;
    }

    public function addUserInfoGitConfig_suite($accountpath,$accountname)
    {
        $this->backupLog_suite("addUserInfoGitConfig -start");
        $userinfo = $this->getUserInfor();
        $gitcmd = "cd $accountpath; git config user.email " . $userinfo['email'] . " ; git config user.name " . $accountname. " git config --local pack.windowMemory '100m' ; git config --local pack.packSizeLimit '100m' ; git config --local pack.threads '1'";
        $result = $this->runShellCommand($gitcmd);
        if ($result['stderr'] != null) {   //ERROR
            $output['status'] = 0;
            $output['info'] = "Problem in adding username and email in cofig file <br/>ERROR: <br/>" . $result['stderr'];
            $this->backupLog_suite("Problem in adding username and email in cofig file for <br/>ERROR: <br/>" . $result['stderr'], $accountname);
            $this->logErrorBackup_suite("Problem in adding username and email in cofig file for <br/>ERROR: <br/>" . $result['stderr'], $accountname);
        } else {  //SUCCESS
            $output['status'] = 1;
            $output['info'] = "successfully added username and email in the config file";
            $this->backupLog_suite("successfully added username and email in the config file", $accountname);
        }
        return $output;
    }

    protected function createAlterTableSQL_suite($path, $tables)
    {
        if (!empty($tables)) {
            $sql = $this->createViewQueries($tables);
            $alterTableQuery = $this->createAlterQueries($tables);
            if (!empty($alterTableQuery)) {
                $sql .= $alterTableQuery;
            }
            $fileName = $path . ODS . $this->alterTables;
            if (file_exists($fileName)) {
                unlink($fileName);
            }
            return $this->writeFile($fileName, $sql);
        } else {
            return false;
        }
    }

    protected function createViewQueries($tables)
    {
        $sql = '';
        if (!empty($tables))
            // Get All Create View Queries
            foreach ($tables as $key => $table) {
                $createViewQuery = $this->getCreateView($table);
                if (!empty ($createViewQuery)) {
                    $sql .= $createViewQuery;
                }
            }
        return $sql;
    }

    protected function createAlterQueries($tables)
    {
        $sql = '';
        if (!empty($tables))
            // Get All Alter table Queries
            foreach ($tables as $key => $table) {
                $createViewQuery = $this->getAlterTable($table);
                if (!empty ($createViewQuery)) {
                    $sql .= $createViewQuery;
                }
            }
        return $sql;
    }

    protected function getCreateView($table)
    {
        $query = $this->getCreateTableFromDB($table);
        $viewPattern = $this->getViewPattern();
        if (preg_match($viewPattern, $query, $matches) > 0) {
            $query = preg_replace($viewPattern, "CREATE VIEW ", $query);
            $return = "--\n";
            $return .= "-- View structure for " . $this->db->QuoteKey($table) . "\n";
            $return .= "--\n\n";
            $dropquery = "DROP TABLE IF EXISTS " . $this->db->QuoteKey($table) . ";\n";
            $dropquery .= "DROP VIEW IF EXISTS " . $this->db->QuoteKey($table) . ";\n";
            $query = $dropquery . $query;
            $return .= $query;
            $return .= ";\n\n";
            return $return;
        } else {
            return null;
        }
    }

    protected function getAlterTable($table)
    {
        $query = $this->getCreateTableFromDB($table);
        $constraintPattern = $this->getConstraintPattern();
        if (preg_match_all($constraintPattern, $query, $matches) > 0) {
            $return = "--\n";
            $return .= "-- Alter table structure for " . $this->db->QuoteKey($table) . "\n";
            $return .= "--\n\n";
            foreach ($matches as $match) {
                foreach ($match as $m) {
                    $m = str_replace(array(
                        ",",
                        ";",
                        "\n"
                    ), "", $m);
                    $return .= "ALTER TABLE " . $this->db->QuoteKey($table) . " ADD " . $m . ";\n";
                }
            }
            $return .= "\n\n";
            return $return;
        } else {
            return null;
        }
    }

    protected function deleteTableFile()
    {
        $tableFile = $this->dbTempFilePath;
        if (file_exists($tableFile)) {
            unlink($tableFile);
        }
    }

    protected function getCrawbackURL($key, $statusMsg = '')
    {
        if (!empty($statusMsg)) {
            $webkey = $this->getWebKey();
            return API_SERVER . "gitbackup/completeGitBackup?webkey=" . $webkey . "&key=" . $key . "&statusMsg=" . urlencode($statusMsg);
        } else {
            $webkey = $this->getWebKey();
            return API_SERVER . "gitbackup/contGitBackup?webkey=" . $webkey . "&key=" . $key;
        }
    }

    protected function getWebKey()
    {
        $query = "SELECT * FROM `#__ose_secConfig` WHERE `key` = 'webkey'";
        $this->db->setQuery($query);
        $webkey = $this->db->loadObject()->value;
        return $webkey;
    }

    protected function sendRequestGitBackup($url)
    {
        $User_Agent = 'Mozilla/5.0 (X11; Linux i686) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31';
        $request_headers = array();
        $request_headers[] = 'User-Agent: ' . $User_Agent;
        $request_headers[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        // Get cURL resource
        $curl = curl_init();
        // Set some options - we are passing in a useragent too here
        curl_setopt_array($curl, array(
            CURLOPT_HTTPHEADER => $request_headers,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'Centrora Security Download Request Agent',
            CURLOPT_TIMEOUT => 5
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        // Close request to clear up some resources
        curl_close($curl);
        return $resp;
    }


    public function localBackup_suite($type = false,$accountname = false, $accountpath = false)
    {
        $this->backupLog_suite("*****starting local backup ");

        if(empty($accountname) && empty($accountpath))
        {
            $accountpath = oRequest::getVar('accountpath', NULL);
            $accountname = oRequest::getVar('accountname', NULL);
        }

        //check if there are any changes
        $temp = $this->findChanges_suite($accountpath);
        if ($temp['status'] == 1) {   //prepare and write the folder list into the file
            $pre_req_result = $this->prerequisitesforcommit_suite($accountpath);
            if (!empty($pre_req_result) && isset($pre_req_result['status']) && $pre_req_result['status'] == 0) {
                //pre -requisite errors :
                //errro in getting db config
                //error in getting the folder list
                return $pre_req_result;
            }
            if (file_exists($this->folder_List)) {
                //if list of folders was created successfully
                $this->ignoreLargeZipFiles_suite($accountpath);
                $result['status'] = 1; //SUCCESS
                return $result;
            } else { //if file was not created
                return oseFirewallBase::prepareCustomErrorMessage("The folder list was not created ", "medium");
            }
        } else if ($temp['status'] == 2) {
            // if there are no changes, there is no need to commit
            $result['status'] = 2;  //STOP THE BACKUP NO NEED TO COMMIT
            $result['info'] = "There are no New Changes to Commit ";
            if (file_exists($this->folder_List)) {
                unlink($this->folder_List);
            }
            return $result;
        } else {
            //error
            return $temp;
        }
    }

    //checks if there are any changes in the files
    public function findChanges_suite($accountpath)
    {
        $this->backupLog_suite("finding chnages for :$accountpath", $accountpath);
        $result = $this->getStatus_suite($accountpath);
        $status = oseFirewallBase::checkSubscriptionStatus(false);
        if ($result['status'] == 1 || $result['status'] == 2) {
            if ($result['status'] == 1) {
                $result['status'] = 1;
                $result['subscription'] = true;
                $this->backupLog_suite("There are some changes that needs to be committed for account : $accountpath", $accountpath);
                return $result;  // the repo has some changes
            } else if ($result['status'] == 2) {
                $result['status'] = 2;
                $result['subscription'] = $status;
                $this->backupLog_suite("There are no new changes that needs to be committed for account : $accountpath", $accountpath);
                return $result;   // the repo is upto date
            }
        } else {
            //STATUS == 0 => PROBLEM IN FINDING CHNAGES
            //EMPTY ARRAY => FORMATING  THE LIST OF CHNAGES
            return oseFirewallBase::prepareCustomErrorMessage($result['info'], "medium"); //return null if there is a problem in accessing the get status
        }
    }

    public function getStatus_suite($accountpath)
    {
        $this->backupLog_suite("Finding new chnages for the account : $accountpath", $accountpath);
        $gitCmd = "cd $accountpath; git status --porcelain -uall";
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR
            $this->logErrorBackup_suite("There was some error in finding the chnages for account : $accountpath " . $output['stderr'], $accountpath);
            $this->backupLog_suite("There was some error in finding the chnages for account : $accountpath " . $output['stderr'], $accountpath);
            return oseFirewallBase::prepareErrorMessage($output['stderr']);
        } else {   //SUCCESS
            if (empty($output['stdout'])) {
                $this->backupLog_suite("There are no new changes to commit  : $accountpath " . $output['stderr'], $accountpath);
                return oseFirewallBase::prepareCustomMessage(2, 'No Changes to commit');
            } else {
                //list of files that needs to be committed
                $output = (string)$output['stdout'];
                $tmp = explode("\n", $output);
                $return = array();
                if (empty($output)) {
                    $this->logErrorBackup_suite("There was some problem in getting the formatted list of new changes for account :$accountpath", $accountpath);
                    $this->backupLog_suite("There was some problem in getting the formatted list of new changes for account :$accountpath", $accountpath);
                    return oseFirewallBase::prepareErrorMessage("There was some problem in getting the formatted list of new changes for account :$accountpath");
                }
                foreach ($tmp as $k => $line) {
                    $return[$k] = explode(" ", trim($line), 2);
                }
                $this->backupLog_suite("The list of changes were prepared successfully", $accountpath);
                return oseFirewallBase::prepareSuccessMessage($return);
            }
        }
    }


    //things to do before perfroming git init for large websitess
    public function prerequisitesforcommit_suite($accountpath)
    {
        $cms = null;
        $account_dbConfig = $this->getDatabaseConfig(false, $accountpath);
        if ($account_dbConfig['status'] == 1) {
            if (count($account_dbConfig['info'])) {
                $cms = $account_dbConfig['info'][0]->cms;
            }
        } else {
            //status = 0
            //account name and path are empty while getting the db config
            //no db config exists
            return $account_dbConfig;
        }
        $this->protectGit_suite($accountpath);
        $this->moveOldZipFilesPatch($accountpath, $cms);
        $list = $this->getFoldersList($accountpath);
        if ((!empty($list)) && (isset($list['status']) && $list['status'] == 0)) {
            //error in getting folder list and formatting the conents
            return $list;
        }
        if (file_exists($this->folder_List)) //delete an already existing folder list if the previous git backup failed
        {
            $this->backupLog_suite("deleted old folder list in $this->folder_List", $accountpath);
            unlink($this->folder_List);
        }
        if ($cms == "wp") {
            $new_list = $this->uploadPriority($accountpath, $list);
            $folderList_ignorePath = 'wp-content' . ODS . 'CentroraBackup' . ODS . 'folderlist.php';
            $this->gitIgnoreFile($folderList_ignorePath, $accountpath);
            $this->writeFolderList($new_list, array());
        } else if ($cms == 'jm') {
            $folderList_ignorePath = 'media' . ODS . 'CentroraBackup' . ODS . 'folderlist.php';
            $this->gitIgnoreFile($folderList_ignorePath, $accountpath);
            $this->writeFolderList($list, array());
        }


    }

    protected function protectGit_suite($accountpath)
    {
        $htaccess = OSEFWDIR . 'protected' . ODS . '.htaccess';
        $dest = $accountpath . ODS . '.git' . ODS . '.htaccess';
        if (!file_exists($dest)) {
            copy($htaccess, $dest);
            $this->backupLog_suite("Git protection has been added for account path : $accountpath", $accountpath);
        }
        $this->backupLog_suite("Git has already been protecetd for account path : $accountpath", $accountpath);

    }

    public function moveOldZipFilesPatch($accountpath, $cms)
    {
        //add an entry for the folder in the gitignore file
        $backupZip_IgnorePath = null;
        if ($cms == 'wp') {
            $backupZip_IgnorePath = 'wp-content' . ODS . 'CentroraBackup' . ODS . 'BackupFiles';
        } else if ($cms == 'jm') {
            $backupZip_IgnorePath = 'media' . ODS . 'CentroraBackup' . ODS . 'BackupFiles';
        }
        $this->gitIgnoreFile($backupZip_IgnorePath, $accountpath);
        $fileslistfromdb = $this->getBackupFilesList();
        if (!empty($fileslistfromdb)) {
            if (!file_exists($this->zipBackupFolder)) {
                mkdir($this->zipBackupFolder);
            }
            $this->movebackupfiles($fileslistfromdb);
        }
    }

    public function getFoldersList($path)      //path should not contain "/" at the end
    {
        $gitCmd = "cd " . $path . ODS . "; ls -d */";
        $output = $this->runShellCommand($gitCmd);
        if (!empty($output) && isset($output['stdout']) && !empty($output['stdout'])) {
            $list = explode("/", $output['stdout']);
            $list = $this->removeNextLineFromString($list);
            if (empty($list)) {
                $this->logErrorBackup_suite("There was some problem in formatting the list of folders for path : $path", $path);
                $this->backupLog_suite("There was some problem in formatting the list of folders for path : $path", $path);
                return oseFirewallBase::prepareCustomErrorMessage("There was some problem in formatting the list of folders for path :$path", "medium");
            }
            $newlist = array_filter($list);
            //reverse the list as the last element is popperd first
            $reversedList = array_reverse($newlist);
            return $reversedList;
        } else {
            $this->logErrorBackup_suite("Cannot get the list of folders while local backup " . $output['stdout'] . " for path : $path", $path);
            $this->backupLog_suite("Cannot get the list of folders while local backup " . $output['stdout'] . " for path : $path", $path);
            return oseFirewallBase::prepareCustomErrorMessage("Cannot get the list of folders while local backup for path : $path", "medium", $output['stdout']);
        }
    }

    public function removeNextLineFromString($array)
    {
        $result = array();
        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $result[$key] = trim(preg_replace('/\s\s+/', ' ', $value));
            }
            return $result;
        } else {
            return array();
        }
    }

    public function uploadPriority($accountpath, $list)
    {
        //add the upload folder and content folder to the end of the list
        //so that they will be backed up first
        $foldername = "wp-content";
        $uploadfolder = "wp-content" . ODS . 'uploads';
        $uploadFolderCompletePath = $accountpath . ODS . $uploadfolder;
        if (file_exists($uploadFolderCompletePath)) {
            $position = array_search($foldername, $list);
            if ($position !== false) {
                array_splice($list, $position, 1);
                array_push($list, $foldername, $uploadfolder);
                $this->backupLog_suite("updated the priority folder list for the accountpath : $accountpath", $accountpath);
                return $list;
            } else {
                $this->backupLog_suite("the folder name does not exists, so using default fodler list for account path : $accountpath", $accountpath);
                return $list;
            }
        } else {
            $this->backupLog_suite("Upload fodler does not exist , using the default priority list for account : $accountpath", $accountpath);
            return $list;
        }

    }

    public function searchValueInArray($valuetoSearch, $array)
    {
        foreach ($array as $key => $value) {
            if (strpos($value, $valuetoSearch)) {
                return $key;
            }
        }
        return false;
    }

    public function gitIgnoreFile($filetoignore, $accountpath = false)
    {
        if ($accountpath == false) {
            $filepath = OSE_ABSPATH . ODS . ".gitignore";
        } else {
            $filepath = $accountpath . ODS . ".gitignore";
        }
        if (file_exists($filepath)) {
            //to avoid duplicate entries for a file
            if (strpos(file_get_contents($filepath), $filetoignore) == false) {
                file_put_contents($filepath, PHP_EOL . $filetoignore . PHP_EOL, FILE_APPEND);
            }
        } else {
            //if file doesnt exist, create 1 and make an entry for the files
            file_put_contents($filepath, PHP_EOL . $filetoignore . PHP_EOL, FILE_APPEND);
        }
        $this->backupLog_suite("git ignored the folder list $accountpath to the file  : $filepath ", $accountpath);
    }

    //returns all the list of all the backupzip files that have been generated
    public function getBackupFilesList()
    {
        $backuptable = '#__osefirewall_backup';
        $backup_table = $this->getAllRows($backuptable);
        return $backup_table;
    }

    //moves the files to the BackupFiles folder
    public function movebackupfiles($list)
    {
        foreach ($list as $value) {
            //to make sure its only updated once
            if (strpos($value['dbBackupPath'], 'BackupFiles') === false || strpos($value['dbBackupPath'], 'BackupFiles') === false) {
                if (!empty($value['dbBackupPath']) && empty($value['fileBackupPath'])) {
                    //db backup files
                    $temp = $this->getBackupFileName($value['dbBackupPath']);
                    $destinationpath = $this->prepareDestinationPath($temp);
                    if ($destinationpath !== null) {
                        //remove double slashes from the path if you have any
                        while (strpos($destinationpath, '//') !== false) {
                            $destinationpath = str_replace('//', '/', $destinationpath);
                        }
                        rename($value['dbBackupPath'], $destinationpath);
                        $varValues = array(
                            'id' => $value['id'],
                            'date' => $value['date'],
                            'type' => $value['type'],
                            'dbBackupPath' => $destinationpath,
                            'fileBackupPath' => $value['fileBackupPath'],
                            'server' => $value['server'],
                        );
                        $this->accountDb->addData('update', '#__osefirewall_backup', 'id', $value['id'], $varValues);
                    } else {
                        return;
                    }
                }
                if (empty($value['dbBackupPath']) && !empty($value['fileBackupPath'])) {
                    $sourcepath = dirname($value['fileBackupPath']);  //gets the path till the folder name
                    $foldername = basename($sourcepath); // gets the folder name
                    if (!empty($foldername)) {
                        $destinationpath = $this->zipBackupFolder . ODS . $foldername;
                    } else {
                        return;
                    }
                    //inserts the new folder name and appends the backup folder name
                    rename($sourcepath, $destinationpath);
                    $sqlpath = $this->prepareSqlPath($value['fileBackupPath']);
                    //remove double slashes from the path if you have any
                    while (strpos($sqlpath, '//') !== false) {
                        $sqlpath = str_replace('//', '/', $sqlpath);
                    }
                    $varValues = array(
                        'id' => $value['id'],
                        'date' => $value['date'],
                        'type' => $value['type'],
                        'dbBackupPath' => $value['dbBackupPath'],
                        'fileBackupPath' => $sqlpath,
                        'server' => $value['server'],
                    );
                    $this->accountDb->addData('update', '#__osefirewall_backup', 'id', $value['id'], $varValues);
                }
            }
        }
    }

    public function getBackupFileName($name)
    {
        $result = preg_split('/CentroraBackup/', $name);
        if (count($result) > 1) {
            return $result[1];
        }
        return null;
    }

    public function prepareDestinationPath($filename)
    {
        $result = null;
        if ($filename != null) {
            $result = $this->zipBackupFolder . ODS . $filename;

        }
        return $result;
    }

    //prepare the updated link of the sql
    public function prepareSqlPath($list)
    {
        $temp = $this->getBackupFileName($list);
        $result = $this->prepareDestinationPath($temp);
        return $result;

    }

    //writes the list of folders in a temporary file named "folderlist"
    public function writeFolderList($list, $backedupfolers)
    {
        $content = "<?php\n" . '$folderslist = array("folderslist"=>' . var_export($list, true) . ', "backedupfolders" =>' . var_export($backedupfolers, true) . ");";
        $this->writeFile($this->folder_List, $content);
        $this->backupLog_suite("wrote the folder list in $this->folder_List");
    }

    public function contLocalBackup_suite($type = false,$accountname = false,$accountpath = false)
    {

        if(empty($accountname) && empty($accountpath))
        {
            $accountpath = oRequest::getVar('accountpath', NULL);
            $accountname = oRequest::getVar('accountname', NULL);
        }
        $this->backupLog_suite("*****continuing the localbackup for account : $accountpath", $accountpath);
        $temp = $this->findChanges_suite($accountpath);
        if (empty($temp) || ((!empty($temp) && isset($temp['status']) && $temp['status'] == 0))) {
            //error in finding chnages or formatting them
            return $temp;
        } else {
            if ($temp['status'] == 1) {
                $result = $this->createLocalBackup_suite($type, $accountpath);
                return $result;
            } else if ($temp['status'] == 2) {
                // if there are no changes, there is no need to commit
                $result['status'] = 2;
                $result['info'] = "The backup is up to date";
                if (file_exists($this->folder_List)) {
                    unlink($this->folder_List);
                }
                $this->runGitGarbageCleaner_suite($accountpath);
                $this->backupLog_suite("No new changes exists for account $accountpath", $accountpath);
                return $result;
            }
        }
    }

    //Complete mechanism to stage and commit all the changes
    public function createLocalBackup_suite($type = false, $accountpath)
    {
        $result = null;
        $listfromfile = $this->getFolderListFromFile();
        if (empty($listfromfile) || (!isset($listfromfile['folderslist'])) || !isset($listfromfile['folderslist'])) {
            $this->logErrorBackup_suite("The folder list is not in proper format for account :$accountpath", $accountpath);
            $this->backupLog_suite("The folder list is not in proper format for account :$accountpath", $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage("The folder list is not im proper format for account :$accountpath", $accountpath);
        }
        $this->backupLog_suite("The folder list is in correct format account :$accountpath", $accountpath);
        $currentfolder = array_pop($listfromfile['folderslist']);
        $this->backupLog_suite("current folder which will be backed up is : $currentfolder", $accountpath);
        array_push($listfromfile['backedupfolders'], $currentfolder);
        if (!empty($currentfolder)) {
            $result = $this->folderLocalBackup_suite($currentfolder, $accountpath, $type);
            //if local backup for folders was successful
            if ($result['status'] == 1) {   //SUCCESS: folder was backed up successfully
                $this->writeFolderList($listfromfile['folderslist'], $listfromfile['backedupfolders']);  //update the folderslist
                return $result;
            } else {
                // return ERROR and do not update the folderslist
                return $result;
            }

        } else {
            $result = $this->restofFilesLocalBackup_suite($type, $accountpath);
            return $result;
        }
    }

    public function restofFilesLocalBackup_suite($type, $accountpath)
    {
        //for rest of the files except the folders
        $this->backupLog_suite("backing up rest of the files for account : $accountpath", $accountpath);
        $currentfolder = "restoffiles";
        $result = $this->stageAllChanges_suite($currentfolder, $accountpath);
        if ($result['status'] == 1) {
            $result = $this->commitChanges_suite($type, $currentfolder, $accountpath);
            if ($result['status'] == 1) {
                $result['status'] = 4; // 4 => to indicate the end of backup loop
                $result['info'] = "The remaining file have been backed up successfully";
                //delete the files since this is the last step
                $this->DeleteFolderListTable();
                return $result;
            } else {
                //ERROR : problems in committing  the changes
                return $result;
            }
        } else {
            //ERROR : problem in staging the files
            return $result;
        }
    }

    public function getFolderListFromFile()
    {
        $folderslist = array();
        if (file_exists($this->folder_List)) {
            require($this->folder_List);
        }
        return $folderslist;
    }

    public function folderLocalBackup_suite($currentfolder, $accountpath, $type)
    {
        $result1 = $this->stageAllChanges_suite($currentfolder, $accountpath);
        if ($result1['status'] == 1) {
            $result = $this->commitChanges_suite($type, $currentfolder, $accountpath);
            if ($result['status'] == 1) {
                $return = array("status" => 1, "type" => $type, "folder" => $currentfolder);
                return $return;
            } else {
                //ERROR : problems in committing the changes
                return $result;
            }
        } else {
            //ERROR : problem in staging the files
            return $result1;
        }
    }

    public function stageAllChanges_suite($path, $accountpath)
    {
        //rest of the files indicate the remainaing file in the website directory except the folders
        if ($path == "restoffiles") {
            $gitCmd = "cd $accountpath ; git add --all";
        } else {
            $filepath = $accountpath . ODS . $path;
            //add index file if the folder is empty
            if (count(array_diff(glob("$filepath/*"), glob("$filepath/*", GLOB_ONLYDIR))) == 0) {
                $result1 = $this->addIndexFile_suite($filepath);
                $this->backupLog_suite("added index file in folder " . $filepath, $accountpath);
            }
            $gitCmd = "cd $accountpath ; git add '" . $accountpath . ODS . $path."'";
        }
        $this->backupLog_suite("stage command is " . $gitCmd, $accountpath);
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR : some problem with stagging the file
            $result2['status'] = 0;
            $result2['info'] = "There was some problem in stagging the files of " . $path . "folder ERROR :" . $output['stderr'];
            $result2['cmd'] = $gitCmd;
            $this->backupLog_suite($result2['info'], $accountpath);
            $this->logErrorBackup_suite($result2['info'], $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage($result2['info'], "medium", $output['stderr']);
        } else {
            //SUCCESS : the changes were staged successfully
            $result1['status'] = 1;
            $result1['info'] = "The Changes were stagged successfully";
            $this->backupLog_suite($result1['info'], $accountpath);
            return $result1;
        }
    }

    //commit the changes
    public function commitChanges_suite($type = false, $foldername, $accountpath)
    {
        $gitsetup = $this->loadgitLibrabry(true);
        if ($foldername == "restoffiles") {
            $commitMessagePrefix = $gitsetup->getCommitMessages($type, $foldername);
            $gitCmd = "cd $accountpath ; git commit -m \"$commitMessagePrefix\"";
        } else {
            $filepath = $accountpath . ODS . $foldername;
            $commitMessagePrefix = $gitsetup->getCommitMessages($type, $foldername);
            $gitCmd = "cd $accountpath ; git commit -m \"$commitMessagePrefix\" "."'$filepath'";
        }
        $this->backupLog_suite("commit command is : " . $gitCmd, $accountpath);
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR :problems in committing the changes
            $result1['status'] = 0;
            $result1['info'] = "There was some problem in committing the local changes for the folder " . $foldername . "ERROR :" . $output['stderr'];
            $result1['cmd'] = $gitCmd;
            $this->backupLog_suite($result1['info']. "command : $gitCmd", $accountpath);
            $this->logErrorBackup_suite($result1['info'], $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage($result1['info'], "medium", $result1['cmd']);
        } else {
            $this->insertNewCommitDb_suite($accountpath);
            //SUCCESS : No problems in committing the changes
            $result1['status'] = 1;
            $result1['info'] = "The changes were committed successfully " . $commitMessagePrefix;
            $this->backupLog_suite($result1['info'], $accountpath);
            return $result1;
        }
    }

    protected function addIndexFile_suite($filepath)
    {
//        $result = touch($filepath . ODS . '.gitkeep');
        $gitcmd  = "touch '$filepath'/.gitkeep";
        $output = $this->runShellCommand($gitcmd);
        return $output;
    }

    //remove the spaces form the folder name
    public function removeSpaces($foldername)
    {
        $pattern = "/\s/";
        if (preg_match($pattern, $foldername)) {
            $string = str_replace(' ', "\\ ", $foldername);
            $result = $string . "\\ /";
            $this->backupLog_suite("The spaces has been removed from the folder name , for folder :$foldername");
            return $result;
        } else {
            return $foldername;
        }
    }

    //once the chnages are commiteed they can be instantly committed to the db to have a persistent log
    public function insertNewCommitDb_suite($accountpath)
    {
        $result = $this->accountDb->getLastCommitInDB();
        $lastcommitid = $result['commit_id'];
        $log = $this->getFormattedGitLog_suite($accountpath);
        $value = $log['data'][0];
        //compare the last commit id in db with the last entry in the log
        if (strcmp($lastcommitid, $value['commitID']) != 0) {
            $commit_id = $value['commitID'];
            $commit_time = $value['commitTime'];
            $commit_msg = $value['commitMsg'];
            $this->setHead($commit_id);
            $varValues = array(
                'commit_id' => $commit_id,
                'commit_time' => $commit_time,
                'commit_message' => $commit_msg,
                'is_head' => 1,
            );
            $this->backupLog_suite("new head is commit id : $commit_id", $accountpath);
            $newCommitID = $this->accountDb->addData('insert', '#__osefirewall_gitlog', '', '', $varValues);
            return $newCommitID;
        }
    }

    //HEAD indicates the current commit the user is on , head is changed agter a commit and revert operation
    public function setHead($commitid)
    {
        $result = $this->getHead();
        $newHead = $this->headArray();
        if ($result != 0) {
            $commitid_oldhead = $result[0]['commit_id'];
            //remove olde head
            $Array = array(
                'is_head' => 0
            );
            $this->accountDb->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid_oldhead, $Array);
            $new_head = $this->accountDb->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid, $newHead);
            return $new_head;
        } else {
            $new_head = $this->accountDb->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid, $newHead);
            return $new_head;
        }
    }

    public function getHead()
    {
        $query = "SELECT * FROM `#__osefirewall_gitlog` WHERE `is_head` = 1";
        $this->accountDb->setQuery($query);
        $result = $this->accountDb->loadResultList();
        if (count($result) == 0) {
            return 0;
        } else {
            return $result;
        }
    }

    //returns the git log with the the format all the commit ids, date and the commit message
    public function getFormattedGitLog_suite($accountpath)
    {
        $result = $this->runGitLog_suite($accountpath);
        $tem = $result['stdout'];
        if (empty($tem)) {
            $this->backupLog_suite("cannot get the converted git log for : $accountpath", $accountpath);
            $this->logErrorBackup_suite("cannot get the converted git log for : $accountpath", $accountpath);
        }
        $tmp = explode("\n", $tem);
        $data = $this->convertResult($tmp);
        if (empty($data)) {
            $this->backupLog_suite("cannot get the converted git log for : $accountpath", $accountpath);
            $this->logErrorBackup_suite("cannot get the converted git log for : $accountpath", $accountpath);
        }
        $data['recordsTotal'] = count($tmp);
        $data['recordsFiltered'] = $data['recordsTotal'];
        return $data;
    }

    public function runGitLog_suite($accountpath)
    {
        $gitCmd = " cd $accountpath ; git log --pretty=format:\"%h--%cd--%s\"";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    public function viewChangeHistory_suite($commitid, $accountpath)
    {
//        if (oseFirewallBase::checkSubscriptionStatus(false)) {
//        format = date#filenames
        if (oseFirewallBase::checkSubscriptionStatus(false)) {
            $gitcmd = "cd $accountpath ; git show --pretty=format:\"%cd#\" --name-only " . $commitid;
            $output = $this->runShellCommand($gitcmd);
            if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || $output['stderr'] != null) {
                //ERROR
                $result['status'] = 0;
                $result['info'] = "Error in getting the log information <br/> ERROR:<br/> " . $output['stderr'];
                return $result;
            } else {
                //seperate date and the file names with the seperator "#"
                $temp = explode("#", $output['stdout']);
                $result['status'] = 1; // premium user
                $result['date'] = $temp[0];
                //splitting the result with the help of spaces to store list of file names in the array
                $filenames = preg_split('/\s+/', $temp[1]);
                $result['files'] = $filenames;
                return $result;
            }
        }else{
            $result['status'] = 2;
            $result['date'] = "This feature is not available for free users";
            $result['files'] = "This feature is not available for free users";
            return $result;
        }
    }

    /*
     * Code to push the backup to the cloud
     */

    public function gitCloudCheck_suite($accountpath)
    {
        $repoexists = $this->isRemoteRepoSet_suite($accountpath);
        if($repoexists['status']== 0 || $repoexists['status'] == 3)
        {
            return $repoexists;
        }else {
            $publickey = $this->publicKeyExists();
            if($publickey!== true)
            {
                return oseFirewallBase::prepareErrorMessage("Public Key does not exist, Please complete the cloud backup setup");
            }
            $privatekey = $this->privateKeyExists();
            if($privatekey!== true)
            {
                return oseFirewallBase::prepareErrorMessage("Private Key does not exists, Please complte the cloud setup first");
            }
            return oseFirewallBase::prepareSuccessMessage("Git cloud is setup successfully");
        }
    }

    public function isRemoteRepoSet_suite($accountpath)
    {
        $gitCmd = "cd $accountpath ; git remote -v";
        $output = $this->runShellCommandWithStandardOutput($gitCmd);
        if (empty($output)) {
            return oseFirewallBase::prepareErrorMessage("Remote repo is not set");
        } else {
            $reponame = "origin";
            if (preg_match("~\b$reponame\b~", $output)) {
                if(strpos($output,"@bitbucket.org")!== false)
                {
                    return oseFirewallBase::prepareCustomDetailedMessage(3,'Bitbucket user',false);
                }else{
                    return oseFirewallBase::prepareSuccessMessage('Remote repo is set');
                }
            } else {
                return oseFirewallBase::prepareErrorMessage("Origin repo is not set");
            }
        }
    }

    public function publicKeyExists()
    {
        if (file_exists($this->publicKey_path)) {
            $content = true;
            return $content;
        }
    }

    public function privateKeyExists()
    {
        if (file_exists($this->privateKey_path)) {
            $content = true;
            return $content;
        }
    }
    public function cloudBackup_suite($accountpath)
    {
//        if (true == true) {
//            $output = $this->getPushResult();
        if (oseFirewallBase::checkSubscriptionStatus(false) == true) {
            $result = $this->findChanges_suite($accountpath);
            if ($result['status'] == 1) {
                $message = "You have some unsaved changes, Please create a backup first and then Upload them to the cloud";
                $output['status'] = 2;
                $output['info'] = $message;
                return $output;
            } elseif ($result['status'] == 2) {
                $result = $this->sshPushSetup_suite($accountpath);
                return $result;
            }
        }else{
            $output['status'] = 3;
            $output['info'] = "Please subscribe to our services to use this feature"; //returns the success message
            return $output;
        }
    }


    //STEP 4: start ssh agent load the key and push the commits
    public function sshPushSetup_suite($accountpath)
    {
        $output1 = $this->getPushResult_suite($accountpath);
        $temp = $output1['stderr'];
        //fatal =>wrong name for remote repo
        //error =>for wrong local branch name
        if (strpos($temp, "fatal") !== false || (strpos($temp, "error") !== false)) {
            $output['status'] = 0;
            $output['info'] = "There was a problem in uploading the backup to the GitLab account <br/>ERROR: <br/>" . $output1['stderr'];  //returns the error
            $output['cmd'] = $output1['cmd'];
            return $output;
        } else {
            $output['status'] = 1;
            $output['info'] = $output1['stderr']; //returns the success message
            return $output;
        }
    }

    public function getPushResult_suite($accountpath)
    {
        $gitCmd = null;
        if (isset($_REQUEST['qatest'])) {
            if ($_REQUEST['qatest'] == true) {
                if (TEST_ENV) {
                    $gitCmd = "cd $accountpath ; ssh-agent bash -c ' chmod 0400 " . $this->privateKey_path . "; ssh-add " . $this->privateKey_path . "; git push --force qatestrepo 6.5.0'";
                } else {
                    $gitCmd = "cd $accountpath ; ssh-agent bash -c ' chmod 0400 " . $this->privateKey_path . "; ssh-add " . $this->privateKey_path . "; git push --force qatestrepo master'";
                }
            }
        } else {
            if (TEST_ENV) {
                $gitCmd = "cd $accountpath ; ssh-agent bash -c ' chmod 0400 " . $this->privateKey_path . "; ssh-add " . $this->privateKey_path . "; git push --force origin master'";

            } else {
                $gitCmd = "cd $accountpath ; ssh-agent bash -c ' chmod 0400 " . $this->privateKey_path . "; ssh-add " . $this->privateKey_path . "; git push --force origin master'";
            }
        }
        $output = $this->runShellCommand($gitCmd);
        $output['cmd'] = $gitCmd;
        return $output;
    }

    protected function getRemoteRepoName()
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            return 'qatestrepo';
        } else {
            if (empty($_SERVER['SERVER_NAME'])) {
                return 'CentroraSecurity-' . rand(1000, 9999);
            } else {
                return substr(str_replace(".", "-", $_SERVER['SERVER_NAME']), 0, 10) . '-' . rand(1000, 9999);

            }
        }

    }

    //function to add url of an remote repos
    public function addRemoteRepo_suite($repourl, $accountpath)
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $gitCmd = "cd $accountpath ; git remote add qatestrepo $repourl";

        } else {
            $gitCmd = "cd $accountpath ; git remote add origin $repourl";
        }
        $result = $this->runShellCommand($gitCmd);
        if ($result['stderr'] == null) {
            return oseFirewallBase::prepareSuccessMessage($result['stderr']);
        } else {
            return oseFirewallBase::prepareErrorMessage($result['stderr']);
        }
    }


    //for debugging only
    public function removeremoterepo_suite($name, $accountpath)
    {
        $gitCmd = "cd $accountpath ; git remote rm " . $name;
        $output = $this->runShellCommand($gitCmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false)) {
            //ERRROR
            $output['status'] = 0;
            $output['info'] = "There was a problem in removing the remote repo  <br/>ERROR: <br/>" . $output['stderr'];  //returns the error
            return $output;
        } else {
            //SUCCESS
            $output['status'] = 1;
            $output['info'] = $output['stderr']; //returns the success message
            return $output;
        }

    }

    public function deletePublicKey()
    {

        if (file_exists($this->publicKey_path)) {
            unlink($this->publicKey_path);
        }
    }

    public function deletePrivateKey()
    {
        if (file_exists($this->privateKey_path)) {
            unlink($this->privateKey_path);
        }
    }

    public function moveKeys_suite($accountpath)
    {
        $this->gitIgnoreFile($this->keyBakup_Gitignore, $accountpath);
        if (!is_dir($this->keyBackup_Folder)) {
            mkdir($this->keyBackup_Folder);
        }
        if (file_exists($this->publicKey_path) && file_exists($this->privateKey_path)) {
            rename($this->publicKey_path, $this->move_publicKeyPath);
            rename($this->privateKey_path, $this->move_PrivateKeyPath);
        }
    }

    //complete ssh mechanism that puts all the modules together
    //generate keys , sytart the bash  session and load the keys to the session
    //add the keys to the session and to the bitbucket account before pushing any code
    public function sshSetup_suite($accountpath, $token, $username)
    {
        $temp = $this->genSshKeys_suite($accountpath);
        if ($temp['status'] == 0 || $temp['status'] == 1) {
            $temp1 = $this->addpublickeytogitLab_suite($accountpath, $token);
            if ($temp1['status'] == 0 || $temp1['status'] == 1) {
                $temp2 = $this->loadsshkey_suite($accountpath, "gitlab");
                if ($temp2['status'] == 1)    //if keys are loaded successfully
                {
                    $output['status'] = 1;
                    $output['info'] = $temp2['info'];
                    return $output;
                } else {  //problems in loading the keys
                    return $temp2;
                }
            } else {
                return $temp1;
                //exit to the controller
                //problems with adding the public key to the bit bucket account
            }
        } else {
            return $temp;
            //exit to the controller
            //problems in generating the ssh key pairs
        }
    }


    ///ALL THE SSH RELATED CODE BELOW
    //step 1 == generate a pair of public and private key
    public function genSshKeys_suite($accountpath)
    {
        $gitCmd = "cd $accountpath ; ssh-keygen -f " . $this->privateKey_path . " -N ''";
        $output = $this->runShellCommand($gitCmd);
        if ($output['stdout'] != null) {
            if (strpos($output['stdout'], "Overwrite") !== false)  //key already exists
            {
                $result['status'] = 0;
                $result['info'] = "The key already exists";
                return $result;
            }
            if (strpos($output['stdout'], "saved") !== false)   //new keys generated
            {
                $result['status'] = 1;
                $result['info'] = "The key has been successfully created";
                return $result;
            }
        } else {
            //unknown problems  for DEBUGGING
            $result['status'] = 2;
            $result['info'] = "There was some problem in generating the ssh keys <br/>ERROR: <br/>" . $output['stdout'];
            return $result;
        }
//        print_r($result);

    }

    public function getPublicKey()
    {
        if (file_exists($this->publicKey_path)) {
            $content = file_get_contents($this->publicKey_path);
            return $content;
        }
    }

    public function getPrivateKey()
    {
        if (file_exists($this->privateKey_path)) {
            $content = file_get_contents($this->privateKey_path);
            return $content;
        }
    }

    //TODO :error with the host key checking
    //STEP 3 : load the ssh keys with the local ssh agent and set no for hostkeychecking
    public function loadsshkey_suite($accountpath, $type = false)
    {
        if($type ==false)
        {
            $gitCmd = "cd $accountpath ; ssh-agent bash -c 'ssh-add " . $this->privateKey_path . "; ssh -T -oStrictHostKeyChecking=no git@bitbucket.org'";

        }else {
            $gitCmd = "cd $accountpath ; ssh-agent bash -c 'ssh-add " . $this->privateKey_path . "; ssh -T -oStrictHostKeyChecking=no git@gitlab.com'";

        }
        $output = $this->runShellCommand($gitCmd);
        if (!empty($type)) {
            $keyword = "Welcome to GitLab";
        } else {
            $keyword = "logged";
        }
        if (strpos($output['stdout'], $keyword) == 0 || strpos($output['stdout'], $keyword) !== false) {
            $return['status'] = 1;
            $return['info'] = $output['stdout'];
            return $return;
        } else {
            $return['status'] = 0;
            $return['info'] = "There was some problem in loading the SSH keys <br/>ERROR: <br/>" . $output['stderr'];
            return $return;
        }
    }

    public function finalGitPush_suite($accountpath)
    {
        $result = $this->stageAllChanges_finalpush_suite($accountpath);
        if ($result['status'] == 1) {
            $temp = $this->commitChanges_finalpush_suite($accountpath);
            if ($temp['status'] == 1) {
                $subscription_status = true;
                if ($subscription_status) {
                    //push the changes to the repo for premium users
                    $result = $this->sshPushSetup_suite($accountpath);
                    return $result;
                } else {
                    $output['status'] = 3;
                    $output['info'] = "Please subscribe to our services to use this feature"; //returns the success message
                    return $output;
                }

            } else {
                //ERRORS IN COMMITTING THE CHANGES
                return $temp;
            }
        } else {   //ERROR WITH STAGGING ALL THE FILES
            return $result;
        }
    }

    //marks the files ad staged  ==>needs to be done before committing the changes
    public function stageAllChanges_finalpush_suite($accountpath)
    {
        $gitCmd = "cd $accountpath ; git add --all";
        $result = $this->runShellCommand($gitCmd);
        if ((strpos($result['stderr'], 'fatal') !== false) || (strpos($result['stderr'], 'error') !== false)) {
            //ERROR
            $output['status'] = 0;
            $output['info'] = "Problem in stagging changes <br/>ERROR: <br/>" . $result['stderr'];
        } else {
            //SUCCESS
            $output['status'] = 1;
            $output['info'] = "Changes have been stagged successfully";
        }
        return $output;
    }


    //commit all the chnages to the repo with the message "centrora security backup + time"
    public function commitChanges_finalpush_suite($accountpath)
    {
        $commitMessagePrefix = $this->getCommitMessages();
        $gitCmd = "cd $accountpath ; git commit --all -m \"$commitMessagePrefix\" ";
        $result = $this->runShellCommand($gitCmd);
        if ((strpos($result['stderr'], 'fatal') !== false) || (strpos($result['stderr'], 'error') !== false)) {
            //if there is a fatal error
            $output['status'] = 0;
            $output['info'] = "There were some problems in committing the changes in final push <br/>ERROR: <br/>" . $result['stderr'];

        } else {
            //if there is no fatal error
            $this->insertNewCommitDb_suite($accountpath);
            //unset the session variable to keep using the same commit messages
            if (isset($_SESSION["commitMessage"])) {
                unset($_SESSION["commitMessage"]);
            }

            $output['status'] = 1;
            $output['info'] = "Changes have been committed successfully";
        }
        return $output;
    }


    /*
     * CODE TO PREPARE AND DOWNLOAD THE ZIP BACKUP OF THE WEBSITE
     */


    public function zipDownloadCloudCheck_suite($accountname,$accountpath)
    {
        $subscription = oseFirewallBase::checkSubscriptionStatus(false);
        if($subscription)
        {
            $remote_repo = $this->isRemoteRepoSet_suite($accountpath);
            $return['subscription'] = true;
            $return['repo'] = $remote_repo['status'];
        }else{
            $return['subscription'] = false;
            $return['repo'] = false;
        }
        return $return;
    }

    public function downloadZipBackup_suite($accountpath,$choice)
    {
        if($choice ==2) {
            $filepath = $accountpath . '/.git/config';
            $ini_array = parse_ini_file($filepath);
            $url = $ini_array['url'];
            $temp = str_replace('git@gitlab.com:', '', $url);
            $final_url = "https://gitlab.com/" . $temp;
            if (!empty($ini_array) && isset($ini_array['username']) && isset($ini_array['reponame'])) {
                $result['status'] = 1;
                $result['usertype'] = 1;
                $result['url'] = "<a href = $final_url>" . $final_url . "</a>";
                $result['instructions'] = "Please Go to the Link :<a href = $final_url target=\"_blank\"> $final_url</a> <br/> and click on the icon <span class = 'glyphicon glyphicon-download-alt'> </span> to download";
                return $result;
            } else {
                $result['status'] = 0;
                $result['usertype'] = 1;
                $result['instructions'] = " <ol><li>Please go to the url <a href = 'https://gitlab.com/users/sign_in'>https://gitlab.com/ </a></li>
                                                <li>Sign in using the username and password </li>
                                                <li> Search for the most recently updated repo and enter the repo by clicking on the name</li>
                                                 <li>click on the icon <span class = 'glyphicon glyphicon-download-alt'> </span> to download</li></ol>";
                return $result;
            }
        }elseif($choice ==1){
            //free users or local download
            $this->gitIgnoreFile($this->centroraBackupFolder_gitignore);
            $result = $this->websiteZipBackup_suite($accountpath);
            if($result['status'] == 1)
            {
                $result['usertype'] = 0;
                $result['instructions'] = "<strong>Downloading through your browser may corrupt the files</strong><br/>
                                        Please run the below commands to download the file using ftp <br/>
                                        <ol>
                                        <li>ftp hostname </li>
                                        <li>Enter username </li>
                                        <li>Enter password </li>
                                        <li>get ".CENTRORABACKUP_ZIPFILE."
                                        </ol>
                                        <button onclick='downloadzip()'>Download Using Browser </button>";
            }
            return $result;
        }
    }

    //determines if there is a need to generate backup.zip based on the uncommitted changes
    public function websiteZipBackup_suite($accountpath)
    {
        if (file_exists($this->centroraBackup_ZipFile)) {
            //delete the file and replace it with a new backup
            unlink($this->centroraBackup_ZipFile);
        }
        $result = $this->generateZip_suite($accountpath);
        return $result;

    }

    //generate the zip file stores it in the centrorabackup folder with the name Backup.zip
    public function generateZip_suite($accountpath)
    {
        $foldertobezipped = basename($accountpath);
        $parenDir = dirname($accountpath);
        $gitcmd = "cd $parenDir ; zip -r " . $this->centroraBackup_ZipFile . " " . $foldertobezipped . " -x " . ODS . $this->backupfiles_ExcludePath . "\*";
        $output = $this->runShellCommand($gitcmd);
        if ($output['stderr'] == null && file_exists($this->centroraBackup_ZipFile)) {
            $result['status'] = 1;
            $result['message'] = "The zip file of the website has been generated";
            return $result;
        } else {

            $result['status'] = 0;
            $result['message'] = "There was some problem in generating the zip file: " . $output['stderr'];
            return $result;
        }
    }

    public function getZipUrl_suite()
    {
        $result['url'] = $this->zipDownload_URL;
        return $result;
    }

    //downloads the zip file
    public function downloadzip()
    {
        $file = $this->centroraBackup_ZipFile;
        if (file_exists($file)) {
            header('Content-Description:     File Transfer');
            header('Content-Type: application/octet-stream');
//                header('Content-Type:application/force-download');
            header('Content-Disposition: attachment; filename=' . basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            while (ob_get_level()) {
                ob_end_clean();
            }
            readfile($file);
            exit;
        } else {
            header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
            print_r("Failed to Download the Backup , Please refresh the page try again ");
            exit;
        }
    }

    public function deleteZipBakcupFile()
    {
        if (file_exists($this->centroraBackup_ZipFile)) {
            unlink($this->centroraBackup_ZipFile);
            $result['status'] = 1;
        } else {
            //if no file exists report ERROR
            $result['status'] = 0;
        }
        return $result;
    }

    public function discardChanges_suite($accountpath)
    {
        $gitcmd = "cd $accountpath ; git reset --hard";
        $output = $this->runShellCommand($gitcmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || $output['stderr'] != null) {
            $result['status'] = 0;
            $result['info'] = "There was some problems in discarding the chnages <br/> ERROR:<br/>" . $output['stderr'];
            $result['cmd'] = $gitcmd;
        } else {
            $result['status'] = 1;
            $result['info'] = "Changes have been successfully reverted ";
        }
        return $result;
    }

    public function getLastBackupTime()
    {
        $result = $this->getHead();
        if ($result[0] != null) {
            $temp = $result[0]['commit_time'];
            $result = strstr($temp, '+', true);
            if ($result == false) {
                $result = strstr($temp, '-', true);
            }
        } else {
            $result = "";   //return null if there is any error
        }
        return $result;
    }

    /*
     * CODE FOR ROLLBACK
     */
    //function to revert back to the previous state using the reset command
    //need commit id for which you want to rollback
    //aso the head needs to updated when you rollback
    public function gitRollback_suite($commitid, $recall, $accountpath)    //TESTING code for revert
    {
        $gitCmd = "cd $accountpath ; git reset --hard $commitid";
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR
            $result['status'] = 0;
            $result['info'] = "There was some problems in reverting <br/>ERROR: <br/>" . $output['stderr'];
            return $result;
        } else {
            //SUCCESS
            $restoreDb['status'] = 1;
            if ($recall == "old") {
                $restoreDb = $this->restoreDBBackup_suite($accountpath);
            }
            if ($restoreDb['status'] == 0) {
                $this->setHead($commitid);
                return $restoreDb;
            } else {
                $this->setHead($commitid);
                $result['status'] = 1;
                $result['info'] = "The system has reverted back to " . $commitid . " successfully";
                return $result;
            }
        }
    }

    protected function getFormattedTablesNames($oldBackupTables)
    {
        $result = array();
        foreach ($oldBackupTables as $key => $table) {

            $result[$key] = basename($table, '.sql');
        }
        return $result;
    }

    protected function getDatabaseFileList()
    {
        $scanPath = $this->centroraBackupFolder . ODS . 'gitbackup';
        $files = array();
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($scanPath), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($objects as $path => $dir) {
            if (is_file($path) && substr($path, -4) == '.sql' && $path != $scanPath . "/" . $this->accountDb->getPrefix() . 'osefirewall_gitlog.sql') {
                $files[] = $path;
            }
        }
        return $files;
    }

    protected function restoreAllTables()
    {
        if (!empty($this->files)) {
            foreach ($this->files as $file) {
                $tablename = basename($file, '.sql');
                $this->accountDb->dropTable($tablename);
                $query = $this->installer->readSQLFile($file);
                if (!empty($query)) {
                    $this->accountDb->setQuery($query);
                    if (!$this->accountDb->query()) {
                        return oseFirewallBase::prepareErrorMessage('Error while restoring :' . $tablename);
                    }
                }
            }
        }
        return oseFirewallBase::prepareSuccessMessage('All tables have been backed up successfully');
    }

    protected function restoreAlterTable()
    {
        $alterTableFile = $this->centroraBackupFolder . ODS . "alterTables.sql";
        if (file_exists($alterTableFile)) {
            if (empty($this->installer)) {
                oseFirewall::loadInstaller();
                $this->installer = new oseFirewallInstaller ();
            }
            $alterFileContent = $this->installer->readSQLFile($alterTableFile);
            if (!empty($alterFileContent)) {
                $this->accountDb->setQuery($alterFileContent);
                if (!$this->accountDb->query()) {
                    return oseFirewallBase::prepareErrorMessage('Error while restoring alter table file ');
                } else {
                    return oseFirewallBase::prepareSuccessMessage('Alter table have been backed up successfully');
                }
            } else {
                return oseFirewallBase::prepareErrorMessage("Alter query file content is empty");
            }
        } else {
            return oseFirewallBase::prepareSuccessMessage("Alter table file does not exists");
        }
    }

    public function uninstall_git_suite($accountname, $accountpath, $keeplog)
    {
        if (file_exists("$accountpath/.git")) {
            $gitcmd = "cd $accountpath ; rm -rf .git";
            $output = $this->runShellCommand($gitcmd);
            if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || $output['stderr'] != null) {
                $result['status'] = 0;
                $result['info'] = "There was some problems in Uninstalling Git <br/> ERROR:<br/>" . $output['stderr'];
                $result['cmd'] = $gitcmd;
                return $result;
            } else {
                if ($keeplog == 0) {
                    //drop the git log table
                    $gitlogtable = $this->prefix . "osefirewall_gitlog";
                    $tableExists = $this->accountDb->isTableExists($gitlogtable);
                    if ($tableExists) {
                        $dropresult = $this->accountDb->truncateTable($gitlogtable);
                        $result['status'] = 1;
                        $result['info'] = "Git has been successfully uninstalled";
                        return $result;
                    } else {
                        $result['status'] = 1;
                        $result['info'] = "Git has been successfully uninstalled";
                        return oseFirewallBase::prepareSuccessMessage("The Git has been successfully uninstalled <br/><h4><b>You can reinstalise the git and gain access to the git log</b></h4>");
                    }
                } else {
                    return oseFirewallBase::prepareSuccessMessage('Git has been successfully uninstalled');
                }
            }
        } else {
            return oseFirewallBase::prepareSuccessMessage('Git has been successfully uninstalled');
        }
    }


    //using Mysql dump to backup the databse
    public function backupDbs_suite($name, $path)
    {
        $this->backupLog_suite("******backupDbs - starting to backup databse for the account $name", $path);
        if (empty($this->account_db_config)) {
            $this->logErrorBackup_suite("backupDbs - The database setup has not been completed for account:$name", $path);
            $this->backupLog_suite("backupDbs - The database setup has not been completed for account:$name", $path);
            return oseFirewallBase::prepareCustomErrorMessage("The database setup has not been completed for account: " . $name, "medium");
        }
        $username = $this->account_db_config['info'][0]->DB_USER;
        $pswd = $this->account_db_config['info'][0]->DB_PASSWORD;
        $dbname = $this->account_db_config['info'][0]->DB_NAME;
        //generate the file which has all the create table queries
        $createTable_result = $this->backup_createTables_suite($path, $username, $pswd, $dbname);
        if ($createTable_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($createTable_result['info'], "low", $createTable_result['details']);
        }
        //generate the file with all the insert queries
        $insertTable_result = $this->backup_insertTables_suite($path, $username, $pswd, $dbname);
        if ($insertTable_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($insertTable_result['info'], "low", $insertTable_result['details']);
        }
        $gitLog_result = $this->backup_gitLogTable_suite($path, $username, $pswd, $dbname);
        if ($gitLog_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($gitLog_result['info'], "low", $gitLog_result['details']);
        }
        $tables = $this->accountDb->getTableList();
        if (!empty($tables)) {
            //genrate the file with all the alter table queries
            $this->createAlterTableSQL_suite($path, $tables);
            $tableList_result = $this->backup_createTableList_suite($tables, $path);
            if ($tableList_result['status'] == 0) {
                return oseFirewallBase::prepareCustomErrorMessage($tableList_result['info'], "low");
            } else {
                return oseFirewallBase::prepareSuccessMessage("Database has been backed up successfully");
            }
        } else {
            $this->backupLog_suite("backupDbs" . "There was some problem in getting the list of tables for account :$name" . "<br/>Database has not been backed for :$name <br/>", $path);
            $this->logErrorBackup_suite("backupDbs" . "There was some problem in getting the list of tables for account :$name" . "<br/>Database has not been backed for :$name <br/>", $path);
            return oseFirewallBase::prepareCustomErrorMessage("There was some problem in accessing the tables list while creating alter table queries", "low");
        }

    }

    //generate all the create queries
    public function backup_createTables_suite($path, $username, $password, $dbname)
    {
        $this->backupLog_suite("backup_createTables - Backing up create tables ");
        if (file_exists($path . ODS . $this->createTables)) {
            unlink($path . ODS . $this->createTables);
        }
        $gitTable = $this->prefix . "osefirewall_gitlog";
        //  $gitcmd = "cd $path; mysqldump -d -u '$username' -p'$password' '$dbname' > $this->createTables";
        $gitcmd = "cd $path;  mysqldump -d -u '$username' -p'$password' '$dbname' --ignore-table='$dbname'.$gitTable > $this->createTables ";//>> /dev/null & 2>1";
        $output = $this->runShellCommand($gitcmd);
        if (file_exists($path . ODS . $this->createTables)) {
            $this->backupLog_suite("backup_createTables - create table file has been created ", $path);
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog_suite("backup_createTables" . "There was some problem in generating create Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'] . "cmd is : ".$gitcmd, $path);
            $this->logErrorBackup_suite("backup_createTables" . "There was some problem in generating create Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'], $path);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating create Table queries", $output['stderr']);
        }
    }

    //generate all the insert queries
    public function backup_insertTables_suite($path, $username, $password, $dbname)
    {
        $this->backupLog_suite("backup_insertTables - generating insert table file for $username", $path);
        if (file_exists($path . ODS . $this->insertData)) {
            unlink($path . ODS . $this->insertData);
        }
        $gitTable = $this->prefix . "osefirewall_gitlog";
        $gitcmd = "cd $path; mysqldump -u '$username' -p'$password' '$dbname' --no-create-info --ignore-table='$dbname'.$gitTable > $this->insertData";
        $output = $this->runShellCommand($gitcmd);
        if (file_exists($path . ODS . $this->insertData)) {
            $this->backupLog_suite("backup_insertTables - insert table file has been created ", $path);
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog_suite("backup_insertTables -" . "There was some problem in generating insert Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'], $path);
            $this->logErrorBackup_suite("backup_insertTables-" . "There was some problem in generating insert Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'], $path);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating insert Table queries", $output['stderr']);
        }
    }

    public function backup_createTableList_suite($tables, $path)
    {
        if (file_exists($path . ODS . $this->tablesList)) {
            unlink($path . ODS . $this->tablesList);
        }
        $write_result = $this->prepareTablesList($tables, $path . ODS . $this->tablesList);
        return $write_result;
    }

    public function backup_gitLogTable_suite($path, $username, $password, $dbname)
    {
        $this->backupLog_suite("backup_gitLogTable - generating gitlog table file for $username", $path);
        if (file_exists($path . ODS . $this->gitLogTable_backup)) {
            unlink($path . ODS . $this->gitLogTable_backup);
        }
        $gitTable = $this->prefix . "osefirewall_gitlog";
        $gitcmd = "cd $path; mysqldump -u '$username' -p'$password' '$dbname' $gitTable > $this->gitLogTable_backup";
        $output = $this->runShellCommand($gitcmd);
        if (file_exists($path . ODS . $this->gitLogTable_backup)) {
            $this->backupLog_suite("backup_gitLogTable - The create Table queries have been successfully generated ", $path);
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog_suite("backup_insertTables -" . "There was some problem in generating gitlog Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'], $path);
            $this->logErrorBackup_suite("backup_insertTables-" . "There was some problem in generating gitlog Table queries" . "<br/>Database has not been backed for :$username <br/>" . $output['stderr'], $path);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating gitlog Table queries", $output['stderr']);
        }
    }

    protected function prepareTablesList($tables, $path)
    {
        $newTableArray = array_reverse($tables);
        $content = "<?php\n" . '$tables = array("tables"=>' . var_export($newTableArray, true) . ");";
        $writeFile_result = $this->writeFile($path, $content);

        if ($writeFile_result) {
            $this->backupLog_suite("File write was successfull");
            return oseFirewallBase::prepareSuccessMessage('File write was successful');
        } else {
            $this->backupLog_suite("There was some problem in writing the DB Table file contents");
            $this->logErrorBackup_suite("There was some problem in writing the DB Table file contents");
            return oseFirewallBase::prepareErrorMessage('There was some problem in writing the DB Table file contents');
        }
    }


    public function restoreDBBackup_suite($path)
    {
        if (empty($this->account_db_config)) {
            return oseFirewallBase::prepareErrorMessage("The database setup has not been completed for account: " . $path);
        }
        $username = $this->account_db_config['info'][0]->DB_USER;
        $pswd = $this->account_db_config['info'][0]->DB_PASSWORD;
        $dbname = $this->account_db_config['info'][0]->DB_NAME;
        $host = $this->account_db_config['info'][0]->DB_HOST;
        $this->restore_createTables_suite($path, $username, $pswd, $dbname, $host);
        $this->restore_alterTables_suite($path, $username, $pswd, $dbname, $host);
        $this->restore_insertTables_suite($path, $username, $pswd, $dbname, $host);
        $result = $this->dropNewDBTable_suite($path);
        if ($result['status'] == 0) {
            return $result;
        } else {
            return oseFirewallBase::prepareSuccessMessage('All tables have been backed up successfully');
        }
    }

    public function restore_createTables_suite($path, $username, $password, $dbname, $host)
    {
        $gitcmd = "cd $path; mysql -u '$username' -p'$password' -h '$host' '$dbname' < $this->createTables";
        $result = $this->runShellCommand($gitcmd);
    }

    public function restore_alterTables_suite($path, $username, $password, $dbname, $host)
    {
        $gitcmd = "cd $path; mysql -u '$username' -p'$password' -h '$host' '$dbname' < $this->alterTables";
        $this->runShellCommand($gitcmd);
    }

    public function restore_insertTables_suite($path, $username, $password, $dbname, $host)
    {
        $gitcmd = "cd $path; mysql -u '$username' -p'$password' -h '$host' '$dbname' < $this->insertData";
        $this->runShellCommand($gitcmd);
    }

    public function getListOfTables_suite($path)
    {
        $tables = array();
        if (file_exists($path . ODS . $this->tablesList)) {
            require($path . ODS . $this->tablesList);
            return $tables['tables'];
        } else {
            return '';
        }
    }

    //Drop the tables that did not exist in the old backup
    //Formula :
    //Current DB Tables - Old BackupDb Tables = Remaing tables => DROP THEM
    public function dropNewDBTable_suite($path)
    {
        $oldTables = $this->getListOfTables_suite($path);
        if (empty($oldTables)) {
            return oseFirewallBase::prepareErrorMessage("There was some problem in getting the list of backup up database table names");
        }
        $currentTables = $this->accountDb->getTableList();
        $dropTablesList = array_diff($currentTables, $oldTables);
        if (!empty($dropTablesList)) {
            foreach ($dropTablesList as $dropTable) {
                $tableExists = $this->accountDb->isTableExists($dropTable);
                if ($tableExists && (strpos($dropTable, "osefirewall_gitlog") == false)) {
                    $dropresult = $this->accountDb->dropTable($dropTable);
                    if ($dropresult == false) {
                        $this->accountDb->dropView($dropTable);
                    }
                }
            }
            return oseFirewallBase::prepareSuccessMessage("The remaining tables has been dropped");
        } else {
            return oseFirewallBase::prepareSuccessMessage("No Difference in the Tables");
        }
    }


    public function initalisegit_suite($accountname, $accountpath)
    {
        $this->backupLog_suite("*****initialisegit - starting to initalise goit for $accountname", $accountpath);
        $isinit = $this->isinit_suite($accountpath);
        if ($isinit['status'] == 2) {
            //git not initalised
            $init_result = $this->init_suite($accountpath);
            if ($init_result['status'] == 0) {
                return oseFirewallBase::prepareCustomErrorMessage($init_result['info'], "medium");
            }
            $addUserInfo_result = $this->addUserInfoGitConfig_suite($accountpath,$accountname);
            if ($addUserInfo_result['status'] == 1) {
                return oseFirewallBase::prepareSuccessMessage("The Git has been successfully Initalised");
            } else {
                return oseFirewallBase::prepareCustomErrorMessage($addUserInfo_result['info'], "low");
            }
        } else if ($isinit['status'] == 1) {
            return oseFirewallBase::prepareSuccessMessage("The git has been already initialized");
        } else {
            //status = 2 => error
            return oseFirewallBase::prepareCustomErrorMessage($isinit['info'], "medium");
        }
    }

    /*
     *
     * Code to backup all the accounts
     */

    public function backupAccountsQueue($list = false,$remote = false)
    {
        //enabnle loggin the backup actions
        if($remote == true)
        {
            $list = $this->getAccountListFromDB();
        }
        $this->backupLog_suite("*****backupAccountsQueue() - Starting Backup Accounts Queue".json_encode($list),false);
        $this->clearErrorLogBackup_suite();
        $this->clearBackupLog_suite();
        $accountList = $this->prepareBackupAccountsList($list);
        if ($accountList['status'] == 1) {
            if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
                unlink(OSE_GITBACKUP_QUEUELIST);
            }
            $backedup_accounts = array();
            $result = $this->writeBackupQueueList($accountList['info'], $backedup_accounts);
//            print_r($result);exit;
            return $result;
        } else {
            return $accountList;
        }

    }

    public function clearErrorLogBackup_suite()
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $tableExists = $this->db->isTableExists("#__osefirewall_errorlog");
        if ($tableExists) {
            $dropresult = $this->db->truncateTable("#__osefirewall_errorlog");
        }
        return true;
    }

    public function clearBackupLog_suite()
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $tableExists = $this->db->isTableExists("#__osefirewall_backuplog");
        if ($tableExists) {
            $dropresult = $this->db->truncateTable("#__osefirewall_backuplog");
        }
        return true;
    }

    public function getAccountListFromDB()
    {
        $temp = array();
        $query = "SELECT `accountpath` FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE 1";
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if(!empty($result))
        {
            foreach($result as $record)
            {
                if(isset($record['accountpath']))
                {
                    $temp[] = $record['accountpath'];
                }
            }
        }
        return $temp;
    }

    //retrurns just thge list of account path
    public function getAccountPathTable()
    {
        $completeList = $this->getAccountListCompleteTable();
        if(empty($completeList))
        {
            return false;
        }else{
            $accountPathList = array_column($completeList, 'path');
            return $accountPathList;
        }

    }


    public function prepareBackupAccountsList($list)
    {
        $this->backupLog_suite("prepareBackupAccountsList - preparing backup account list", json_encode($list));
        $backupAccountList_verified = array();
        if (!empty($list)) {
            foreach ($list as $accountpath) {
                $dbconfigExists = $this->checkAccountStatus($accountpath);
                if ($dbconfigExists) {
                    array_push($backupAccountList_verified, $accountpath);
                }
            }
            if (empty($backupAccountList_verified)) {
                $this->logErrorBackup_suite("prepareBackupAccountsList() - The account list is empty  ", false);
                $this->backupLog_suite("prepareBackupAccountsList() - The account list is empty ", false);
                return oseFirewallBase::prepareErrorMessage("The account list is empty ");
            } else {
                $this->backupLog_suite("prepareBackupAccountsList() - The account list has been prepared  ", json_encode($backupAccountList_verified));
                return oseFirewallBase::prepareSuccessMessage($backupAccountList_verified);
            }
        } else {
            $this->logErrorBackup_suite("The list of accounts that needs to be backed up is empty, exiting prepareBackupAccountsList() with error ", false);
            $this->backupLog_suite("The list of accounts that needs to be backed up is empty", false);
            return oseFirewallBase::prepareErrorMessage("The list of accounts that needs to be backed up is empty");
        }
    }

    public function checkAccountStatus($accountpath)
    {
        $config = $this->checkifDbConfigExistsPath($accountpath);
        $fileExists = $this->checkIfFileExists($accountpath);
        if ($fileExists['status'] == 0) {
            return false;
        }
        if ($config['status'] == 1) {
            $dbconfig = $this->getFormattedDbConfig($config['info']);
            if ($dbconfig['status'] == 1) {
                $dbConnection_result = $this->testDbConnection_suite($dbconfig['info']);
                if ($dbConnection_result['status'] == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    public function checkDbConnectivity($config)
    {
        if ($config['status'] == 1) {
            $dbconfig = $this->getFormattedDbConfig($config['info']);
            if ($dbconfig['status'] == 1) {
                $dbConnection_result = $this->testDbConnection_suite($dbconfig['info']);
                if ($dbConnection_result['status'] == 1) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    public function checkifDbConfigExistsPath($accountpath)
    {
        $this->backupLog_suite("checking if db config exists", $accountpath);
        $query = "SELECT * FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE `accountpath`=" . $this->db->quoteValue($accountpath);
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if (empty($result)) {
            $this->logErrorBackup_suite("DB Config does not exists for the account, exiting the checkifDbConfigExistsPath() with error\n Failed to backup $accountpath" . CONTACT_SUPPORT, $accountpath);
            $this->backupLog_suite("DB Config does not exists for the account, exiting the checkifDbConfigExistsPath() with error", $accountpath);
            return oseFirewallBase::prepareErrorMessage('DB Config does not exists for the account ' . $accountpath);
        } else {
            $this->backupLog_suite("db config exists , exiting function checkifDbConfigExistsPath() with success  ", $accountpath);
            return oseFirewallBase::prepareSuccessMessage($result);
        }
    }

    public function getFormattedDbConfig($dbconfig)
    {
        $this->backupLog_suite("getFormattedDbConfig - getting the formatted db config ");
        $result = array();
        if (!empty($dbconfig)) {
            if (isset($dbconfig[0]) && isset($dbconfig[0]['dbconfig'])) {
                $temp = json_decode($dbconfig[0]['dbconfig']);
                if (isset($temp[0])) {
                    $result = (array)$temp[0];
                    $this->backupLog_suite("getFormattedDbConfig() - db configurations has been formatted");
                    return oseFirewallBase::prepareSuccessMessage($result);
                } else {
                    $this->logErrorBackup_suite("getFormattedDbConfig() -The db config cannot be accessed, exiting with error ", false);
                    $this->backupLog_suite("getFormattedDbConfig() -The db config cannot be accessed, exiting with error");
                    return oseFirewallBase::prepareErrorMessage("The db config cannot be accessed");
                }
            } else {
                $this->logErrorBackup_suite("getFormattedDbConfig() -There was some problem in accessing the formatted db config , exiting with error", false);
                $this->backupLog_suite("getFormattedDbConfig() -There was some problem in accessing the formatted db config, exiting with error");
                return oseFirewallBase::prepareErrorMessage("There was some problem in accessing the formatted db config");
            }
        } else {
            $this->logErrorBackup_suite("getFormattedDbConfig() -The db config is empty , exiting with error", false);
            $this->backupLog_suite("getFormattedDbConfig() -The db config is empty, exiting with error");
            return oseFirewallBase::prepareErrorMessage("The db config is empty");
        }
    }


    public function writeBackupQueueList($list1, $backedup_accounts)
    {
        $list = array_reverse($list1);
        $content = "<?php\n" . '$accountlist = array("accountslist"=>' . var_export($list1, true) . ', "backedupaccounts" =>' . var_export($backedup_accounts, true) . ");";
        $folderpath = CENTRORABACKUP_FOLDER . ODS . "gitbackup";
        if (!file_exists($folderpath)) {
            mkdir($folderpath);
        }
        $result = $this->writeFile(OSE_GITBACKUP_QUEUELIST, $content);
        if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
            $this->backupLog_suite("BackupQueue List is prepared");
            return oseFirewallBase::prepareSuccessMessage("BackupQueue List is prepared");
        } else {
            $this->backupLog_suite("There was some problem in generating the list of accounts for backup", json_encode($backedup_accounts));
            $this->logErrorBackup_suite("There was some problem in generating the list of accounts for backup", json_encode($backedup_accounts));
            return oseFirewallBase::prepareErrorMessage("There was some problem in generating the list of accounts for backup");
        }
    }

    public function readBackupQueueList()
    {
        $accountlist = array();
        if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
            require(OSE_GITBACKUP_QUEUELIST);
        }
        return $accountlist;
    }

    public function contBackupQueue()
    {
        $this->backupLog_suite("******* Entering contBackupQueue",false);
        $accountlist = array();
        $backedupAccountList = array();
        if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
            $accountlist = $this->readBackupQueueList();
            if (!empty($accountlist) && isset($accountlist['accountslist']) && isset($accountlist['backedupaccounts']) && !empty($accountlist['accountslist'])) {
                $currentAccount = array_pop($accountlist['accountslist']);
                $accountname = $this->getAccountName($currentAccount);
                $isinit = $this->isinit_suite($currentAccount);
                if ($isinit['status'] == 1) {
                    //is initialises
                    $result['status'] = 1;
                    $result['info'] = "Git has been initialised";
                    $result['name'] = $accountname;
                    $result['path'] = $currentAccount;
                    return $result;
                } else if ($isinit['status'] == 2) {
                    //git has not been initalised
                    $result['status'] = 2;
                    $result['info'] = "Git has not been initialised";
                    $result['name'] = $accountname;
                    $result['path'] = $currentAccount;
                    return $result;
                } else {
                    //return error
                    return oseFirewallBase::prepareCustomErrorMessage($isinit['info'], "low");
                }
            } elseif (empty($accountlist['accountslist']) && !empty($accountlist['backedupaccounts'])) {
                $this->deleteBackupQueueList();
                $this->backupLog_suite("The backup has been completed ");
                return oseFirewallBase::prepareCustomMessage(3, "Backup Queue Completed");
            } else {
                $this->backupLog_suite("contBackupQueue - The Backup Queue List Does is empty");
                $this->logErrorBackup_suite("contBackupQueue - The Backup Queue List Doesis empty");
                return oseFirewallBase::prepareCustomErrorMessage("The Backup Queue List is empty ", "high");
            }
        } else {
            $this->backupLog_suite("contBackupQueue - The Backup Queue List Does not exists");
            $this->logErrorBackup_suite("contBackupQueue - The Backup Queue List Does not exists");
            return oseFirewallBase::prepareCustomErrorMessage("The Backup Queue List Does not exists ", "high");
        }
    }

    public function getAccountName($accountpath)
    {
        $query = "SELECT `accountname` FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE `accountpath`=" . $this->db->quoteValue($accountpath);
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if (isset($result[0]) && isset($result[0]['accountname'])) {
            return $result[0]['accountname'];
        } else {
            $this->logErrorBackup_suite("account name cannot be accessed for : $accountpath" . $accountpath);
            $this->backupLog_suite("account name cannot be accessed for : $accountpath" . $accountpath);
            return false;
        }
    }


    public function isbackupQueueCompleted($accountname, $accountpath)
    {
        $this->backupLog_suite("*****checking if the backup queue is completed for account : $accountname", $accountpath);
        if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
            $accountlist = $this->readBackupQueueList();
            if (empty($accountlist || (!isset($accountlist['accountslist'])) || (!isset($accountlist['backedupaccounts'])))) {
                $this->backupLog_suite("The backup queue is empty for accountname : $accountname", $accountpath);
                $this->logErrorBackup_suite("The backup queue is empty for accountname : $accountname", $accountpath);
                return oseFirewallBase::prepareCustomErrorMessage("The backup queue is empty for accountname : $accountname", $accountpath);
            }
            if (!empty($accountlist['accountslist'])) {
                if (in_array($accountpath, $accountlist['accountslist'])) {
                    $key = array_search($accountpath, $accountlist['accountslist']);
                    if (($key !== false) && array_key_exists($key, $accountlist['accountslist'])) {
                        unset($accountlist['accountslist'][$key]);
                        array_push($accountlist['backedupaccounts'], $accountpath); //BUG FOR WRITING FILE
                        $this->writeBackupQueueList($accountlist['accountslist'], $accountlist['backedupaccounts']);
                        $this->backupLog_suite("backup queue file is updated , backup completed for : $accountname", $accountpath);
                        return oseFirewallBase::prepareSuccessMessage("Account : $accountname has been backed up successfully , Continue");
                    } else {
                        $this->backupLog_suite("Cannot find $accountpath key in the list", $accountpath);
                        $this->logErrorBackup_suite("Cannot find $accountpath  key in the list", $accountpath);
                        return oseFirewallBase::prepareCustomErrorMessage("Cannot find $accountpath key  in the list", $accountpath);
                    }
                } else {
                    $this->backupLog_suite("Cannot find $accountpath  in the list", $accountpath);
                    $this->logErrorBackup_suite("Cannot find $accountpath  in the list", $accountpath);
                    return oseFirewallBase::prepareCustomErrorMessage("Cannot find $accountpath  in the list", $accountpath);
                }
            } else if (empty($accountlist['accountslist']) && !empty($accountlist['backedupaccounts'])) {
                // $this->deleteBackupQueueList();
                $this->backupLog_suite("Backup Queue Completed ");
                return oseFirewallBase::prepareCustomMessage(2, "Backup Queue Completed");
            }
        } else {
            $this->backupLog_suite("The backup Queue file does not exists for accountname : $accountname", $accountpath);
            $this->logErrorBackup_suite("The backup Queue file does not exists for accountname : $accountname", $accountpath);
            return oseFirewallBase::prepareCustomErrorMessage("The backup Queue file does not exists ", "high");
        }
    }

    public function deleteBackupQueueList()
    {
        if (file_exists(OSE_GITBACKUP_QUEUELIST)) {
            unlink(OSE_GITBACKUP_QUEUELIST);
        }
    }


    //logs only errors related to the backup queue
    public function logErrorBackup_suite($message, $accountpath = false)
    {
        if(file_exists(O_ENABLE_GITBACKUP_LOG))
        {
            if (empty($this->db)) {
                $this->db = oseFirewall::getDBO();
            }
            $varValues = array(
                'account' => $accountpath,//cleanupVar
                'message' => substr($message, 0, 1000),
                'datetime' => date('Y-m-d h:i:s')
            );
            $result = $this->db->addData('insert', "#__osefirewall_errorlog", '', '', $varValues);
            if (empty($result)) {
                return false;
            } else {
                return true;
            }
        }
    }

    //keep log about the backup
    public function backupLog_suite($message, $accountpath = false)
    {
        if(file_exists(O_ENABLE_GITBACKUP_LOG))
        {
            if(empty($this->db))
            {
                $this->db = oseFirewall::getDBO();
            }
            $varValues = array(
                'account' => $accountpath,//cleanupVar
                'message' => substr($message, 0, 1000),
                'datetime' => date('Y-m-d h:i:s')
            );
            $result = $this->db->addData('insert', "#__osefirewall_backuplog", '', '', $varValues);
            if (empty($result)) {
                return false;
            } else {
                return true;
            }
        }

    }


    public function checkGitBackupPreRequisite()
    {
        $result = null;
        oseFirewall::callLibClass('gitBackup', 'gitActivationPanel');
        $activationpanel = new gitActivationPanel();
        $systemInfo = $activationpanel->checkSysteminfo();
        if ($systemInfo == true) {
            return oseFirewallBase::prepareSuccessMessage("pre-requisites are satisfied");
        } else {
            $systemStatus = $activationpanel->systemInfo();
            if (!empty($systemStatus)) {
                foreach ($systemStatus as $value) {
                    if ($value['status'] == false) {
                        $result .= "<ul>";
                        $result .= "<span class=\"fa fa-times color-red\">";
                        $result .= " " . $value['info'];
                        $result .= "</span> </ul>";
                    } else if ($value['status'] == true) {
                        $result .= "<ul>";
                        $result .= "<span class=\"fa fa-check color-green\">";
                        $result .= " " . $value['info'];
                        $result .= "</span> </ul>";
                    }
                }
                return oseFirewallBase::prepareErrorMessage($result);
            } else {
                return oseFirewallBase::prepareErrorMessage("Pre-Requisites are not met");
            }
        }
    }

    //convert arrays into the table format
    public function tableFormatString($content)
    {
        $result = null;
        $result .= '<table id="gitbackup_errorlog" style="width:100%">';
        $result .= "<tr><th> Date </th> <th>Account Path </th> <th> Message</th></tr>";
        foreach ($content as $record) {
            $result .= "<tr><td >" . $record['datetime'] . "</td><td>" . $record['accountpath'] . "</td><td >" . $record['message'] . "</td></tr>";
        }
        $result .= "</table>";
        return $result;
    }


    //runs git garbage collector to optimise the disk usage by git
    public function runGitGarbageCleaner_suite($accountpath)
    {
        $gitcmd = "cd $accountpath; git gc --quiet";
        $output = $this->runShellCommand($gitcmd);
        return $output;
    }


    public function addpublickeytogitLab_suite($accountpath = false, $privatetoken)
    {
        $publickey = $this->getPublicKey();
        $gitCmd = "cd $accountpath ;curl -X POST --header \"PRIVATE-TOKEN: $privatetoken\" -F \"title=centrora_security\" -F \"key=$publickey\" \"https://gitlab.com/api/v3/user/keys\"";
        $output = $this->runShellCommand($gitCmd);
        if (!empty($output['stdout'])) {
            $request_output = json_decode($output['stdout']);
            if (property_exists($request_output, 'message') && $request_output->message == "401 Unauthorized") {
                $requet_final = oseFirewallBase::prepareErrorMessage("There was some problem in adding the private key to the Gitlab Account : <br/> The provided token is Invalid, Please check the token validity in the Gitlab Account");
                return $requet_final;
            } else if (property_exists($request_output, 'id') && property_exists($request_output, 'title') && property_exists($request_output, 'created_at')) {
                $requet_final = oseFirewallBase::prepareSuccessMessage("The Private Key has been added successfully to the GitLab Account ");
                return $requet_final;
            } else if (property_exists($request_output, 'message') && property_exists($request_output->message, 'fingerprint') && $request_output->message->fingerprint[0] == "has already been taken") {
                //if the key is already added to the account
                $requet_final = oseFirewallBase::prepareErrorMessage("The Private Key has been already been added to the GitLab Account ");
                return $requet_final;
            } else {
                //display other errors
                $requet_final = oseFirewallBase::prepareErrorMessage("There was some problem in adding the private key to the GitLab Account <br/> " . $output['stdout']);
                return $requet_final;
            }

        } else {
            //unknon error
            return oseFirewallBase::prepareErrorMessage("There was some problem in adding the private key to the GitLab Account <br/>Error : <br/>" . $output['stderr']);
        }
    }

    public function saveRemoteGit_gitLab_suite($accountpath, $token, $username)
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $this->removeremoterepo_suite('qatestrepo', $accountpath);
            $this->moveKeys_suite($accountpath);
        } else {
            $this->removeremoterepo_suite('origin', $accountpath);
            $this->deletePrivateKey();
            $this->deletePublicKey();
        }

        $reponame = $this->getRemoteRepoName_suite($username);     //define the name of repo which will store all the backups
        $gitCmd = "cd $accountpath ; git config --add cent.username " . $username;
        $this->runShellCommand($gitCmd);
        $gitCmd = " cd $accountpath ; git config --add cent.reponame " . $reponame;
        $this->runShellCommand($gitCmd);
        $result = $this->createRemoteRepo_GitLab($token, $reponame);
        if ($result['status'] == 1) {   //repo created successfully
            $repo_url = $result['info'];
            $temp = $this->addRemoteRepo_suite($repo_url, $accountpath);
            if ($temp['status'] == 1)     //repo added successfully
            {
                return $temp;
            } else {
                //problems in adding the repo
                return $temp;
            }
        } else { //problems in creating repo
            return $result;
        }
    }

    private function getRemoteRepoName_suite($username)
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            return 'qatestrepo';
        } else {
            return substr(str_replace(".", "-", $username), 0, 10) . '-' . rand(1000, 9999);
        }
    }

    public function createRemoteRepo_GitLab($token, $reponame)
    {
        $gitcmd = "curl --header \"PRIVATE-TOKEN: $token \" -X POST \"https://gitlab.com/api/v3/projects?name=$reponame\"";
        $result = $this->runShellCommand($gitcmd);
        if (empty($result)) {
            return oseFirewallBase::prepareErrorMessage("There was some problem in creating a new repository on GitLab" . CONTACT_SUPPORT);
        }
        $result_encoded = json_decode($result['stdout']);
        if (!empty($result_encoded) && (property_exists($result_encoded, 'ssh_url_to_repo') === true)) {
            return oseFirewallBase::prepareSuccessMessage($result_encoded->ssh_url_to_repo);
        } else {
            if (property_exists($result_encoded, "message") && $result_encoded->message == "401 Unauthorized") {
                return oseFirewallBase::prepareErrorMessage("There was some problem in creating a new repository on GitLab" . CONTACT_SUPPORT . "<br/> <b>Error Details:</b> <br/> Please Insert a valid Access Token");
            } else {
                $formattedMessage = $this->formatErrorMessageArray(get_object_vars($result_encoded));
                return oseFirewallBase::prepareErrorMessage("There was some problem in creating a new repository on GitLab" . CONTACT_SUPPORT . "<br/> <b>Error Details:</b> <br/>" . $formattedMessage);

            }
        }
    }


    public function formatErrorMessageArray($array)
    {
        $result = null;
        if (!empty($array)) {
            $array  = $this->flatten($array);
            foreach ($array as $key => $value) {
                $result .= $key . "->" . $value . "<br/>";
            }
            return $result;
        }

    }


    public function flatten($array, $prefix = '') {
        $result = array();
        foreach($array as $key=>$value) {
            if(is_array($value)) {
                $result = $result + $this->flatten($value, $prefix . $key . '.');
            }
            else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    public function ignoreLargeZipFiles_suite($accountpath)
    {
        $size = 1000; //in Mb
        $gitcmd = "cd $accountpath; find . -size +".$size."M";
        $result = $this->runShellCommand($gitcmd);
        if ((!empty($result)) && isset($result['stdout']) && (!empty($result['stdout']))) {
            $large_fileList = $result['stdout'];
            $errorMsg = $result['stderr'];
            if (strpos($large_fileList, "command not found") !== false || (!empty($errorMsg) && strpos($errorMsg, "command not found") !== false)) {
                $this->backupLog_suite("ignorezipfiles - $errorMsg",$accountpath);
                $this->logErrorBackup_suite("ignorezipfiles - $errorMsg",$accountpath);
            } else {
                $formattedList = explode("./", $large_fileList);
                $formattedList = array_filter($formattedList);
                foreach ($formattedList as $file) {
                    if (!empty($file) && (strpos($file, "git/objects/") == false)) {
                        $filepath = $accountpath . ODS . ".gitignore";
                        if (file_exists($filepath)) {
                            //to avoid duplicate entries for a file
                            if (strpos(file_get_contents($filepath), $file) == false) {
                                file_put_contents($filepath, PHP_EOL . $file . PHP_EOL, FILE_APPEND);
                            }
                        } else {
                            //if file doesnt exist, create 1 and make an entry for the files
                            file_put_contents($filepath, PHP_EOL . $file . PHP_EOL, FILE_APPEND);
                        }
                    }
                }
                return oseFirewallBase::prepareSuccessMessage("Files have been ignored successfully");
            }
        }
    }

    public function complGitBackupv6()
    {
        //check config and send email                                           ]
        $settings = $this->getCronSettingsLocal(4);
        if(!empty($settings))
        {
            if($settings->recieveEmail == 1)
            {
                $result =  $this->sendGitBackupCompletionEMail();
                return $result;
            }

        }
    }

    public function getCronSettingsLocal($type)
    {
        $query = "SELECT `value` FROM `#__osefirewall_cronsettings` WHERE `type`= ".$this->db->quoteValue($type);
        $this->db->setQuery($query);
        $result = $this->db->loadResult();
        $decoded = json_decode($result['value']);
        return $decoded;
    }

    public function sendGitBackupCompletionEMail()
    {
        oseFirewall::callLibClass('emails', 'emails');
        $emailManager = new oseFirewallemails ();
        if(isset($_SERVER['HTTP_HOST']))
        {
            $currentDomain = $_SERVER['HTTP_HOST'];
            $domain = preg_replace('/[:\/;*<>|?]/', '', $currentDomain);
        }else{
            $currentDomain = $_SERVER['HOSTNAME'];
            $domain = preg_replace('/[:\/;*<>|?]/', '', $currentDomain);
        }
        $content = $this->getEmailContent();
        if(empty($content) || (isset($content['status']) && $content['status'] == 0))
        {
            return $content;
        }else{
            if(empty($domain))
            {
                $subject = 'Git Backup Completed';

            }else{
                $subject = 'Git Backup Completed '. " on [" . $domain . "]";

            }
            $emailManager->sendEMailV7($content,$subject);
            return oseFirewallBase::prepareSuccessMessage("Confirmation Email Sent");
        }

    }


    private function getEmailContent()
    {
        $content = $this->getFormattedLastBackupReport();
        if(empty($content))
        {
            return oseFirewallBase::prepareErrorMessage("There was some problem in generating the backups report ");
        }
        if(isset($content['status']) && $content['status'] == 0)
        {
            return $content;
        }
        $message = "<b>Git Backup</b> was completed with the following status: <br/><br/>";
        $message .= '<table border="1" cellpadding="10" cellspacing="1">
					<thead>	<tr><th>Account Name</th><th>Status</th><th>Last Backup Time</th></tr></thead>';
        $message.=$content;
        $message .= "<br/><br/>";
        $message .= "Centrora Security protects all your websites from malware and other malicious code.<br/><br/>";
        $message .= "Kind regards<br/>";
        return $message;
    }

    //format the account name and time using the table format
    private function  getFormattedLastBackupReport()
    {
        $lastBackupList = $this->getLastBackupTimeForAllAccounts();
        if(!empty($lastBackupList))
        {
            $content = "<tbody>";
            foreach($lastBackupList as $accountname=>$backupTime)
            {
                if($backupTime == "NONE")
                {
                    $content.= "<tr><td>".$accountname.'</td><td>' . "Account Backup is not UptoDate, Please check the backup status on the Gitbackup Page" . '</td><td>' . $backupTime . ' </td></tr>';
                }else{
                    $content.= "<tr><td>".$accountname.'</td><td>' . "Account has been backed up successfully" . '</td><td>' . $backupTime . ' AEST </td></tr>';
                }
            }
            $content.="</tbody></table>";
            return $content;
        }else{
            return oseFirewallBase::prepareErrorMessage("The account list from DB is empty");
        }
    }

    //get the list of accountnames along wioth their backup time
    private function getLastBackupTimeForAllAccounts()
    {
        $result = array();
        $accountList = $this->getAccountPathList();
        if(!empty($accountList))
        {
            foreach($accountList as $account)
            {
                if(isset($account['accountpath']))
                {
                    $result[$account['accountname']] = $this->getLastBackupDateTime($account['accountname'],$account['accountpath']);
                }
            }
            return $result;
        }else{
            return false;
        }
    }

    //get list of all the accounts with db config
    private function getAccountPathList()
    {
        $query = "SELECT `accountname`, `accountpath` FROM " . $this->db->quoteTable($this->dbconfiggit_table) . " WHERE 1";
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        return $result;
    }



    public function canrunCronJob()
    {
        oseFirewall::callLibClass('gitBackup', 'gitActivationPanel');
        $activationpanel = new gitActivationPanel();
        $flag = $activationpanel->checkSysteminfo();
        if($flag ==false)
        {
            $req = $activationpanel->getUnSatisfiedRequirements();
            return oseFirewallBase::prepareCustomMessage(1,"Following Pre-requisites are not satisfied : <br/>".$req."<br/> Please check the GitBackup Page for more details");
        }else{
            return oseFirewallBase::prepareCustomMessage(4,"All the requirements are satisfied ");
        }
    }

    public function getFileNotification_suite($accountname,$accountpath)
    {
        $result =array();
        $init = $this->isinit_suite($accountpath);
        if($init['status']!= 1)
        {
            return oseFirewallBase::prepareCustomMessage(3,'Not initialised');
        }
        $local = $this->getCountLocalFilesToCommit_suite($accountpath);
        $cloud = $this->getCloudFilesToPush($accountpath);
        if($local['status']==1)
        {
            $result['local'] = $local['info'];
        }else{
            $result['local'] = 0;
        }
        if($cloud['status']==1)
        {
            $result['cloud'] = $cloud['info'];
        }else{
            $result['cloud'] = $local['info'];
        }
        return $result;
    }

    public function getCountLocalFilesToCommit_suite($accountpath)
    {
        $gitCmd = "cd $accountpath; git status --porcelain -uall";
        $output = $this->runShellCommand($gitCmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || !empty($output['stderr'])) {
            $result['status'] = 0;
            $result['info'] = "There was some problems in getting the count of hashes to push <br/> ERROR:<br/>" . $output['stderr'];
            return $result;
        } else {
            if(empty($output['stdout']))
            {
                return oseFirewallBase::prepareSuccessMessage(0);
            }else{
                $hashArray = explode("\n",$output['stdout']);
                return oseFirewallBase::prepareSuccessMessage(count($hashArray));
            }
        }
    }
    public function getCloudFilesToPush($accountpath)
    {
        $cloudCheck = $this->isRemoteRepoSet_suite($accountpath);
        if($cloudCheck['status']!=1)
        {
            return oseFirewallBase::prepareErrorMessage('Remote repo is not set');
        }

        $gitcmd = "cd $accountpath;  git log --pretty=format:%h origin/master..master";
        $output = $this->runShellCommand($gitcmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || !empty($output['stderr'])) {
            $result['status'] = 0;
            $result['info'] = "There was some problems in getting the count of hashes to push <br/> ERROR:<br/>" . $output['stderr'];
            return $result;
        } else {
            if(empty($output['stdout']))
            {
                return oseFirewallBase::prepareSuccessMessage(0);
            }else{
                $hashArray = explode("\n",$output['stdout']);
                return oseFirewallBase::prepareSuccessMessage(count($hashArray));
            }
        }
    }

    public function getErrorLog_suite()
    {
        $errorLog = $this->getErrorLogContent_suite();
        if($errorLog['status'] ==1)
        {
            $content = $this->formatErrorLogContents_suite($errorLog['info']);
            return oseFirewallBase::prepareSuccessMessage($content);
        }else{
            return $errorLog;
        }
    }

    public function getErrorLogContent_suite()
    {
        $query = "SELECT * FROM #__osefirewall_errorlog WHERE 1";
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if(!empty($result))
        {
            return oseFirewallBase::prepareSuccessMessage($result);
        }else{
            return oseFirewallBase::prepareErrorMessage("Error Log is Empty");
        }
    }

    public function formatErrorLogContents_suite($logentry)
    {
        $message = '<table border="1" cellpadding="10" cellspacing="1">
					<thead>	<tr><th>Message</th><th>Account</th><th>Date&Time</th></tr></thead>';
        foreach($logentry as $content)
        {
            $message.= '<tbody>';
            if(isset($content['message']) && isset($content['account']) && $content['datetime'])
            {
                $message .= '<tr><td>'.$content['message'].' </td><td> '.$content['account'].'</td><td>'.$content['datetime'].'</td></tr>';
            }
            $message.= '</tbod>';
        }
        $message.= '</table>';
        return $message;
    }

    public function toggleBackupLog_suite($value)
    {
        if($value == 1)
        {
            if(!file_exists(O_ENABLE_GITBACKUP_LOG))
            {
//                touch(O_ENABLE_GITBACKUP_LOG);
//                chmod(O_ENABLE_GITBACKUP_LOG,0755);
                $this->runShellCommand("touch ".O_ENABLE_GITBACKUP_LOG ." ;");
            }
            return true;
        }else{
            $this->runShellCommand("rm -rf ".O_ENABLE_GITBACKUP_LOG ." ;");
//            unlink(O_ENABLE_GITBACKUP_LOG);
            return false;
        }
    }

    public function writeCronParams($action,$accountname, $accountpath,$key)
    {
        $settings = array();
        $settings['action'] = $action;
        $settings['accountname'] = $accountname;
        $settings['accountpath'] = $accountpath;
        $settings['key'] = $key;
        $this->saveCronSettings(json_encode($settings),7); //for gitbackup v6 , id7 = temporary cron params for the api server
    }



    public function getFormattedRecentBackupTable()
    {
        $result = array();
        $data =array();
        $complte_accountstable = $this->getAccountListCompleteTable();
        $i = 0;
        if(!empty($complte_accountstable))
        {
            foreach($complte_accountstable as $account)
            {
                if(isset($account['id']) && isset($account['name']) &&  isset($account['latestbackup'])) {
                    $data[$i]['id'] = $account['id'];
                    $temp1 = strip_tags($account['name']);
                    $temp1 =  str_replace("Go to Account",'',$temp1);
                    $temp1 = str_replace("Initiate",'',$temp1);
                    $data[$i]['name'] = $temp1;
                    $data[$i]['latestbackup'] = $account['latestbackup'];
                    $i++;
                }
            }
            if(count($data)>0)
            {
                $result['data'] = $data;
                $result['recordsTotal'] = count($data);
                $result['recordsFiltered']=count($data);
                return $result;
            }else{
                $result['data'] = 0;
                $result['recordsTotal'] = 0;
                $result['recordsFiltered']=0;
                return $result;
            }
        }else{
            $result['data'] = 0;
            $result['recordsTotal'] = 0;
            $result['recordsFiltered']=0;
            return $result;
        }
    }

    public function manageQueues()
    {
        $accountList = $this->getAccountListFromDB();
        if(count($accountList)==0)
        {
            return oseFirewallBase::prepareErrorMessage('Empty list');
        }elsE{
            return oseFirewallBase::prepareSuccessMessage('List is not empty');
        }
    }






}