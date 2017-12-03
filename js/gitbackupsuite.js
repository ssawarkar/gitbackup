/**
 * Created by suraj on 15/11/16.
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
var controller = "Gitbackupsuite";
var option = "com_ose_firewall";
var url = ajaxurl;

jQuery(document).ready(function ($) {
    canRetrieveAccounts();
    manageQueues();
    addHiddenalues(0,0);
    addHiddenaluesRollback(0,0,0);
    addchoice(0);
    addAccountNamePath(0,0);
    setAccountNamePathSession(0,0);
    $("#accountspage").show();
    $("#gitactivationpanel").hide();
    $("#accountgtstatus").hide();
    $("#add-dbconfig-form").submit(function() {
        showLoading(O_PLEASE_WAIT);
        var accountname = document.getElementById("accountname").value;
        var accountpath = document.getElementById("accountpath").value;
        var postdata = $("#add-dbconfig-form").serialize();
        postdata += '&accountname='+accountname;
        postdata += '&accountpath='+accountpath;
        postdata += '&centnounce=' + $('#centnounce').val();
        $.ajax({
            type: "POST",
            url: url,
            data: postdata, // serializes the form's elements.
            success: function(data)
            {
                hideLoading();
                data = jQuery.parseJSON(data);
                if (data.status == 1) {
                    $('#formModal').modal('hide');
                    addAccountNamePath(0,0);
                    showDialogue(data.info,'UPDATE','CLOSE');
                    manageQueues();
                    isGitInit(accountpath,accountname);
                }
                else {
                    showDialogue(data.info, O_ERROR, O_OK);
                    $('#formModal').modal('show');
                }
            }
        });
        return false; // avoid to execute the actual submit of the form.
    });

    //BitBucket Form to create a new account
    $("#remoteGit-form_suite").submit(function () {
        showLoading(O_PLEASE_WAIT);
        var accountpath = document.getElementById("accountpath_session").value;
        var accountname = document.getElementById("accountname_session").value;
        var data = $("#remoteGit-form_suite").serialize();
        data += '&centnounce=' + $('#centnounce').val();
        data += '&accountpath='+accountpath;
        data += '&accountname='+accountname;
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
                    //showDialogue(data.message, "UPDATE", "close");
                    //location.reload();

                }
                else {
                    //showLoading(O_ADD_SEC_FAIL);
                    $('#gitLabModal_suite').modal('hide');
                    hideLoading(5);
                    showDialogue(data.info, "ERROR", "close");
                }
            }
        });
        return false; // avoid to execute the actual submit of the form.
    });
    //show the bitbucket choice form
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


function canRetrieveAccounts()
{
    showLoading('Retrieving List of Accounts Please Wait....');
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'canRetrieveAccounts',
                task: 'canRetrieveAccounts',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 0)
                {
                    //cannot retieve account list
                    //block the entire page
                    showDialogue(data.info,"ERROR","CLOSE");
                }else {
                    //success in retrieving the account list
                    //git has been initalized
                    var AccountGitBackupTable = $('#AccountGitBackupTable').dataTable({
                        processing: true,
                        serverSide: true,
                        //paging: false,
                        //bFilter: false,
                        ajax: {
                            url: url,
                            type: "POST",

                            data: function (d) {
                                d.option = option;
                                d.controller = controller;
                                d.action = 'getAccountTable';
                                d.task = 'getAccountTable';
                                d.centnounce = $('#centnounce').val();
                            }
                        },
                        columns: [
                            {"data": "id",searchable:false},
                            {"data": "name"},
                            {"data": "latestbackup",searchable:false},
                            {"data": "backupnow",sortable: false,searchable:false},
                            {"data": "download",sortable: false,searchable:false},
                            {"data": "upload",sortable: false,searchable:false},
                            {"data": "uninstall",sortable: false,searchable:false},
                        ]
                    });
                    $('#AccountGitBackupTable tbody').on('click', 'tr', function () {
                        $(this).toggleClass('selected');
                    });
                    //enableQueues();
                }
            }
        });
    });
}

function manageQueues()
{
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'manageQueues',
                task: 'manageQueues',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    $('#indv_accounts').show();
                    $('#all_Accounts').show();
                }
                else if(data.status == 0){
                    $('#indv_accounts').hide();
                    $('#all_Accounts').hide();
                }
            }
        });
    });
}







//initialise the git and copy the git log to the db
function enableGitBackup() {
    //showLoading("The system is now initialising gitbackup  " +
    //    "<br/>  Please Wait......");
    backupDatabase('init');
}

//generates a backup of all the files and commits them in the repo after checking the status
function createBackupAllFiles(accountname,accountpath) {
    hideLoading();
    jQuery('#commitMessageModal').modal();
}

//form to accept user message for the backup amd stores that in the session variable
jQuery(document).ready(function ($) {
    $("#commitMessage-form").submit(function () {
        var message = document.getElementById("message").value;
        if(!/^[a-zA-Z0-9 _]+$/.test(message))
        {
            $("#errormessage").text("PLEASE ENTER ALPHANUMERIC CHARACTERS ONLY");
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
                    if (data.status == 1) {
                        $('#commitMessageModal').modal('hide');
                        showLoading("The system is now generating a backup of the complete website and the database." +
                            " <br/>  Please Wait......");
                        //check view hiddden
                        push=document.getElementById("push").value;
                        zip = document.getElementById("zip").value;
                        rollback=document.getElementById("rollback").value;
                        recall=document.getElementById("recall").value;
                        commitid=document.getElementById("commitid").value;
                        push = (push === undefined) ? 0 : push;
                        zip = (zip === undefined) ? 0 : zip;
                        rollback = (rollback === undefined) ? 0 : rollback;
                        recall = (recall === undefined) ? 0 : recall;
                        commitid = (commitid === undefined) ? 0 : commitid;
                        //localBackup("commit");
                        backupDatabase('commit',push,zip,rollback,recall,commitid);
                        return;
                    }else if(data.status == 0) {
                        showDialogue('There was some problem in setting the value of the commit message ','ERRORE','CLOSE')
                        toggleBackupLog(0);
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
        message: "Do you want to restore the database of "+commitHead+ "<br/>" +
        "<h6><span class =\"text-danger\">It is highly recommended that you should NOT restore the database of "+commitHead+". <br/>As you might loose some new changes in the database</span></h6>",
        title: "Confirmation",
        buttons: {
            success: {
                label: "Yes",
                className: "btn-success",
                callback: function() {
                    gitRollback_findChanges(commitHead , 0)

                }
            },
            danger: {
                label: "No",
                className: "btn-danger",
                callback: function() {
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
    showLoading("The system is now rolling back <br/> Please Wait......");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status == 1) {
                    hideLoading(10);
                    $('#gitBackupTable').dataTable().api().ajax.reload();
                    addHiddenaluesRollback(0,0,0);
                    showDialogue("The system has been rolled back to "+ commitHead , "UPDATE", "close");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                    if((typeof(data.details) !== 'undefined') )
                    {
                        showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"<br/>"+data.details+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                    }else {
                        showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                    }
                    toggleBackupLog(0);
                }
            }
        });
    });
}

function initaliseGit(type,push,zip,rollback,recall,commitid) {
    showLoading("The system is now Initalsing Git :  " +
        " <br/>  Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'initaliseGit',
                task: 'initaliseGit',
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
    showLoading('Initiating Files Backup <br/> Please Wait....');
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                type : type,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                //STATUS :
                //0=>Error
                //1 => cont folder backup
                //2=> No new chnages to commit
                //3=>
                //4=>Remaining files have been backed up ; end of backup loop
                if(data.status == 1)
                {
                    //call contlocalbackup
                    showLoading('Backing Up Folder '+data.folder+ '<br/> Please Wait ......');
                    contLocalBackup(type,push,zip,roll_back,recall,commitid);
                }
                else if(data.status == 4 || data.status == 2)  //4 to indicate the end of the backup loop and 2 to indicate there no new chnages
                {
                    //SUCCESS
                    switch(type){
                        case 'init' :
                            if(data.status == 4 || data.status ==2 ){
                                hideLoading(10);
                                showDialogue("The git has been initialised for the website" , "UPDATE", "close");
                                setTimeout(1000);
                                showGitLogTable($('#accountname_session').val(),$('#accountpath_session').val());
                                $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                                toggleBackupLog(0);
                                getFileNotification();
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
                                    toggleBackupLog(0);
                                    getFileNotification();
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
                                    showDialogue(data.info, "ERROR", "close");
                                    toggleBackupLog(0);
                                    getFileNotification();
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
                                        showDialogue("The Backup has been Created Successfully" , "SUCCESS", "close");
                                        setTimeout(1000);
                                        showGitLogTable($('#accountname_session').val(),$('#accountpath_session').val());
                                        $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                                        //$('#gitBackupTable').dataTable().api().ajax.reload();
                                        getFileNotification();
                                        toggleBackupLog(0);
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
function gitCloudBackup_accountspage(name,path)
{
    setAccountNamePathSession(name,path);
    gitCloudBackup();
}


function gitCloudBackup(zip,finalpush) {
    toggleBackupLog(1);
    showLoading("The system is now uploading the backup to the cloud " +
        "<br/>  This might take a bit of time <br/> Please Wait......");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                    }else {
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
function gitCloudPush(zip) {
    zip = (zip === undefined) ? 0 : zip;
    showLoading("The system is now uploading the backup to the cloud " +
        "<br/>  This might take a bit of time <br/> Please Wait......");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                        showDialogue("A copy of backup is stored on the cloud", "UPDATE", "close");
                        //setTimeout(1000);
                        $('#gitBackupTable').dataTable().api().ajax.reload();
                        getFileNotification();
                        toggleBackupLog(0);
                    }
                }
                else
                if(data.status == 2)
                { //uncommited chnages detecetd
                    //assign value to the view push and zip
                    addHiddenalues(1,zip);
                    createBackupAllFiles();
                }
                else
                if(data.status == 3)
                { //FREE USERS
                    hideLoading(10);
                    addHiddenalues(0,0);
                    $("#pop_subscription").fadeIn();
                    toggleBackupLog(0);
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
    showLoading("The system is now uploading the backup to the cloud " +
        "<br/>  This might take a bit of time <br/> Please Wait......");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                        showDialogue("A copy of backup is stored on the cloud", "UPDATE", "close");
                        //setTimeout(1000);
                        //location.reload();
                        //$('#gitBackupTable').dataTable().api().ajax.reload();
                        $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                        showGitLogTable($('#accountpath_session').val(),$('#accountpath_session').val());
                        getFileNotification();
                        toggleBackupLog(0);
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                action: 'websiteZipBackup',
                task: 'websiteZipBackup',
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                choice : choice,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (typeof data.usertype !== 'undefined' && data.usertype !== null) {
                    if (data.usertype == 1) {
                        if (data.status == 1) {
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');
                        } else {
                            addHiddenalues(0, 0);
                            hideLoading();
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');
                        }
                    }
                    else if (data.usertype == 0) {
                        if (data.status == 1) {
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.instructions, 'UPDATE', 'CLOSE');

                        } else {
                            hideLoading();
                            addHiddenalues(0, 0);
                            showDialogue(data.info, 'UPDATE', 'CLOSE');
                        }
                    }
                    else {
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


function downloadzip() {
    showLoading("The system is now preparing to download the Zip file of the website " +
        "<br/> Please Wait......");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                }else
                {
                    showDialogue("The zip Backup file does not exists", "ERROR", "close");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
                }

            }
        });
    });
}


function findChanges_accountstable(accountname,accountpath)
{
    setAccountNamePathSession(accountname,accountpath);
    findChanges();
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    bootbox.dialog({
                        message: "There are some unsaved changes do you want to keep them ?",
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
                                        addHiddenalues(1,1);
                                        gitCloudBackup(1); // 1 to indicate its a zip backup request
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
                else if(data.status ==2)
                { //if there are no changes
                    showLoading("The system is now preparing to download the Zip file of the website " +
                        " <br/> Please Wait......");
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
    showLoading('Fetching information <br/> Please wait...')
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if (data.status == 1) {
                    //alert(data.files);
                    var filelist ='' ;
                    for(var i=0; i<data.files.length; ++i) {
                        filelist = filelist+data.files[i] + '<br/>';
                    }
                    bootbox.dialog({
                        message: "Date :" + data.date + "<br>" + "Files changed: " + filelist,
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
function addchoice(choice) {
    jQuery(document).ready(function ($) {
        $("#choice").val(choice);   //1
    });
}
function addHiddenaluesRollback(rollback, recall,commitid) {
    jQuery(document).ready(function ($) {
        $("#rollback").val(rollback);
        $("#recall").val(recall);
        $("#commitid").val(commitid);
    });
}

function addAccountNamePath(name,path)
{
    jQuery(document).ready(function ($) {
        $("#accountname").val(name);
        $("#accountpath").val(path);
    });
}

function setAccountNamePathSession(name,path)
{
    jQuery(document).ready(function ($) {
        $("#accountname_session").val(name);
        $("#accountpath_session").val(path);
    });
}

jQuery(document).ready(function($){

    $('#carousel-generic').carousel({
        interval: false
    });

    //getLastBackupTime();
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
function checkAccountstatus(accountname,accountpath) {
    showLoading("Checking Account Status  <br/> Please wait"); //Retrieving Database Configuration
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                accountname : accountname,
                accountpath : accountpath,
                action: 'checkDBConfigExists',
                task: 'checkDBConfigExists',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 0)
                {
                    retrieveDBConfig(accountpath,accountname)
                }else {
                    //show the git initialization screen
                    //or the git log table
                    isGitInit(accountpath,accountname);
                }
            }
        });
    });
}

function retrieveDBConfig(accountpath,accountname)
{
    showLoading("Retrieving Database Configuration <br/> Please wait");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                accountpath : accountpath,
                action: 'getWebSiteInfo',
                task: 'getWebSiteInfo',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 0)
                {
                    //cannot retrieve the config ddetails
                    addAccountNamePath(accountname,accountpath);
                    $('#formModal').modal('show');
                }else if(data.status == 2)
                {
                    //TODO DISBALE THE ACCOUNT LINK
                    showDialogue(data.info,'ERROR','CLOSE');
                }
                else {
                    //details have been retrived
                    //check the db connection with the details
                    saveDBConfig(data.info,accountname,accountpath);
                }
            }
        });
    });
}

function saveDBConfig(dbconfig,accountname,accountpath)
{
    showLoading("Saving the Database Configuration <br/> Please wait");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                dbconfig : dbconfig,
                accountname : accountname,
                accountpath : accountpath,
                action: 'addDBConfigFileContent',
                task: 'addDBConfigFileContent',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 0)
                {
                    addAccountNamePath(accountname,accountpath);
                    showDialogue(data.info,'ERROR','CLOSE');
                    $('#formModal').modal('show');
                }else {
                    //db connection was successfull
                    addAccountNamePath(0,0);
                    manageQueues();
                    showDialogue(data.info,'UPDATE','CLOSE');
                    isGitInit(accountpath,accountname);
                }
            }
        });
    });
}
function showAccountsTable()
{
    $("#accountspage").show();
    $("#accountgtstatus").hide();
    $("#gitactivationpanel").hide();
    $('#AccountGitBackupTable').dataTable().api().ajax.reload();
    setAccountNamePathSession(0,0);

}


function showGitLogTable(accountname,accountpath) {
    jQuery(document).ready(function ($) {
        $("#gitBackupTable").dataTable().fnDestroy();
        $("#accountspage").hide();
        $("#accountgtstatus").show();
        $("#gitactivationpanel").hide();
        $("#gitintitialized").show();
        $("#account-name-text").text('Account Name: ' + accountname);
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
                    d.accountname = accountname,
                        d.accountpath = accountpath,
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
    });
    getLastBackupTime();
    getFileNotification();

}

function isGitInit(accountpath,accountname)
{
    showLoading('Checking Git status for the account');
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "GET",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                accountpath : accountpath,
                accountname : accountname,
                action: 'isinit',
                task: 'isinit',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 2)
                {
                    //git is not init
                    setAccountNamePathSession(accountname,accountpath);
                    showEnableGitPanel();
                }else  if(data.status == 1){
                    //git has been initalized
                    setAccountNamePathSession(accountname,accountpath);
                    showGitLogTable(accountname,accountpath);
                }else {
                    showDialogue(dat.info,"ERROR",'CLOSE');
                }
            }
        });
    });
}



function showEnableGitPanel()
{
    jQuery(document).ready(function ($) {
        $("#accountspage").hide();
        $("#accountgtstatus").show();
        $("#gitintitialized").hide();
        $("#gitactivationpanel").show();
    });
}

function enablegitbackup_accountstable(accountname,accountpath)
{
    setAccountNamePathSession(accountname,accountpath);
    enableGitBackup();
}

function createBackupAllFiles_accountstable(accountname,accountpath)
{
    setAccountNamePathSession(accountname,accountpath);
    createBackupAllFiles();
}

function uninstallgit_confirm(accountname,accountpath) {
    bootbox.dialog({
        message: "Do you want to Uninstall Git "+ "<br/>" +
        "<h6><span class =\"text-danger\">This will delete all of your git history </span></h6>",
        title: "Confirmation",
        buttons: {
            success: {
                label: "Uninstall Git",
                className: "btn-success",
                callback: function() {
                    uninstallgit(accountname,accountpath,0);

                }
            },
        }
    });
}

function uninstallgit(accountname,accountpath,keeplog) {
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
                accountname : accountname,
                accountpath :accountpath,
                keephistory : keeplog,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                hideLoading();
                if(data.status == 1) {
                    $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                    showDialogue(data.info, "UPDATE", "CLOSE");
                }else {
                    showDialogue(data.info,"ERROR","CLOSE");
                }
            }
        });
    });
}


/*
 All the code for the backup queue maanagement
 */

function prepareBackupAccountsQueue(all)
{
    toggleBackupLog(1);
    all = (all === undefined) ? 0 : 1;
    if(all == 1)
    {
        ids= encodeAllnames($('#AccountGitBackupTable').dataTable().api().data());
    }else {
        ids= encodeAllnames($('#AccountGitBackupTable').dataTable().api().rows('.selected').data());
    }
    showLoading("The system is now preparing to backup the account(s)" +
        "<br/> Please Wait......");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'backupAccountsQueue',
                task: 'backupAccountsQueue',
                list : ids,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 0)
                {
                    //TODO CHECK ERROR LOG
                    //separte funcrtion show error as well as error log
                    //CHECK IF THE LIST IS EMPTY
                    hideLoading();
                    showDialogue(data.info,"ERROR","CLOSE");
                    errorLogInfo();
                    toggleBackupLog(0);
                }else {
                    continueBackupQueue();
                }
            }
        });
    });
}

function continueBackupQueue()
{
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'contBackupQueue',
                task: 'contBackupQueue',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status ==1 ){
                    //git has been intialised
                    backupDatabase_queue("commit",data.name,data.path);
                }else if(data.status ==2)
                {
                    //git not intialised
                    enableGitBackup_queue(data.name,data.path);
                }else if(data.status ==3)
                {
                    //backup completed
                    hideLoading();
                    $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                    errorLogInfo();
                    toggleBackupLog(0);
                }
                else {
                    if(data.status == 0 && typeof data.impact !== 'undefined' && data.impact !== null &&  data.impact == "high")
                    {
                        hideLoading();
                        $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                        showDialogue(data.info,"ERROR","CLOSE");
                        errorLogInfo();
                        toggleBackupLog(0);
                    }else {
                        //TODO CHECK FOR THE NEXT ACCOUNT
                        //THE CURRENT ACCOUNT HAS SOME ERRORS : THE FILE DOES NOT EXISTS FOR THE PATH
                        showLoading(data.info);
                        backupQueueCompleted(data.name,data.path);
                    }

                }
            }
        });
    });
}


function enableGitBackup_queue(accountname,accountpath) {
    showLoading("Account : "+accountpath + "<br/> Initialising Backup <br/> Please Wait ..");
    backupDatabase_queue('init',accountname,accountpath);
}


function backupDatabase_queue(type,accountname,accountpath) {
    showLoading("Account : "+accountname + "<br/> Backing Up Database <br/> Please Wait ..");
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
                accountname : accountname,
                accountpath : accountpath,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    if(type == 'init')
                    {
                        initaliseGit_queue(type,accountname,accountpath);
                    }else {
                        localBackup_queue(type,accountname,accountpath);
                    }

                }else if(data.status ==0)
                {
                    if(typeof data.impact !== 'undefined' && data.impact !== null && data.impact == "low")
                    {
                        //TODO : ERROR HANDLING FOR BACKUP queue
                        //Datasbe backup failed
                        //continue to backup files for the account
                        if(type == 'init')
                        {
                            initaliseGit_queue(type,accountname,accountpath);
                        }else {
                            localBackup_queue(type,accountname,accountpath);
                        }
                    }else if(typeof data.impact !== 'undefined' && data.impact !== null &&  data.impact == "medium"){
                        //the databse setyp failed for the account
                        //go to the next account and continue
                        showLoading(data.info);
                        backupQueueCompleted(accountname,accountpath);
                    }else {
                        //high impact stop the program execution and show the error along with the log
                        hideLoading(10);
                        if((typeof(data.details) !== 'undefined') )
                        {
                            toggleBackupLog(0);
                            showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"<br/>"+data.details+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                        }else {
                            toggleBackupLog(0);
                            showDialogue("There was some problem in backing up the database <br/> Error :<br/>"+data.info+"Please contact the support team at support@centrora.com to address this issue", "ERROR", "close");
                        }
                    }

                }
            }
        });
    });
}

function initaliseGit_queue(type,accountname,accountpath) {
    showLoading("Account : "+accountname + "<br/> Initialising Git <br/> Please Wait ..");
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'initaliseGit',
                task: 'initaliseGit',
                accountname : accountname,
                accountpath :accountpath,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if (data.status ==1) {
                    localBackup_queue(type,accountname,accountpath);
                } else if(data.status == 0){
                    if(typeof data.impact !== 'undefined' && data.impact !== null && data.impact == "low")
                    {
                        //failed to add user into the git
                        //continue backup
                        localBackup_queue(type,accountname,accountpath);
                    }else if(typeof data.impact !== 'undefined' && data.impact !== null && data.impact == "medium")
                    {
                        //failed to init or failed to get init status
                        //go  to the next account
                        showLoading(data.info);
                        backupQueueCompleted(accountname,accountpath);
                    }else {
                        //high impact
                        hideLoading(10);
                        showDialogue(data.info ,"ERROR", "close");
                        toggleBackupLog(0);
                    }
                }
            }
        });
    });
}



function localBackup_queue(type,name,path) {
    showLoading("Account : "+name + "<br/> Initiating Files Backup <br/> Please Wait ..");
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
                accountname : name,
                accountpath : path,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                //status 1 => list of all the folders was created successfully
                //status 2 => No New Changes
                if(data.status == 1 || data.status == 2)
                {
                    contLocalBackup_queue(type,name,path);
                }
                else if(data.status == 0 && typeof data.impact !== 'undefined' && data.impact !== null && data.impact == "medium") {
                    //continue with the next account
                    showLoading(data.info);
                    backupQueueCompleted(name,path);
                }else{
                    hideLoading();
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                }
            }
        });
    });
}

function contLocalBackup_queue(type,name,path) {
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
                accountname:name,
                accountpath: path,
                type: type,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                //STATUS :
                //0=>Error
                //1 => cont folder backup
                //2=> No new chnages to commit
                //3=>
                //4=>Remaining files have been backed up ; end of backup loop
                if (data.status == 1) {
                    //call contlocalbackup
                    showLoading('Account : ' + name + '<br/>Backing Up Folder ' + data.folder + '<br/>Please Wait ......');
                    contLocalBackup_queue(type, name, path);
                }
                else if (data.status == 4 || data.status == 2)  //4 to indicate the end of the backup loop and 2 to indicate there no new chnages
                {
                    //TODO CONTINUE THE LOOP
                    backupQueueCompleted(name,path);
                }
                else if(data.status == 0 && typeof data.impact !== 'undefined' && data.impact !== null && data.impact == "medium") {
                    //move to next account
                    showLoading(data.info);
                    backupQueueCompleted(name,path);
                }else{
                    hideLoading(10);
                    showDialogue(data.info, "ERROR", "close");
                    toggleBackupLog(0);
                }
            }
        });
    });
}


function backupQueueCompleted(name,path)
{
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'backupQueueCompleted',
                task: 'backupQueueCompleted',
                accountpath : path,
                accountname : name,
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status ==1 )
                {
                    //continue backup Queue
                    showLoading("Backup Completed for Account : "+name);
                    continueBackupQueue();
                } else if(data.status ==2)
                {
                    //backup completed
                    hideLoading();
                    showDialogue("All the accounts have been backed up successfully", "UPDATE", "close");
                    $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                    errorLogInfo();
                    toggleBackupLog(0);
                }else {
                    //error
                    //TODO ERROR HANDLING
                    hideLoading();
                    $('#AccountGitBackupTable').dataTable().api().ajax.reload();
                    showDialogue(data.info, "ERROR", "close");
                    errorLogInfo();
                    toggleBackupLog(0);
                }

            }
        });
    });
}

function display_PrerequisiteInfo()
{
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'getPrerequisites',
                task: 'getPrerequisites',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 0)
                {
                    showDialogue(data.info, "Please check the Pre-Requisites for GitBackup", "close");
                }
            }
        });
    });
}

function errorLogInfo()
{
    jQuery(document).ready(function ($) {
        $.ajax({
            type: "POST",
            url: url,
            dataType: 'json',
            data: {
                option: option,
                controller: controller,
                action: 'showErrorLog',
                task: 'showErrorLog',
                centnounce: $('#centnounce').val()
            },
            success: function (data) {
                if(data.status == 1)
                {
                    bootbox.dialog({
                        message: data.info,
                        title: "Error Log",
                        main: {
                            label: "Close",
                            className: "btn-primary",
                            callback: function () {
                                window.close();
                            }
                        }
                    });
                    $(".modal-dialog").css("width", "900px");
                }else {
                    showDialogue("All the accounts have been backed up successfully", "UPDATE", "close");
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
                accountname : $('#accountname_session').val(),
                accountpath : $('#accountpath_session').val(),
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
}

