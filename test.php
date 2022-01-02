<?php
$ch = curl_init($argv[1]);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => 1,
    CURLOPT_FOLLOWLOCATION => 1,
    CURLOPT_HEADER => 1,
    CURLOPT_NOBODY => 1,
]);
$data = curl_exec($ch);
$size = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
curl_close($ch);
echo "SIZE: $size\r\n";
?>