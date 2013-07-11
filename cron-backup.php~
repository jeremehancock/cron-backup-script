<?php
###############################################################
# Cron Backup Script
###############################################################
# Developed by Jereme Hancock for Cloud Sites
###############################################################

// specify namespace
   namespace OpenCloud;

// require Cron Backup Config
require_once('cron-backup-config.php');

#Set the date and name for the backup files
date_default_timezone_set('America/Chicago');
$date = date("M-d-Y_H-i-s");
$backupname = "backup-$date.tar.gz";

//Dump the mysql database
shell_exec("mysqldump -h $db_host -u $db_user --password='$db_password' $db_name > db_backup.sql");

//Backup Site
shell_exec("tar -czpvf sitebackup.tar.gz ./web/content");

//Compress DB and Site backup into one file
shell_exec("tar --exclude 'sitebackup' --remove-files -czpvf $backupname sitebackup.tar.gz db_backup.sql");

//Set API Timeout
define('RAXSDK_TIMEOUT', '3600');

// require Cloud Files API
require_once('cron-backup-api/lib/rackspace.php');

// authenticate to Cloud Files
define('AUTHURL', 'https://identity.api.rackspacecloud.com/v2.0/');
$mysecret = array(
    'username' => $username,
    'apiKey' => $key
);

// establish our credentials
$connection = new Rackspace(AUTHURL, $mysecret);
// now, connect to the ObjectStore service
$ostore = $connection->ObjectStore('cloudFiles', "$datacenter");
    
// create container if it doesn't already exist
$cont = $ostore->Container();
$cont->Create(array('name'=>"$url-cron-backups"));

// set zipit object
$obj = $cont->DataObject();

$obj->Create(array('name' => "$backupname", 'content_type' => 'application/x-gzip'), $filename="$backupname");

//After your backup has been uploaded, remove the tar ball from the filesystem.
shell_exec("rm $backupname");

?>
