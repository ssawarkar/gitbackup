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
require_once (dirname(__FILE__)."/Process.php");
oseFirewall::callLibClass('gitBackup', 'GitSetup');

class gitActivationPanel
{
    public static $systemInfo ;

    public function __construct()
    {
        $this->db = oseFirewall::getDBO();

    }

    public function checkProcOpen()
    {
        if( function_exists("proc_open"))
        {
            self::$systemInfo[10]['status'] = true;
            self::$systemInfo[10]['info'] = "Proc open is enabled";
            return true;
        }
        else {
            self::$systemInfo[10]['status'] = false;
            self::$systemInfo[10]['info'] = "Proc open is disabled";
            return false;
        }
    }
    //check git installeed
    public function getGitInstalled()
    {
        $gitsetup = new GitSetup();
        $gitCmd = "which git";
        $output = $gitsetup->runShellCommandWithStandardOutput($gitCmd);
        $output = (string)$output;
        if($output!= null) {
            self::$systemInfo[0]['status'] = true;
            self::$systemInfo[0]['info'] = "Git location :".$output;
            return true;
        }else
            self::$systemInfo[0]['status'] = false;
        self::$systemInfo[0]['info'] = "Git is not installed, simply contact your hosting company to install Git, it takes them 10 seconds to install it. Here are the instructions: <br/><br/> <b>For CENTOS:</b> sudo yum install git <br/><br/> <b>For Ubuntu:</b> sudo apt-get install git";
        return false;
    }

    //git version
    public function getGitVersion()
    {
        $gitsetup = new GitSetup();
        $gitCmd = "git --version";
        $output = $gitsetup->runShellCommandWithStandardOutput($gitCmd);
        $output = (string)$output;
        if($output!= null) {
            self::$systemInfo[0]['status'] = true;
            self::$systemInfo[0]['info'] = "Git version :".$output;
            return true;
        }else
            self::$systemInfo[0]['status'] = false;
        self::$systemInfo[0]['info'] = "Git is not installed, simply contact your hosting company to install Git, it takes them 10 seconds to install it. ";
        return false;
    }

//get php version
    public function getPhpVersion()
    {
        self::$systemInfo[1]['status'] = true;
        self::$systemInfo[1]['info'] = "PHP ".phpversion();
        return phpversion();
    }

//to check if external commands can be executed
//PHP function `proc_open()` must be enabled as gitbackup uses it to execute Git commands. Please update your php.ini.'

    public function tryRunProcess()
    {
        try {
            $process = new Process("echo test");
            $process->run();
            self::$systemInfo[2]['status'] = true;
            self::$systemInfo[2]['info'] ="Possible to execute external commands ";
            return true;
        } catch (Exception $e) {
            self::$systemInfo[2]['status'] = false;
            self::$systemInfo[2]['info'] = "Cannot execute external commands, please update php.ini file ";
            return false;
        }
    }

    public function tryWrite()
    {
        $gitsetup = new GitSetup();
        $gitCmd = "ls -ld";
        $output = $gitsetup->runShellCommandWithStandardOutput($gitCmd);
        $output = (string)$output;
        $temp = explode(" ", $output);
        $temps = (string)$temp[0];
        $permission = explode("-", $temps);
        $permissions = (string)$permission[0];
        if (strpos($permissions, 'x') !== false && strpos($permissions, 'w') !== false) {
            self::$systemInfo[3]['status'] = true;
            self::$systemInfo[3]['info'] = "Has access right to the file system";
            return true;
        } else {
            self::$systemInfo[3]['status'] = false;
            self::$systemInfo[3]['info'] = "GitBackup needs write access in the site root, its nested directories and the system temp directory. Please update the permissions";
            return false;
        }
    }

    public function tryWpdbaccess()
    {
        if (is_writable(ABSPATH . WPINC . '/wp-db.php')) {
            self::$systemInfo[4]['status'] = true;
            self::$systemInfo[4]['info'] = "WpDb hook enabled";
            return true;
        } else

        {
            self::$systemInfo[4]['status'] = false;
            self::$systemInfo[4]['info'] = "For GitBackup to do its magic, it needs to change the `wpdb` class and put some code there. ' .
                'To do so it needs write access to the `wp-includes/wp-db.php` file. Please update the permissions.'";
            return false;
        }
    }

    public function testGitignore()
    {
        $gitignorePath = ABSPATH . '.gitignore';
        $gitignoreExists = is_file($gitignorePath);
        if (!$gitignoreExists) {
            self::$systemInfo[5]['status'] = true;
            self::$systemInfo[5]['info'] = "Please initialise the git";
            return true;
        } else
        {
            self::$systemInfo[5]['status'] = false;
            self::$systemInfo[5]['info'] = "The git has the .gitignore file";
            return false;
        }
    }


    public function testDirectoryLayout()
    {
        $uploadDirInfo = wp_upload_dir();
        $isStandardLayout = true;
        $isStandardLayout &= ABSPATH . 'wp-content' === WP_CONTENT_DIR;
        $isStandardLayout &= WP_CONTENT_DIR . '/plugins' === WP_PLUGIN_DIR;
        $isStandardLayout &= WP_CONTENT_DIR . '/themes' === get_theme_root();
        $isStandardLayout &= WP_CONTENT_DIR . '/uploads' === $uploadDirInfo['basedir'];

        if ($isStandardLayout) {
            self::$systemInfo[6]['status'] = true;
            self::$systemInfo[6]['info'] = "It has a standard directory structure";
            return true;
        } else {

            self::$systemInfo[6]['status'] = false;
            self::$systemInfo[6]['info'] = 'It\'s necessary to use standard WordPress directory layout with the current version of GitBackup.';
            return false;
        }
    }

    public function getWebSize()
    {
        $totalCount = 0;
        $list = $this->db->getTableList();
//        print_r(count($list )); exit;
        foreach ($list as $table) {
            $result = $this->db->getTotalNumber('id', $table);
            $totalCount = $totalCount + $result;
        }
        self::$systemInfo[8]['status'] = true;
        return $totalCount;
    }

    public function checkWebSize()
    {
        if ($this->getWebSize() < 500) {
            self::$systemInfo[8]['info'] =  "The website has " . $this->getWebSize() . " entities ";
            return true;
        } else {
            self::$systemInfo[8]['info'] =  "The initialization will take a little longer. This website contains " . $this->getWebSize() . " entities, which is a lot.";
            return false;
        }

    }

    //return the total size of a directory
    public function countWebsiteFileSize()
    {
        $bytestotal = 0;
        $path = realpath(ABSPATH);
        if($path!==false){
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytestotal += $object->getSize();
            }
        }
        return $bytestotal;
    }

    public function sshcheck()
    {
        $gitsetup = new GitSetup();
        if($gitsetup->evalagent())
        {
            self::$systemInfo[9]['status'] = true;
            self::$systemInfo[9]['info'] = "The server can use ssh";
            return true;
        }
        else {
            self::$systemInfo[9]['status'] = false;
            self::$systemInfo[9]['info'] = "The plugin needs ssh to run commands";
            return false;
        }
    }


    public function systemInfo()
    {
        $this->getGitInstalled();
        if (self::$systemInfo[0]['status'] == false) {
            return self::$systemInfo;
        }
        else {
            $this->getGitVersion();
            $this->getPhpVersion();
            $this->tryRunProcess();
            $this->tryWrite();
            //$this->testGitignore();
            if (OSE_CMS == 'wordpress') {
                $this->testDirectoryLayout();
            }
            $this->checkWebSize();
            $this->sshcheck();
            $this->checkMysqlDumpVersion();
            $this->iszipEnabled();
            return self::$systemInfo;
        }
    }


    public function checkSysteminfo()
    {
        foreach(self::systemInfo() as $value)
        {
            if($value['status'] == false)
            {
                return false;
            }

        }
        return true;
    }

    public function printSystmInfo()
    {
        foreach($this->systemInfo() as $value)
        {
            print("<pre>" . print_r($value['info'], true) . "</pre>");
        }
    }

    public function checkMysqlDumpVersion()
    {
        $gitsetup = new GitSetup();
        $gitCmd = "mysqldump --version";
        $output = $gitsetup->runShellCommandWithStandardOutput($gitCmd);
        $output = (string)$output;
        if(strpos($output,"mysqldump  Ver") !== false) {
            self::$systemInfo[11]['status'] = true;
            self::$systemInfo[11]['info'] = $output;
            return true;
        }else {
            self::$systemInfo[11]['status'] = false;
            self::$systemInfo[11]['info'] = "MySQLDump is not accessible, Please contact your hosting company to install it";
            return false;
        }
    }


    public function iszipEnabled()
    {
        $gitsetup = new GitSetup();
        $gitCmd = "which zip";
        $output = $gitsetup->runShellCommandWithStandardOutput($gitCmd);
        if(empty($output))
        {
            self::$systemInfo[12]['status'] = false;
            self::$systemInfo[12]['info'] = "Zip Command is not Enabled, You will not be able to download the Website Backup, Please enable Zip Command";
            return false;
        }else{
            self::$systemInfo[12]['status'] = true;
            self::$systemInfo[12]['info'] = "Zip Command is Enabled";
            return true;
        }
    }



    public function getUnSatisfiedRequirements()
    {
        $result = null;
        foreach(self::$systemInfo as $value)
        {
            if($value['status'] == false)
            {
//                $result.= $value['info']."<br/>";
                $result.=  "<ul>";
                $result.=  "<span class='fa fa-times color-red'>";
                $result.=  " " . $value['info'];
                $result.=  "</span> </ul>";
            }
        }
        return $result;
    }


}
