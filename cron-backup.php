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

// Set the date and name for the backup files
date_default_timezone_set('America/Chicago');
$date = date("M-d-Y_H-i-s");
$backupname = "$url-backup-$date.zip";

// Check for newer versions of script
function file_get_data($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
    curl_setopt($ch, CURLOPT_URL, $url);
    $data = curl_exec($ch);
    curl_close($ch);
    return $data;
}

$latest_version = file_get_data('https://raw.github.com/jeremehancock/cron-backup-script-setup/master/version.txt');
$latest_version = preg_replace( "/\r|\n/", "", $latest_version );

if(!isset($installed_version)) {
echo "New version available!\nTo update simply re-install following instructions found here: http://www.rackspace.com/knowledge_center/article/scheduled-backup-cloud-sites-to-cloud-files\n";
}

elseif ($installed_version < $latest_version) {
echo "New version available!\nTo update simply re-install following instructions found here: http://www.rackspace.com/knowledge_center/article/scheduled-backup-cloud-sites-to-cloud-files\n";
}

if ($db_backup == "true") {
// Check mysql database credentials
$db_connection = mysql_connect($db_host,$db_user,$db_password);

if (!$db_connection) {
echo date("h:i:s")." -- Database connection failed! Check your database credentials in the cron-backup-config.php file.\n";
die();
}
else {
// Dump the mysql database
echo date("h:i:s")." -- Starting database dump...\n";
// First we ensure we are in the root of the site and not the root of account. This is needed since cron runs from the root of the account
chdir("$path");
chdir("../../");
// No longer will we need to check for mariadump
shell_exec("mysqldump -h $db_host -u $db_user --password='$db_password' $db_name > db_backup.sql");

echo date("h:i:s")." -- Database dump complete!\n";
}
}

// Backup site files
echo date("h:i:s")." -- Starting files backup...\n";
chdir("$path");
shell_exec("zip -9pr ../../sitebackup.zip .");
chdir("../../");

if (file_exists("sitebackup.zip")) {
echo date("h:i:s")." -- Files backup complete!\n";
}
else {
echo date("h:i:s")." -- File backup failed! Be sure your site is not over 4 gigs.\n";
if ($db_backup == "true") {
shell_exec("rm db_backup.sql");
}
die();
}

if ($db_backup == "true") {
// Compress DB and Site backup into one file
echo date("h:i:s")." -- Combining database and files into one archive started...\n";
shell_exec("zip -9pr $backupname sitebackup.zip db_backup.sql");

if (file_exists("$backupname")) {
echo date("h:i:s")." -- Combining database and files into one archive complete!\n";
}
else {
echo date("h:i:s")." -- Combining database and files into one archive failed! Be sure your site files plus the database is not over 4 gigs\n";
shell_exec("rm sitebackup.zip ; rm db_backup.sql");
die();
}
}
else {
// Rename sitebackup.zip
shell_exec("mv sitebackup.zip $backupname");
}

// md5 for local backup
$md5 = md5_file($backupname);

// Set API Timeout
define('RAXSDK_TIMEOUT', '3600');

// require Cloud Files API
require_once('cron-backup-api/lib/rackspace.php');

// Authenticate to Cloud Files
echo date("h:i:s")." -- Connecting to Cloud Files\n";
try {
define('AUTHURL', 'https://identity.api.rackspacecloud.com/v2.0/');
$mysecret = array(
    'username' => $username,
    'apiKey' => $key
);

echo date("h:i:s")." -- Connected to Cloud Files!\n";
// establish our credentials
$connection = new Rackspace(AUTHURL, $mysecret);
// now, connect to the ObjectStore service

$ostore = $connection->ObjectStore('cloudFiles', "$datacenter");
}

catch (HttpUnauthorizedError $e) {
if ($db_backup == "true") {
echo date("h:i:s")." -- Cloud Files API connection could not be established! Check your API credentials in the cron-backup-config.php file.\n";
shell_exec("rm $backupname ; rm sitebackup.zip ; rm db_backup.sql");
die();
}
else {
echo date("h:i:s")." -- Cloud Files API connection could not be established! Check your API credentials in the cron-backup-config.php file.\n";
shell_exec("rm $backupname");
die();
}
}

echo date("h:i:s")." -- Creating Cloud Files Container...\n";    
// create container if it doesn't already exist
$cont = $ostore->Container();
$cont->Create(array('name'=>"$url-cron-backups"));

echo date("h:i:s")." -- Cloud Files container created or already exists!\n";

echo date("h:i:s")." -- Moving backup to Cloud Files...\n";
// set zipit object
$obj = $cont->DataObject();

$obj->Create(array('name' => "$backupname", 'content_type' => 'application/x-gzip'), $filename="$backupname");

// get etag(md5)
$etag = $obj->hash;

// compare md5 wih etag
if ($md5 != $etag) {
$obj->Delete(array('name'=>"$backupname"));
echo date("h:i:s")." -- Backup failed integrity check! Please try again.\n";
}
else {
echo date("h:i:s")." -- Backup moved to Cloud Files Successful!\n";
}

if ($db_backup == "true") {
echo date("h:i:s")." -- Cleaning up local backups...\n";
//After your backup has been uploaded, remove the zip from the filesystem.
shell_exec("rm $backupname ; rm sitebackup.zip ; rm db_backup.sql");
echo date("h:i:s")." -- Local backups cleaned up!\n";
}
else {
shell_exec("rm $backupname");
}

echo date("h:i:s")." -- Backup complete!\n";
?>
