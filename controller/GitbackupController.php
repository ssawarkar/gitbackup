<?php
namespace App\Controller;
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
if (!defined('OSE_FRAMEWORK') && !defined('OSEFWDIR') && !defined('_JEXEC'))
{
    die('Direct Access Not Allowed');
}

class GitbackupController extends \App\Base
{
    public function action_enableGitBackup()
    {
        $this->model->loadRequest();
        $result = $this->model->enableGitBackup();
        return $this->model->returnJSON($result);
    }
    public function action_createBackupAllFiles()
    {
        $this->model->loadRequest();
        $result = $this->model->createBackupAllFiles();
        return $this->model->returnJSON($result);
    }


    public function action_gitRollback()
    {
        $this->model->loadRequest();
        $commitHead = $this->model->getVar('commitHead',null);
        $recall = $this->model->getVar('recall',null);
        $result = $this->model->gitRollback($commitHead, $recall);
        return $this->model->returnJSON($result);
    }

    public function action_getGitStatus()
    {
        $this->model->loadRequest();
        $result = $this->model->getGitStatus();
        $this->model->returnJSON($result);
    }

    //return the chnages in the git and provides a detailed log of the changes
    public function action_getGitLog()
    {
        $this->model->loadRequest();
        $result= $this->model->getGitLog();
        return $this->model->returnJSON($result);
    }

    public function action_stageAllChange()
    {
        $this->model->loadRequest();
        $result= $this->model->stageAllChange();
        $this->model->returnJSON($result);
    }

    public function action_backupDB()
    {
        $this->model->loadRequest();
        $result= $this->model->backupDB();
        return $this->model->returnJSON($result);
    }

    public function action_contBackupDB()
    {
        $table = $this->model->getVar('table', null);
        $result = $this->model->contBackupDB();
        return $this->model->returnJSON($result);

    }

    public function action_gitCloudCheck()
    {
        $this->model->loadRequest();
        $result= $this->model->gitCloudCheck();
        return $this->model->returnJSON($result);
    }

    public function action_saveRemoteGit()
    {
        $this->model->loadRequest();
        $username = $this->model->getVar('username', null);
        $accesstoken = $this->model->getVar('accesstoken', null);
//        TODO CHECK FOR ALL THE CONDITIONS
        if(!empty($accesstoken) && !empty($username))
        {
            //use gitlab
            if (!filter_var($username, FILTER_VALIDATE_EMAIL) === false) {
                $return = \oseFirewallBase::prepareErrorMessage("Please insert username and not email address");
                return $this->model->returnJSON($return);
            }else{
                $result = $this->model->saveRemoteGit_gitLab($accesstoken,$username);
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

    public function action_gitCloudPush()
    {
        $this->model->loadRequest();
        $result= $this->model->gitCloudPush();
        return $this->model->returnJSON($result);
    }

    public function action_websiteZipBackup()
    {
        $this->model->loadRequest();
        $choice = $this->model->getVar('choice', null);
        $result = $this->model->websiteZipBackup($choice);

        return $this->model->returnJSON($result);
    }
    public function action_deleteZipBakcupFile()
    {
        $this->model->loadRequest();
        $result = $this->model->deleteZipBakcupFile();
        return $this->model->returnJSON($result);
    }

    public function action_findChanges()
    {
        $this->model->loadRequest();
        $result = $this->model->findChanges();
        return $this->model->returnJSON($result);
    }
    public function action_discardChanges()
    {
        $this->model->loadRequest();
        $result = $this->model->discardChanges();
        return $this->model->returnJSON($result);
    }
    public function action_downloadzip()
    {
        $this->model->loadRequest();
        $this->model->downloadzip();
    }
    public function action_getZipUrl()
    {
        $this->model->loadRequest();
        $result = $this->model->getZipUrl();
        return $this->model->returnJSON($result);
    }
    public function action_viewChangeHistory()
    {
        $this->model->loadRequest();
        $commitid = $this->model->getVar('commitid', null);
        $result= $this->model->viewChangeHistory($commitid);
        return $this->model->returnJSON($result);
    }

    public function action_userSubscription()
    {
        $this->model->loadRequest();
        $result['status']= $this->model->userSubscription();
        $this->model->returnJSON($result);
    }
    public function action_getLastBackupTime()
    {
        $this->model->loadRequest();
        $result= $this->model->getLastBackupTime();
        return $this->model->returnJSON($result);
    }

    public function action_setCommitMessage()
    {
        $this->model->loadRequest();
        $commmitmessage = $this->model->getVar('commitmessage', null);
        $result = $this->model->setCommitMessage($commmitmessage);
        return $this->model->returnJSON($result);
    }

    public function action_test() {
        $this->model->loadRequest();
        $result = $this->model->findChanges();
        return $this->model->returnJSON($result);
    }

    public function action_checksystemInfo()
    {
        $this->model->loadRequest();
        $result= $this->model->checksystemInfo();
        return $this->model->returnJSON($result);
    }
    public function action_chooseRandomCommitId()
    {
        $this->model->loadRequest();
        $result= $this->model->chooseRandomCommitId();
        return $this->model->returnJSON($result);
    }

//    public function action_localbackup()
//    {
//        $this->model->loadRequest();
//        $filetable = $this->model->getVar('filetable', null);
//        $result = $this->model->localbackup();
//        return $this->model->returnJSON($result);
//    }

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

    public function action_finalGitPush()
    {

        $this->model->loadRequest();
        $result= $this->model->finalGitPush();
        return $this->model->returnJSON($result);
    }

    public function action_uninstallgit()
    {
        $this->model->loadRequest();
        $keephistory = $this->model->getVar('keephistory',null);
        $result = $this->model->uninstallgit($keephistory);
        return $this->model->returnJSON($result);

    }

    public function action_initalisegit()
    {
        $this->model->loadRequest();
        $result = $this->model->initalisegit();
        return $this->model->returnJSON($result);

    }
    public function action_toggleBackupLog()
    {
        $this->model->loadRequest();
        $value = $this->model->getVar('value', null);
        $result = $this->model->toggleBackupLog($value);
        return $this->model->returnJSON($result);
    }

    public function action_getFileNotification()
    {
        $this->model->loadRequest();
        $result = $this->model->getFileNotification();
        return $this->model->returnJSON($result);
    }

    public function action_zipDownloadCloudCheck()
    {
        $this->model->loadRequest();
        $result = $this->model->zipDownloadCloudCheck();
        return $this->model->returnJSON($result);
    }





}
?>