<?php

require_once "ors-config.php";

$url =
    "https://api.heigit.org/openrouteservice/v2/directions/driving-car";

$data = [
    "coordinates" => [
        [80.2707, 13.0827],   // Chennai: longitude, latitude
        [77.4892, 10.2381]    // Kodaikanal: longitude, latitude
    ]
];

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [
        "Authorization: " . $ORS_API_KEY,
        "Content-Type: application/json",
        "Accept: application/json"
    ],

    CURLOPT_POSTFIELDS =>
        json_encode($data),

    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);

if ($response === false) {

    die(
        "cURL Error: " .
        curl_error($ch)
    );

}

$httpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

curl_close($ch);

$result =
    json_decode(
        $response,
        true
    );

echo "<pre>";

echo "HTTP Status: ";
echo $httpCode;
echo "\n\n";


if (
    isset($result["routes"][0]["summary"])
) {

    $summary =
        $result["routes"][0]["summary"];

    echo "ROUTE FOUND ✅\n\n";

    echo "Distance: ";

    echo round(
        $summary["distance"] / 1000,
        2
    );

    echo " km\n";

    echo "Duration: ";

    echo round(
        $summary["duration"] / 60
    );

    echo " minutes\n\n";

    echo "Chennai → Kodaikanal route is working.";

} else {

    echo "API RESPONSE:\n\n";

    print_r($result);

}

echo "</pre>";