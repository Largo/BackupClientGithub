<?php

if(!php_sapi_name()==="cli") {
    die("This script must be run from the command line.");
}

require('composer_modules/autoload.php');

use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\EncryptionKeyPair;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\Symmetric\EncryptionKey;

// Load Secrets from dotenv file 
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

function decrypt($content, $private_key, $server_public_key) {
    $ourPrivateKey = new \ParagonIE\Halite\Asymmetric\EncryptionSecretKey(
        new HiddenString(
            sodium_hex2bin($private_key->getString())
        )
    );
    $theirPublicKey = new \ParagonIE\Halite\Asymmetric\EncryptionPublicKey(
        new HiddenString(
            sodium_hex2bin($server_public_key->getString())
        )
    );

    $otp_post_decrypted = \ParagonIE\Halite\Asymmetric\Crypto::decrypt(
        $content,
        $ourPrivateKey,
        $theirPublicKey
    );

    return $otp_post_decrypted;
}


$CLIENT_PROJECTS = explode(",", $_ENV["CLIENT_PROJECTS"]);
foreach($CLIENT_PROJECTS as $project) {
    $project = trim($project);
    $private_key = new HiddenString($_ENV[$project . "_PRIVATE_KEY"]);
    $server_public_key = new HiddenString($_ENV[$project . "_SERVER_PUBLIC_KEY"]);
    if(isset($_ENV["CLIENT_BACKUP_PATH"])) {
        $backupPath = $_ENV["CLIENT_BACKUP_PATH"];
    } else {
        $backupPath = "";
    }

    if(!empty($backupPath)) {
        $path = $backupPath;
    } else {
        $path = __DIR__ . "/backups/" . $project;
    }

    if(file_exists($path)){
       $files = glob($path . '/*.encrypted'); // get all file names
       foreach($files as $file) {
           try {
              // iterate files
              $filename = $file;
              $content = file_get_contents($filename);
              $decryptedContent = decrypt($content, $private_key, $server_public_key);
              if($decryptedContent) {
                echo "Decrypted file: " . $filename . "\n";
                $decryptedFilename = str_replace(".encrypted", "", $filename);
                file_put_contents($decryptedFilename, $decryptedContent->getString());
              } else {
                echo "Failed to decrypt file: " . $filename . "\n";
              }
           } catch(Exception $e) {
              echo "Failed to decrypt file: " . $filename . "\n";
              echo $e;
           }
       }
    }
}
