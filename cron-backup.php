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
$backupname = "$url-backup-$date.tar.gz";

//Dump the mysql database
echo "Starting database dump\n";
shell_exec("mysqldump -h $db_host -u $db_user --password='$db_password' $db_name > db_backup.sql");
echo "Database dump complete\n";

//Backup Site
echo "Starting files backup\n";
shell_exec("tar -czpvf sitebackup.tar.gz -C $path .");
echo "Files backup complete\n";

//Compress DB and Site backup into one file
echo "Combining database and files into one archive started\n";
shell_exec("tar --exclude 'sitebackup' --remove-files -czpvf $backupname sitebackup.tar.gz db_backup.sql");
echo "Combining database and files into one archive complete\n";

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

echo "Connecting to Cloud Files\n";
// establish our credentials
$connection = new Rackspace(AUTHURL, $mysecret);
// now, connect to the ObjectStore service

$ostore = $connection->ObjectStore('cloudFiles', "$datacenter");

echo "Creating Cloud Files Container\n";    
// create container if it doesn't already exist
$cont = $ostore->Container();
$cont->Create(array('name'=>"$url-cron-backups"));

echo "Moving backup to Cloud Files\n";
// set zipit object
$obj = $cont->DataObject();

$obj->Create(array('name' => "$backupname", 'content_type' => 'application/x-gzip'), $filename="$backupname");

echo "Cleaning up local backups\n";
//After your backup has been uploaded, remove the tar ball from the filesystem.
shell_exec("rm $backupname");

echo "Backup complete\n";
?>
