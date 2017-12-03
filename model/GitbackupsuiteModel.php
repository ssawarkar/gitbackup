<?php
/**
 * Created by PhpStorm.
 * User: suraj
 * Date: 15/11/16
 * Time: 9:17 AM
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
if (! defined ( 'OSE_FRAMEWORK' ) && ! defined ( 'OSEFWDIR' ) && ! defined ( '_JEXEC' )) {
    die ( 'Direct Access Not Allowed' );
}
require_once ('GitbackupModel.php');
class GitbackupsuiteModel extends GitBackupModel {
    public  $gitbackupsuit;
    public $accountname ;
    public $accountpath;
    public function __construct() {
        parent::__construct();
        $this->loadLibrary ();
    }
    protected function loadLibrary () {
        oseFirewall::callLibClass('gitBackup', 'GitSetup');
        oseFirewall::callLibClass('gitBackup', 'GitSetupsuite');
        oseFirewall::callLibClass('panel','panel');
        $this->qatest = oRequest :: getInt('qatest', false);
        $this->accountname = oRequest :: getVar('accountname', false);
        $this->accountpath = oRequest :: getVar('accountpath', false);
        $this->gitbackup = new GitSetup($this->qatest);
        if(empty($this->accountname) && empty($this->accountpathc))
        {
            $inital_setp = false;
            $this->gitbackupsuite = new GitSetupsuite($this->qatest,$this->accountname, $this->accountpath,$inital_setp);
        }else {
            $this->gitbackupsuite = new GitSetupsuite($this->qatest,$this->accountname, $this->accountpath,true);
        }
    }
    public function loadLocalScript() {
        $this->loadAllAssets ();
        oseFirewall::loadCSSFile('CentroraFeatureCSS', 'featuretable.css', false);
        oseFirewall::loadJSFile ('CentroraDashboard', 'gitbackupsuite.js', false);
    }
    public function canRetrieveAccounts()
    {
        $result = $this->gitbackupsuite->getAccountsList();
        return $result;
    }
    public function getAccountTable()
    {
        $result = $this->gitbackupsuite->getAccountListTable();
        $result['draw'] = $this->getInt('draw');
        return $result;
    }
    public function addDBConfig($data,$accountname,$accountPath)
    {
        $result = $this->gitbackupsuite->addDataBaseConfig($data,$accountname,$accountPath);
        return $result;
    }
    public function checkDBConfigExists($accountname)
    {
        $this->gitbackupsuit1 = new GitSetupsuite($this->qatest,$this->accountname, $this->accountpath,false);
        $result = $this->gitbackupsuit1->checkifDbConfigExists($accountname);
        return $result;
    }
    public function getWebSiteInfo($accountpath)
    {
        $result = $this->gitbackupsuite->getWebsiteInfo($accountpath);
        return $result;
    }
    public function checkDBConnection($dbconfig)
    {
        $result = $this->gitbackupsuite->testDbConnection($dbconfig);
        return $result;
    }

    public function getGitLog($accountname,$accountpath)
    {
        $result =  $this->gitbackupsuite->getGitLogfromDB_suite($accountname,$accountpath);
        $result['draw'] = $this->getInt('draw');
        return $result;
    }
    public function changetoAccountDir($accountpath)
    {
        $result =  $this->gitbackupsuite->changetoAccountDir($accountpath);
        return $result;
    }
    public function isinit($accountpath) {
        $is_init = $this->gitbackupsuite->isinit_suite($accountpath);
        return $is_init;
    }
    public function backupDB($accountname,$accountPath)
    {
        $result = $this->gitbackupsuite->backupDBs_suite($accountname,$accountPath);
        return $result;
    }

    public function localBackup($type)
    {
        $result = $this->gitbackupsuite->localBackup_suite($type);
        return $result;
    }

    public function contLocalBackup($type)
    {
        $result = $this->gitbackupsuite->contLocalBackup_suite($type);
        return $result;
    }

    public function viewChangeHistory($commitid,$accountpath)
    {
        $result = $this->gitbackupsuite->viewChangeHistory_suite($commitid,$accountpath);
        return $result;
    }

    public function gitCloudCheck($accountpath)
    {
        $result = $this->gitbackupsuite->gitCloudCheck_suite($accountpath);
        return $result;
    }

    public function gitCloudPush($accountpath)
    {
        $result = $this->gitbackupsuite->cloudBackup_suite($accountpath);
        return $result;
    }

    public function saveRemoteGit_gitLab($accountpath,$token,$username)
    {
        $result0 = $this->gitbackupsuite->saveRemoteGit_gitLab_suite($accountpath,$token,$username);
        if($result0['status'] == 1) {
            $result1 = $this->gitbackupsuite->sshSetup_suite($accountpath,$token,$username);
            if($result1['status'] == 1)
            {
                return oseFirewallBase::prepareSuccessMessage("Repo has been created and ssh key has been added ");
            }else{
                $result['status'] = 0;
                $result['info'] = "<b>Repo creation status : </b><br/> Repo has been generated successfully". "<br/> <b>Error in SSH Keys generation :</b> <br/>".$result1['info'];
                return $result;
            }
        }
        else {
            $result['status'] = 0;
            $result['info'] = "<b>Error in repo creation :</b> <br/>".$result0['info']. "<br/><b> Error in SSH Keys generation :</b> <br/>The SSH keys has not been generated";
            return $result;
        }
    }


    public function findChanges($accountpath)
    {
        $result = $this->gitbackupsuite->findChanges_suite($accountpath);
        return $result;
    }

    public function websiteZipBackup($accountpath,$choice)
    {
        $result = $this->gitbackupsuite->downloadZipBackup_suite($accountpath,$choice);
        return $result;
    }

    public function getZipUrl($accountpath)
    {
        $result = $this->gitbackupsuite->getZipUrl_suite($accountpath);
        return $result;
    }
    public function downloadzip()
    {
        $this->gitbackupsuite->downloadzip();
    }
    public function deleteZipBakcupFile()
    {
        $result = $this->gitbackupsuite->deleteZipBakcupFile();
        return $result;
    }
    public function discardChanges($accountpath)
    {
        $result = $this->gitbackupsuite->discardChanges_suite($accountpath);
        return $result;
    }
    public function finalGitPush($accountpath)
    {
        $result = $this->gitbackupsuite->finalGitPush_suite($accountpath);
        return $result;
    }
    public function getLastBackupTime()
    {
        $result['commitTime'] = $this->gitbackupsuite->getLastBackupTime();
        return $result;
    }
    public function gitRollback($commitHead, $recall,$accountpath)
    {

        $result = $this->gitbackupsuite->gitRollback_suite($commitHead, $recall,$accountpath);
        return $result;
    }
    public function uninstallgit($accountname,$accountpath,$keeplog)
    {
        $result = $this->gitbackupsuite->uninstall_git_suite($accountname,$accountpath,$keeplog);
        return $result;
    }
    public function initalisegit($accountname,$accountpath)
    {
        $result = $this->gitbackupsuite->initalisegit_suite($accountname,$accountpath);
        return $result;
    }
    public function backupAccountsQueue($list)
    {
        $result = $this->gitbackupsuite->backupAccountsQueue($list);
        return $result;
    }
    public function contBackupQueue()
    {
        $result = $this->gitbackupsuite->contBackupQueue();
        return $result;
    }
    public function backupQueueCompleted($accountname,$accountpath)
    {
        $result = $this->gitbackupsuite->isbackupQueueCompleted($accountname,$accountpath);
        return $result;
    }


    public function getPrerequisites()
    {
        $result = $this->gitbackupsuite->checkGitBackupPreRequisite();
        return $result;
    }

    public function showErrorLog()
    {
        $result = $this->gitbackupsuite->getErrorLog_suite();
        return $result;
    }

    public function getFileNotification($accountname,$accountpath)
    {
        $result = $this->gitbackupsuite->getFileNotification_suite($accountname,$accountpath);
        return $result;
    }

    public function toggleBackupLog($value)
    {
        $result = $this->gitbackupsuite->toggleBackupLog_suite($value);
        return $result;
    }

    public function manageQueues()
    {
        $result = $this->gitbackupsuite->manageQueues();
        return $result;

    }
    public function zipDownloadCloudCheck($accountname,$accountpath)
    {
        $result = $this->gitbackupsuite->zipDownloadCloudCheck_suite($accountname,$accountpath);
        return $result;
    }


}

