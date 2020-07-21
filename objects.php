<?php

require 'aws.phar';

// used classes
use Aws\S3\S3Client;
use Aws\Rekognition\RekognitionClient;

// aws credentials
$bucket = "";
$region = "";
$access_key_id = "";
$secret_access_key = "";

// s3 object
$s3 = new S3Client([
    'region' => $region,
    'version' => 'latest',
    'credentials' => [
        'key' => $access_key_id,
        'secret' => $secret_access_key,
    ]
]);

// aws rekognition object
$rekognition = new RekognitionClient([
    'region' => $region,
    'version' => 'latest',
    'credentials' => [
        'key' => $access_key_id,
        'secret' => $secret_access_key,
    ]
]);

if(isset($_REQUEST['imgUrl'])){
    $imgUrl = $_REQUEST['imgUrl'];

    try{
        // downloads image to server
        $tempFilePath = explode("?", basename($imgUrl))[0];
        $tempFile = fopen($tempFilePath, "w") or die("Error: Unable to open file.");
        $fileContents = file_get_contents($imgUrl);
        $tempFile = file_put_contents($tempFilePath, $fileContents);

        // uploads temp image file to s3 bucket
        $upload = $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $tempFilePath,
            'SourceFile' => $tempFilePath,
            'ACL' => 'public-read',
        ]);

        // Detects each face and describes its attributes
        $result = $rekognition->detectLabels([
            'Image' => [
                'S3Object' => [
                    'Bucket' => $bucket,
                    'Name' => $tempFilePath,
                ]
            ],
            'MinConfidence' => 50,
        ]);

        // deletes temp s3 image
        $s3del =$s3->deleteObject([
            'Bucket' => $bucket,
            'Key' => $tempFilePath
        ]);

        // garbage collection
        gc_collect_cycles();
        try {unlink($tempFilePath);} catch(Exception $ex) {}

        header('Content-Type: application/json');
        print(json_encode(array('result' => $result['Labels'])));
        exit;
    }
    catch(Exception $exp){
        exit($exp);
    }
}
?>