<?php
/*
    Client that connects to the server and downloads the backup.
    The client sends the onetime password and the APIKEY to the server.
    The server verifies the APIKEY and the onetime password.
    If the APIKEY and the onetime password are correct, the server
    creates a temporary file and writes the backup to it.
    The backup folder is different for each client.
    THE OTP IS encrypted using public key cryptography.
    The backup is encrypted by the server.

    This file can be run by a cronjob.


    Todo:
    - Maybe a GUI to configure the client
    - put backup into different folder per project
    - Send email if failed
    - define cronjob.
    - test backup if it contains sql (search for create)
    - test backup if it can be decrypted
    - add create database to sql export
*/

if(!php_sapi_name()==="cli") {
    die("This script must be run from the command line.");
}

require('composer_modules/autoload.php');
require('_download.php');

use ParagonIE\HiddenString\HiddenString;

// Load Secrets from dotenv file 
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Get the projects out of the env file. They are comma serparated.
$CLIENT_PROJECTS = explode(",", $_ENV["CLIENT_PROJECTS"]);
// iterate over projects
foreach($CLIENT_PROJECTS as $project) {
    echo "Backupping: " . $project . "\n";
    try {

        // Get the project specific variables
        $project = trim($project);
        // get the Onetime password secret
        $secret = new HiddenString($_ENV[$project . "_OTP_SECRET"]);
        // get the random APIKEY for additional security
        $apikey = new HiddenString($_ENV[$project . "_API_KEY"]);
        // get the url to the backup script
        $client_url = new HiddenString($_ENV[$project . "_URL"]);
        // get the public key for encryption
        $public_key = new HiddenString($_ENV[$project . "_PUBLIC_KEY"]);
        // get the private key for decryption
        $private_key = new HiddenString($_ENV[$project . "_PRIVATE_KEY"]);
        // get the backup server scripts public key for encryption
        $server_public_key = new HiddenString($_ENV[$project . "_SERVER_PUBLIC_KEY"]);
        // start download
        
        // backup path
        if(isset($_ENV["CLIENT_BACKUP_PATH"])) {
            $backupPath = $_ENV["CLIENT_BACKUP_PATH"];
        } else {
            $backupPath = null;
        }
    
        download($project, $client_url, $secret, $apikey, $private_key, $public_key, $server_public_key, $backupPath);
    } catch(Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
