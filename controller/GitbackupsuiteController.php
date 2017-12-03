<?php
/**
 * Created by PhpStorm.
 * User: suraj
 * Date: 15/11/16
 * Time: 9:14 AM
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
namespace App\Controller;
if (!defined('OSE_FRAMEWORK') && !defined('OSEFWDIR') && !defined('_JEXEC'))
{
    die('Direct Access Not Allowed');
}
require('GitbackupController.php');
class GitbackupsuiteController extends GitbackupController
{
    public function action_canRetrieveAccounts()
    {
        $this->model->loadRequest();
        $result = $this->model->canRetrieveAccounts();
        return $this->model->returnJSON($result);
    }
    public function action_getAccountTable()
    {
        $this->model->loadRequest();
        $result = $this->model->getAccountTable();
        return $this->model->returnJSON($result);
    }

    public function action_addDBConfig()
    {
        $this->model->loadRequest();
        $data['DB_NAME'] = $this->model->getVar('dbname', null);
        $data['DB_USER'] = $this->model->getVar('dbuser', null);
        $data['DB_PASSWORD'] = $this->model->getVar('dbpassword', null);
        $data['DB_HOST'] = $this->model->getVar('host', null);
        $data['TABLE_PREFIX'] = $this->model->getVar('table_prefix', null);
        $accountname = $this->model->getVar('accountname', null);
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->addDBConfig($data, $accountname, $accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_checkDBConfigExists()
    {
        $this->model->loadRequest();
        $accountname = $this->model->getVar('accountname', null);
        $result = $this->model->checkDBConfigExists($accountname);
        return $this->model->returnJSON($result);
    }

    public function action_getWebSiteInfo()
    {
        $this->model->loadRequest();
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->getWebSiteInfo($accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_checkDBConnection()
    {
        $this->model->loadRequest();
        $dbconfig = $this->model->getVar('dbconfig', null);
        $result = $this->model->checkDBConnection($dbconfig);
        return $this->model->returnJSON($result);
    }

    //add config variables found in the config files
    public function action_addDBConfigFileContent()
    {
        $this->model->loadRequest();
        $dbconfig = $this->model->getVar('dbconfig', null);
        $accountname = $this->model->getVar('accountname', null);
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->addDBConfig($dbconfig, $accountname, $accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_getGitLog()
    {
        $this->model->loadRequest();
        $accountname = $this->model->getVar('accountname', null);
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->getGitLog($accountname,$accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_changetoAccountDir()
    {
        $this->model->loadRequest();
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->changetoAccountDir($accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_isinit()
    {
        $this->model->loadRequest();
        $accountPath = $this->model->getVar('accountpath', null);
        $result = $this->model->isinit($accountPath);
        return $this->model->returnJSON($result);
    }
    public function action_backupDB()
    {
        $this->model->loadRequest();
        $accountname = $this->model->getVar('accountname', null);
        $accountPath = $this->model->getVar('accountpath', null);
        $result= $this->model->backupDB($accountname,$accountPath);
        return $this->model->returnJSON($result);
    }

    public function action_contBackupDB()
    {
        $result = $this->model->contBackupDB();
        return $this->model->returnJSON($result);
    }

    public function action_localBackup()
    {
        $this->model->loadRequest();
        $type = $this->model->getVar('type', null);
        $result= $this->model->localBackup($type);
        return $this->model->returnJSON($result);
    }
    public function action_contLocalBackup()
    {
        $this->model->loadRequest();
        $type = $this->model->getVar('type', null);
        $result= $this->model->contLocalBackup($type);
        return $this->model->returnJSON($result);
    }

    public function action_viewChangeHistory()
    {
        $this->model->loadRequest();
        $commitid = $this->model->getVar('commitid', null);
        $accountpath = $this->model->getVar('accountpath', null);
        $result= $this->model->viewChangeHistory($commitid,$accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_gitCloudCheck()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result= $this->model->gitCloudCheck($accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_gitCloudPush()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result= $this->model->gitCloudPush($accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_saveRemoteGit()
    {
        $this->model->loadRequest();
        $username = $this->model->getVar('username', null);
        $accountpath = $this->model->getVar('accountpath', null);
        $accesstoken = $this->model->getVar('accesstoken', null);
//        TODO CHECK FOR ALL THE CONDITIONS
        if(!empty($accesstoken) && !empty($username))
        {
            //use gitlab
            if (!filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
                $return = \oseFirewallBase::prepareErrorMessage("Please insert username and not email address");
                return $this->model->returnJSON($return);
            }else{
                $result = $this->model->saveRemoteGit_gitLab($accountpath,$accesstoken,$username);
                return $this->model->returnJSON($result);
            }
        }else {
            if (empty($username)) {
                $return = \oseFirewallBase::prepareErrorMessage("Username cannot be blank ");
                return $this->model->returnJSON($return);
            } else {
                $return = \oseFirewallBase::prepareErrorMessage("Please enter the Access Token");
                return $this->model->returnJSON($return);
            }
        }
    }

    public function action_findChanges()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result = $this->model->findChanges($accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_finalGitPush()
    {

        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result= $this->model->finalGitPush($accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_websiteZipBackup()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $choice = $this->model->getVar('choice', null);
        $result = $this->model->websiteZipBackup($accountpath,$choice);
        return $this->model->returnJSON($result);
    }
    public function action_getZipUrl()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result = $this->model->getZipUrl($accountpath);
        return $this->model->returnJSON($result);
    }
    public function action_downloadzip()
    {
        $this->model->loadRequest();
        $this->model->downloadzip();
    }
    public function action_deleteZipBakcupFile()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result = $this->model->deleteZipBakcupFile();
        return $this->model->returnJSON($result);
    }
    public function action_discardChanges()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result = $this->model->discardChanges($accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_getLastBackupTime()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $result= $this->model->getLastBackupTime();
        return $this->model->returnJSON($result);
    }

    public function action_gitRollback()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $commitHead = $this->model->getVar('commitHead');
        $recall = $this->model->getVar('recall');
        $result = $this->model->gitRollback($commitHead, $recall,$accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_uninstallgit()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $accountname = $this->model->getVar('accountname');
        $keephistory = $this->model->getVar('keephistory');
        $result = $this->model->uninstallgit($accountname,$accountpath,$keephistory);
        return $this->model->returnJSON($result);

    }

    public function action_initalisegit()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $accountname = $this->model->getVar('accountname');
        $result = $this->model->initalisegit($accountname,$accountpath);
        return $this->model->returnJSON($result);

    }
    public function action_backupAccountsQueue()
    {
        $this->model->loadRequest();
        $path_array = $this->model->getVar('list', null);
        $list = $this->model->JSON_decode($path_array);
        if(empty($list))
        {
            $result =  \oseFirewallBase::prepareErrorMessage("Please select at least one account");
        }else {
            $result = $this->model->backupAccountsQueue($list);
        }
        return $this->model->returnJSON($result);

    }

    public function action_contBackupQueue()
    {
        $this->model->loadRequest();
        $result = $this->model->contBackupQueue();
        return $this->model->returnJSON($result);
    }

    public function action_backupQueueCompleted()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $accountname = $this->model->getVar('accountname');
        $result = $this->model->backupQueueCompleted($accountname,$accountpath);
        return $this->model->returnJSON($result);
    }
    public function action_getPrerequisites()
    {
        $this->model->loadRequest();
        $result = $this->model->getPrerequisites();
        return $this->model->returnJSON($result);
    }
    public function action_showErrorLog()
    {
        $this->model->loadRequest();
        $result = $this->model->showErrorLog();
        return $this->model->returnJSON($result);
    }

    public function action_getFileNotification()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $accountname = $this->model->getVar('accountname');
        $result = $this->model->getFileNotification($accountname,$accountpath);
        return $this->model->returnJSON($result);
    }

    public function action_toggleBackupLog()
    {
        $this->model->loadRequest();
        $value = $this->model->getVar('value', null);
        $result = $this->model->toggleBackupLog($value);
        return $this->model->returnJSON($result);
    }

    public function action_manageQueues()
    {
        $this->model->loadRequest();
        $result = $this->model->manageQueues();
        return $this->model->returnJSON($result);
    }
    public function action_zipDownloadCloudCheck()
    {
        $this->model->loadRequest();
        $accountpath = $this->model->getVar('accountpath', null);
        $accountname = $this->model->getVar('accountname');
        $result = $this->model->zipDownloadCloudCheck($accountname,$accountpath);
        return $this->model->returnJSON($result);
    }









}