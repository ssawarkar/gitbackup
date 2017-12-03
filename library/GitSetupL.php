<?php
/**
 * Created by PhpStorm.
 * User: suraj
 * Date: 29/04/2016
 * Time: 2:41 PM
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
oseFirewall::callLibClass('gitBackup', 'GitSetup');

class GitSetupL extends GitSetup{

    public function stageAllChanges($path)
    {
        //rest of the files indicate the remainaing file in the website directory except the folders
        if($path == "restoffiles")
        {
            $gitCmd = "git add --all";
        }
        else {
            $filepath = OSE_ABSPATH.ODS.$path;
            if (count(array_diff(glob("$filepath/*"), glob("$filepath/*", GLOB_ONLYDIR))) == 0) {
                $result = $this->addIndexFile($filepath);
                $this->backupLog("added index file in folder " . $filepath);
            }
            $gitCmd = "git add '" . OSE_ABSPATH . ODS . $path."'";
        }
        $this->backupLog("stage command is " . $gitCmd);
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR : some problem with stagging the file
            $result2['status'] = 0;
            $result2['info'] = "There was some problem in stagging the files of " . $path . "folder ERROR :" . $output['stderr'];
            $result2['cmd'] = $gitCmd;
            $this->backupLog($result2['info']." Command: ".$result2['cmd']);
            $this->logErrorBackup($result2['info']." Command:".$result2['cmd']);
            return oseFirewallBase::prepareCustomErrorMessage($result2['info'], "medium", $output['stderr']);
        } else {
            //SUCCESS : the changes were staged successfully
            $result1['status'] = 1;
            $result1['info'] = "The Changes were stagged successfully";
            $this->backupLog($result1['info']);
            return $result1;
        }
    }

    //returns the list of the folders in the website directory
    public function getFoldersList($path)      //path should not contain "/" at the end
    {
        $gitCmd = "cd " . $path . ODS . "; ls -d */";
        $output = $this->runShellCommand($gitCmd);
        if (!empty($output) && isset($output['stdout']) && !empty($output['stdout'])) {
            $list = explode("/", $output['stdout']);
            $list = $this->removeNextLineFromString($list);
            if (empty($list)) {
                $this->logErrorBackup("There was some problem in formatting the list of folders for path : $path");
                $this->backupLog("There was some problem in formatting the list of folders for path : $path");
                return oseFirewallBase::prepareCustomErrorMessage("There was some problem in formatting the list of folders for path :$path", "medium");
            }
            $newlist = array_filter($list);
            //reverse the list as the last element is popperd first
            $reversedList = array_reverse($newlist);
            return $reversedList;
        } else {
            $this->logErrorBackup("Cannot get the list of folders while local backup " . $output['stdout'] . " for path : $path");
            $this->backupLog("Cannot get the list of folders while local backup " . $output['stdout'] . " for path : $path");
            return oseFirewallBase::prepareCustomErrorMessage("Cannot get the list of folders while local backup for path : $path", "medium", $output['stdout']);
        }
    }

    public function removeNextLineFromString($array)
    {
        $result = array();
        foreach($array as $key=>$value)
        {
            $result[$key] =  trim(preg_replace('/\s\s+/', ' ', $value));
        }
        return $result;
    }

    //writes the list of folders in a temporary file named "folderlist"
    public function writeFolderList($list, $backedupfolers)
    {
        //$folderlist
        $content = "<?php\n" . '$folderslist = array("folderslist"=>' . var_export($list, true) . ', "backedupfolders" =>' . var_export($backedupfolers, true) . ");";
        $this->writeFile(FOLDER_LIST, $content);
        $this->backupLog("wrote the folder list in ".FOLDER_LIST);
    }

    //Deletes the folder list when all the folders are committed
    public function DeleteFolderListTable()
    {
        if(file_exists(FOLDER_LIST))
        {
            unlink(FOLDER_LIST);
        }
    }

    //return the list of folders from the temp file
    public function getFolderListFromFile()
    {
        $folderslist= array();
        if (file_exists(FOLDER_LIST)) {
            require(FOLDER_LIST);
        }
        return $folderslist;

    }
    protected function addIndexFile ($filepath) {
        $result =touch($filepath.ODS.'.gitkeep');
        return $result;
    }

    //commit the changes
    public function commitChanges($type = false, $foldername)
    {
        $gitsetup = $this->loadgitLibrabry();
        if ($foldername == "restoffiles") {
            $commitMessagePrefix = $gitsetup->getCommitMessages($type, $foldername);
            $gitCmd = "git commit -m \"$commitMessagePrefix\"";
        } else {
            $filepath = OSE_ABSPATH . ODS . $foldername;
            //add index file if the folder is empty

            if (count(array_diff(glob("$filepath/*"), glob("$filepath/*", GLOB_ONLYDIR))) == 0) {
                $result = $this->addIndexFile($filepath);
                $this->backupLog("added index file in folder " . $filepath);
            }
            $commitMessagePrefix = $gitsetup->getCommitMessages($type, $foldername);
            $gitCmd = "git commit -m \"$commitMessagePrefix\" "."'$filepath'";
        }
        $this->backupLog("commit command is : " . $gitCmd);
        $output = $this->runShellCommand($gitCmd);
        if ((strpos($output['stderr'], 'fatal') !== false) || (strpos($output['stderr'], 'error') !== false)) {
            //ERROR :problems in committing the changes
            $result1['status'] = 0;
            $result1['info'] = "There was some problem in committing the local changes for the folder " . $foldername . "ERROR :" . $output['stderr'];
            $result1['cmd'] = $gitCmd;
            $this->backupLog($result1['info']." Command:".$result1['cmd']);
            $this->logErrorBackup($result1['info']." Command:".$result1['cmd']);
            return oseFirewallBase::prepareCustomErrorMessage($result1['info'], "medium", $result1['cmd']);
        } else {
            $this->insertNewCommitDb();
            //SUCCESS : No problems in committing the changes
            $result1['status'] = 1;
            $result1['info'] = "The changes were committed successfully " . $commitMessagePrefix;
            $this->backupLog($result1['info']);
            return $result1;
        }

    }

    public function localBackup($type = false)
    {
        $this->backupLog("starting local backup ");
        $temp = $this->findChanges();
        if ($temp['status'] == 1) {   //prepare and write the folder list into the file
            $pre_req_result = $this->prerequisitesforcommit();
            if (!empty($pre_req_result) && isset($pre_req_result['status']) && $pre_req_result['status'] == 0) {
                //pre -requisite errors :
                //errro in getting db config
                //error in getting the folder list
                return $pre_req_result;
            }
            if (file_exists(FOLDER_LIST)) {
                //if list of folders was created successfully
                $this->ignoreLargeZipFiles();
                $result['status'] = 1; //SUCCESS
                return $result;
            } else { //if file was not created
                return oseFirewallBase::prepareCustomErrorMessage("The folder list was not created ", "medium");
//                $result['status'] = 0; //ERROR
//                return $result;
            }
        } else if ($temp['status'] == 2) {
            // if there are no changes, there is no need to commit
            $result['status'] = 2;  //STOP THE BACKUP NO NEED TO COMMIT
            $result['info'] = "There are no New Changes to Commit ";
            return $result;
        } else {
            //error
            return $temp;
        }
    }

    public function contLocalBackup($type = false)
    {
        $this->backupLog("continuing the local backup ");
        $gitsetup = $this->loadgitLibrabry();
        $temp = $gitsetup->findChanges();
        if (empty($temp) || ((!empty($temp) && isset($temp['status']) && $temp['status'] == 0))) {
            //error in finding chnages or formatting them
            if(empty($temp))
            {
                return oseFirewallBase::prepareErrorMessage("There was some problem in running the command git status ");
            }
            return $temp;
        } else {
            if ($temp['status'] == 1) {
                $result = $this->createLocalBackup($type);
                return $result;
            } else if ($temp['status'] == 2) {
                // if there are no changes, there is no need to commit
                $result['status'] = 2;
                $result['info'] = "The backup is up to date";
                if (file_exists(FOLDER_LIST)) {
                    unlink(FOLDER_LIST);
                }
                $this->runGitGarbageCleaner();
                $this->backupLog("No new changes exists");
                return $result;
            }
        }
    }

    public function runGitGarbageCleaner()
    {
        //TODO INCREASE THE TIME
        $gitcmd = "cd ".OSE_ABSPATH."; git gc --quiet";
        $output = $this->runShellCommand($gitcmd);
        return $output;
    }


    //things to do before perfroming git init for large websitess
    public function prerequisitesforcommit()
    {
        $this->protectGit();
        $list = $this->getFoldersList(OSE_ABSPATH);
        if ((!empty($list)) && (isset($list['status']) && $list['status'] == 0)) {
            //error in getting folder list and formatting the conents
            return $list;
        }
        if (file_exists(FOLDER_LIST)) //delete an already existing folder list if the previous git backup failed
        {
            $this->backupLog("deleted old folder list in ".FOLDER_LIST);
            unlink(FOLDER_LIST);
        }
        if(OSE_CMS == "wordpress")
        {
            $new_list = $this->uploadPriotity($list);
            $folderList_ignorePath = 'wp-content' . ODS . 'CentroraBackup' . ODS . 'folderlist.php';
            $this->gitIgnoreFile($folderList_ignorePath);
            $this->writeFolderList($new_list,array());
        }
        else
        {
            $folderList_ignorePath = 'media' . ODS . 'CentroraBackup' . ODS . 'folderlist.php';
            $this->gitIgnoreFile($folderList_ignorePath);
            $this->writeFolderList($list,array());
        }
    }

    //Complete mechanism to stage and commit all the changes
    public function createLocalBackup($type = false)
    {
        $result = null;
        $listfromfile = $this->getFolderListFromFile();
        if (empty($listfromfile) || (!isset($listfromfile['folderslist'])) || !isset($listfromfile['folderslist'])) {
            $this->logErrorBackup("The folder list is not in proper format ");
            $this->backupLog("The folder list is not in proper format ");
            return oseFirewallBase::prepareErrorMessage("The folder list is not im proper format ");
        }
        $this->backupLog("The folder list is in correct format ");
        $currentfolder = array_pop($listfromfile['folderslist']);
        $this->backupLog("current folder which will be backed up is : $currentfolder");
        array_push($listfromfile['backedupfolders'], $currentfolder);
        if (!empty($currentfolder)) {
            $result = $this->folderLocalBackup($currentfolder, $type);
            //if local backup for folders was successful
            if ($result['status'] == 1) {   //SUCCESS: folder was backed up successfully
                $this->writeFolderList($listfromfile['folderslist'], $listfromfile['backedupfolders']);  //update the folderslist
                return $result;
            } else {
                // return ERROR and do not update the folderslist
                return $result;
            }

        } else {
            $result = $this->restofFilesLocalBackup($type);
            return $result;
        }

    }

    //remove the spaces form the folder name
    public function removeSpaces($foldername)
    {
        $pattern = "/\s/";
        if(preg_match($pattern,$foldername))
        {
            $string = str_replace(' ', "\\ ", $foldername);
            $result = $string."\\ /";
            return $result;
        }else {
            return $foldername;
        }
    }

    /*function to backup folders; stages the folders and then commits them
     *
     */
    public function folderLocalBackup($currentfolder, $type)
    {
        $result1 = $this->stageAllChanges($currentfolder);
        if ($result1['status'] == 1) {
            $result = $this->commitChanges($type, $currentfolder);
            if ($result['status'] == 1) {
                $return = array("status" => 1, "type" => $type, "folder" => $currentfolder,"info"=> "folder $currentfolder has been backed up successfully");
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
    public function restofFilesLocalBackup($type)
    {
        //for rest of the files except the folders
        $this->backupLog("backing up rest of the files ");
        $currentfolder = "restoffiles";
        $result = $this->stageAllChanges($currentfolder);
        if ($result['status'] == 1) {
            $result = $this->commitChanges($type, $currentfolder);
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

    public function loadgitLibrabry($remote = false)
    {
        oseFirewallBase::callLibClass('gitBackup','GitSetup');
        $gitsetup = new GitSetup(false,true);
        return $gitsetup;
    }

    public static function aJaxReturns ($result, $status, $msg, $continue=false, $id = null) {
        $return = array (
            'success' => (boolean)$result,
            'status' => $status,
            'result' => $msg,
            'cont' => (boolean)$continue,
            'id' => (int)$id
        );
        $tmp = oseJSON::encode ($return);
        return $tmp;
    }

    public function uploadPriotity($list)
    {
        //add the upload folder and content folder to the end of the list
        //so that they will be backed up first
        $foldername = "wp-content";
        $uploadfolder = "wp-content" . ODS . 'uploads';
        $position = array_search($foldername, $list);
        if ($position !== false) {
            array_splice($list, $position, 1);
//            print_r($list); exit;
            array_push($list, $foldername, $uploadfolder);
            return $list;
        }
        else
        {
            return $list;
        }
    }




}
