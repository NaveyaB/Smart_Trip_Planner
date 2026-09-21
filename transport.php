<?php

session_start();
include "db.php";
require_once "ors-config.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* =====================================================
   DESTINATION
===================================================== */

$place = trim($_GET["place"] ?? "Kodaikanal");

if ($place === "") {
    $place = "Kodaikanal";
}


/* =====================================================
   GET LATEST TRIP
===================================================== */

$stmt = $conn->prepare(
    "SELECT *
     FROM trips
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 1"
);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    header("Location: plan-trip.php");
    exit();
}

$trip = $result->fetch_assoc();

$stmt->close();
/* =====================================================
   STARTING LOCATION + DESTINATION
===================================================== */

$startingLocation = trim(
    $trip["starting_location"] ?? ""
);

$destination = trim($place);

if ($startingLocation === "") {
    $startingLocation = "Dindigul";
}
/* =====================================================
   OPENROUTESERVICE GEOCODING
===================================================== */

function getCoordinates($placeName, $apiKey)
{
    $url =
        "https://api.openrouteservice.org/geocode/search"
        . "?api_key="
        . urlencode($apiKey)
        . "&text="
        . urlencode($placeName . ", Tamil Nadu, India")
        . "&size=1";

    $ch = curl_init($url);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_TIMEOUT => 20,

        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]

    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $result = json_decode(
        $response,
        true
    );

    if (
        !isset(
            $result["features"][0]["geometry"]["coordinates"]
        )
    ) {
        return null;
    }

    return [
        "longitude" =>
            (float)$result["features"][0]["geometry"]["coordinates"][0],

        "latitude" =>
            (float)$result["features"][0]["geometry"]["coordinates"][1]
    ];
}


/* =====================================================
   GET START + DESTINATION COORDINATES
===================================================== */

$startCoordinates = getCoordinates(
    $startingLocation,
    $ORS_API_KEY
);

$destinationCoordinates = getCoordinates(
    $destination,
    $ORS_API_KEY
);
/* =====================================================
   CAR ROUTE - DISTANCE + TRAVEL TIME
===================================================== */

$carDistanceKm = null;
$carDurationMin = null;
$carRouteError = "";

if (
    $startCoordinates !== null &&
    $destinationCoordinates !== null
) {

    $routeUrl =
        "https://api.heigit.org/openrouteservice/v2/directions/driving-car";

    $routeData = [

        "coordinates" => [

            [
                $startCoordinates["longitude"],
                $startCoordinates["latitude"]
            ],

            [
                $destinationCoordinates["longitude"],
                $destinationCoordinates["latitude"]
            ]

        ]

    ];

    $ch = curl_init($routeUrl);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            "Authorization: " .
            $ORS_API_KEY,

            "Content-Type: application/json",

            "Accept: application/json"

        ],

        CURLOPT_POSTFIELDS =>
            json_encode($routeData),

        CURLOPT_TIMEOUT => 30

    ]);

    $routeResponse =
        curl_exec($ch);

    if ($routeResponse === false) {

        $carRouteError =
            curl_error($ch);

    } else {

        $routeHttpCode =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $routeResult =
            json_decode(
                $routeResponse,
                true
            );

        if (
            $routeHttpCode === 200 &&
            isset(
                $routeResult["routes"][0]["summary"]
            )
        ) {

            $routeSummary =
                $routeResult["routes"][0]["summary"];

            $carDistanceKm =
                round(
                    $routeSummary["distance"] / 1000,
                    1
                );

            $carDurationMin =
                round(
                    $routeSummary["duration"] / 60
                );

        } else {

            $carRouteError =
                "Car route could not be calculated.";
        }
    }

    curl_close($ch);

} else {

    $carRouteError =
        "Starting location or destination could not be found.";
}


/* =====================================================
   FORMAT CAR TRAVEL TIME
===================================================== */

$carTravelTime = "--";

if ($carDurationMin !== null) {

    $hours =
        intdiv(
            $carDurationMin,
            60
        );

    $minutes =
        $carDurationMin % 60;

    if ($hours > 0) {

        $carTravelTime =
            $hours .
            " hr " .
            $minutes .
            " min";

    } else {

        $carTravelTime =
            $minutes .
            " min";
    }
}
/* =====================================================
   BIKE ROUTE - DISTANCE + TRAVEL TIME
===================================================== */

$bikeDistanceKm = null;
$bikeDurationMin = null;
$bikeTravelTime = "--";
$bikeRouteError = "";

if (
    $startCoordinates !== null &&
    $destinationCoordinates !== null
) {

    $bikeUrl =
        "https://api.heigit.org/openrouteservice/v2/directions/cycling-regular";

    $bikeData = [

        "coordinates" => [

            [
                $startCoordinates["longitude"],
                $startCoordinates["latitude"]
            ],

            [
                $destinationCoordinates["longitude"],
                $destinationCoordinates["latitude"]
            ]

        ]

    ];

    $ch = curl_init($bikeUrl);

    curl_setopt_array($ch, [

        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_POST => true,

        CURLOPT_HTTPHEADER => [

            "Authorization: " . $ORS_API_KEY,

            "Content-Type: application/json",

            "Accept: application/json"

        ],

        CURLOPT_POSTFIELDS =>
            json_encode($bikeData),

        CURLOPT_TIMEOUT => 30

    ]);

    $bikeResponse = curl_exec($ch);

    if ($bikeResponse === false) {

        $bikeRouteError =
            curl_error($ch);

    } else {

        $bikeHttpCode =
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

        $bikeResult =
            json_decode(
                $bikeResponse,
                true
            );

        if (
            $bikeHttpCode === 200 &&
            isset(
                $bikeResult["routes"][0]["summary"]
            )
        ) {

            $bikeSummary =
                $bikeResult["routes"][0]["summary"];

            $bikeDistanceKm =
                round(
                    $bikeSummary["distance"] / 1000,
                    1
                );

            $bikeDurationMin =
                round(
                    $bikeSummary["duration"] / 60
                );

        } else {

            $bikeRouteError =
                "Bike route could not be calculated.";
        }
    }

    curl_close($ch);

} else {

    $bikeRouteError =
        "Starting location or destination could not be found.";
}


/* =====================================================
   FORMAT BIKE TRAVEL TIME
===================================================== */

if ($bikeDurationMin !== null) {

    $hours =
        intdiv(
            $bikeDurationMin,
            60
        );

    $minutes =
        $bikeDurationMin % 60;

    if ($hours > 0) {

        $bikeTravelTime =
            $hours .
            " hr " .
            $minutes .
            " min";

    } else {

        $bikeTravelTime =
            $minutes .
            " min";
    }
}


/* =====================================================
   TRIP VALUES
===================================================== */

$totalBudget = (float)($trip["total_budget"] ?? 0);

$members = max(
    1,
    (int)($trip["members"] ?? 1)
);

$days = max(
    1,
    (int)($trip["days"] ?? 1)
);


/* =====================================================
   CURRENT SELECTED TRANSPORT
===================================================== */

$currentTransport = "";

if (
    isset($_SESSION["selected_transport"]["type"]) &&
    $_SESSION["selected_transport"]["type"] !== ""
) {
    $currentTransport =
        $_SESSION["selected_transport"]["type"];
}


/* =====================================================
   SELECT TRANSPORT
===================================================== */

$error = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["transport_type"])
) {

    $transportType =
        trim($_POST["transport_type"]);

    $allowed = [
        "Car",
        "Bike",
        "Bus",
        "Train"
    ];

    if (
        in_array(
            $transportType,
            $allowed,
            true
        )
    ) {

        
    
    
    
  $_SESSION["selected_transport"] = [

    "type" => $transportType,

    "destination" => $place,

    "note" => "Transport is not included in your budget."
];

/* Save selected transport + correct arrival time */

$tripId = (int)($trip["id"] ?? 0);
$updatedArrivalTime = null;


/* Car / Bike route duration */
if ($transportType === "Car" && $carDurationMin !== null) {

    $durationToUse = $carDurationMin;

} elseif ($transportType === "Bike" && $bikeDurationMin !== null) {

    $durationToUse = $bikeDurationMin;

} else {

    $durationToUse = null;
}


/* Calculate arrival time only for Car / Bike */
if (
    $durationToUse !== null &&
    !empty($trip["start_date"]) &&
    !empty($trip["departure_time"])
) {

    try {

        $travelStart = new DateTime(
            $trip["start_date"] . " " . $trip["departure_time"]
        );

        $travelStart->modify(
            "+" . $durationToUse . " minutes"
        );

        $updatedArrivalTime =
            $travelStart->format("H:i:s");

    } catch (Exception $e) {

        $updatedArrivalTime = null;
    }
}


/* Save to database */

if ($transportType === "Car" || $transportType === "Bike") {

    $updateStmt = $conn->prepare("
        UPDATE trips
        SET destination = ?, transport = ?, arrival_time = ?
        WHERE id = ?
        AND user_id = ?
    ");

    if ($updateStmt) {

        $updateStmt->bind_param(
            "sssii",
            $place,
            $transportType,
            $updatedArrivalTime,
            $tripId,
            $user_id
        );

        $updateStmt->execute();
        $updateStmt->close();
    }

} else {

    /* Bus / Train have no fake arrival time */

    $updateStmt = $conn->prepare("
        UPDATE trips
        SET destination = ?, transport = ?, arrival_time = NULL
        WHERE id = ?
        AND user_id = ?
    ");

    if ($updateStmt) {

        $updateStmt->bind_param(
            "ssii",
            $place,
            $transportType,
            $tripId,
            $user_id
        );

        $updateStmt->execute();
        $updateStmt->close();
    }
}

$currentTransport = $transportType;
header("Location: my-trips.php?place=" . urlencode($place));
exit();} else {

        $error =
            "Please select a transport option.";
    }
}


/* =====================================================
   GOOGLE MAP LINKS
===================================================== */

$placeQuery =
    urlencode(
        $place . " Tamil Nadu"
    );

$busMap =
    "https://www.google.com/maps/search/?api=1&query=" .
    $placeQuery .
    "+Bus+Stand";

$trainMap =
    "https://www.google.com/maps/search/?api=1&query=" .
    $placeQuery .
    "+Railway+Station";


/* =====================================================
   TRANSPORT DATA
===================================================== */

$transportOptions = [

    [
        "type" => "Car",
        "category" => "PERSONAL",
        "icon" => "fa-solid fa-car-side",
        "title" => "Car",
        "description" =>
            "Flexible travel with direct access to your selected stay.",
        "meta1_icon" => "fa-solid fa-road",
        "meta1" => "Route distance",
        "meta2_icon" => "fa-regular fa-clock",
        "meta2" => "Estimated travel time",
        "badge" => "ROUTE",
        "map" => "",
        "animation" => "car-card"
    ],

    [
        "type" => "Bike",
        "category" => "PERSONAL",
        "icon" => "fa-solid fa-motorcycle",
        "title" => "Bike",
        "description" =>
            "A flexible choice for scenic routes and short local travel.",
        "meta1_icon" => "fa-solid fa-road",
        "meta1" => "Route distance",
        "meta2_icon" => "fa-regular fa-clock",
        "meta2" => "Estimated travel time",
        "badge" => "ROUTE",
        "map" => "",
        "animation" => "bike-card"
    ],

    [
        "type" => "Bus",
        "category" => "PUBLIC TRANSPORT",
        "icon" => "fa-solid fa-bus",
        "title" => "Bus",
        "description" =>
            "Check route details, departure information and nearby bus points.",
        "meta1_icon" => "fa-solid fa-route",
        "meta1" => "Live route support",
        "meta2_icon" => "fa-regular fa-clock",
        "meta2" => "Departure timing",
        "badge" => "LIVE",
        "map" => $busMap,
        "animation" => "bus-card"
    ],

    [
        "type" => "Train",
        "category" => "PUBLIC TRANSPORT",
        "icon" => "fa-solid fa-train",
        "title" => "Train",
        "description" =>
            "Check train routes, station details and available timing separately.",
        "meta1_icon" => "fa-solid fa-route",
        "meta1" => "Live route support",
        "meta2_icon" => "fa-regular fa-clock",
        "meta2" => "Train timing",
        "badge" => "LIVE",
        "map" => $trainMap,
        "animation" => "train-card"
    ]
];

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    Transport |
    <?php echo htmlspecialchars($place); ?>
</title>


<link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800;900&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>


<style>

/* =====================================================
   VARIABLES
===================================================== */

:root{

    --green-dark:#12351e;
    --green-deep:#164b28;
    --green:#2d7544;
    --green-main:#3d8752;
    --green-light:#cfe8d4;
    --green-soft:#e8f5ea;
    --green-pale:#f3faf4;

    --text:#102d18;
    --muted:#49634f;

    --white:#ffffff;

    --shadow:
        0 16px 36px
        rgba(28,82,42,.14);
}


/* =====================================================
   RESET
===================================================== */

*{
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{
    margin:0;

    font-family:
        "Manrope",
        Arial,
        sans-serif;

    color:var(--text);

    overflow-x:hidden;

    min-height:100vh;

    /*
       BACKGROUND IMAGE
       Change this image path later only if needed.
    */

   background:
    linear-gradient(
        rgba(225,243,228,.88),
        rgba(211,234,216,.94)
    ),
    url("image/transport-bg.png")
    center / cover fixed no-repeat;

}    


/* =====================================================
   BACKGROUND LIGHT EFFECTS
===================================================== */

body::before{

    content:"";

    position:fixed;

    width:430px;
    height:430px;

    top:-180px;
    right:-120px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(73,145,87,.17),
            transparent 70%
        );

    pointer-events:none;

    animation:
        floatGlow 7s ease-in-out infinite;
}

body::after{

    content:"";

    position:fixed;

    width:360px;
    height:360px;

    bottom:-180px;
    left:-120px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(75,140,87,.11),
            transparent 70%
        );

    pointer-events:none;

    animation:
        floatGlowReverse 9s ease-in-out infinite;
}


/* =====================================================
   PAGE
===================================================== */

.page{

    position:relative;

    z-index:2;

    max-width:1140px;

    margin:0 auto;

    padding:
        28px 20px 65px;
}


/* =====================================================
   TOPBAR
===================================================== */

.topbar{

    display:flex;

    align-items:center;

    justify-content:space-between;

    margin-bottom:20px;

    animation:
        topEnter .7s ease both;
}

.back-btn{

    display:inline-flex;

    align-items:center;

    gap:9px;

    padding:
        10px 14px;

    border-radius:11px;

    background:
        rgba(248,253,249,.82);

    border:
        1px solid #afd0b5;

    color:#28653a;

    text-decoration:none;

    font-size:13px;

    font-weight:900;

    transition:.3s ease;

    box-shadow:
        0 5px 13px
        rgba(32,83,43,.05);
}

.back-btn:hover{

    transform:
        translateX(-4px);

    background:#ffffff;

    box-shadow:
        0 9px 18px
        rgba(32,83,43,.10);
}

.brand{

    color:#1b5d31;

    font-size:18px;

    font-weight:900;
}


/* =====================================================
   HERO
===================================================== */

.hero{

    position:relative;

    overflow:hidden;

    padding:34px;

    border-radius:28px;

    background:
        linear-gradient(
            135deg,
            rgba(200,229,205,.96),
            rgba(181,216,188,.95)
        );

    border:
        1px solid #8eb996;

    box-shadow:
        var(--shadow);

    animation:
        heroEnter .8s ease both;
}

.hero::before{

    content:"";

    position:absolute;

    width:270px;
    height:270px;

    top:-140px;
    right:-100px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(255,255,255,.58),
            transparent 70%
        );

    animation:
        heroGlow 5s ease-in-out infinite;
}

.hero-kicker{

    position:relative;

    color:#27683a;

    font-size:11px;

    font-weight:900;

    letter-spacing:1.8px;
}

.hero h1{

    position:relative;

    margin:
        8px 0 7px;

    color:#0e2b16;

    font-size:39px;

    line-height:1.12;

    font-weight:900;
}

.hero p{

    position:relative;

    max-width:760px;

    margin:0;

    color:#395941;

    font-size:14px;

    line-height:1.75;

    font-weight:600;
}


/* =====================================================
   TRIP SUMMARY
===================================================== */

.trip-summary{

    position:relative;

    display:flex;

    flex-wrap:wrap;

    gap:11px;

    margin-top:22px;
}

.summary-card{

    min-width:175px;

    padding:
        13px 15px;

    border-radius:15px;

    background:
        rgba(247,252,248,.86);

    border:
        1px solid #a6cbaa;

    box-shadow:
        0 8px 18px
        rgba(30,81,42,.07);

    transition:.3s ease;
}

.summary-card:hover{

    transform:
        translateY(-3px);

    box-shadow:
        0 13px 25px
        rgba(30,81,42,.11);
}

.summary-card span{

    display:block;

    color:#526e5a;

    font-size:10px;

    font-weight:900;
}

.summary-card strong{

    display:block;

    margin-top:4px;

    color:#143820;

    font-size:20px;

    line-height:1.2;

    font-weight:900;
}


/* =====================================================
   FINAL COST NOTICE
===================================================== */

.cost-notice{

    position:relative;

    margin-top:20px;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:15px;

    padding:
        18px 22px;

    border-radius:18px;

    background:
        linear-gradient(
            135deg,
            rgba(217,239,221,.98),
            rgba(198,226,203,.98)
        );

    border:
        2px solid #79ab83;

    box-shadow:
        0 14px 28px
        rgba(34,87,46,.15),
        0 0 28px
        rgba(68,145,82,.13);

    overflow:hidden;

    animation:
        noticeEnter .9s ease both;
}

.cost-notice::before{

    content:"";

    position:absolute;

    left:-120%;

    top:0;

    width:50%;

    height:100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.48),
            transparent
        );

    transform:
        skewX(-22deg);

    animation:
        noticeShine 4.5s ease-in-out infinite;
}

.cost-icon{

    position:relative;

    width:50px;
    height:50px;

    flex:0 0 50px;

    display:grid;

    place-items:center;

    border-radius:15px;

    background:
        linear-gradient(
            145deg,
            #4d965d,
            #266139
        );

    color:#ffffff;

    font-size:21px;

    box-shadow:
        0 10px 21px
        rgba(34,87,46,.21);

    animation:
        walletPulse 2.7s ease-in-out infinite;
}

.cost-text{

    position:relative;

    text-align:center;
}

.cost-text strong{

    display:block;

    color:#0f2f19;

    font-size:19px;

    line-height:1.35;

    font-weight:900;
}


/* =====================================================
   ERROR
===================================================== */

.error{

    margin-top:15px;

    padding:13px 15px;

    border-radius:12px;

    background:#f8dddd;

    border:1px solid #dfaaaa;

    color:#792929;

    font-size:12px;

    font-weight:800;
}


/* =====================================================
   SECTION
===================================================== */

.section{

    margin-top:32px;
}

.section-title{

    margin-bottom:18px;
}

.section-title span{

    color:#28693d;

    font-size:11px;

    font-weight:900;

    letter-spacing:1.5px;
}

.section-title h2{

    margin:
        6px 0;

    color:#102d18;

    font-size:31px;

    line-height:1.15;

    font-weight:900;
}

.section-title p{

    margin:0;

    color:#496650;

    font-size:13px;

    line-height:1.65;

    font-weight:600;
}


/* =====================================================
   GRID
===================================================== */

.transport-grid{

    display:grid;

    grid-template-columns:
        repeat(4,1fr);

    gap:16px;
}


/* =====================================================
   CARD
===================================================== */

.transport-card{

    position:relative;

    overflow:hidden;

    min-height:425px;

    border-radius:22px;

    background:
        linear-gradient(
            150deg,
            rgba(232,246,235,.98),
            rgba(204,228,209,.98)
        );

    border:
        1px solid #94bf9c;

    box-shadow:
        0 12px 27px
        rgba(31,83,43,.11);

    transition:
        transform .35s ease,
        box-shadow .35s ease,
        border-color .35s ease;

    animation-duration:.8s;

    animation-timing-function:ease;

    animation-fill-mode:both;
}

.transport-card.car-card{

    animation-name:
        carReveal;
}

.transport-card.bike-card{

    animation-name:
        bikeReveal;

    animation-delay:.08s;
}

.transport-card.bus-card{

    animation-name:
        busReveal;

    animation-delay:.16s;
}

.transport-card.train-card{

    animation-name:
        trainReveal;

    animation-delay:.24s;
}

.transport-card:hover{

    transform:
        translateY(-7px);

    border-color:#5c9d69;

    box-shadow:
        0 22px 42px
        rgba(29,82,42,.18),
        0 0 30px
        rgba(72,147,87,.15);
}


/* =====================================================
   TOP SHINE
===================================================== */

.transport-card::before{

    content:"";

    position:absolute;

    top:0;

    left:-120%;

    width:60%;

    height:100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.30),
            transparent
        );

    transform:
        skewX(-22deg);

    transition:.4s ease;
}

.transport-card:hover::before{

    animation:
        cardShine .9s ease;
}


/* =====================================================
   GREEN GLOW LINE
===================================================== */

.transport-card::after{

    content:"";

    position:absolute;

    left:7%;

    bottom:0;

    width:86%;

    height:3px;

    border-radius:50%;

    background:
        linear-gradient(
            90deg,
            transparent,
            #4f985e,
            transparent
        );

    filter:blur(1px);

    opacity:.55;

    transition:.35s ease;
}

.transport-card:hover::after{

    width:100%;

    left:0;

    opacity:1;

    box-shadow:
        0 0 16px
        rgba(67,142,80,.38);
}


/* =====================================================
   BADGE
===================================================== */

.live-badge{

    position:absolute;

    top:14px;

    right:14px;

    z-index:4;

    display:inline-flex;

    align-items:center;

    gap:5px;

    padding:
        6px 9px;

    border-radius:999px;

    background:
        rgba(250,253,250,.96);

    border:
        1px solid #a8cbaa;

    color:#28683b;

    font-size:8px;

    font-weight:900;

    letter-spacing:.6px;

    box-shadow:
        0 6px 13px
        rgba(31,82,42,.07);
}

.live-dot{

    width:6px;
    height:6px;

    border-radius:50%;

    background:#4d9b60;

    animation:
        livePulse 1.5s infinite;
}


/* =====================================================
   ICON AREA
===================================================== */

.transport-icon-wrap{

    height:145px;

    display:grid;

    place-items:center;

    background:
        linear-gradient(
            145deg,
            rgba(217,237,220,.98),
            rgba(192,222,198,.98)
        );

    border-bottom:
        1px solid #a9cbaa;

    overflow:hidden;
}

.transport-icon{

    position:relative;

    width:72px;
    height:72px;

    display:grid;

    place-items:center;

    border-radius:21px;

    background:
        linear-gradient(
            145deg,
            #4f955d,
            #255f37
        );

    color:#ffffff;

    font-size:29px;

    box-shadow:
        0 14px 27px
        rgba(34,88,47,.23);

    transition:
        .35s ease;
}

.transport-card:hover .transport-icon{

    box-shadow:
        0 0 0 7px
        rgba(90,159,101,.13),
        0 0 30px
        rgba(61,135,77,.27);
}


/* Different icon motion */

.car-card:hover .transport-icon{

    transform:
        translateX(4px)
        scale(1.08);
}

.bike-card:hover .transport-icon{

    transform:
        rotate(-6deg)
        scale(1.08);
}

.bus-card:hover .transport-icon{

    animation:
        busMove .7s ease;
}

.train-card:hover .transport-icon{

    animation:
        trainMove .7s ease;
}


/* =====================================================
   BODY
===================================================== */

.transport-body{

    padding:
        18px;
}

.transport-type{

    display:inline-block;

    padding:
        6px 9px;

    border-radius:999px;

    background:
        #f5fbf6;

    border:
        1px solid #add0b3;

    color:#28683b;

    font-size:9px;

    font-weight:900;
}

.transport-body h3{

    margin:
        11px 0 7px;

    color:#102d18;

    font-size:21px;

    line-height:1.15;

    font-weight:900;
}

.transport-body p{

    margin:0;

    min-height:63px;

    color:#44614b;

    font-size:12px;

    line-height:1.62;

    font-weight:600;
}


/* =====================================================
   META
===================================================== */

.transport-meta{

    display:grid;

    gap:8px;

    margin-top:14px;
}

.meta-row{

    display:flex;

    align-items:center;

    gap:9px;

    padding:
        9px 10px;

    border-radius:10px;

    background:
        rgba(247,252,248,.82);

    border:
        1px solid #b1d1b7;

    color:#38583f;

    font-size:10px;

    font-weight:800;

    transition:.25s ease;
}

.meta-row:hover{

    transform:
        translateX(3px);

    background:#ffffff;
}

.meta-row i{

    width:18px;

    color:#286c3d;

    font-size:13px;
}


/* =====================================================
   MAP BUTTON BUS / TRAIN
===================================================== */

.map-box{

    margin-top:10px;

    padding:8px;

    border-radius:10px;

    background:
        linear-gradient(
            135deg,
            #ddf0e0,
            #cfe6d3
        );

    border:
        1px solid #a9ccb0;
}

.map-button{

    width:100%;

    display:flex;

    align-items:center;

    justify-content:center;

    gap:7px;

    padding:
        9px 10px;

    border-radius:9px;

    background:
        #ffffff;

    border:
        1px solid #9fc5a7;

    color:#236138;

    text-decoration:none;

    font-size:10px;

    font-weight:900;

    transition:.3s ease;
}

.map-button:hover{

    background:
        #d8ecdc;

    color:#184d29;

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 17px
        rgba(34,89,46,.12);
}


/* =====================================================
   RADIO
===================================================== */

.transport-radio{

    display:none;
}


/* =====================================================
   SELECT BUTTON DEFAULT WHITE
===================================================== */

.transport-select{

    display:flex;

    align-items:center;

    justify-content:center;

    gap:8px;

    width:100%;

    margin-top:14px;

    padding:
        11px;

    border-radius:11px;

    background:
        #ffffff;

    border:
        1px solid #a8caaF;

    color:#28663a;

    cursor:pointer;

    font-size:11px;

    font-weight:900;

    box-shadow:
        0 6px 13px
        rgba(32,81,42,.05);

    transition:
        .3s ease;
}

.transport-select:hover{

    transform:
        translateY(-2px);

    border-color:
        #6d9f77;

    box-shadow:
        0 9px 18px
        rgba(31,84,44,.10);
}


/* =====================================================
   SELECTED = GREEN
===================================================== */

.transport-radio:checked
+ .transport-select{

    background:
        linear-gradient(
            135deg,
            #347d49,
            #205d34
        );

    border-color:
        #28693c;

    color:
        #ffffff;

    box-shadow:
        0 0 0 3px
        #acd1b3,
        0 0 27px
        rgba(51,126,70,.29);

    transform:
        translateY(-2px);
}

.transport-radio:checked
+ .transport-select i{

    animation:
        checkPop .45s ease;
}


/* =====================================================
   CONTINUE
===================================================== */

.continue-box{

    margin-top:26px;

    padding:18px;

    display:flex;

    justify-content:flex-end;

    border-radius:18px;

    background:
        linear-gradient(
            135deg,
            rgba(220,238,223,.96),
            rgba(202,226,207,.96)
        );

    border:
        1px solid #9ec5a5;

    box-shadow:
        0 10px 24px
        rgba(32,83,43,.08);
}

.continue-btn{

    position:relative;

    overflow:hidden;

    border:0;

    min-width:225px;

    padding:
        14px 21px;

    border-radius:12px;

    background:
        linear-gradient(
            135deg,
            #347e49,
            #205d34
        );

    color:#ffffff;

    cursor:pointer;

    font-family:inherit;

    font-size:13px;

    font-weight:900;

    box-shadow:
        0 12px 24px
        rgba(31,87,45,.21);

    transition:.3s ease;
}

.continue-btn::before{

    content:"";

    position:absolute;

    left:-120%;

    top:0;

    width:55%;

    height:100%;

    background:
        linear-gradient(
            90deg,
            transparent,
            rgba(255,255,255,.42),
            transparent
        );

    transform:
        skewX(-20deg);
}

.continue-btn:hover::before{

    animation:
        buttonShine .8s ease;
}

.continue-btn:hover{

    transform:
        translateY(-3px);

    box-shadow:
        0 18px 32px
        rgba(31,87,45,.28),
        0 0 22px
        rgba(61,141,78,.17);
}

.continue-btn:active{

    transform:
        scale(.97);
}

.continue-btn i{

    margin-left:6px;

    transition:.3s ease;
}

.continue-btn:hover i{

    transform:
        translateX(5px);
}


/* =====================================================
   ANIMATIONS
===================================================== */

@keyframes topEnter{

    from{
        opacity:0;
        transform:
            translateY(-12px);
    }

    to{
        opacity:1;
        transform:
            translateY(0);
    }
}

@keyframes heroEnter{

    from{
        opacity:0;
        transform:
            translateY(20px)
            scale(.98);
    }

    to{
        opacity:1;
        transform:
            translateY(0)
            scale(1);
    }
}

@keyframes noticeEnter{

    from{
        opacity:0;
        transform:
            translateY(14px)
            scale(.98);
    }

    to{
        opacity:1;
        transform:
            translateY(0)
            scale(1);
    }
}

@keyframes noticeShine{

    0%,35%{
        left:-120%;
    }

    55%,100%{
        left:150%;
    }
}

@keyframes walletPulse{

    0%,100%{
        transform:scale(1);
    }

    50%{
        transform:
            scale(1.06)
            rotate(-2deg);
    }
}

@keyframes heroGlow{

    0%,100%{
        transform:
            translate(0,0)
            scale(1);
    }

    50%{
        transform:
            translate(-13px,16px)
            scale(1.08);
    }
}

@keyframes floatGlow{

    0%,100%{
        transform:
            translate(0,0);
    }

    50%{
        transform:
            translate(-17px,18px);
    }
}

@keyframes floatGlowReverse{

    0%,100%{
        transform:
            translate(0,0);
    }

    50%{
        transform:
            translate(17px,-15px);
    }
}

@keyframes carReveal{

    from{
        opacity:0;
        transform:
            translateX(-35px)
            rotate(-2deg);
    }

    to{
        opacity:1;
        transform:
            translateX(0)
            rotate(0);
    }
}

@keyframes bikeReveal{

    from{
        opacity:0;
        transform:
            translateY(35px)
            rotate(3deg);
    }

    to{
        opacity:1;
        transform:
            translateY(0)
            rotate(0);
    }
}

@keyframes busReveal{

    from{
        opacity:0;
        transform:
            translateX(35px)
            scale(.96);
    }

    to{
        opacity:1;
        transform:
            translateX(0)
            scale(1);
    }
}

@keyframes trainReveal{

    from{
        opacity:0;
        transform:
            translateY(-25px)
            scale(.96);
    }

    to{
        opacity:1;
        transform:
            translateY(0)
            scale(1);
    }
}

@keyframes cardShine{

    from{
        left:-120%;
    }

    to{
        left:145%;
    }
}

@keyframes busMove{

    0%{
        transform:
            translateX(0);
    }

    45%{
        transform:
            translateX(9px);
    }

    75%{
        transform:
            translateX(-3px);
    }

    100%{
        transform:
            translateX(0);
    }
}

@keyframes trainMove{

    0%{
        transform:
            translateX(0);
    }

    45%{
        transform:
            translateX(10px);
    }

    100%{
        transform:
            translateX(0);
    }
}

@keyframes checkPop{

    0%{
        transform:
            scale(.55)
            rotate(-20deg);
    }

    70%{
        transform:
            scale(1.25)
            rotate(5deg);
    }

    100%{
        transform:
            scale(1)
            rotate(0);
    }
}

@keyframes livePulse{

    0%{
        transform:scale(.85);

        box-shadow:
            0 0 0 0
            rgba(76,154,93,.45);
    }

    70%{
        transform:scale(1);

        box-shadow:
            0 0 0 7px
            rgba(76,154,93,0);
    }

    100%{
        transform:scale(.85);
    }
}

@keyframes buttonShine{

    from{
        left:-120%;
    }

    to{
        left:150%;
    }
}


/* =====================================================
   RESPONSIVE
===================================================== */

@media(max-width:1000px){

    .transport-grid{
        grid-template-columns:
            repeat(2,1fr);
    }

}


@media(max-width:620px){

    .page{

        padding:
            20px 13px 45px;
    }

    .brand{

        display:none;
    }

    .hero{

        padding:24px;

        border-radius:22px;
    }

    .hero h1{

        font-size:30px;
    }

    .hero p{

        font-size:13px;
    }

    .section-title h2{

        font-size:27px;
    }

    .transport-grid{

        grid-template-columns:
            1fr;
    }

    .transport-card{

        min-height:auto;
    }

    .cost-notice{

        justify-content:flex-start;

        text-align:left;
    }

    .cost-text strong{

        font-size:16px;
    }

    .continue-box{

        justify-content:stretch;
    }

    .continue-btn{

        width:100%;

        min-width:0;
    }

}

</style>

</head>


<body>

<div class="page">


    <!-- =================================================
         TOPBAR
    ================================================== -->

    <div class="topbar">

        <a
            href="stay-food.php?place=<?php
            echo urlencode($place);
            ?>"
            class="back-btn"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Back to Stay & Food

        </a>


        <div class="brand">

            Smart Budget Trip Planner

        </div>

    </div>


    <!-- =================================================
         HERO
    ================================================== -->

    <section class="hero">

        <div class="hero-kicker">

            STEP 3 · TRANSPORT

        </div>


        <h1>

            How will you travel?

        </h1>


        <p>

            Choose your preferred way to reach
            <?php
            echo htmlspecialchars($place);
            ?>.
            Route and timing information are shown
            separately from your trip budget.

        </p>


        <div class="trip-summary">


            <div class="summary-card">

                <span>
                    DESTINATION
                </span>

                <strong>

                    <?php
                    echo htmlspecialchars($place);
                    ?>

                </strong>

            </div>


            <div class="summary-card">

                <span>
                    TRAVELERS
                </span>

                <strong>

                    <?php
                    echo $members;
                    ?>

                </strong>

            </div>


            <div class="summary-card">

                <span>
                    TRIP DAYS
                </span>

                <strong>

                    <?php
                    echo $days;
                    ?>

                </strong>

            </div>


            <div class="summary-card">

                <span>
                    TRIP BUDGET
                </span>

                <strong>

                    ₹<?php
                    echo number_format(
                        $totalBudget
                    );
                    ?>

                </strong>

            </div>

        </div>

    </section>


    <!-- =================================================
         IMPORTANT NOTICE
    ================================================== -->

    <div class="cost-notice">

        <div class="cost-icon">

            <i
                class="fa-solid fa-wallet"
            ></i>

        </div>


        <div class="cost-text">

            <strong>
                Transport is not included in your budget.
            </strong>

        </div>

    </div>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo htmlspecialchars($error);
            ?>

        </div>

    <?php endif; ?>


    <!-- =================================================
         TRANSPORT OPTIONS
    ================================================== -->

    <section class="section">

        <div class="section-title">

            <span>
                TRAVEL OPTIONS
            </span>

            <h2>
                Choose your transport
            </h2>

            <p>
                Select one option for your trip.
            </p>

        </div>


        <form method="POST">


            <div class="transport-grid">


                <?php foreach (
                    $transportOptions
                    as $option
                ): ?>


                    <article
                        class="
                        transport-card
                        <?php
                        echo htmlspecialchars(
                            $option["animation"]
                        );
                        ?>
                        "
                    >


                        <!-- BADGE -->

                        <span
                            class="live-badge"
                        >

                            <?php
                            if (
                                $option["badge"] ===
                                "LIVE"
                            ):
                            ?>

                                <span
                                    class="live-dot"
                                ></span>

                            <?php endif; ?>

                            <?php
                            echo htmlspecialchars(
                                $option["badge"]
                            );
                            ?>

                        </span>


                        <!-- ICON -->

                        <div
                            class="
                            transport-icon-wrap
                            "
                        >

                            <div
                                class="
                                transport-icon
                                "
                            >

                                <i
                                    class="<?php
                                    echo htmlspecialchars(
                                        $option["icon"]
                                    );
                                    ?>"
                                ></i>

                            </div>

                        </div>


                        <!-- BODY -->

                        <div
                            class="transport-body"
                        >


                            <span
                                class="
                                transport-type
                                "
                            >

                                <?php
                                echo htmlspecialchars(
                                    $option["category"]
                                );
                                ?>

                            </span>


                            <h3>

                                <?php
                                echo htmlspecialchars(
                                    $option["title"]
                                );
                                ?>

                            </h3>


                            <p>

                                <p>

    <?php if ($option["type"] === "Car"): ?>

        <?php echo htmlspecialchars($startingLocation); ?>
        →
        <?php echo htmlspecialchars($destination); ?>

    <?php else: ?>

        <?php
        echo htmlspecialchars(
            $option["description"]
        );
        ?>

    <?php endif; ?>

 <?php if ($option["type"] === "Car"): ?>

    <div class="meta-row">

        <i class="fa-solid fa-road"></i>

        <?php if ($carDistanceKm !== null): ?>

            <?php
            echo number_format(
                $carDistanceKm,
                1
            );
            ?>
            km

        <?php else: ?>

            Distance unavailable

        <?php endif; ?>

    </div>


    <div class="meta-row">

        <i class="fa-regular fa-clock"></i>

        <?php
        echo htmlspecialchars(
            $carTravelTime
        );
        ?>

    </div>


<?php elseif ($option["type"] === "Bike"): ?>

    <div class="meta-row">

        <i class="fa-solid fa-road"></i>

        <?php if ($bikeDistanceKm !== null): ?>

            <?php
            echo number_format(
                $bikeDistanceKm,
                1
            );
            ?>
            km

        <?php else: ?>

            Distance unavailable

        <?php endif; ?>

    </div>


   <div class="meta-row">

    <i class="fa-regular fa-clock"></i>

    <?php if ($bikeTravelTime !== "--"): ?>

        <?php echo htmlspecialchars($bikeTravelTime); ?>

    <?php else: ?>

        Travel time unavailable

    <?php endif; ?>

</div>
<?php else: ?>

    <div class="meta-row">

        <i class="<?php
            echo htmlspecialchars(
                $option["meta1_icon"]
            );
        ?>"></i>

        <?php
        echo htmlspecialchars(
            $option["meta1"]
        );
        ?>

    </div>


    <div class="meta-row">

        <i class="<?php
            echo htmlspecialchars(
                $option["meta2_icon"]
            );
        ?>"></i>

        <?php
        echo htmlspecialchars(
            $option["meta2"]
        );
        ?>

    </div>

<?php endif; ?>

                           

                                   


                        <!-- MAP ONLY BUS / TRAIN -->

                            <?php
                            if (
                                $option["map"] !== ""
                            ):
                            ?>

                                <div
                                    class="map-box"
                                >

                                    <a
                                        href="<?php
                                        echo htmlspecialchars(
                                            $option["map"]
                                        );
                                        ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="map-button"
                                    >

                                        <i
                                            class="
                                            fa-solid
                                            fa-map-location-dot
                                            "
                                        ></i>

                                        Open Live Map

                                        <i
                                            class="
                                            fa-solid
                                            fa-arrow-up-right-from-square
                                            "
                                        ></i>

                                    </a>

                                </div>

                            <?php endif; ?>


                            <!-- SELECT -->

                            <input
                                type="radio"
                                name="transport_type"
                                value="<?php
                                echo htmlspecialchars(
                                    $option["type"]
                                );
                                ?>"
                                id="transport_<?php
                                echo strtolower(
                                    $option["type"]
                                );
                                ?>"
                                class="
                                transport-radio
                                "
                                <?php
                                echo (
                                    $currentTransport ===
                                    $option["type"]
                                )
                                ? "checked"
                                : "";
                                ?>
                            >


                            <label
                                for="transport_<?php
                                echo strtolower(
                                    $option["type"]
                                );
                                ?>"
                                class="
                                transport-select
                                "
                            >

                                <i
                                    class="
                                    fa-solid
                                    fa-check
                                    "
                                ></i>

                                <?php
                                echo (
                                    $currentTransport ===
                                    $option["type"]
                                )
                                ? "Selected"
                                : "Select " .
                                  htmlspecialchars(
                                      $option["title"]
                                  );
                                ?>

                            </label>


                        </div>

                    </article>


                <?php endforeach; ?>


            </div>


            <!-- =================================================
                 CONTINUE
            ================================================== -->

            <div
                class="continue-box"
            >

                <button
                    type="submit"
                    class="continue-btn"
                >

                    Continue to My Trip

                    <i
                        class="
                        fa-solid
                        fa-arrow-right
                        "
                    ></i>

                </button>

            </div>


        </form>

    </section>


</div>

</body>

</html>