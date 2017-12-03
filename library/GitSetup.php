<?php
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
    ini_set("display_errors","on");
}
require_once (dirname(__FILE__)."/Process.php");
define('WEBSITE_SIZE_THRESHOLD', 50000000);
class GitSetup
{
    private $workingDirectoryRoot = OSE_DEFAULT_SCANPATH;
    private $tempDirectory = null;
    private $commitMessagePrefix;
    private $gitBinary;
    private $gitProcessTimeout = null;
    private $db;
    private $maxextime = '';
    public static $repo = null;
    public static $countCommitLog = 0;
    public $gitLogtable = "#__osefirewall_gitlog";

    public function __construct($qatest = false,$remote = false)
    {
        if($remote)
        {
            return true;
        }
        $this->qatest = $qatest;
        $this->db = oseFirewall::getDBO();
        $this->optimisePHP();
        date_default_timezone_set ( 'Australia/Melbourne' );
        if($remote == false)
        {
            $exits = $this->folderExits();
            if ($exits == false) {
                $this->createBackupFolder();
            }
        }
        $this->initialSetup();
    }


    public function initialSetup()
    {
        $this->removeCentBackupFolderFromIgnore();
        $this->gitIgnoreFile(O_IGNORE_BACKUPLOG);
        $this->gitIgnoreFile(O_IGNORE_ERRORLOG);
    }

//    intitialise the project as a git repo so that the git can keep track of all the changes
//    after intitialising the git it checks if the db is ready and it is empty and inserts
//    all the entries from the git log in tho the db
    public function init()
    {
        $result = $this->runShellCommand("git init");
        if ((strpos($result['stderr'], 'fatal') !== false) || (strpos($result['stderr'], 'error') !== false) || (!file_exists(GITFOLDER))) {
            //ERROR : if there is a fatal error
            $output['status'] = 0;
            $output['info'] = oLang::_get('PROBLEM_IN_GIT') . $result['stderr'];

        } else {
            //IF THE COMMAND AND THE FOLDER WERE CREATED SUCCESSFULLY
            $output['status'] = 1;
            $output['info'] = "The git has been initialised successfully";
        }
        return $output;
    }

    public function isinit()
    {
        /*
         *  0 => error
         *  1 => initalise
         *  2 => not initalised
         */
        $this->backupLog("isint - checking if git is initialised for the account ");
        $fileExists = $this->checkIfFileExists(OSE_ABSPATH);
        if ($fileExists['status'] = 1) {
            $flag = $this->runShellCommand("git rev-parse --is-inside-work-tree");
            $result = $flag['stdout'];
            if ($result == true) {
                $this->backupLog("GIT HAS BEEN INITALISED FOR THE ACCOUNT ");
                return oseFirewallBase::prepareSuccessMessage('The git has been initialised');
            } else {
                $this->backupLog("GIT HAS NOT BEEN INITALISED " . $flag['stderr']);
                return oseFirewallBase::prepareCustomMessage(2, $flag['stderr']);
            }
        } else {
            //ERROR IN CHANGING DIRECTORY
            return $fileExists;
        }
    }

    public function checkIfFileExists($accounpath)
    {
        $this->backupLog("checkIfFileExists()  - checking if the file exists for $accounpath");
        if (file_exists($accounpath)) {
            $this->backupLog("checkIfFileExists() - file exists , exiting with success");
            return oseFirewallBase::prepareSuccessMessage("The file $accounpath exists ");
        } else {
            $this->backupLog("checkIfFileExists() -The file $accounpath does not exists ,exiting function with error ");
            $this->logErrorBackup("The file $accounpath does not exists ");
            return oseFirewallBase::prepareErrorMessage("The file $accounpath does not exists ");
        }
    }


    public function backupLog($message)
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $varValues = array(
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

    //logs only errors related to the backup queue
    public function logErrorBackup($message)
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $varValues = array(
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

    public function clearErrorLogBackup()
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

    public function clearBackupLog()
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

    //returns the count of the commits from the database
    public function getCommitCountFromDb()
    {
        $result = $this->db->getTotalNumber('id', '#__osefirewall_gitlog');
        return $result;
    }

    //gets the entries form the git log and inserts them into the database
    public function insertLoginDB()
    {
        $log = $this->getFormattedGitLog();
        $temp1 = $log['data'];
        $temp = array_reverse($temp1);  //IMP : the array needs tobe entered in reverse order in db because new entries are entered at the last place
        $tmps = $log['data'][0]['commitID'];
        foreach ($temp as $value) {
            $commit_id = $value['commitID'];
            $commit_time = $value['commitTime'];
            $commit_msg = $value['commitMsg'];
            $varValues = array(
                'commit_id' => $commit_id,
                'commit_time' => $commit_time,
                'commit_message' => $commit_msg,
                'is_head' => 0,
            );
            //get the first value of log
            $website_id = $this->db->addData('insert', '#__osefirewall_gitlog', '', '', $varValues);
        }
        //set the head to be the last entry (first entry from log and last entry in DB)
        $Array = $this->headArray();
        $new_head = $this->db->addData('update', '#__osefirewall_gitlog', 'commit_id', $tmps, $Array);
        return $website_id;
    }

    public function headArray()
    {
        $Array = array(
            'is_head' => 1,
        );
        return $Array;
    }

    //gets all the entries from the database for the git log
    public function getGitLogFromDb()
    {
        $limit = oRequest::getInt('length', 15);
        $start = oRequest::getInt('start', 0);
        $dbReady = oseFirewall::isDBReady();
        if ($dbReady == true) {
            if (!empty($limit)) {
                $this->getLimitStm($start, $limit);
            }
            $db = oseFirewall::getDBO();
            $query = "SELECT * FROM `#__osefirewall_gitlog` WHERE 1 ORDER BY `id` DESC". " " . $this->limitStm;;
            $db->setQuery($query);
            $result = $db->loadResultList();
            $result = $this->gitLogConversion($result);
            return $result;
        }
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
        $this->db->setQuery($sql);
        $result = $this->db->loadResultList();
        return count($result);
    }

    protected function getLimitStm($start, $limit)
    {
        if (!empty($limit)) {
            $this->limitStm = " LIMIT " . (int)$start . ", " . (int)$limit;
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
            $this->db->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid_oldhead, $Array);
            $new_head = $this->db->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid, $newHead);
            return $new_head;
        } else {

            $new_head = $this->db->addData('update', '#__osefirewall_gitlog', 'commit_id', $commitid, $newHead);
            return $new_head;
        }
    }

    //returns the entry which is the current head
    public function getHead()
    {
        $db = oseFirewall::getDBO();
        $query = "SELECT * FROM `#__osefirewall_gitlog` WHERE `is_head` = 1";
        $db->setQuery($query);
        $result = $db->loadResultList();
        if (count($result) == 0) {
            return 0;
        } else {
            return $result;
        }
    }

    public static function startsWith($haystack, $needle)
    {
        return strncmp($haystack, $needle, strlen($needle)) === 0;
    }

    //returns the git log with the the format all the commit ids, date and the commit message
    public function getFormattedGitLog()
    {
        $result = $this->runGitLog();
        $tem = $result['stdout'];
        $tmp = explode("\n", $tem);
        $data = $this->convertResult($tmp);
        $data['recordsTotal'] = count($tmp);
        $data['recordsFiltered'] = $data['recordsTotal'];
        return $data;
    }
    //retunrs the reflog fir the system which has information about transition between commits
    //use it for debugging not a feature for a customer
    public function getRefLog()
    {
        $result = $this->runRefLog();
        $tem = $result['stdout'];
        $tmp = explode("\n", $tem);
        $data = $this->convertResult($tmp);
        return $data;
    }

    //to run the reflog for debugginh
    public function runRefLog()
    {
        $gitCmd = "git reflog --pretty=format:\"%h--%cd--%s\"";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //marks the files ad staged  ==>needs to be done before committing the changes
    public function stageAllChanges_finalpush()
    {
        $gitCmd = "git add --all";
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

    protected function getCommitMessages($type = false, $foldername = false)
    {

        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $commitMessagePrefix = "QATEST : PLEASE DO NOT USE THIS VERSION FOR ROLLING BACK(FOR TESTING PURPOSE ONLY)";
            $foldermsgPrefix = $this->getCommitMessagesforFolders($foldername);
            return $commitMessagePrefix . $foldermsgPrefix;

        }
        //if type and foldername are defined
        if ($type == 'init' && $foldername != false) {
            //if type == init and we have the foldername use the below commmit messaage
            $msgPrefix = "Initial Local Backup ";
            $foldermsgPrefix = $this->getCommitMessagesforFolders($foldername);
            return $msgPrefix . $foldermsgPrefix;
        }
        //if type is not defined ==> local backup and folderame is defined
        if ($type != 'init' && $foldername != false) { //for the user defined commit messages
            $temp = $this->getSessionValue("commitMessage");
            if ($temp !== null) {
                $foldermsgPrefix = $this->getCommitMessagesforFolders($foldername);
                return $temp . $foldermsgPrefix;
            } else {
                //for the default commit messages
                $commitMessagePrefix_temp = "Centrora Security Backup " . date("D M j G:i:s T Y");
                $foldermsgPrefix = $this->getCommitMessagesforFolders($foldername);
                return $commitMessagePrefix_temp . $foldermsgPrefix;
            }
        }
        if ($type == false && $foldername == false) {
            $commitMessagePrefix_temp = "Centrora Security Backup " . date("D M j G:i:s T Y");
            return $commitMessagePrefix_temp;
        }
    }

    //returns the folder name for the commit message
    public function getCommitMessagesforFolders($foldername)
    {
        // get the string for the folder name and append that to the custom messages in the getCommitMessage function
        if ($foldername == "restoffiles") {
            $commitMessagePrefix = " : Rest of the files";
            return $commitMessagePrefix;
        } else {
            $commitMessagePrefix = " : " . $foldername;
            return $commitMessagePrefix;
        }
    }

    //commit all the chnages to the repo with the message "centrora security backup + time"
    public function commitChanges_finalpush()
    {
        $commitMessagePrefix = $this->getCommitMessages();
        $gitCmd = "git commit --all -m \"$commitMessagePrefix\" ";
        $result = $this->runShellCommand($gitCmd);
        if ((strpos($result['stderr'], 'fatal') !== false) || (strpos($result['stderr'], 'error') !== false)) {
            //if there is a fatal error
            $output['status'] = 0;
            $output['info'] = "There were some problems in committing the changes in final push <br/>ERROR: <br/>" . $result['stderr'];

        } else {
            //if there is no fatal error
            $this->insertNewCommitDb();
            //unset the session variable to keep using the same commit messages
            if (isset($_SESSION["commitMessage"])) {
                unset($_SESSION["commitMessage"]);
            }

            $output['status'] = 1;
            $output['info'] = "Changes have been committed successfully";
        }
        return $output;
    }

    public function getSessionValue($key)
    {
        if (isset($_SESSION[$key])) {
            if ($_SESSION[$key] != null && $_SESSION[$key]!== "undefined") {
                return $_SESSION[$key];
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    public function setSessionValue($key, $value)
    {
        if (!session_id())
            session_start();
        $_SESSION[$key] = $value;
        if ($_SESSION[$key] == $value) {   //SUCCESS
            $result['status'] = 1;
        } else { //ERROR
            $result['status'] = 0;
        }
        return $result;
    }

    //once the chnages are commiteed they can be instantly committed to the db to have a persistent log
    public function insertNewCommitDb()
    {
//        $result = $this->db->getLastCommitInDB();
//        $lastcommitid = $result['commit_id'];  //last commit from db
        $log = $this->getFormattedGitLog();
        $value = $log['data'][0];  //last coommit id from the log
        //compare the last commit id in db with the last entry in the log
        $commitExists = $this->db->commitExistsInLog($value['commitID']);
        if (!$commitExists) {
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
            $newCommitID = $this->db->addData('insert', '#__osefirewall_gitlog', '', '', $varValues);
            return $newCommitID;
        }
    }
    //checks if there are any changes in the files
    public function findChanges()
    {
        $this->backupLog("finding changes");
        $result = $this->getStatus();
        if ($result['status'] == 1 || $result['status'] == 2) {
            $status = oseFirewallBase::checkSubscriptionStatus(false);
            if ($result['status'] == 1) {
                $result['status'] = 1;
                $result['subscription'] = $status;
                $this->backupLog("There are some changes that needs to be committed ");
                return $result;  // the repo has some changes
            } else if ($result['status'] == 2) {
                $result['status'] = 2;
                $result['subscription'] = $status;
                $this->backupLog("There are no new changes that needs to be committed");
                return $result;   // the repo is upto date
            }
        } else {
            //STATUS == 0 => PROBLEM IN FINDING CHNAGES
            //EMPTY ARRAY => FORMATING  THE LIST OF CHNAGES
            return oseFirewallBase::prepareCustomErrorMessage($result['info'], "medium"); //return null if there is a problem in accessing the get status
        }

    }
    //function to revert back to the previous state using the reset command
    //need commit id for which you want to rollback
    //aso the head needs to updated when you rollback
    public function gitRollback($commitid, $recall)    //TESTING code for revert
    {
        //IF COMMIT WAS SUCCESSFUL
        $gitCmd = "git reset --hard $commitid";   //checkout vs reset
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR
            $result['status'] = 0;
            $result['info'] = "There was some problems in reverting <br/>ERROR: <br/>" . $output['stderr'];
        } else {
            //SUCCESS
            $restoreDb['status'] = 1;
            if ($recall == "old") {
                $restoreDb = $this->restoreDBBackup();
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

    public function restoreDBBackup()
    {
        $dbconfig = $this->getDbConfig();
        if($dbconfig['status'] == 0)
        {
            return $dbconfig;
        }
        $username = $dbconfig['info']['user'];
        $pswd = $dbconfig['info']['password'];
        //$prefix = $dbconfig['info']['dbprefix'];
        $dbname = $dbconfig['info']['db'];
        $host = $dbconfig['info']['host'];
        $this->restore_createTables($username, $pswd, $dbname,$host);
        $this->restore_alterTables($username, $pswd, $dbname,$host);
        $this->restore_insertTables($username, $pswd, $dbname,$host);
        $result = $this->dropNewDBTable();
        if ($result['status'] == 0) {
            return $result;
        } else {
            return oseFirewallBase::prepareSuccessMessage('All tables have been backed up successfully');
        }
    }

    /*
     * New code for restoring the databse
     */

    public function restore_createTables($username, $pswd, $dbname,$host)
    {
        $gitcmd = "mysql -u '$username' -p'$pswd' -h '$host' '$dbname' <".O_GITBACKUP_CREATETABLEFILE;
        $result = $this->runShellCommand($gitcmd);
    }

    public function restore_alterTables( $username, $pswd, $dbname,$host)
    {
        $gitcmd = "mysql -u '$username' -p'$pswd' -h '$host' '$dbname' <".O_GITBACKUP_ALTERTABLEFILE;
        $this->runShellCommand($gitcmd);
    }

    public function restore_insertTables($username, $pswd, $dbname,$host)
    {
        $gitcmd = "mysql -u '$username' -p'$pswd' -h '$host' '$dbname' <".O_GITBACKUP_INSERDATAFILE;
        $this->runShellCommand($gitcmd);
    }

    public function getListOfTables()
    {
        $tables = array();
        if (file_exists(O_GITBACKUP_TABLELISTFILE)) {
            require(O_GITBACKUP_TABLELISTFILE);
            return $tables['tables'];
        } else {
            return '';
        }
    }

    //Drop the tables that did not exist in the old backup
    //Formula :
    //Current DB Tables - Old BackupDb Tables = Remaing tables => DROP THEM
    public function dropNewDBTable()
    {
        $oldTables = $this->getListOfTables();
        if (empty($oldTables)) {
            return oseFirewallBase::prepareErrorMessage("There was some problem in getting the list of backup up database table names");
        }
        $currentTables = $this->db->getTableList();
        $dropTablesList = array_diff($currentTables, $oldTables);
        if (!empty($dropTablesList)) {
            foreach ($dropTablesList as $dropTable) {
                $tableExists = $this->db->isTableExists($dropTable);
                if ($tableExists && (strpos($dropTable, "osefirewall_gitlog") == false)) {
                    $dropresult = $this->db->dropTable($dropTable);
                    if ($dropresult == false) {
                        $this->db->dropView($dropTable);
                    }
                }
            }
            return oseFirewallBase::prepareSuccessMessage("The remaining tables has been dropped");
        } else {
            return oseFirewallBase::prepareSuccessMessage("No Difference in the Tables");
        }
    }

    // Debugging function complete function to rollback which commits the changes first and then rolls back to the
    //provided commit id
    public function gitRollbackComplete($commitid)
    {
        $result = $this->rollbackToPreviousState($commitid);  //change to $id
        return $result;
    }

    // Debugging function to get the commit id provided by the user
    public function getPreviousCommitId()
    {
        $id = "615c3b273ba34639129b0b873899a129904704f1";
        return $id;
    }

    //returns any changes madein the filess
    public function getStatus($array = false)
    {
        $this->backupLog("Finding new chnages");
        $gitCmd = "git status --porcelain -uall";
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR
            $this->logErrorBackup("There was some error in finding the chnages " . $output['stderr']);
            $this->backupLog("There was some error in finding the chnages " . $output['stderr']);
            return oseFirewallBase::prepareErrorMessage($output['stderr']);
        } else {   //SUCCESS
            if (empty($output['stdout'])) {
                $this->backupLog("There are no new changes to commit " . $output['stderr']);
                return oseFirewallBase::prepareCustomMessage(2, 'No Changes to commit');
            } else {
                //list of files that needs to be committed
                $output = (string)$output['stdout'];
                $tmp = explode("\n", $output);
                $return = array();
                if (empty($output)) {
                    $this->logErrorBackup("There was some problem in getting the formatted list of new changes");
                    $this->backupLog("There was some problem in getting the formatted list of new changes");
                    return oseFirewallBase::prepareErrorMessage("There was some problem in getting the formatted list of new changes for account ");
                }
                foreach ($tmp as $k => $line) {
                    $return[$k] = explode(" ", trim($line), 2);
                }
                $this->backupLog("The list of changes were prepared successfully");
                return oseFirewallBase::prepareSuccessMessage($return);
            }
        }
    }

    //copies the code to the bitbucket repo
    public function bitbucketBackup()
    {
        if (oseFirewallBase::checkSubscriptionStatus(false) == true) {
            $result = $this->findChanges();
            if ($result['status']) {
                $message = "You have some unsaved changes, Please create a backup first and then Upload them to the cloud";
                $output['status'] = 2;
                $output['info'] = $message;
                return $output;
            } else {
                $result = $this->sshPushSetup();
                return $result;
            }
        } else {
            $output['status'] = 3;
            $output['info'] = "Please subscribe to our services to use this feature"; //returns the success message
            return $output;
        }
    }

    public function cloudBackup()
    {
        $result = $this->findChanges();
        if ($result['status'] == 1) {
            $message = "You have some unsaved changes, Please create a backup first and then Upload them to the cloud";
            $output['status'] = 2;
            $output['info'] = $message;
            return $output;
        } elseif ($result['status'] == 2) {
            $result = $this->sshPushSetup();
            return $result;
        }
    }


    //for debugging only
    public function removeremoterepo($name)
    {
        $gitCmd = "git remote rm " . $name;
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

    // getter for remote repo name
    public function getRepoName()
    {
        return self::$repo;
    }

    //setter for remote repo name
    public function setRepoName($reponame)
    {
        self::$repo = $reponame;
    }

    //checks if the remote repo on bitbucket is set or not
    public function isRemoteRepoSet()
    {
        $gitCmd = "git remote -v";
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


    protected function convertSSHURL($username, $repourl)
    {
        $newurl = str_replace("https://$username@bitbucket.org/", "git@bitbucket.org:", $repourl);
        return $newurl;
    }

    //function to add url of an remote repos
    public function addRemoteRepo($repourl)
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $gitCmd = "git remote add qatestrepo $repourl";

        } else {
            $gitCmd = "git remote add origin $repourl";
        }
        $result = $this->runShellCommand($gitCmd);
        if ($result['stderr'] == null) {
            return oseFirewallBase::prepareSuccessMessage($result['stderr']);
        } else {
            return oseFirewallBase::prepareErrorMessage($result['stderr']);
        }
    }

    //function to switch and use an specific remote repo
    public function switchToRepo()
    {
        $gitCmd = "git fetch && git checkout origin";
        $result = $this->runShellCommand($gitCmd);
        return $result;
    }

    //function to create an remote repo on bitbucket using the commandline
    public function createRemoteRepo($username, $password, $reponame)
    {
        $gitCmd = "curl -X POST -v -u $username:$password -H \"Content-Type: application/json\" \
  https://api.bitbucket.org/2.0/repositories/$username/$reponame \
  -d '{\"scm\": \"git\", \"is_private\": \"true\", \"fork_policy\": \"no_public_forks\" }'";
        $result = $this->runShellCommand($gitCmd);
        //to check if the resposne from ajax has error or results
        // stdout => has the repsonse and stderr=>has the err
        if ($result['stdout'] !== null) {
            $temp_result = $result['stdout'];
            $decodedarray = json_decode($temp_result, true);
            //check if the repo was created
            $error = $this->errorinRepoCreation($decodedarray);
            if (!$error) {
                $url = $this->getRepoUrl($decodedarray);
                $return['status'] = 1;
                $return['info'] = $url;
                return $return;
            } else {
                //if there is an attempt to create a repo twice
                $return['status'] = 0;
                $return['info'] = $error['message'];
                return $return;
            }
        } else {
            //for wrong useraname and password the stdout will be empty
            $return['status'] = 0;
            $return['info'] = "Wrong username or password";
            return $return;
        }
    }

    public function getRepoUrl($array)
    {
        $url = $array['links']['clone'][1]['href'];
        return $url;
    }

    public function errorinRepoCreation($array)
    {
        //responce will contain an array with field error if there is a problem
        if (isset($array['error'])) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Ends the $haystack string with the suffix $needle?
     * @param  string
     * @param  string
     * @return bool
     */
    public static function endsWith($haystack, $needle)
    {
        return strlen($needle) === 0 || substr($haystack, -strlen($needle)) === $needle;
    }

    protected function runShellCommand($command, $args = '')
    {
        $functionArgs = func_get_args();
        array_shift($functionArgs);
        $result = $this->runProcess($command);
        return $result;
    }

    private function runProcess($cmd)
    {
        $dyldLibraryPath = getenv("DYLD_LIBRARY_PATH");
        if ($dyldLibraryPath != "") {
            putenv("DYLD_LIBRARY_PATH=");
        }
        $process = new Process($cmd, $this->workingDirectoryRoot);
        if ($this->gitProcessTimeout !== null) {
            $process->setTimeout($this->gitProcessTimeout);
        }
        $process->run();
        $result = array(
            'stdout' => $process->getOutput(),
            'stderr' => $process->getErrorOutput()
        );
        putenv("DYLD_LIBRARY_PATH=$dyldLibraryPath");
        if ($result['stdout'] !== null) $result['stdout'] = trim($result['stdout']);
        if ($result['stderr'] !== null) $result['stderr'] = trim($result['stderr']);

        return $result;
    }

    public function runShellCommandWithStandardOutput($command, $args = '')
    {
        $result = call_user_func_array(array($this, 'runShellCommand'), func_get_args());
        return $result['stdout'];
    }

    //function to get the git log
    public function runGitLog()
    {
        $gitCmd = "git log --pretty=format:\"%h--%cd--%s\"";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //return an formatted git log with commitid, commitdat and message
    public function convertResult($tmps)
    {
        $return = array();
        if (!empty($tmps)) {
            foreach ($tmps as $tmp) {
                $element = explode("--", $tmp);
                $array['commitID'] = $element[0];
                $array['commitTime'] = $element[1];
                $array['commitMsg'] = $element[2];
                $array['rollback'] = "<a href='javascript:void(0);' title = 'Inactive' onClick= 'confirmRollback(" . $element[0] . ")' ><i class='text-block glyphicon glyphicon-minus-sign'></i></a>";  //not used anywhere
                $return['data'][] = $array;
            }
        }
        return $return;
    }

    //Debug function to standaardise the message in the log, it replaces the string reset:moving to ==> Rolling back to
    public function rollbackMessageinLog($temps)
    {
        if (strpos($temps, 'Reset') !== false) {
            $temps = str_replace('reset: moving to', 'Reverted Back to', $temps);
        }
        return $temps;
    }

    //Debug function
    public function rollbackMessage($tmps)
    {
        if (!empty($tmps)) {
            foreach ($tmps as $tmp) {
                $element = explode("--", $tmp);
                if (strpos($tmps['commitMsg'], 'reset:') !== false) {
                    str_replace('reset: moving to', 'Rolling Back to', $element[2]);
                }
            }
        }

    }
    /*New code to backup databse using mysql dump
    */

    public function getDbConfig()
    {
        $this->backupLog("trying to retrieve the db config");
        $result = array();
        if (OSE_CMS == "joomla") {
            $config = JFactory::getConfig();
            $dbname = $config->get('db');
            $prefix = $config->get('dbprefix');
            $pass = $config->get('password');
            $username = $config->get('user');
            $host = $config->get('host');
            if (!empty($username) && !empty($dbname) && !empty($prefix) && !empty($pass) &&  !empty($host)) {
                $result['db'] = $dbname;
                $result['dbprefix'] = $prefix;
                $result['password'] = $pass;
                $result['user'] = $username;
                $result['host'] = $host;
                $this->backupLog("Db config retrieved successfully - joomla ");
                return oseFirewallBase::prepareSuccessMessage($result);
            } else {
                return oseFirewallBase::prepareErrorMessage("There was some problem accessing the db config." . CONTACT_SUPPORT);
            }
        } else {
            $table_prefix = false;
            //TODO GET WORDPRESS DETAILS
            require (OSE_ABSPATH.ODS.'wp-config.php');
            $dbname = DB_NAME;
            $username = DB_USER;
            $pass = DB_PASSWORD;
            $prefix = $table_prefix;
            $host = DB_HOST;
            if (!empty($dbname) && !empty($username) && !empty($pass) && !empty($prefix) && !empty($host)) {
                $result['db'] = $dbname;
                $result['dbprefix'] = $prefix;
                $result['password'] = $pass;
                $result['user'] = $username;
                $result['host'] = $host;
                return oseFirewallBase::prepareSuccessMessage($result);
            } else {
                return oseFirewallBase::prepareErrorMessage("There was some problem accessing the db config." . CONTACT_SUPPORT);
            }
        }
    }


    public function backupDbs()
    {
        $this->clearBackupLog();
        $this->clearErrorLogBackup();
        $this->backupLog("backupDbs - starting to backup database");
        $dbconfig = $this->getDbConfig();
        if ($dbconfig['status'] == 0) {
            //ERROR IN RETRIEVING THE DB CONFIG
            return $dbconfig;
        }
        $username = $dbconfig['info']['user'];
        $pswd = $dbconfig['info']['password'];
        $prefix = $dbconfig['info']['dbprefix'];
        $dbname = $dbconfig['info']['db'];
        $host = $dbconfig['info']['host'];
        //generate the file which has all the create table queries
        $createTable_result = $this->backup_createTables($prefix, $username, $pswd, $dbname,$host);
        if ($createTable_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($createTable_result['info'], "low", $createTable_result['details']);
        }
        //generate the file with all the insert queries
        $insertTable_result = $this->backup_insertTables($prefix, $username, $pswd, $dbname,$host);
        if ($insertTable_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($insertTable_result['info'], "low", $insertTable_result['details']);
        }
        $gitLog_result = $this->backup_gitLogTable($prefix, $username, $pswd, $dbname,$host);
        if ($gitLog_result['status'] == 0) {
            return oseFirewallBase::prepareCustomErrorMessage($gitLog_result['info'], "low", $gitLog_result['details']);
        }
        $tables = $this->db->getTableList();
        if (!empty($tables)) {
            //genrate the file with all the alter table queries
            $this->createAlterTableSQL($tables);
            $tableList_result = $this->backup_createTableList($tables);
            if ($tableList_result['status'] == 0) {
                return oseFirewallBase::prepareCustomErrorMessage($tableList_result['info'], "low");
            } else {
                return oseFirewallBase::prepareSuccessMessage("Database has been backed up successfully");
            }
        } else {
            $this->backupLog("backupDbs" . "There was some problem in getting the list of tables " . "<br/>Database has not been backed Up <br/>");
            $this->logErrorBackup("backupDbs" . "There was some problem in getting the list of tables " . "<br/>Database has not been backed Up<br/>");
            return oseFirewallBase::prepareCustomErrorMessage("There was some problem in accessing the tables list while creating alter table queries", "low");
        }
    }

    public function testDbConnection($dbname,$user,$pswd,$host)
    {
        $this->backupLog("testDbConnection - testing db connection ");
        try {
            $dbh = new pdo("mysql:host=$host;dbname=$dbname",
                $user,
                $pswd,
                array(PDO::ATTR_ERRMODE, PDO::ERRMODE_SILENT));   // array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
            $this->backupLog("testDbConnection - DB CONNECTION SUCCESSFUL");
            return (array('status' => true));
        } catch (PDOException $ex) {
            $this->backupLog("testDbConnection - FAILED TO CONNECT TO THE DATABASE, exiting with error");
            $this->logErrorBackup("testDbConnection - FAILED TO CONNECT TO THE DATABASE,exiting with error");
            return oseFirewallBase::prepareErrorMessage($ex->getMessage());
        }
    }




//    //generate all the create queries
    public function backup_createTables($prefix, $username, $password, $dbname,$host)
    {
        $this->backupLog("backup_createTables - Backing up create tables ");
        if (file_exists(O_GITBACKUP_CREATETABLEFILE)) {
            unlink(O_GITBACKUP_CREATETABLEFILE);
        }
        $gitTable = $prefix . "osefirewall_gitlog";
        $gitcmd = "mysqldump -d -u '$username' -p'$password' -h'$host' '$dbname' --ignore-table='$dbname'.$gitTable >" . O_GITBACKUP_CREATETABLEFILE ;//. " >> /dev/null & 2>1";
        $output = $this->runShellCommand($gitcmd);
        if (file_exists(O_GITBACKUP_CREATETABLEFILE)) {
            $this->backupLog("backup_createTables - create table file has been created ");
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog("backup_createTables" . "There was some problem in generating create Table queries" . "<br/>Database has not been backed  <br/>" . $output['stderr']);
            $this->logErrorBackup("backup_createTables" . "There was some problem in generating create Table queries" . "<br/>Database has not been backed <br/>" . $output['stderr']);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating create Table queries", $output['stderr']);
        }
    }

    //generate all the insert queries
    public function backup_insertTables($prefix, $username, $password, $dbname,$host)
    {
        $this->backupLog("backup_insertTables - generating insert table file ");
        if (file_exists(O_GITBACKUP_INSERDATAFILE)) {
            unlink(O_GITBACKUP_INSERDATAFILE);
        }
        $gitTable = $prefix . "osefirewall_gitlog";
        $gitcmd = "mysqldump -u '$username' -p'$password' -h'$host' '$dbname' --no-create-info --ignore-table='$dbname'.$gitTable >" . O_GITBACKUP_INSERDATAFILE;
        $output = $this->runShellCommand($gitcmd);
        if (file_exists(O_GITBACKUP_INSERDATAFILE)) {
            $this->backupLog("backup_insertTables - insert table file has been created ");
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog("backup_insertTables -" . "There was some problem in generating insert Table queries" . "<br/>Database has not been backed <br/>" . $output['stderr']);
            $this->logErrorBackup("backup_insertTables-" . "There was some problem in generating insert Table queries" . "<br/>Database has not been backed <br/>" . $output['stderr']);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating insert Table queries", $output['stderr']);
        }
    }

    public function backup_gitLogTable($prefix, $username, $password, $dbname,$host)
    {
        $this->backupLog("backup_gitLogTable - generating gitlog table file");
        if (file_exists(O_GITBACKUP_GITLOGTABLEFILE)) {
            unlink(O_GITBACKUP_GITLOGTABLEFILE);
        }
        $gitTable = $prefix . "osefirewall_gitlog";
        $gitcmd = "mysqldump -u '$username' -p'$password' -h'$host' '$dbname' $gitTable >" . O_GITBACKUP_GITLOGTABLEFILE;
        $output = $this->runShellCommand($gitcmd);
        if (file_exists(O_GITBACKUP_GITLOGTABLEFILE)) {
            $this->backupLog("backup_gitLogTable - The create Table queries have been successfully generated ");
            return oseFirewallBase::prepareSuccessMessage("The create Table queries have been successfully generated");
        } else {
            $this->backupLog("backup_insertTables -" . "There was some problem in generating gitlog Table queries" . "<br/>Database has not been backed  <br/>" . $output['stderr']);
            $this->logErrorBackup("backup_insertTables-" . "There was some problem in generating gitlog Table queries" . "<br/>Database has not been backed  <br/>" . $output['stderr']);
            return oseFirewallBase::prepareCustomDetailedMessage(0, "There was some problem in generating gitlog Table queries", $output['stderr']);
        }
    }

    protected function prepareTablesList($tables, $path)
    {
        $newTableArray = array_reverse($tables);
        $content = "<?php\n" . '$tables = array("tables"=>' . var_export($newTableArray, true) . ");";
        $writeFile_result = $this->writeFile($path, $content);

        if ($writeFile_result) {
            $this->backupLog("File write was successfull");
            return oseFirewallBase::prepareSuccessMessage('File write was successful');
        } else {
            $this->backupLog("There was some problem in writing the DB Table file contents");
            $this->logErrorBackup("There was some problem in writing the DB Table file contents");
            return oseFirewallBase::prepareErrorMessage('There was some problem in writing the DB Table file contents');
        }
    }

    public function backup_createTableList($tables)
    {
        if (file_exists(O_GITBACKUP_TABLELISTFILE)) {
            unlink(O_GITBACKUP_TABLELISTFILE);
        }
        $write_result = $this->prepareTablesList($tables, O_GITBACKUP_TABLELISTFILE);
        return $write_result;
    }


    protected function createAlterTableSQL($tables)
    {
        if (!empty($tables)) {
            $sql = $this->createViewQueries($tables);
            $alterTableQuery = $this->createAlterQueries($tables);
            if (!empty($alterTableQuery)) {
                $sql .= $alterTableQuery;
            }
            if (file_exists(O_GITBACKUP_ALTERTABLEFILE)) {
                unlink(O_GITBACKUP_ALTERTABLEFILE);
            }
            return $this->writeFile(O_GITBACKUP_ALTERTABLEFILE, $sql);
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

    public function writeFile($file, $content)
    {
        $result = file_put_contents($file, $content);
        return ($result == false) ? false : true;
    }

    protected function deleteTableFile()
    {
        $tableFile = OSE_FWDATA . ODS . "backup" . ODS . "dbtables.php";
        if (file_exists($tableFile)) {
            unlink($tableFile);
        }
    }


    public function initalisegit()
    {
        $this->backupLog("initialise git - starting to initialise git");
        $isinit = $this->isinit();
        if ($isinit['status'] == 2) {
            //git not initalised
            $init_result = $this->init();
            if ($init_result['status'] == 0) {
                return oseFirewallBase::prepareCustomErrorMessage($init_result['info'], "medium");
            }
            $addUserInfo_result = $this->addUserInfoGitConfig();
            if ($addUserInfo_result['status'] == 1) {
                return oseFirewallBase::prepareSuccessMessage("The Git has been successfully Initialized");
            } else {
                return oseFirewallBase::prepareCustomErrorMessage($addUserInfo_result['info'], "low");
            }
        } else if ($isinit['status'] == 1) {
            return oseFirewallBase::prepareSuccessMessage("The git has been already initialized");
        } else {
            //status = 0 => error
            return oseFirewallBase::prepareCustomErrorMessage($isinit['info'], "medium");
        }
    }


    public function loaddGitSetupLLibrary()
    {
        oseFirewallBase::callLibClass('gitBackup', 'GitSetupL');
        $gitsetupl = new GitSetupL();
        return $gitsetupl;
    }


    public function test()
    {
        $gitCmd = "echo hello";
        $output = $this->runShellCommand($gitCmd);
        print_r($output);
        $gitCmd = "echo hello";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    private function getAllRows($table)
    {
        $query = 'SELECT * FROM ' . $this->db->quoteKey($table);
        $this->db->setQuery($query);
        $results = $this->db->loadResultList();
        return $results;
    }

    private function getCreateTable($table)
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

    private function getColumns($row)
    {
        $k = array();
        $i = 0;
        foreach ($row as $key => $value) {
            $k [$i] = $this->db->QuoteKey($key);
            $i++;
        }
        $return = " (" . implode(", ", $k) . ") ";
        return $return;
    }

    private function countFile($path)
    {
        $size = 0;
        $ignore = array(
            '.',
            '..',
            'cgi-bin',
            '.DS_Store'
        );
        $files = scandir($path);
        foreach ($files as $t) {
            if (in_array($t, $ignore))
                continue;
            if (is_dir(rtrim($path, '/') . '/' . $t)) {
                $size += $this->countFile(rtrim($path, '/') . '/' . $t);
            } else {
                $size++;
            }
        }
        return $size;
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
                        $v [$i] = $this->db->QuoteValue($value);
                    }
                }
                $i++;
            }
            $varray [] = "(" . implode(", ", $v) . ")";
        }
        $return = " VALUES \n" . implode(",\n", $varray) . ";";
        return $return;
    }

    private function getViewPattern()
    {
        return "/CREATE\s*ALGORITHM\=UNDEFINED\s*[\w|\=|\`|\@|\s]*.*?VIEW/ims";
    }

    private function getConstraintPattern()
    {
        return "/\,[CONSTRAINT|\s|\`|\w]+FOREIGN\s*KEY[\s|\`|\w|\(|\)]+ON\s*[UPDATE|DELETE]+\s*[RESTRICT|NO\s*ACTION|CASCADE|SET\s*NULL]+/ims";
    }

    private function getCreateTableFromDB($table)
    {
        $sql = 'SHOW CREATE TABLE ' . $this->db->quoteKey($table);
        $this->db->setQuery($sql);
        $result = $this->db->loadResult();
        $tmp = array_values($result);
        return $tmp [1];
    }

    protected function getDatabaseFileList()
    {
        $scanPath = CENTRORABACKUP_FOLDER . ODS . 'gitbackup';
        $files = array();
        $objects = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(realpath($scanPath), RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST, RecursiveIteratorIterator::CATCH_GET_CHILD);
        foreach ($objects as $path => $dir) {
            if (is_file($path) && substr($path, -4) == '.sql' && $path != $scanPath . "/" . $this->db->getPrefix() . 'osefirewall_gitlog.sql') {
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
                $this->db->dropTable($tablename);
                $query = $this->installer->readSQLFile($file);
                $this->db->setQuery($query);
                if (!$this->db->query()) {
                    return false;
                }
            }
        }
        return true;
    }

    protected function restoreAlterTable()
    {
        $alterTableFile = CENTRORABACKUP_FOLDER . ODS . "alterTalbes.sql";
        $finalQuery = $this->installer->readSQLFile($alterTableFile);
        $this->db->setQuery($finalQuery);
        if (!$this->db->query()) {
            return false;
        }
    }

    private function restoreDB()
    {
        $this->files = $this->getDatabaseFileList();
        oseFirewall::loadInstaller();
        $this->installer = new oseFirewallInstaller ();
        $result = $this->restoreAllTables();
        if ($result == true) {
            // restore altertable SQL;
            return $this->restoreAlterTable();
        } else {
            return false;
        }
    }

    public function scheduleGitBackup()
    {
        oseFirewall::loadRequest();
        $key = oRequest::getVar('key', NULL);
        $step = oRequest::getVar('step', NULL);
        if (!empty($key)) {
            if ($step == 0) {
                $this->backupDB(true);
            } else {
                $this->writeSQL(true);
            }
        }
        exit;
    }

    //get list of the tables in the backupdb file
    public function getBackupDbtables()
    {
        $tables = array();
        $tableFile = OSE_FWDATA . ODS . "backup" . ODS . "dbtables.php";
        if (file_exists($tableFile)) {
            require($tableFile);
        }
        return $tables;
    }

    private function getCrawbackURL($key, $statusMsg = '')
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

    private function sendRequestGitBackup($url)
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

    public function gitCloudCheck()
    {
        $repoexists = $this->isRemoteRepoSet();
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

    protected function getRemoteRepoName()
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            return 'qatestrepo';
        } else {
            if(isset($_SERVER['DOMAIN']) && !empty($_SERVER['DOMAIN']))
            {
                return substr(str_replace(".", "-", $_SERVER['DOMAIN']), 0, 10) . '-' . rand(1000, 9999);
            }elseif(isset($_SERVER['HOSTNAME']) && !empty($_SERVER['HOSTNAME'])){
                return substr(str_replace(".", "-", $_SERVER['HOSTNAME']), 0, 10) . '-' . rand(1000, 9999);
            }
            else{
                return substr(str_replace(".", "-", $_SERVER['HTTP_HOST']), 0, 10) . '-' . rand(1000, 9999);
            }
        }

    }

    public function saveRemoteGit($username, $password)
    {

        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $this->removeremoterepo('qatestrepo');
            $this->moveKeys();
        } else {
            $this->removeremoterepo('origin');
            $this->deletePrivateKey();
            $this->deletePublicKey();
        }

        $reponame = $this->getRemoteRepoName();     //define the name of repo which will store all the backups
        $gitCmd = "git config --add cent.username " . $username;
        $this->runShellCommand($gitCmd);
        $gitCmd = "git config --add cent.reponame " . $reponame;
        $this->runShellCommand($gitCmd);
        $result = $this->createRemoteRepo($username, $password, $reponame);
        if ($result['status'] == 1) {   //repo created successfully
            $temp = $this->addRemoteRepo($username, $result['info']);
            if ($temp['status'] == 1)     //repo added successfully
            {
                $result['status'] = 1;
                $result['message'] = $temp['info'];
                return $result;

            } else {   //problems in adding the repo
                $result['status'] = 0;
                $result['message'] = $temp['info'];
                return $result;
            }

        } else { //problems in creating repo
            $result['status'] = 0;
            $result['message'] = $result['info'];
            return $result;
        }
    }

    protected function protectGit()
    {
        $htaccess = OSEFWDIR . 'protected' . ODS . '.htaccess';
        $dest = OSE_ABSPATH . ODS . '.git' . ODS . '.htaccess';
        if (!file_exists($dest)) {
            copy($htaccess, $dest);
        }
    }

    public function folderExits()
    {
        if (file_exists(CENTRORABACKUP_FOLDER)) {
            $htaccess = OSEFWDIR . 'protected' . ODS . '.htaccess';
            $dest = CENTRORABACKUP_FOLDER . ODS . '.htaccess';
            copy($htaccess, $dest);
            if (!file_exists(CENTRORABACKUP_FOLDER . ODS . 'gitbackup')) {
                mkdir(CENTRORABACKUP_FOLDER . ODS . 'gitbackup');
            }
            return true;
        } else {
            return false;
        }
    }

    public function createBackupFolder()
    {
        if (OSE_CMS == "wordpress") {
            mkdir(OSE_BACKUPPATH . ODS . 'CentroraBackup');
            mkdir(OSE_BACKUPPATH . ODS . 'CentroraBackup' . ODS . 'gitbackup');
            $htaccess = OSEFWDIR . 'protected' . ODS . '.htaccess';
            $dest = OSE_BACKUPPATH . ODS . 'CentroraBackup' . ODS . '.htaccess';
            copy($htaccess, $dest);
        } else {
            mkdir(OSE_ABSPATH . ODS . 'media' . ODS . 'CentroraBackup');
            mkdir(OSE_ABSPATH . ODS . 'media' . ODS . 'CentroraBackup' . ODS . 'gitbackup');
            $htaccess = OSEFWDIR . 'protected' . ODS . '.htaccess';
            $dest = OSE_ABSPATH . ODS . 'media' . ODS . 'CentroraBackup' . ODS . '.htaccess';
            copy($htaccess, $dest);
        }
    }

//    private function optimizePHP()
//    {
//        $this->setMaxExTime();
//        if (function_exists('ini_set')) {
//            ini_set('max_execution_time', $this->maxextime);
//            ini_set('memory_limit', '1024M');
//            ini_set("pcre.recursion_limit", "524");
//        }
//    }

    private function setMaxExTime()
    {
        if (empty($this->maxextime)) {
            $this->maxextime = 400;
        }
    }

    ///ALL THE SSH RELATED CODE BELOW
    //step 1 == generate a pair of public and private key
    public function genSshKeys()
    {
        $gitCmd = "ssh-keygen -f " . PRIVATEKEY_PATH . " -N ''";
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

    //for debugging only
    public function getPublicKey()
    {

        if (file_exists(PUBLICKEY_PATH)) {
            $content = file_get_contents(PUBLICKEY_PATH);
            return $content;
        }
    }

    public function getPrivateKey()
    {
        if (file_exists(PRIVATEKEY_PATH)) {
            $content = file_get_contents(PRIVATEKEY_PATH);
            return $content;
        }
    }

    public function publicKeyExists()
    {

        if (file_exists(PUBLICKEY_PATH)) {
            $content = true;
            return $content;
        }
    }

    public function privateKeyExists()
    {
        if (file_exists(PRIVATEKEY_PATH)) {
            $content = true;
            return $content;
        }
    }


    public function deletePublicKey()
    {

        if (file_exists(PUBLICKEY_PATH)) {
            unlink(PUBLICKEY_PATH);
        }
    }

    public function deletePrivateKey()
    {
        if (file_exists(PRIVATEKEY_PATH)) {
            unlink(PRIVATEKEY_PATH);
        }
    }

    //TODO :error with the host key checking
    //STEP 3 : load the ssh keys with the local ssh agent and set no for hostkeychecking
    public function loadsshkey($type = false)
    {
        if($type ==false)
        {
            $gitCmd = "ssh-agent bash -c 'ssh-add " . PRIVATEKEY_PATH . "; ssh -T -oStrictHostKeyChecking=no git@bitbucket.org'";

        }else {
            $gitCmd = "ssh-agent bash -c 'ssh-add " . PRIVATEKEY_PATH . "; ssh -T -oStrictHostKeyChecking=no git@gitlab.com'";

        }
        $output = $this->runShellCommand($gitCmd);
        if (strpos($output['stdout'], "Welcome to GitLab") !== false) {
            $return['status'] = 1;
            $return['info'] = $output['stdout'];
            return $return;
        } else {
            $return['status'] = 0;
            $return['info'] = "There was some problem in loading the SSH keys <br/>ERROR: <br/>" . $output['stderr'];
            return $return;
        }
    }

    //step 2: add the public key to the bitbucket account
    //add public key to the bitbucket repo
    public function addpublickeytobitbucket($username, $password)
    {
        $publickey = $this->getPublicKey();
        $temp = urlencode($publickey);
        $gitCmd = " curl POST -v -u $username:$password -H \"Content - Type: application / json\" https://api.bitbucket.org/1.0/users/{$username}/ssh-keys --data \"key=$temp\"";
        $output = $this->runShellCommand($gitCmd);
        $outputcontent = $output['stdout'];
        if ($outputcontent != null) {
            if (strpos($outputcontent, "pk") != false) {
                $return['status'] = 1;
                $return['info'] = "The Public key has been successfully added to the bitbucket account";
                return $return;
            }
            if (strpos($outputcontent, "already") != false) {
                $return['status'] = 0;
                $return['info'] = "Someone has already registered the public key on bitbucket account";
                return $return;
            }
        } else {
            $return['status'] = 2;
            $return['info'] = "The was some problem in copying the public key to the bitbucket account";
            return $return;  //exit to the controller
        }
    }


    public function addpublickeytogitLab($privatetoken)
    {
        $publickey = $this->getPublicKey();
//        $temp = urlencode($publickey);
        $gitCmd = "curl -X POST --header \"PRIVATE-TOKEN: $privatetoken\" -F \"title=centrora_security\" -F \"key=$publickey\" \"https://gitlab.com/api/v3/user/keys\"";
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


    //complete ssh mechanism that puts all the modules together
    //generate keys , sytart the bash  session and load the keys to the session
    //add the keys to the session and to the bitbucket account before pushing any code
    public function sshSetup($token,$username)
    {
        $temp = $this->genSshKeys();
        if ($temp['status'] == 0 || $temp['status'] == 1) {
            $temp1 = $this->addpublickeytogitLab($token);
            if ($temp1['status'] == 0 || $temp1['status'] == 1) {
                $temp2 = $this->loadsshkey("gitlab");
                if ($temp2['status'] == 1)    //if keys are loaded successfully
                {
                    $output['status'] = 1;
                    $output['info'] = $temp2['info'];
                    return $output;
                } else {  //problems in loading the keys
                    $output['status'] = 0;
                    $output['info'] = $temp2['info'];
                    return $output;
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

    public function getPushResult()
    {
        $gitCmd = null;
        if (isset($_REQUEST['qatest'])) {
            if ($_REQUEST['qatest'] == true) {
                if (TEST_ENV) {
                    $gitCmd = "ssh-agent bash -c ' chmod 0400 " . PRIVATEKEY_PATH . "; ssh-add " . PRIVATEKEY_PATH . "; git push --force qatestrepo 6.5.0'";
                } else {
                    $gitCmd = "ssh-agent bash -c ' chmod 0400 " . PRIVATEKEY_PATH . "; ssh-add " . PRIVATEKEY_PATH . "; git push --force qatestrepo master'";
                }
            }
        } else {
            if (TEST_ENV) {
                $gitCmd = "ssh-agent bash -c ' chmod 0400 " . PRIVATEKEY_PATH . "; ssh-add " . PRIVATEKEY_PATH . "; git push --force origin master'";

            } else {
                $gitCmd = "ssh-agent bash -c ' chmod 0400 " . PRIVATEKEY_PATH . "; ssh-add " . PRIVATEKEY_PATH . "; git push --force origin master'";
            }
        }
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //STEP 4: start ssh agent load the key and push the commits
    public function sshPushSetup()
    {
        $output = $this->getPushResult();
        $temp = $output['stderr'];
        //fatal =>wrong name for remote repo
        //error =>for wrong local branch name
        if (strpos($temp, "fatal") !== false || (strpos($temp, "error") !== false)) {
            $output['status'] = 0;
            $output['info'] = "There was a problem in uploading the backup to the GitLab account <br/>ERROR: <br/>" . $output['stderr'];  //returns the error
            return $output;
        } else {
            $output['status'] = 1;
            $output['info'] = $output['stderr']; //returns the success message
            return $output;
        }
    }

    public function finalGitPush()
    {
        $result = $this->stageAllChanges_finalpush();
        if ($result['status'] == 1) {
            $temp = $this->commitChanges_finalpush();
            if ($temp['status'] == 1) {
                $subscription_status = oseFirewallBase::checkSubscriptionStatus(false);
                if ($subscription_status) {
                    //push the changes to the repo for premium users
                    $result = $this->sshPushSetup();
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

//    DEBUGGIN ONLY :check if the ssh exists
    public function checksshexists()
    {
        $gitCmd = "ssh -v";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    // DEBUGGIN ONLY: to check if the ssh key has been linked
    public function checksshconnection()
    {
        $gitCmd = "ssh -T git@bitbucket.org";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //DEBUGGIN ONLY : retrive public key bitbucket
    public function getsshkeyfrombitbucket($username, $password)
    {
        $gitCmd = " curl GET -v -u $username:$password -H \"Content - Type: application / json\" https://api.bitbucket.org/1.0/users/{$username}/ssh-keys";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //TODO : does not work
    //DEBUGGIN ONLY : to delet public key from bitbucket account
    public function deletesshkeyfrombitbucket($username, $password, $keyid)
    {
        $gitCmd = " curl DELETE  https://api.bitbucket.org/1.0/users/{$username}/ssh-keys/{$keyid}";
        $output = $this->runShellCommand($gitCmd);
        return $output;
    }

    //another alternativ eto start the ssh agent
    public function startsshagent()
    {
        $gitCmd = "exec ssh-agent bash";
        $output = $this->runShellCommand($gitCmd);
//        print_r($output);
        return $output;
    }

    //add the key to the list of known_hosts
    //returns the username for bitbicket if logged in
    //debugging only
    public function addsshkeytosession()
    {
        $gitCmd = "ssh -T -oStrictHostKeyChecking=no git@bitbucket.org";
        $output = $this->runShellCommand($gitCmd);
        if (strpos($output['stdout'], "logged") !== false) {

            $temp = explode(" ", $output['stdout']);
            $temp1 = explode('.', $temp[3]);
            return $temp1[0];
        } else {
            return false;
        }
    }

    //DEBUGGING - to start the ssh agent
    public function evalagent()
    {
        $gitCmd = "eval `ssh-agent -s`";
        $output = $this->runShellCommand($gitCmd);
        if ($output['stdout'] != null) {
            return true;
        } else
            return false;
    }

    public function getCurrentHead()
    {
        $gitcmd = "git rev-parse HEAD";
        $output = $this->runShellCommand($gitcmd);
        return $output;
    }

    //generate the zip file stores it in the centrorabackup folder with the name Backup.zip
    public function generateZip()
    {
        $changedir = $this->changeDirectory(OSE_ABSPATH);
        $foldertobezipped = basename(OSE_ABSPATH);
        $gitcmd = $changedir . "; zip -r " . CENTRORABACKUP_ZIPFILE . " " . $foldertobezipped . " -x " . ODS . BACKUPFILES_EXCLUDEPATH . "\*";
        $output = $this->runShellCommand($gitcmd);

        if ($output['stderr'] == null && file_exists(CENTRORABACKUP_ZIPFILE)) {
            $result['status'] = 1;
            $result['message'] = "The zip file of the website has been generated";
            return $result;
        } else {

            $result['status'] = 0;
            $result['message'] = "There was some problem in generating the zip file: " . $output['stderr'];
            return $result;
        }
    }

    //return the command to chnage the directory to a specific pathname
    public function changeDirectory($pathname)
    {
        $parent = dirname($pathname);
        $gitcmd = "cd " . $parent;
        return $gitcmd;
    }

    //determines if there is a need to generate backup.zip based on the uncommitted changes
    public function websiteZipBackup()
    {
        if (file_exists(CENTRORABACKUP_ZIPFILE)) {
            //delete the file and replace it with a new backup
            unlink(CENTRORABACKUP_ZIPFILE);
        }
        $result = $this->generateZip();
        return $result;
    }

    //downloads the zip file
    public function downloadzip()
    {
        $file = CENTRORABACKUP_ZIPFILE;
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

    //make an entry in the .gitignore file
    public function gitIgnoreFile($filetoignore)
    {
        $filepath = OSE_ABSPATH . ODS . ".gitignore";
        if (file_exists($filepath)) {
            //to avoid duplicate entries for a file
            if (strpos(file_get_contents($filepath), $filetoignore) == false) {
                file_put_contents($filepath, PHP_EOL . $filetoignore . PHP_EOL, FILE_APPEND);
            }else {
                return true;
            }
        } else {
            //if file doesnt exist, create 1 and make an entry for the files
            file_put_contents($filepath, PHP_EOL . $filetoignore . PHP_EOL, FILE_APPEND);
        }
    }

    //remove a folder from gitignore file
    public function removeCentBackupFolderFromIgnore()
    {
        $filepath = OSE_ABSPATH . ODS . ".gitignore";
        if(file_exists($filepath))
        {
            $content= file_get_contents($filepath);
            //if the centrora backup folder is ignored , add it to git
            if (strpos($content, CENTRORABACKUP_FOLDER_GITIGNORE) !== false) {
                $newContent = str_replace(CENTRORABACKUP_FOLDER_GITIGNORE, '',$content);
                file_put_contents($filepath,$newContent);
            }
        }
    }



    //complete functionality to download the zip file

    public function zipDownloadCloudCheck()
    {
        $subscription = oseFirewallBase::checkSubscriptionStatus(false);
        if($subscription)
        {
            $remote_repo = $this->isRemoteRepoSet();
            $return['subscription'] = true;
            $return['repo'] = $remote_repo['status'];
        }else{
            $return['subscription'] = false;
            $return['repo'] = false;
        }
        return $return;
    }
    public function downloadZipBackup($choice)
    {
        //ignore the file before generating the zip
        if($choice ==2) {
            $filepath = OSE_ABSPATH.'/.git/config';
            $ini_array = parse_ini_file($filepath);
            $url = $ini_array['url'];
            $temp = str_replace('git@gitlab.com:','',$url);
            $final_url = "https://gitlab.com/".$temp;
            if(!empty($ini_array) && isset($ini_array['username']) && isset($ini_array['reponame']))
            {
                $result['status'] = 1;
                $result['usertype'] = 1;
                $result['url'] = "<a href = $final_url>".$final_url."</a>";
                $result['instructions'] = "Please Go to the Link :<a href = $final_url target=\"_blank\"> $final_url</a> <br/> and click on the icon <span class = 'glyphicon glyphicon-download-alt'> </span> to download";
                return $result;
            }else{
                $result['status'] = 0;
                $result['usertype'] = 1;
                $result['instructions'] = " <ol><li>Please go to the url <a href = 'https://gitlab.com/users/sign_in'>https://gitlab.com/ </a></li>
                                                <li>Sign in using the username and password </li>
                                                <li> Search for the most recently updated repo and enter the repo by clicking on the name</li>
                                                 <li>click on the icon <span class = 'glyphicon glyphicon-download-alt'> </span> to download</li></ol>";
                return $result;
            }
        }elseif($choice ==1){
            //free users
            $this->gitIgnoreFile(CENTRORABACKUP_ZIPBACKUP_GITIGNORE);
            $result = $this->websiteZipBackup();
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

    public function deleteZipBakcupFile()
    {
        if (file_exists(CENTRORABACKUP_ZIPFILE)) {
            unlink(CENTRORABACKUP_ZIPFILE);
            $result['status'] = 1;

        } else {
            //if no file exists report ERROR
            $result['status'] = 0;
        }
        return $result;
    }

    public function discardChanges()
    {
        $gitcmd = "git reset --hard";
        $output = $this->runShellCommand($gitcmd);
        if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || $output['stderr'] != null) {
            $result['status'] = 0;
            $result['info'] = "There was some problems in discarding the chnages <br/> ERROR:<br/>" . $output['stderr'];
        } else {
            $result['status'] = 1;
            $result['info'] = "Changes have been successfully reverted ";
        }
        return $result;
    }

    public function getZipUrl()
    {
        $result['url'] = ZIP_DOWNLOAD_URL;
        return $result;

    }

    public function viewChangeHistory($commitid)
    {

        if (oseFirewallBase::checkSubscriptionStatus(false)) {
//        format = date#filenames
            $gitcmd = "git show --pretty=format:\"%cd#\" --name-only " . $commitid;
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
        } else {
            //FREE USERS
            $result['status'] = 2;
            $result['date'] = "This feature is not available for free users";
            $result['files'] = "This feature is not available for free users";
            return $result;
        }
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

    public function getUserInfor()
    {
        oseFirewallBase::loadUsers();
        $result = oseUsers::getUserInfo();
        return $result;
    }

    //add username and email address in git config file
    public function addUserInfoGitConfig()
    {
        $userinfo = $this->getUserInfor();
        $gitcmd = "git config --local user.email " . $userinfo['email'] . " ; git config --local user.name " . $userinfo['name'] ." ; git config core.preloadIndex false ; git config --local pack.windowMemory '100m'; git config --local pack.packSizeLimit '100m'; git config --local pack.threads '1' ";
        $result = $this->runShellCommand($gitcmd);
        if ($result['stderr'] != null) {   //ERROR
            $output['status'] = 0;
            $output['info'] = "Problem in adding username and email in config file <br/>ERROR: <br/>" . $result['stderr'];
        } else {  //SUCCESS
            $output['status'] = 1;
            $output['info'] = "successfully added username and password in the config file";
        }
        return $output;
    }


    public function clearGitLogTable()
    {
        $query = "TRUNCATE TABLE `#__osefirewall_gitlog`;";
        $this->db->setQuery($query);
        $result = $this->db->query();
        return $result;
    }

    public function getCommitIDFromGitLog()
    {
        $gitcmd = "git log  --pretty=format:\"%h\"";
        $output = $this->runShellCommand($gitcmd);
        $commitids = preg_split('/\s+/', $output['stdout']);
        return $commitids;
    }

    public function chooseRandomCommitId()
    {
        $result = $this->getCommitIDFromGitLog();
        $temp ['key'] = array_rand($result);
        $temp ['value'] = $result[$temp ['key']];
        return $temp;
    }

    public function fileExists($pathname)
    {
        if (file_exists($pathname)) {
            return true;
        } else {
            return false;
        }
    }

    //gets the list of backedup tables from the dbtables.php file
    public function getListOfBackedUpTablesFromTheFileList()
    {
        $tables = $this->getBackupDbtables();
        $temp = $tables['backeduplist'];
        return $temp;
    }

    //check for a particular .sql files in the gitbackup folder
    public function checkSQLFileExists($filename)
    {
        $backeduptablefilepath = CENTRORABACKUP_FOLDER . ODS . 'gitbackup' . ODS . $filename . '.sql';
        if (file_exists($backeduptablefilepath)) {
            return true;
        } else {
            return false;
        }
    }


    public function deleterepoonBitbucket($username, $password)
    {
        $gitCmd = " curl  -v -u $username:$password -H \"Content - Type: application / json\" https://api.bitbucket.org/2.0/repositories/{$username}/qatestrepo -X DELETE";  //repositories/
        $output = $this->runShellCommand($gitCmd);
        $temp['stderr'] = json_decode($output['stderr'], true);
        $temp['stdout'] = json_decode($output['stdout'], true);
        return $output;   //TODO ERROR HANDLING FOR THE RESULT
    }

    //get the commit id of the gead from the git log
    public function getHeadFromGitLog()
    {
        $gitcmd = "git rev-parse HEAD ";
        $output = $this->runShellCommand($gitcmd);
        if (empty($output['stderr'])) {
            $result['status'] = 1;
            $commitid = substr($output['stdout'], 0, 7);
            $result['commitid'] = (string)$commitid;
        } else {
            $result['status'] = 0;
            $result['commitid'] = $output['stderr'];
        }
        return $result;
    }

    public function createtestDbEntry()
    {
        $varValues = array(
            'key' => 'qatest',
            'value' => 'true',
            'type' => 'qatest',
        );
        $query = $this->db->addData('insert', '#__ose_secConfig', '', '', $varValues);
        return $query;
    }

    public function deletetestDbEntry()
    {
        $db = oseFirewall::getDBO();
        $query = " DELETE FROM `#__ose_secConfig` WHERE `key` = \"qatest\"";
        $db->setQuery($query);
        $result = $db->loadResultList();
        if (count($result) == 0) {
            return true;
        } else {
            return false;
        }
    }


    public function testDbEntryExists()
    {
        $db = oseFirewall::getDBO();
        $query = "SELECT * FROM `#__ose_secConfig` WHERE `key` = \"qatest\"";
        $db->setQuery($query);
        $result = $db->loadResultList();
        if (count($result) == 0) {
            return false;
        } else {
            return true;
        }
    }

    public function getRemoteRepoUrl($name)
    {
        $gitcmd = "git remote get-url " . $name;
        $result = $this->runShellCommand($gitcmd);

        if ($result['stderr'] != null) {
            $temp['status'] = 0;
            $temp['info'] = $result['stderr'];

        } else {
            $temp['status'] = 1;
            $temp['info'] = $result['stdout'];
        }
        return $temp;
    }

    public function getRemoteRepoHead($branchname, $reponame)
    {
        $gitcmd = "git rev-parse --verify " . $reponame . "/" . $branchname;
        $result = $this->runShellCommand($gitcmd);
        if ($result['stderr'] != null) {
            $temp['status'] = 0;
            $temp['info'] = $result['stderr'];

        } else {
            $temp['status'] = 1;
            $temp['info'] = substr($result['stdout'], 0, 7);;
        }
        return $temp;
    }

    public function moveKeys()
    {
        $this->gitIgnoreFile(KEY_BACKUP_GITIGNORE);
        if (!is_dir(KEY_BACKUP)) {
            mkdir(KEY_BACKUP);
        }
        if (file_exists(PUBLICKEY_PATH) && file_exists(PRIVATEKEY_PATH)) {
            rename(PUBLICKEY_PATH, MOVE_PUBLICKEY_PATH);
            rename(PRIVATEKEY_PATH, MOVE_PRIVATEKEY_PATH);
        }
    }

    public function moveKeyAfterTest()
    {
        if (file_exists(MOVE_PUBLICKEY_PATH) && file_exists(MOVE_PRIVATEKEY_PATH)) {
            rename(MOVE_PUBLICKEY_PATH, PUBLICKEY_PATH);
            rename(MOVE_PRIVATEKEY_PATH, PRIVATEKEY_PATH);
        }
        if (is_dir(KEY_BACKUP)) {
            rmdir(KEY_BACKUP);
        }
    }

    public function biggitbackup()
    {
        if ($this->isinit()) {
            $webkey = $this->getWebKey();
            if (!empty($webkey)) {
                $url = API_SERVER . "gitbackup/bggitbackup?webkey=" . $webkey;
                $this->sendRequestGitBackup($url);
                $result = array('result' => true, 'msg' => 'gitbackup commenced at backend');
            } else {
                $result = array('result' => false, 'msg' => 'No webkey');
            }
        } else {
            $result = array('result' => false, 'msg' => 'Git is not initialized');
        }
        return $result;
    }

    public function synchGitLogDb()
    {
        $this->insertLoginDB();
    }


    public function uninstall_git($keeplog)
    {
        if (file_exists(OSE_ABSPATH . "/.git")) {
            $gitcmd = "cd " . OSE_ABSPATH . ";rm -rf .git";
            $output = $this->runShellCommand($gitcmd);
            if (strpos($output['stderr'], "fatal") !== false || (strpos($output['stderr'], "error") !== false) || $output['stderr'] != null) {
                $result['status'] = 0;
                $result['info'] = "There was some problems in Uninstalling Git <br/> ERROR:<br/>" . $output['stderr'];
                $result['cmd'] = $gitcmd;
                return $result;
            } else {
                if ($keeplog == 0) {
                    //drop the git log table
                    $tableExists = $this->db->isTableExists('#__osefirewall_gitlog');
                    if ($tableExists) {
                        $dropresult = $this->db->truncateTable('#__osefirewall_gitlog');
                        $result['status'] = 1;
                        $result['info'] = "Git has been successfully uninstalled";
                        return $result;
                    } else {
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


    public function saveRemoteGit_gitLab($token, $username)
    {
        if (isset($_REQUEST['qatest']) && $_REQUEST['qatest'] == true) {
            $this->removeremoterepo('qatestrepo');
            $this->moveKeys();
        } else {
            $this->removeremoterepo('origin');
            $this->deletePrivateKey();
            $this->deletePublicKey();
        }
        $reponame = $this->getRemoteRepoName();     //define the name of repo which will store all the backups
        $gitCmd = "git config --add cent.username " . $username;
        $this->runShellCommand($gitCmd);
        $gitCmd = "git config --add cent.reponame " . $reponame;
        $this->runShellCommand($gitCmd);
        $result = $this->createRemoteRepo_GitLab($token, $reponame);
        if ($result['status'] == 1) {   //repo created successfully
            $repo_url = $result['info'];
            $temp = $this->addRemoteRepo($repo_url);
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
            $array = $this->flatten($array);
            foreach ($array as $key => $value) {
                $result .= $key . "->" . $value . "<br/>";
            }
            return $result;
        }

    }

    public function flatten($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    public function ignoreLargeZipFiles()
    {
        $size = 1000; //in Mb
        $gitcmd = "cd " . OSE_ABSPATH . "; find . -size +" . $size . "M";
        $result = $this->runShellCommand($gitcmd);
        if ((!empty($result)) && isset($result['stdout']) && (!empty($result['stdout']))) {
            $large_fileList = $result['stdout'];
            $errorMsg = $result['stderr'];
            if (strpos($large_fileList, "command not found") !== false || (!empty($errorMsg) && strpos($errorMsg, "command not found") !== false)) {
                $this->backupLog("ignorezipfiles - $errorMsg");
                $this->logErrorBackup("ignorezipfiles - $errorMsg");
            } else {
                $formattedList = explode("./", $large_fileList);
                $formattedList = array_filter($formattedList);
                foreach ($formattedList as $file) {
                    if (!empty($file) && (strpos($file, "git/objects/") == false)) {
                        $filepath = OSE_ABSPATH . ODS . ".gitignore";
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


    public function dev_runsshCommands()
    {
        $gitcmd =  "find / -type s";
        $result = $this->runShellCommand($gitcmd);
        return $result;
    }


    public function checkWebsiteSize()
    {
        $cmd = "du -s ".OSE_ABSPATH;
        $gitsetup = new GitSetup();
        $output = $gitsetup->runShellCommand($cmd);
        if(empty($output['stderr']))
        {
            return oseFirewallBase::prepareErrorMessage("The disk usage commands cannot be executed , Please enable the Disk Usage (du) command");
        }else{
            $temp = explode('/',$output);
            $size = $temp[0];
            if($size>10380902)
            {
                return oseFirewallBase::prepareErrorMessage( "Website is greater than 10 Gb, The plugin does not support cloud backup for more than 10GB");
            }else{
                return oseFirewallBase::prepareSuccessMessage("Website Size is : $size");
            }
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



    //code to check if the cron job can be enabled for the users
    //condition:
    //1.pre requiste should be satisfied
    //2.remote repo should be set else enable cron job
    //3.the code has been pushed once into the repo
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


    public function checkisRepoBare()
    {
        $gitcmd = "git rev-parse --is-bare-repository ";
        $result = $this->runShellCommand($gitcmd);
        if($result['stdout'] === true)
        {
            return true;
        }else{
            return false;
        }
    }

    public function complGitBackupv6()
    {
        //check config and send email
        $settings = $this->getCronSettingsLocal(4);
        if(!empty($settings))
        {
            if($settings->recieveEmail == 1)
            {
                $this->sendGitBackupCompletionEMail();
                return oseFirewallBase::prepareSuccessMessage("Confirmation Email Sent");
            }

        }
    }

    public function sendGitBackupCompletionEMail()
    {
        oseFirewall::callLibClass('emails', 'emails');
        $emailManager = new oseFirewallemails ();
        $content = $this->getEmailContent();
        $subject = 'Git Backup Completed '. " on [" . $_SERVER['HTTP_HOST'] . "]";
        $emailManager->sendEMailV7($content,$subject);
    }


    private function getEmailContent()
    {
        $lastBackupTime =  $this->getLastBackupTime();
        $currentDomain = $_SERVER['HTTP_HOST'];
        $domain = preg_replace('/[:\/;*<>|?]/', '', $currentDomain);
        $status = "Your Webite has been backed up Successfully";
        $message = "<b>Git Backup</b> was completed with the following status: <br/><br/>";
        $message .= '<table border="1" cellpadding="10" cellspacing="1">
					<thead>	<tr><th>Domain</th><th>Status</th><th>Completion</th></tr></thead>
					<tbody><tr><td>' . $domain . '</td><td>' . $status . '</td><td>' . $lastBackupTime . ' (AEST)</td></tr></tbody></table>';
        $message .= "<br/><br/>";
        $message .= "Centrora Security protects all your websites from malware and other malicious code.<br/><br/>";
        $message .= "Kind regards<br/>";
        return $message;
    }



    public function saveCronSettings($encoded_settings,$type)
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $settingsExists = $this->cronSettingsExists($type);
        if($settingsExists)
        {
            //update
            $content = array(
                'value' =>$encoded_settings
            );
            $result = $this->db->addData('update', '#__osefirewall_cronsettings', 'type', $type, $content);
        }else{
            //insert
            $content = array(
                'value' => $encoded_settings,
                'type'=>$type
            );
            $result = $this->db->addData('insert', '#__osefirewall_cronsettings', '', '', $content);
        }
        return $result;
    }

    public function cronSettingsExists($type)
    {
        $query = "SELECT `value` FROM `#__osefirewall_cronsettings` WHERE `type`= ".$this->db->quoteValue($type);
        $this->db->setQuery($query);
        $result = $this->db->loadResultList();
        if(!empty($result))
        {
            return true;
        }else{
            return false;
        }
    }

    public function clearCronSettings($type)
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $exists = $this->cronSettingsExists($type);
        if($exists)
        {
            $query = "DELETE FROM `#__osefirewall_cronsettings` WHERE `type`= ".$this->db->quoteValue($type);
            $this->db->setQuery($query);
            $result = $this->db->loadResultList();
            if(!empty($result))
            {
                return true;
            }else{
                return false;
            }
        }else{
            return true;
        }
    }


    public function getCronSettingsLocal($type)
    {
        if(empty($this->db))
        {
            $this->db = oseFirewall::getDBO();
        }
        $query = "SELECT `value` FROM `#__osefirewall_cronsettings` WHERE `type`= ".$this->db->quoteValue($type);
        $this->db->setQuery($query);
        $result = $this->db->loadResult();
        $decoded = json_decode($result['value']);
        return $decoded;
    }

    public function preReqCheck()
    {
        oseFirewall::callLibClass('gitBackup', 'gitActivationPanel');
        $activationpanel = new gitActivationPanel();
        $flag = $activationpanel->checkSysteminfo();
        $isinit = $this->isinit();
        if($flag == false || $isinit['status'] == 2)
        {
            if($flag == false)
            {
                $req = $activationpanel->getUnSatisfiedRequirements();
            }else{
                $req = "Git has not been initialised";
            }
            return oseFirewallBase::prepareCustomMessage(0,"Following Pre-requisites are not satisfied : <br/>".$req."<br/> Please check the GitBackup Page for more details");
        }else{
            return oseFirewallBase::prepareSuccessMessage("Pre requisites are satisfied ");
        }
    }


    public function getErrorLog()
    {
        $errorLog = $this->getErrorLogContent();
        if($errorLog['status'] ==1)
        {
            $content = $this->formatErrorLogContents($errorLog['info']);
            return oseFirewallBase::prepareSuccessMessage($content);
        }else{
            return $errorLog;
        }
    }

    public function getErrorLogContent()
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

    public function formatErrorLogContents($logentry)
    {
        $message = '<table border="1" cellpadding="10" cellspacing="1">
					<thead>	<tr><th>Message</th><th>Date&Time</th></tr></thead>';
        foreach($logentry as $content)
        {
            $message.= '<tbody>';
            if(isset($content['message']) && isset($content['account']) && $content['datetime'])
            {
                $message .= '<tr><td>'.$content['message'].' </td><td> '.$content['datetime'].'</td></tr>';
            }
            $message.= '</tbod>';
        }
        $message.= '</table>';
        return $message;
    }

    public function toggleBackupLog($value)
    {
        if($value == 1)
        {
            if(!file_exists(O_ENABLE_GITBACKUP_LOG))
            {
                touch(O_ENABLE_GITBACKUP_LOG);
                chmod(O_ENABLE_GITBACKUP_LOG,0755);
            }
            return true;
        }else{
            unlink(O_ENABLE_GITBACKUP_LOG);
            return false;
        }
    }


    public function getFileNotification()
    {
        $result =array();
        $init = $this->isinit();
        if($init['status']!= 1)
        {
            return oseFirewallBase::prepareCustomMessage(3,'Not initialised');
        }
        $accountpath = OSE_ABSPATH;
        $local = $this->getCountLocalFilesToCommit($accountpath);
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

    public function getCountLocalFilesToCommit($accountpath)
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
        $cloudCheck = $this->isRemoteRepoSet();
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

}