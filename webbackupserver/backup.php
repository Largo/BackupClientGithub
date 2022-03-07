<?php

/**
 * This script is used to backup the database.
 * The client sends the onetime password and the APIKEY to the server.
 * The server verifies the APIKEY and the onetime password.
 * If the APIKEY and the onetime password are correct, the server
 * creates a temporary file in memory and writes the backup to it.
 * It then echos the content of the file.
 * 
 * The backup is tested if it contains sql (search for create)
 * 
 * TODO:
 * - MySQLDump was changed, move change outside project. / document change
 * - Document how to install / can dependencies be removed?
 * - always install in seperate folder?
 */

$debug = false;

// Add exception handler to make sure httpcode 500 is returned on error
function exception_handler($exception) {
    http_response_code(500);
    global $debug;
    if($debug) {
        echo "Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString() . "\n";
    } else {
        echo "Uncaught exception: " , $exception->getMessage(), "\n";
    }
  }
  
set_exception_handler('exception_handler');

require('composer_modules/autoload.php');

use OTPHP\TOTP;
use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\EncryptionKeyPair;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\Symmetric\EncryptionKey;


try {
    // read dotfile
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    // Setup onetime password
    $otp = TOTP::create();
    $secret = new ParagonIE\HiddenString\HiddenString($_ENV["SERVER_OTP_SECRET"]);
    $apikey = new ParagonIE\HiddenString\HiddenString($_ENV["SERVER_API_KEY"]);

    // Add option server debug to try to output errors if debug activated
    if(isset($_ENV["SERVER_DEBUG"])) {
        $debug = $_ENV["SERVER_DEBUG"] === "1";
    } else {
        $debug = false;
    }

    if($debug) {
        // show errors
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
    }
    
    // Check if the APIKEY and the onetime password are correct
    if(empty($secret)) {
        throw new Exception("OTP secret not set");
    }
    if(empty($apikey)) {
        throw new Exception("APIKEY not set");
    }

    if(isset($_POST["APIKEY"])) {
    if($apikey->getString() !== $_POST["APIKEY"]) {
        throw new Exception("APIKEY not verified");
        exit;
    }
    } else {
        throw new Exception("APIKEY not verified");
        exit;
    }


    // check if otp is correct
    if(isset($_POST["otp"])) {
        $otp_post = new HiddenString($_POST["otp"]);
    } else {
        throw new Exception("OTP not set");
    }

    // check if public key of the client is sent
    if(isset($_ENV["CLIENT_PUBLIC_KEY"])) {
        $CLIENT_PUBLIC_KEY = new HiddenString($_ENV["CLIENT_PUBLIC_KEY"]);
    } else {
        throw new Exception("CLIENT_PUBLIC_KEY not set");
    }

    
    // safely load server keys
    $SERVER_PUBLIC_KEY = new HiddenString($_ENV["SERVER_PUBLIC_KEY"]);
    $SERVER_PRIVATE_KEY = new HiddenString($_ENV["SERVER_PRIVATE_KEY"]);

    $ourPrivateKey = new \ParagonIE\Halite\Asymmetric\EncryptionSecretKey(
        new HiddenString(
            sodium_hex2bin($SERVER_PRIVATE_KEY->getString())
        )
    );
    $theirPublicKey = new \ParagonIE\Halite\Asymmetric\EncryptionPublicKey(
        new HiddenString(
            sodium_hex2bin($CLIENT_PUBLIC_KEY->getString())
        )
    );

    // decrypt the otp with the client public key and our servers private key
    $otp_post_decrypted = \ParagonIE\Halite\Asymmetric\Crypto::decrypt(
        $otp_post->getString(),
        $ourPrivateKey,
        $theirPublicKey
    );

    $otp = OTPHP\TOTP::create(
        $secret->getString(),
        30,     // The period (30 seconds)
        'sha256', // The digest algorithm
        12       // The output will generate x digits
    );

    // verify if the decrypted otp password is correct
    if($otp->verify($otp_post_decrypted->getString()) && $otp_post->getString() != "") {
        //echo "OTP verified";
    } else {
        throw new Exception("OTP not verified");
        exit;
    }


    // Prepare connection to the database and check if we have all the login credentials
    $SERVER_DATABASE_HOST = new HiddenString($_ENV["SERVER_DATABASE_HOST"]);
    $SERVER_DATABASE_NAME = new HiddenString($_ENV["SERVER_DATABASE_NAME"]);
    $SERVER_DATABASE_USERNAME = new HiddenString($_ENV["SERVER_DATABASE_USERNAME"]);
    $SERVER_DATABASE_PASSWORD = new HiddenString($_ENV["SERVER_DATABASE_PASSWORD"]);


    if(empty($SERVER_DATABASE_HOST->getString())) {
        throw new Exception("SERVER_DATABASE_HOST not set");
    }
    if(empty($SERVER_DATABASE_NAME->getString())) {
        throw new Exception("SERVER_DATABASE_NAME not set");
    }
    if(empty($SERVER_DATABASE_USERNAME->getString())) {
        throw new Exception("SERVER_DATABASE_USERNAME not set");
    }
    if(empty($SERVER_DATABASE_PASSWORD->getString())) {
        throw new Exception("SERVER_DATABASE_PASSWORD not set");
    }


    // Function to export the database
    function export($SERVER_DATABASE_HOST, $SERVER_DATABASE_NAME, $SERVER_DATABASE_USERNAME, $SERVER_DATABASE_PASSWORD) {
        include_once(dirname(__FILE__) . '/ifsnop-mysqldump-php-fc9c119/src/Ifsnop/Mysqldump/Mysqldump.php');
        $dsn = new HiddenString("mysql:host=" . $SERVER_DATABASE_HOST->getString() . ";dbname=". $SERVER_DATABASE_NAME->getString() . ";charset=utf8");
        $dump = new Ifsnop\Mysqldump\Mysqldump($dsn->getString(), $SERVER_DATABASE_USERNAME->getString(), $SERVER_DATABASE_PASSWORD->getString(), [
            //'compress' => Ifsnop\Mysqldump\Mysqldump::GZIP,
        ]);

        // Set max filesize in memory before we need to flush to a temp file
        $memorySizeBeforeTempFile = 20 * 1024 * 1024;
        // Write the unencrypted dump to the memory buffer. The library had to be slightly modified to allow this.
        // This way we can avoid insecure temp files.
        $memoryPath = "php://temp/maxmemory:$memorySizeBeforeTempFile";
        $fp = fopen($memoryPath, 'r+');
        // dump to buffer
        $dump->start($fp);

        // Read what we have written out of the buffer by rewinding the buffer and then writing it into the variable.
        rewind($fp);
        // Protect the unencrypted backup in a hiddenString
        $unencryptedBackup = new HiddenString((string) stream_get_contents($fp));
        // close buffer
        fclose($fp);

        // return the unencrypted backup
        return $unencryptedBackup;
    }

    $unencryptedBackup = export($SERVER_DATABASE_HOST, $SERVER_DATABASE_NAME, $SERVER_DATABASE_USERNAME, $SERVER_DATABASE_PASSWORD);
    
    // test backup if it is correct by checking if it contains CREATE TABLE
    if(strpos($unencryptedBackup->getString(), "CREATE TABLE") === false) {
        throw new Exception("Backup is not correct");
        exit;
    }

    // encrypt the backup with the client public key and our servers private key
    $SERVER_PRIVATE_KEY = new HiddenString($_ENV["SERVER_PRIVATE_KEY"]);

    $ourPrivateKey = new \ParagonIE\Halite\Asymmetric\EncryptionSecretKey(
        new HiddenString(
            sodium_hex2bin($SERVER_PRIVATE_KEY->getString())
        )
    );
    
    $encryptedBackup = \ParagonIE\Halite\Asymmetric\Crypto::encrypt(
        $unencryptedBackup,
        $ourPrivateKey,
        $theirPublicKey
    );

    // output the encrypted backup to our client
    echo $encryptedBackup;
} catch (Exception $e) {
    // $e; // DO NOT output anything here, or it will be visible to the user. Set breakpoint here for debugging.
      if($debug) {
          throw new Exception("Error! " . $e->getMessage() . ' ' . $e->getTraceAsString());
      } else {
          throw new Exception("Error!");
      }
}