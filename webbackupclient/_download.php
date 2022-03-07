<?php

use OTPHP\TOTP;
use ParagonIE\HiddenString\HiddenString;
use ParagonIE\Halite\EncryptionKeyPair;
use ParagonIE\Halite\Symmetric\AuthenticationKey;
use ParagonIE\Halite\Symmetric\EncryptionKey;

// Function to download the dump
function download($clientname, $client_url, $OTP_SECRET, $API_KEY, $PRIVATE_KEY, $PUBLIC_KEY, $SERVER_PUBLIC_KEY, $backupPath = null) {

    if(empty($OTP_SECRET)) {
        die("OTP_SECRET CANNOT BE EMPTY");
    }

    // create a onetime password
    $otp = TOTP::create(
        $OTP_SECRET->getString(),
        30,     // The period (30 seconds)
        'sha256', // The digest algorithm
        12       // The output will generate x digits
    );

    // get an otp
    $otp_now = $otp->now();

    // prepare the encryption the otp with the servers public key
    $bob_public = new \ParagonIE\Halite\Asymmetric\EncryptionPublicKey(
        new HiddenString(
            sodium_hex2bin($SERVER_PUBLIC_KEY->getString())
        )
    );

    $ourPrivateKey = new \ParagonIE\Halite\Asymmetric\EncryptionSecretKey(
        new HiddenString(
            sodium_hex2bin($PRIVATE_KEY->getString())
        )
    );
    
    // encrypt the otp with the client private key and our servers private key
    $otp_post = \ParagonIE\Halite\Asymmetric\Crypto::encrypt(
        new HiddenString(
            $otp_now
        ),
        $ourPrivateKey,
        $bob_public
    );

    // prepare the post data to send with the request
    $data = ['otp' => $otp_post, 'APIKEY' => $API_KEY->getString()];
    
    $body = http_build_query($data);
    $url = $client_url->getString();
    
    // use curl to send post request to url
    $ch = curl_init();
    // set user agent for curl to avoid being banned
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:97.0) Gecko/20100101 Firefox/97.0');
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // required for HTTP error codes to be reported
    //curl_setopt($ch, CURLOPT_FAILONERROR, true);

    // execute the request
    $result = curl_exec ($ch);
    // check for errors
    $error = curl_errno($ch) ? curl_error($ch) : '';
    // get the http response code
    $responseCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    
    // check the response
    if(curl_errno($ch) === 0 && !empty($result) && $result !== false && $responseCode === 200) {
        $downloadContent = $result;
        // create folder temp
        if(!empty($backupPath)) {
            $path = $backupPath;
        } else {
            $path = __DIR__ . "/backups/" . $clientname;
        }
        
        if(!file_exists($path)){
            mkdir($path, 0700, true);
        }
        // create file temp/fileName
        $date = date("Y-m-d_H-i-s");

        // set the filename of the file and add the date
        $fileName = "$path/" . $date . "_" . $clientname . "_sql_dump.sql.encrypted";
        $file = fopen($fileName, 'w');
        // write the encrypted content to the file
        fwrite($file, $downloadContent);
        fclose($file);

        echo "\nDownload successful\n";
    } else {
        print_r($result);
        // Output error in the failure case
        print_r($error ? $error : '');
    
        echo "\nDownload failed\n";
    
        // TODO: send mail?
    }
    // close the curl request
    curl_close ($ch);
}