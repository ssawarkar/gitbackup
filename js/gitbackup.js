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
var controller = "gitbackup";
var option = "com_ose_firewall";
var url = ajaxurl;

jQuery(document).ready(function ($) {
    addHiddenalues(0,0);
    addHiddenaluesRollback(0,0,0);
    addchoice(0);
    getFileNotification();
    var gitBackupDataTable = $('#gitBackupTable').dataTable({
        processing: true,
        ordering:  false,
        searching: false,
        serverSide: true,
        paging: true,
        bFilter: false,
        ajax: {
            url: url,
            type: "POST",

            data: function (d) {
                d.option = option;
                d.controller = controller;
                d.action = 'getGitLog';
                d.task = 'getGitLog';
                d.centnounce = $('#centnounce').val();
            }
        },
        columns: [
            {"data": "id"},
            {"data": "commitTime"},
            {"data": "commitID"},
            {"data": "commitMsg"},
            {"data": "rollback"},
            {"data": "zipDownload"},

        ]
    });
    $('#gitBackupTable tbody').on('click', 'tr', function () {
        $(this).toggleClass('selected');
    });

    $("#remoteGit-form").submit(function () {
        showLoading('Please wait...');
        var data = $("#remoteGit-form").serialize();
        data += '&centnounce=' + $('#centnounce').val();
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: data, // serializes the form's elements.
            success: function (data) {
                if (data.status === 1) {
                    $('#gitLabModal_suite').modal('hide');
                    hideLoading(5);
                    zip = document.getElementById("zip").value;
                    var finalpush = 0;
                    gitCloudBackup(zip,finalpush);

                }
                else {
                    $('#gitLabModal_suite').modal('hide');
                    hideLoading(5);
                    showDialogue(data.info, "ERROR", "close");
                }
            }
        });
        return false; // avoid to execute the actual submit of the form.
    });


    //show and hide gitlab forms
    $('#gitLabChoice_option_createaccount').click(function(){
        //gitLabChoice
        $('#gitLabChoice').modal('hide');
        $('#gitLab_createaccount').modal();
    });

    $('#gitLab_option_haveaccount').click(function(){
        //gitLabChoice
        $('#gitLab_createaccount').modal('hide');
        $('#gitLabModal_suite').modal();
    });


    $('#gitLabChoice_option_haveaccount').click(function(){
        $('#gitLabChoice').modal('hide');
        $('#gitLabModal_suite').modal();
    });

});

//initialise the git and copy the git log to the db
function enableGitBackup() {
    showLoading(O_SYSTEM_INITIALISING_GITBACKUP+
        "<br/>  "+O_LOADING_TEXT);
    toggleBackupLog(1);
    backupDatabase('init');
}

//generates a backup of all the files and commits them in the repo after checking the status
function createBackupAllFiles() {
    hideLoading();
    jQuery('#commitMessageModal').modal();
}

//form to accept user message for the backup amd stores that in the session variable
jQuery(document).ready(function ($) {
    $("#commitMessage-form").submit(function () {
        var message = document.getElementById("message").value;
        if(!/^[a-zA-Z0-9 _]+$/.test(message))
        {
            $("#errormessage").text(O_ENTER_ALPHANUMERIC_CHARACTERS_ONLY);
            return false;
        }
        else {
            toggleBackupLog(1);
            showLoading('Please wait...');
            var data = $("#commitMessage-form").serialize();
            data += '&centnounce=' + $('#centnounce').val();
            data += '&commitmessage=' + message;
            $.ajax({
                type: "POST",
                url: url,
                dataType: 'json',
                data: data, // serializes the form's elements.
                success: function (data) {
                    if (data.status) {
                        $('#commitMessageModal').modal('hide');
                        showLoading(O_SYSTEM_GENERATING_BACKUP +
                            " <br/>  Please Wait......");
                        //check view hiddden
                        push=document.getElementById("push").value;
                        zip = document.getElementById("zip").value;
                        push = (push === undefined) ? 0 : push;
                        zip = (zip === undefined) ? 0 : zip;
                        rollback=document.getElementById("rollback").value;
                        recall=document.getElementById("recall").value;
                        commitid=document.getElementById("commitid").value;
                        rollback = (rollback === undefined) ? 0 : rollback;
                        recall = (recall === undefined) ? 0 : recall;
                        commitid = (commitid === undefined) ? 0 : commitid;
                        //localBackup("commit");
                        backupDatabase('commit',push,zip,rollback,recall,commitid);
                        return;
                    }
                }
            });
            return false; // avoid to execute the actual submit of the form.
        }
    });
});

function confirmRollback(commitHead)
{
    bootbox.dialog({
        message: O_ASK_RESTORE_DATABASE+commitHead+ "<br/>" +
        "<h6><span class =\"text-danger\">"+O_RECOMMENED_NOT_RESTORE_OLD_DATABASE+"</span></h6>",
        title: "Confirmation",
        buttons: {
            success: {
                label: "Yes",
                className: "btn-success",
                callback: function() {
                    //rollback(commitHead, "old");
                    gitRollback_findChanges(commitHead , 0)

                }
            },
            danger: {
                label: "No",
                className: "btn-danger",
                callback: function() {
                    //rollback(commitHead, "new");
                    gitRollback_findChanges(commitHead , 1)

                }
            },
            main: {
                label: "Close",
                className: "btn-primary",
                callback: function() {
                    window.close();
                }
            }
        }
    });
}
function gitRollback_findChanges(commitHead , recall) {
    showLoading("The system is now rolling back <br/> Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'findChanges',
                task: 'findChanges',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    if(recall == 0)
                    {
                        addHiddenaluesRollback(1,0,commitHead);
                    }else {
                        addHiddenaluesRollback(1,1,commitHead);
                    }
                    createBackupAllFiles();
                }
                else if(data.status == 2){
                    if(recall == 0)
                    {
                        gitRollback(commitHead,"old");
                    }else {
                        gitRollback(commitHead,"new");
                    }

                }
            }
        });
    });
}




//rollback mechanism to reset back to an old backup
function gitRollback(commitHead , recall) {
    showLoading(O_SYSTEM_ROLLING_BACK+"<br/> "+ O_LOADING_TEXT);
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'gitRollback',
                task: 'gitRollback',
                commitHead: commitHead,
                recall: recall,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    hideLoading(10);
                    jQuery('#gitBackupTable').dataTable().api().ajax.reload();
                    addHiddenaluesRollback(0,0,0);
                    showDialogue(O_SYSTEM_ROLLING_BACK_TO+ commitHead , "UPDATE", "close");
                }
                else {
                    hideLoading(10);
                    addHiddenaluesRollback(0,0,0);
                    showDialogue(data.info, "ERROR", "close");
                }
            }
        });
    });
}
function backupDatabase(type, push,zip,rollback,recall,commitid) {
    showLoading("The system is now backing up your Database <br/> please wait......");
    push = (push === undefined) ? 0 : push;
    zip = (zip === undefined) ? 0 : zip;
    rollback = (rollback === undefined) ? 0 : rollback;
    recall = (recall === undefined) ? 0 : recall;
    commitid = (commitid === undefined) ? 0 : commitid;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'backupDB',
                task: 'backupDB',
                type: type,
                push: push,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    if(type == 'init')
                    {
                        initaliseGit(type,push,zip,rollback,recall,commitid);
                    }else {
                        localBackup(type,push,zip,rollback,recall,commitid);
                    }

                }else if(data.status ==0)
                {
                    addHiddenalues(0,0);
                    addHiddenaluesRollback(0,0,0);
                    hideLoading(10);
                    toggleBackupLog(0);
                    if((typeof(data.details) !== 'undefined') )
                    {
                        showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"<br/>"+data.details+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                    }else {
                        showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                    }
                }
            }
        });
    });
}

function initaliseGit(type,push,zip,rollback,recall,commitid) {
    showLoading("The system is now Initialising Git :  " +
        " <br/>  Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'initalisegit',
                task: 'initalisegit',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status ==1) {
                    localBackup(type,push,zip,rollback,recall,commitid);
                } else {
                    hideLoading(10);
                    showDialogue(data.info ,"ERROR", "close");
                    toggleBackupLog(0);
                }
            }
        });
    });
}

//TODO : manage the type variable
function localBackup(type,push,zip,rollback,recall,commitid) {
    type = (type === undefined) ? 0 : type;
    push = (push === undefined) ? 0 : push;
    zip = (zip === undefined) ? 0 : zip;
    rollback = (rollback === undefined) ? 0 : rollback;
    recall = (recall === undefined) ? 0 : recall;
    commitid = (commitid === undefined) ? 0 : commitid;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'localBackup',
                task: 'localBackup',
                type : type,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                //status 1 => list of all the folders was created successfully
                //status 2 => No New Changes
                if(data.status == 1 || data.status == 2)
                {
                    contLocalBackup(type,push,zip,rollback,recall,commitid);
                }
                else {
                    addHiddenalues(0,0);
                    addHiddenaluesRollback(0,0,0);
                    hideLoading();
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                }
            }
        });
    });
}

function contLocalBackup(type,push,zip,roll_back,recall,commitid)
{
    type = (type === undefined) ? 0 : type;
    push = (push === undefined) ? 0 : push;
    zip = (zip === undefined) ? 0 : zip;
    roll_back = (roll_back === undefined) ? 0 : roll_back;
    recall = (recall === undefined) ? 0 : recall;
    commitid = (commitid === undefined) ? 0 : commitid;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'contLocalBackup',
                task: 'contLocalBackup',
                type : type,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    //call contlocalbackup
                    showLoading('Backing Up Folder '+data.folder+ '<br/> Please Wait ......');
                    contLocalBackup(type,push,zip,roll_back,recall,commitid);
                }
                else if(data.status == 4 || data.status ==2)  //4 to indicate the end of the backup loop and 2 to indicate there no new chnages
                {
                    //SUCCESS
                    switch(type){
                        case 'init' :
                            if(data.status == 4 || data.status ==2 ){
                                hideLoading(10);
                                showDialogue(O_GIT_INITIALISED_WEBSITE , "UPDATE", "close");
                                setTimeout(1000);
                                toggleBackupLog(0);
                                getFileNotification();
                                location.reload();
                            }
                            else {
                                hideLoading(10);
                                showDialogue(data.info ,"ERROR", "close");
                                toggleBackupLog(0);
                                getFileNotification();
                            }
                            break;

                        case 'commit' :
                        default :
                            if(roll_back == 1)
                            {
                                if(data.status == 4 || data.status == 2 )
                                {
                                    if(recall == 1)
                                    {
                                        gitRollback(commitid, "new");
                                    }
                                    else
                                    {
                                        gitRollback(commitid, "old");

                                    }

                                }
                                else {
                                    //ERROR WITH THE COMMIT IN THE ROLLBACK
                                    addHiddenaluesRollback(0,0,0);
                                    hideLoading(10);
                                    showDialogue(data.info, "ERROR", "close");
                                }

                            }
                            else if(push == 1) {
                                //need to push after commit is successfull
                                //hideLoading();
                                if(data.status == 4 || data.status ==2 ){
                                    //if commit was successfull
                                    //gitCloudPush(zip);
                                    var finalpush = 1;
                                    gitCloudBackup(zip, finalpush);
                                }
                                else
                                {
                                    addHiddenalues(0,0);
                                    //problems with the commit , backup will not be pushed
                                    hideLoading(10);
                                    toggleBackupLog(0);
                                    showDialogue(data.info, "ERROR", "close");
                                }
                            }
                            else {
                                //complete the local backup and download the zip => for the free users
                                if(data.status == 4 || data.status ==2 )
                                {
                                    if(zip ==1)
                                    {
                                        //websiteZipBackup();
                                        downloadZipBackup_cloudCheck();
                                    }
                                    else
                                    {
                                        hideLoading(10);
                                        showDialogue(O_BACKUP_SUCCESSFULLY , "SUCCESS", "close");
                                        setTimeout(1000);
                                        toggleBackupLog(0);
                                        $('#gitBackupTable').dataTable().api().ajax.reload();
                                        getFileNotification();
                                    }
                                }
                                else {
                                    hideLoading(10);
                                    showDialogue(data.info, "ERROR", "close");
                                    toggleBackupLog(0);
                                    getFileNotification();
                                }
                            }
                            break;
                    }

                }
                else {
                    //ERROR
                    addHiddenalues(0,0);
                    addHiddenaluesRollback(0,0,0);
                    hideLoading(10);
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                    getFileNotification();
                }
            }
        });
    });
}

function gitCloudBackup(zip,finalpush) {
    toggleBackupLog(1);
    showLoading(O_BACKUP_ALL_TO_CLOUD +
        "<br/>" + O_LOADING_TEXT);
    zip = (zip === undefined) ? 0 : zip;
    finalpush = (finalpush === undefined) ? 0 : finalpush;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'gitCloudCheck',
                task: 'gitCloudCheck',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status ==1) {
                    if(finalpush == 0)
                    {
                        gitCloudPush(zip);
                    }else
                    {
                        finalGitPush(zip);
                    }
                } else if(data.status ==0){
                    if(zip==1)
                    {
                        hideLoading(10);
                        downloadZipBackup_cloudCheck();
                    }else{
                        hideLoading(10);
                        $('#gitLabChoice').modal();
                    }
                }else if(data.status == 3) {
                    if (zip == 1) {
                        hideLoading(10);
                        downloadZipBackup_cloudCheck();
                    } else {
                        hideLoading(10);
                        bitbucketUserWarning();
                    }
                }
            }
        });
    });
}

function bitbucketUserWarning()
{
    jQuery(document).ready(function ($) {
    var content = "You are currently using <b> BitBucket</b> for cloud backup,<br/> The new version uses <b>GitLab (10GB of cloud storage)</b> instead of BitBucket (2GB of cloud storage)<br/> Would you like to switch to GitLab";
    bootbox.dialog({
        message: content,
        title: "Update",
        buttons: {
            success: {
                label: "Yes",
                className: "btn-success",
                callback: function () {
                    $('#gitLabChoice').modal();
                },
                main: {
                    label: "Close",
                    className: "btn-primary",
                    callback: function () {
                        addHiddenalues(0, 0);
                        window.close();
                    }
                }
            }
        }
    });
    });

}

function gitCloudPush(zip) {
    zip = (zip === undefined) ? 0 : zip;
    showLoading(O_BACKUP_ALL_TO_CLOUD +
        " <br/>" + O_LOADING_TEXT);
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'gitCloudPush',
                task: 'gitCloudPush',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1) {
                    if(zip == 1){
                        //websiteZipBackup();
                        downloadZipBackup_cloudCheck();
                    }else {
                        addHiddenalues(0,0);
                        hideLoading();
                        showDialogue(O_COPY_BACKUP_ON_CLOUD, "UPDATE", "close");
                        $('#gitBackupTable').dataTable().api().ajax.reload();
                    }
                }
                else
                if(data.status == 2)
                {
                    //assign value to the view push and zip
                    addHiddenalues(1,zip);
                    createBackupAllFiles();
                }
                else
                if(data.status == 3)
                { //FREE USERS
                    hideLoading(10);
                    addHiddenalues(0,0);
                    toggleBackupLog(0);
                    $("#pop_subscription").fadeIn();
                }
                else
                {
                    hideLoading();
                    addHiddenalues(0,0);
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);

                }
            }
        });
    });
}

//commit all the code and pushes them directly to the repository
function finalGitPush(zip) {
    showLoading(O_BACKUP_ALL_TO_CLOUD+
        O_LOADING_TEXT);
    zip = (zip === undefined) ? 0 : zip;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'finalGitPush',
                task: 'finalGitPush',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    //SUCCESS
                    //push was successfull
                    if(zip == 1)
                    {
                        //websiteZipBackup();
                        downloadZipBackup_cloudCheck();
                    }else {
                        addHiddenalues(0,0);
                        hideLoading();
                        showDialogue(O_COPY_BACKUP_ON_CLOUD, "UPDATE", "close");
                        toggleBackupLog(0);
                        $('#gitBackupTable').dataTable().api().ajax.reload();
                        getFileNotification();
                    }
                }else
                if(data.status == 3)
                { //FREE USERS
                    hideLoading(10);
                    addHiddenalues(0,0);
                    $("#pop_subscription").fadeIn();
                    toggleBackupLog(0);
                    getFileNotification();
                }
                else
                {  //ERROR IN THE FINAL GIT PUSH
                    hideLoading();
                    addHiddenalues(0,0);
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                    getFileNotification();

                }
            }
        });
    });
}

jQuery(document).ready(function ($) {
    $("#pop_subscription").hide();
    $("#git_pre_info").click(function() {
        $("#pop_subscription").fadeIn();
    });
    $("#subscribe-button").click(function() {
        $("#pop_subscription").fadeIn();
    });
    $("#pop_close").click(function() {
        $("#pop_subscription").fadeOut();
    });
});

function downloadZipBackup_cloudCheck()
{
    showLoading("The system is now generating the Zip file of the website " +
        " <br/> Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'zipDownloadCloudCheck',
                task: 'zipDownloadCloudCheck',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                var choice = $('#choice').val();
                if (typeof choice !== 'undefined' && choice !== null && choice ==0) {
                    if (data.subscription == 1) {
                        if (data.repo == 0) {
                            //if remote repo not set
                            downloadzip_form_no_repo();
                        } else if (data.repo == 1) {
                            //remote repo is set
                            downloadzip_form_repo_set();
                        } else if (data.repo == 3) {
                            //bitbucket account
                            downloadzip_form_bitbucket_repo();
                        }
                    } else {
                        //show instructions to download
                        websiteZipBackup(1);
                    }
                }else{
                    if(choice == 1 || choice ==2)
                    {
                        websiteZipBackup(choice);
                    }
                }

            }
        });
    });
}


function downloadzip_form_no_repo()
{
    hideLoading();
    jQuery(document).ready(function ($) {
        var content = "How Would You like to Download the backup" +
            "<h6><span class =\"text-danger\">It is highly recommended to download from cloud <br/> Files can get corrupted while downloading backup from local files</span></h6>";
        bootbox.dialog({
            message: content,
            title: "Update",
            buttons: {
                success: {
                    label: "Cloud Backup Download",
                    className: "btn-success",
                    callback: function () {
                        addHiddenalues(1,1);
                        addchoice(2);
                        $('#gitLabChoice').modal();

                    }
                },
                danger: {
                    label: "Local Backup Download",
                    className: "btn-danger",
                    callback: function () {
                        addHiddenalues(0,1);
                        addchoice(1);
                        websiteZipBackup(1);

                    }
                }
            }
        });
    });

}

function downloadzip_form_repo_set()
{
    hideLoading();
    jQuery(document).ready(function ($) {
        var content = "How Would You like to Download the backup" +
            "<h6><span class =\"text-danger\">Note : Files can get corrupted while downloading backup from local files</span></h6>";
        bootbox.dialog({
            message: content,
            title: "Update",
            buttons: {
                success: {
                    label: "Cloud Backup Download",
                    className: "btn-success",
                    callback: function () {
                        addHiddenalues(1, 1);
                        addchoice(2);
                        websiteZipBackup(2);

                    }
                },
                danger: {
                    label: "Local Backup Download",
                    className: "btn-danger",
                    callback: function () {
                        addHiddenalues(0,1);
                        addchoice(1);
                        websiteZipBackup(1);
                    }
                }
            }
        });
    });

}

function downloadzip_form_bitbucket_repo()
{
    hideLoading();
    jQuery(document).ready(function ($) {
        var content = "The new version has discontinued bitbucket services, please either switch to GitLab or download backup using local files " +
            "<h6><span class =\"text-danger\">Note : Files can get corrupted while downloading backup from local files</span></h6>";
        bootbox.dialog({
            message: content,
            title: "Update",
            buttons: {
                success: {
                    label: "Cloud Backup Download",
                    className: "btn-success",
                    callback: function () {
                        addHiddenalues(1,1);
                        addchoice(2);
                        $('#gitLabChoice').modal();
                    }
                },
                danger: {
                    label: "Local Backup Download",
                    className: "btn-danger",
                    callback: function () {
                        addHiddenalues(0,1);
                        addchoice(1);
                        websiteZipBackup(1);
                    }
                }
            }
        });
    });

}
/*
 0->donwload local
 1-> set remote repo
 2->push +downlaod
 3->bitbucket users
 */

function websiteZipBackup(choice) {
    //TODO SHOW THE LINK TO THE USER
    showLoading(O_SYSTEM_GENERATING_ZIP_OF_WEBSITE +
        " <br/>"+O_LOADING_TEXT);
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'websiteZipBackup',
                task: 'websiteZipBackup',
                choice : choice,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (typeof data.usertype !== 'undefined' && data.usertype !== null) {
                    if(data.usertype == 1)
                    {
                        if (data.status == 1) {
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');
                        } else{
                            addHiddenalues(0, 0);
                            hideLoading();
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');
                        }
                    }
                    else if(data.usertype ==0)
                    {
                        if(data.status == 1)
                        {
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');

                        }else{
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.info, 'UPDATE', 'CLOSE');
                        }
                    }
                    else
                    {
                    //error in downlaod
                        addHiddenalues(0, 0);
                        hideLoading();
                        showDialogue(data.info, "ERROR", "close");
                    }
                }
            }
        });
    });
}
//
function downloadzip() {
    showLoading(O_SYSTEM_PREPARING_ZIP_OF_WEBSITE +
        "<br/>" +O_LOADING_TEXT);
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'getZipUrl',
                task: 'getZipUrl',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                var win = window.open(data.url, '_blank');
                win.focus();
                addHiddenalues(0,0);
            }
        });
    });
}
//function called by cron jobs every 1 hour to delete the old zip file of the backup
function deleteZipBakcupFile() {
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'deleteZipBakcupFile',
                task: 'deleteZipBakcupFile',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                }else
                {
                    showDialogue(O_ZIP_BACKUP_NOT_EXISTS, "ERROR", "close");
                }
            }
        });
    });
}
function discardChanges(zip) {
    zip = (zip === undefined) ? false : zip;
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'discardChanges',
                task: 'discardChanges',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1){
                    if(zip ==1)
                    {
                        //websiteZipBackup();
                        addHiddenalues(0,1);
                        downloadZipBackup_cloudCheck();
                    }
                }
                else {
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                }

            }
        });
    });
}


function findChanges() {
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'findChanges',
                task: 'findChanges',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    bootbox.dialog({
                        message: O_KEEP_UNSAVED_CHANGES,
                        title: "Confirmation",
                        buttons: {
                            success: {
                                label: "Yes",
                                className: "btn-success",
                                callback: function() {
                                    //if you have changes and the user wants to keep them
                                    //push the changes first and then genrate a backup
                                    if(data.subscription == 0)
                                    { // for free users commit the changes and download the zip
                                        addHiddenalues(0,1);
                                        createBackupAllFiles();
                                    }
                                    else {
                                        //premium users will push the changes
                                        //chnages to just do a local backup
                                        addHiddenalues(1,1);
                                        createBackupAllFiles(); // 1 to indicate its a zip backup request
                                    }

                                }
                            },
                            danger: {
                                label: "No",
                                className: "btn-danger",
                                callback: function() {
                                    discardChanges(1);
                                }
                            },
                            main: {
                                label: "Close",
                                className: "btn-primary",
                                callback: function() {
                                    window.close();
                                }
                            }
                        }
                    });
                }
                else if(data.status == 2)
                { //if there are no changes
                    showLoading(O_SYSTEM_PREPARING_ZIP_OF_WEBSITE +
                        " <br/> " +O_LOADING_TEXT);
                    //websiteZipBackup();
                    downloadZipBackup_cloudCheck();
                }else{
                    showDialogue(data.info,'ERROR','CLOSE');
                }
            }
        });
    });
}

function viewChangeHistory(commitid) {

    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'viewChangeHistory',
                task: 'viewChangeHistory',
                commitid: commitid,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    //alert(data.files);
                    var filelist ='' ;
                    for(var i=0; i<data.files.length; ++i) {
                        filelist = filelist+data.files[i] + '<br/>';
                    }
                    bootbox.dialog({
                        message: O_DATE+" :" + data.date + "<br><b>" + O_FILE_CHANGES+ "</b>: " + filelist,
                        title: "Details",
                        buttons: {
                            main: {
                                label: "Close",
                                className: "btn-primary",
                                callback: function () {
                                    window.close();
                                }
                            }
                        }
                    });

                }else if(data.status == 0)
                {
                    //ERROR
                    showDialogue(data.info, "ERROR", "close");
                }
                else {
                    $("#pop_subscription").fadeIn();
                }
            }

        });
    });
}

function addHiddenalues(push, zip) {
    jQuery(document).ready(function ($) {
        $("#push").val(push);   //1
        $("#zip").val(zip);
    });
}

function addHiddenaluesRollback(rollback, recall,commitid) {
    jQuery(document).ready(function ($) {
        $("#rollback").val(rollback);
        $("#recall").val(recall);
        $("#commitid").val(commitid);
    });
}
function addchoice(choice) {
    jQuery(document).ready(function ($) {
        $("#choice").val(choice);   //1
    });
}


jQuery(document).ready(function($){

    $('#carousel-generic').carousel({
        interval: false
    });

    getLastBackupTime();
    $('#close-msg-box').click(function(){
            $("#msg-box").fadeOut();
        }
    );
    $('.git-backup-infoTag .text-success .glyphicon .glyphicon-info-sign').click(function(){
            $("#pop_subscription").fadeIn();
        }
    );
});

function getLastBackupTime() {
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'getLastBackupTime',
                task: 'getLastBackupTime',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                var time = data.commitTime;
                time = time.substring(0,time.length - 5);
                $('#backup-time').text(time);
            }
        });
    });
}



function uninstallgit_confirm() {
    bootbox.dialog({
        message: "Do you want to Uninstall Git "+ "<br/>" +
        "<h6><span class =\"text-danger\">This will delete all of your git history </span></h6>",
        title: "Confirmation",
        buttons: {
            success: {
                label: "Uninstall Git",
                className: "btn-success",
                callback: function() {
                    uninstallgit(0);

                }
            },
        }
    });
}


function uninstallgit(keeplog) {
    showLoading("The system is now uninstalling Git" +
        "<br/> Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'uninstallgit',
                task: 'uninstallgit',
                keephistory : keeplog,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 1) {
                    location.reload();
                    showDialogue(data.info, "UPDATE", "CLOSE");
                }else {
                    showDialogue(data.info,"ERROR","CLOSE");
                }
            }
        });
    });
}
function getFileNotification() {
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'getFileNotification',
                task: 'getFileNotification',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (typeof data.status !== 'undefined' && data.status !== null) {
                    if(data.status ==3)
                    {
                        return;
                    }
                }
                if(data.local>0)
                {
                    if( $("#localBackup .badge.badge-notify").length )
                    {
                        $("#localBackup .badge.badge-notify").remove();
                        $("#localBackup").append("<span class='badge badge-notify'>"+"&nbsp;"+"</span>");
                    }else{
                        $("#localBackup").append("<span class='badge badge-notify'>"+"&nbsp;"+"</span>");

                    }
                }
                if(data.local==0)
                {
                    if( $("#localBackup .badge.badge-notify").length )
                    {
                        $("#localBackup .badge.badge-notify").remove();
                    }
                }
                var temp = parseInt(data.local) + parseInt(data.cloud);
                if(temp >0)
                {
                    if( $("#cloudPush .badge.badge-notify").length )
                    {
                        $("#cloudPush .badge.badge-notify").remove();
                        $("#cloudPush").append("<span class='badge badge-notify'>"+"&nbsp;"+"</span>");
                    }else{
                        $("#cloudPush").append("<span class='badge badge-notify'>"+"&nbsp;"+"</span>");

                    }
                }
                if(temp == 0)
                {
                    if( $("#cloudPush .badge.badge-notify").length )
                    {
                        $("#cloudPush .badge.badge-notify").remove();
                    }
                }
            }
        });
    });
}




function toggleBackupLog(value) {
    jQuery(document).ready(function ($) {
    $.ajax({
        type: "POST",
        url: url,
        dataType: 'json',
        data: {
            option: option,
            controller: controller,
            action: 'toggleBackupLog',
            task: 'toggleBackupLog',
            value: value,
            centnounce: $('#centnounce').val()
        },
        success: function (data) {
        }
    });
    });
}

