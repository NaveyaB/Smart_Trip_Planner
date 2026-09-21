<?php

require_once "db.php";
require_once "ors-config.php";


/* =====================================================
   TEST LOCATIONS
===================================================== */

$startLocation = "Chennai";
$endLocation   = "Kodaikanal";


/* =====================================================
   GET LOCATION FROM DATABASE
===================================================== */

function getLocationCoordinates(
    mysqli $conn,
    string $locationName
) {

    $sql = "SELECT latitude, longitude
            FROM locations
            WHERE location_name = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param(
        "s",
        $locationName
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {

        $stmt->close();

        return null;
    }

    $row = $result->fetch_assoc();

    $stmt->close();

    return [

        "latitude" =>
            (float) $row["latitude"],

        "longitude" =>
            (float) $row["longitude"]

    ];
}


/* =====================================================
   START COORDINATES
===================================================== */

$start = getLocationCoordinates(
    $conn,
    $startLocation
);


/* =====================================================
   END COORDINATES
===================================================== */

$end = getLocationCoordinates(
    $conn,
    $endLocation
);


/* =====================================================
   CHECK LOCATIONS
===================================================== */

if (!$start) {

    die(
        "Starting location not found: " .
        htmlspecialchars(
            $startLocation
        )
    );

}

if (!$end) {

    die(
        "Destination not found: " .
        htmlspecialchars(
            $endLocation
        )
    );

}


/* =====================================================
   ORS NEEDS:
   [LONGITUDE, LATITUDE]
===================================================== */

$startCoordinates = [

    $start["longitude"],
    $start["latitude"]

];

$endCoordinates = [

    $end["longitude"],
    $end["latitude"]

];


/* =====================================================
   ORS ROUTE REQUEST
===================================================== */

$url =
    "https://api.heigit.org/" .
    "openrouteservice/" .
    "v2/directions/cycling-regular";


$requestData = [

    "coordinates" => [

        $startCoordinates,
        $endCoordinates

    ]

];


$ch = curl_init($url);


curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_TIMEOUT => 30,

    CURLOPT_HTTPHEADER => [

        "Authorization: " .
        $ORS_API_KEY,

        "Content-Type: application/json",

        "Accept: application/json"

    ],

    CURLOPT_POSTFIELDS =>
        json_encode(
            $requestData
        )

]);


$response =
    curl_exec($ch);


if ($response === false) {

    die(
        "ORS request failed: " .
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


/* =====================================================
   SHOW RESULT
===================================================== */

echo "<pre>";

echo "HTTP Status: ";
echo $httpCode;

echo "\n\n";


if (
    isset(
        $result["routes"][0]["summary"]
    )
) {

    $summary =
        $result["routes"][0]["summary"];


    $distanceKm =
        $summary["distance"] / 1000;


    $durationMinutes =
        $summary["duration"] / 60;


    $hours =
        (int) floor(
            $durationMinutes / 60
        );


    $minutes =
        (int) round(
            $durationMinutes % 60
        );


    echo "ROUTE FOUND ✅\n\n";


    echo "From: ";
    echo htmlspecialchars(
        $startLocation
    );

    echo "\n";


    echo "To: ";
    echo htmlspecialchars(
        $endLocation
    );

    echo "\n\n";


    echo "Distance: ";

    echo number_format(
        $distanceKm,
        2
    );

    echo " km\n";


    echo "Travel Time: ";

    echo $hours;
    echo " hr ";

    echo $minutes;
    echo " min\n\n";


    echo "Database Coordinates:\n";

    echo "Start: ";
    print_r(
        $startCoordinates
    );

    echo "\n";

    echo "End: ";
    print_r(
        $endCoordinates
    );

} else {

    echo "ORS RESPONSE:\n\n";

    print_r(
        $result
    );

}


echo "</pre>";