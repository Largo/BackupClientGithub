<?php

if(!php_sapi_name()==="cli") {
    die("This script must be run from the command line.");
}

require('composer_modules/autoload.php');

// use halite to create a private and public key to sign a sentence and verifiy it


use \ParagonIE\Halite\EncryptionKeyPair;
use \ParagonIE\Halite\Symmetric\AuthenticationKey;
use \ParagonIE\Halite\Symmetric\EncryptionKey;
use OTPHP\TOTP;

//TODO: output copy paste configuration for both sides

echo "Copy paste the following configuration for both sides:\n\n";





echo "client .env file:\n";

echo "CLIENT_PROJECTS=\"XY\"\n";  
echo "XY_URL=\"https://xy.ch/webbackupserver/backup.php\"\n";

$otp = OTPHP\TOTP::create();
$otp_secret = $otp->getSecret();
echo "XY_OTP_SECRET=\"" . $otp_secret . "\"\n";

$apikey_generator = OTPHP\TOTP::create();
$api_key = $apikey_generator->getSecret();
echo "XY_API_KEY=\"" . $api_key . "\"\n";

$alice_keypair = \ParagonIE\Halite\KeyFactory::generateEncryptionKeyPair();
$alice_secret = $alice_keypair->getSecretKey();
$alice_public = $alice_keypair->getPublicKey();
$xy_public_key = sodium_bin2hex($alice_public->getRawKeyMaterial());
$xy_private_key = sodium_bin2hex($alice_secret->getRawKeyMaterial());

$bob_keypair = \ParagonIE\Halite\KeyFactory::generateEncryptionKeyPair();
$bob_secret = $bob_keypair->getSecretKey();
$bob_public = $bob_keypair->getPublicKey();
$server_public_key = sodium_bin2hex($bob_public->getRawKeyMaterial());
$server_private_key = sodium_bin2hex($bob_secret->getRawKeyMaterial());


echo "XY_PUBLIC_KEY=\"" . $xy_public_key . "\"\n";
echo "XY_PRIVATE_KEY=\"" . $xy_private_key . "\"\n";
echo "XY_SERVER_PUBLIC_KEY=\"" . $server_public_key . "\"\n";

echo "\n";

echo "Server .env file \n\n";
echo "SERVER_OTP_SECRET=\"" . $otp_secret . "\"\n";
echo "SERVER_API_KEY=\"" . $api_key . "\"\n";


echo "CLIENT_PUBLIC_KEY=\"" . $xy_public_key . "\"\n";
echo "SERVER_PUBLIC_KEY=\"" . $server_public_key . "\"\n";
echo "SERVER_PRIVATE_KEY=\"" . $server_private_key . "\"\n";

$SERVER_DATABASE_HOST = "localhost";
$SERVER_DATABASE_NAME = "databasename";
$SERVER_DATABASE_USERNAME = "username";
$SERVER_DATABASE_PASSWORD = "password";

echo "SERVER_DATABASE_HOST=\"" . $SERVER_DATABASE_HOST . "\"\n";
echo "SERVER_DATABASE_NAME=\"" . $SERVER_DATABASE_NAME . "\"\n";
echo "SERVER_DATABASE_USERNAME=\"" . $SERVER_DATABASE_USERNAME . "\"\n";
echo "SERVER_DATABASE_PASSWORD=\"" . $SERVER_DATABASE_PASSWORD . "\"\n";

