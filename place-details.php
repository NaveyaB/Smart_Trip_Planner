<?php
session_start();
include "db.php";

if (file_exists("pexels-api.php")) {
    require_once "pexels-api.php";
}

date_default_timezone_set("Asia/Kolkata");

/* =========================
   LOGIN
========================= */
if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

/* =========================
   DESTINATION
========================= */
$placeKey = trim($_GET["place"] ?? "Kodaikanal");
if ($placeKey === "") $placeKey = "Kodaikanal";

$destinationInfo = [
    "Kodaikanal" => [
        "tagline" => "Kodaikanal Waits for You",
        "description" => "A peaceful hill escape filled with cool weather, scenic viewpoints, quiet forests and unforgettable moments.",
        "hero" => "image/kodaikanal.jpg"
    ],
    "Palani" => [
        "tagline" => "A Spiritual Hill Escape",
        "description" => "A famous hill destination known for its temple, peaceful surroundings and traditional culture.",
        "hero" => "image/palani.jpg"
    ],
    "Sirumalai" => [
        "tagline" => "Into the Quiet Hills",
        "description" => "A calm hill destination surrounded by greenery, viewpoints, forests and peaceful natural scenery.",
        "hero" => "image/sirumalai.jpg"
    ],
    "Dindigul" => [
        "tagline" => "Discover Dindigul",
        "description" => "A destination filled with history, temples, local culture, scenic spots and traditional town life.",
        "hero" => "image/dindigul.jpg"
    ],
    "Madurai" => [
        "tagline" => "Experience the Soul of Madurai",
        "description" => "A historic city known for temples, heritage architecture, culture, food and vibrant local life.",
        "hero" => "image/madurai.jpg"
    ],
    "Coimbatore" => [
        "tagline" => "The Gateway to the Hills",
        "description" => "A lively city offering temples, museums, nature, shopping, culture and easy access to hill destinations.",
        "hero" => "image/coimbatore.jpg"
    ],
    "Marudamalai" => [
        "tagline" => "Temple in the Hills",
        "description" => "A scenic hill destination combining spirituality, nature, viewpoints and peaceful surroundings.",
        "hero" => "image/marudamalai.jpg"
    ],
    "Valparai" => [
        "tagline" => "Into the Tea Hills",
        "description" => "A green mountain destination filled with tea estates, waterfalls, viewpoints and forest landscapes.",
        "hero" => "image/valparai.jpg"
    ],
    "Pollachi" => [
        "tagline" => "Green Countryside Escape",
        "description" => "A scenic region known for coconut groves, dams, waterfalls, forests, temples and countryside experiences.",
        "hero" => "image/pollachi.jpg"
    ]
];

$placeName = $placeKey;
foreach ($destinationInfo as $name => $info) {
    if (strcasecmp($name, $placeKey) === 0) {
        $placeName = $name;
        break;
    }
}

$currentInfo = $destinationInfo[$placeName] ?? [
    "tagline" => "Your Next Destination",
    "description" => "Explore the best places and experiences available at this destination.",
    "hero" => "image/kodaikanal.jpg"
];

$tagline = $currentInfo["tagline"];
$placeDescription = $currentInfo["description"];
$heroImage = $currentInfo["hero"];

/* Hero fallback */
if (!file_exists(__DIR__ . "/" . $heroImage)) {
    if (function_exists("searchPexelsPhoto")) {
        try {
            $heroPhoto = searchPexelsPhoto($placeName . " Tamil Nadu travel hills");
            if ($heroPhoto && isset($heroPhoto["src"]["large2x"])) {
                $heroImage = $heroPhoto["src"]["large2x"];
            }
        } catch (Throwable $e) {}
    }
    if (strpos($heroImage, "image/") === 0) {
        $heroImage = "https://images.unsplash.com/photo-1500534623283-312aade485b7?auto=format&fit=crop&w=1800&q=85";
    }
}

/* =========================
   LATEST TRIP
========================= */
$stmt = $conn->prepare(
    "SELECT * FROM trips WHERE user_id = ? ORDER BY id DESC LIMIT 1"
);
if (!$stmt) die("Database error: " . $conn->error);

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

/* =========================
   TRIP VALUES
========================= */
$budget = (float)($trip["total_budget"] ?? 0);
$members = max(1, (int)($trip["members"] ?? 1));
$days = max(1, (int)($trip["days"] ?? 1));

$transport = trim($trip["transport"] ?? "");
$stayPreference = trim($trip["stay"] ?? "");
$foodPreference = trim($trip["food"] ?? "");

$startDateRaw = trim($trip["start_date"] ?? "");
$departureTime = trim($trip["departure_time"] ?? "");
$arrivalTime = trim($trip["arrival_time"] ?? "");
$startingLocation = trim($trip["starting_location"] ?? "");

$selectedStay = $_SESSION["selected_stay"] ?? null;
$selectedRestaurant = $_SESSION["selected_restaurant"] ?? null;

/* =========================
   PLACES FROM DATABASE
========================= */
$allPlaces = [];

$stmt = $conn->prepare(
    "SELECT id, place_name, category, description, visit_duration,
            best_time, activity, estimated_cost, priority, image_url
     FROM destination_places
     WHERE destination = ?
     ORDER BY priority ASC, id ASC"
);

if (!$stmt) die("Place database error: " . $conn->error);

$stmt->bind_param("s", $placeName);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $allPlaces[] = [
        "id" => (int)$row["id"],
        "name" => $row["place_name"],
        "category" => $row["category"] ?? "",
        "description" => $row["description"] ?? "",
        "visit_duration" => max(15, (int)($row["visit_duration"] ?? 60)),
        "activity" => $row["activity"] ?? "",
        "image" => trim($row["image_url"] ?? "")
    ];
}
$stmt->close();

/* Get images only when DB image is empty */
foreach ($allPlaces as $i => $place) {
    if ($allPlaces[$i]["image"] !== "") continue;

    $image = $heroImage;

    if (function_exists("searchPexelsPhoto")) {
        try {
            $photo = searchPexelsPhoto(
                $place["name"] . " " . $placeName . " Tamil Nadu tourism"
            );
            if ($photo && isset($photo["src"]["large2x"])) {
                $image = $photo["src"]["large2x"];
            }
        } catch (Throwable $e) {}
    }

    $allPlaces[$i]["image"] = $image;
}

/* =========================
   3 PLACES PER DAY
========================= */
$maxPlacesPerDay = 3;
$selectedPlaces = array_slice($allPlaces, 0, $days * $maxPlacesPerDay);
$dayPlaces = [];

for ($d = 1; $d <= $days; $d++) {
    $dayPlaces[$d] = [];
}

foreach ($selectedPlaces as $index => $place) {
    $dayNumber = (int)floor($index / $maxPlacesPerDay) + 1;
    if ($dayNumber <= $days) {
        $dayPlaces[$dayNumber][] = $place;
    }
}

/* =========================
   HELPERS
========================= */
function cleanTime(string $time): string {
    if ($time === "") return "";
    try {
        return (new DateTime($time))->format("h:i A");
    } catch (Throwable $e) {
        return $time;
    }
}

function tripDate(string $start, int $day): string {
    if ($start === "") return "";
    try {
        $date = new DateTime($start);
        $date->modify("+" . ($day - 1) . " days");
        return $date->format("D, d M Y");
    } catch (Throwable $e) {
        return "";
    }
}

function durationText(int $minutes): string {
    $minutes = max(0, $minutes);
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;

    if ($h && $m) return $h . " hr " . $m . " min";
    if ($h) return $h . " hr";
    return $m . " min";
}

/* =========================
   ITINERARY
========================= */
$dayActivities = [];

foreach ($dayPlaces as $dayNumber => $places) {

    $activities = [];

    if ($dayNumber === 1) {
        if ($departureTime !== "") {
            $activities[] = [
                "type" => "travel",
                "time" => cleanTime($departureTime),
                "title" => "Start Journey",
                "description" => $startingLocation !== ""
                    ? "Start from " . $startingLocation . " by " . ($transport ?: "your selected transport") . "."
                    : "Start your journey by " . ($transport ?: "your selected transport") . "."
            ];
        }

        $activities[] = [
            "type" => "meal",
            "time" => "07:30 AM",
            "title" => "Breakfast",
            "description" => "Have breakfast during the journey before reaching " . $placeName . "."
        ];

        $activities[] = [
            "type" => "arrival",
            "time" => $arrivalTime !== "" ? cleanTime($arrivalTime) : "Around 12:00 PM",
            "title" => "Arrive at " . $placeName,
            "description" => "Arrive at your destination and get ready to begin your stay."
        ];

        if ($selectedStay) {
            $activities[] = [
                "type" => "stay",
                "time" => "01:00 PM",
                "title" => "Hotel Check-in",
                "description" => "Check in at " . $selectedStay["name"] . ", refresh and get ready for the day."
            ];
        }

        $activities[] = [
            "type" => "meal",
            "time" => "01:30 PM",
            "title" => "Lunch",
            "description" => $selectedRestaurant
                ? "Lunch at " . $selectedRestaurant["name"] . "."
                : "Have lunch according to your food preference."
        ];
    } else {
        $activities[] = [
            "type" => "meal",
            "time" => "08:00 AM",
            "title" => "Breakfast",
            "description" => $selectedStay
                ? "Breakfast at " . $selectedStay["name"] . "."
                : "Breakfast before starting the day."
        ];
    }

    $slots = $dayNumber === 1
        ? ["02:30 PM", "04:30 PM", "06:15 PM"]
        : ["10:00 AM", "01:30 PM", "04:30 PM"];

    foreach ($places as $index => $place) {
        $activities[] = [
            "type" => "place",
            "time" => $slots[$index] ?? "05:30 PM",
            "title" => $place["name"],
            "description" => $place["description"],
            "activity" => $place["activity"],
            "category" => $place["category"],
            "duration" => $place["visit_duration"],
            "image" => $place["image"]
        ];
    }

    $activities[] = [
        "type" => "meal",
        "time" => $dayNumber === 1 ? "08:00 PM" : "07:30 PM",
        "title" => "Dinner",
        "description" => $selectedRestaurant
            ? "Dinner at " . $selectedRestaurant["name"] . "."
            : "Dinner according to your food preference."
    ];

    $activities[] = [
        "type" => "return",
        "time" => $dayNumber === 1 ? "09:00 PM" : "08:45 PM",
        "title" => "Return to Stay",
        "description" => "Return to your stay and relax for the night."
    ];

    $dayActivities[$dayNumber] = $activities;
}

/* =========================
   WEATHER PLACEHOLDER
========================= */
$weatherDays = [];
for ($d = 1; $d <= min($days, 3); $d++) {
    $weatherDays[] = [
        "day" => $d,
        "date" => tripDate($startDateRaw, $d)
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?php echo htmlspecialchars($placeName); ?> | Smart Budget Trip Planner</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<style>
:root{
    --green:#2f7445;
    --green-dark:#1e5631;
    --green-soft:#e8f5e9;
    --green-light:#f1f8f1;
    --mint:#dcefe0;
    --line:#c9dfce;
    --text:#163b24;
    --muted:#5d7464;
}

*{box-sizing:border-box}

body{
    margin:0;
    font-family:"Manrope",Arial,sans-serif;
    background:linear-gradient(180deg,#edf8ef,#e3f1e6);
    color:var(--text);
}

a{text-decoration:none}

.navbar{
    background:#e6f3e8;
    border-bottom:1px solid #c9dfce;
    position:sticky;
    top:0;
    z-index:20;
}

.navbar-inner{
    max-width:1040px;
    margin:auto;
    min-height:68px;
    padding:0 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
}

.brand{
    display:flex;
    align-items:center;
    gap:10px;
    font-size:17px;
    font-weight:800;
    color:var(--green-dark);
}

.brand-icon{
    width:38px;
    height:38px;
    border-radius:12px;
    display:grid;
    place-items:center;
    background:var(--green);
    color:#fff;
    box-shadow:0 7px 16px rgba(42,101,57,.18);
}

.nav-actions{
    display:flex;
    gap:8px;
}

.nav-btn{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:9px 12px;
    border-radius:10px;
    font-size:11px;
    font-weight:800;
}

.nav-btn.light{
    background:#f0f8f1;
    color:var(--green);
    border:1px solid #c8decc;
}

.nav-btn.dark{
    background:var(--green);
    color:#fff;
}

.hero{
    min-height:300px;
    background:
        linear-gradient(90deg,rgba(11,44,21,.66),rgba(17,67,34,.16)),
        url("<?php echo htmlspecialchars($heroImage); ?>")
        center/cover no-repeat;
    display:flex;
    align-items:flex-end;
}

.hero-inner{
    width:min(1040px,100%);
    margin:auto;
    padding:44px 18px 38px;
    color:#fff;
}

.kicker{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 10px;
    border-radius:999px;
    background:rgba(225,247,230,.90);
    color:var(--green-dark);
    font-size:9px;
    font-weight:800;
    letter-spacing:1px;
}

.hero h1{
    margin:12px 0 6px;
    font-size:50px;
    line-height:1;
}

.hero p{
    margin:0;
    max-width:620px;
    font-size:13px;
    line-height:1.7;
}

.summary{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
    margin-top:18px;
}

.summary-pill{
    display:flex;
    align-items:center;
    gap:7px;
    padding:9px 11px;
    border-radius:11px;
    background:rgba(240,250,242,.94);
    color:#234f30;
    border:1px solid #c4ddc8;
    font-size:11px;
    font-weight:800;
}

.page{
    max-width:1040px;
    margin:auto;
    padding:28px 18px 55px;
}

.layout{
    display:grid;
    grid-template-columns:minmax(0,1fr) 285px;
    gap:18px;
    align-items:start;
}

.section-label{
    color:var(--green);
    font-size:10px;
    font-weight:800;
    letter-spacing:1.4px;
}

.section-title{
    margin-bottom:15px;
}

.section-title h2{
    margin:5px 0;
    font-size:30px;
}

.section-title p{
    margin:0;
    color:var(--muted);
    font-size:12px;
}

.day-list{
    display:grid;
    gap:12px;
}

.day-card{
    background:linear-gradient(135deg,#e9f6eb,#dcefe0);
    border:1px solid var(--line);
    border-radius:19px;
    overflow:hidden;
    box-shadow:0 10px 24px rgba(38,89,50,.10);
}

.day-header{
    width:100%;
    border:0;
    background:transparent;
    padding:15px 16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    cursor:pointer;
    color:var(--text);
    text-align:left;
}

.day-header-left{
    display:flex;
    align-items:center;
    gap:11px;
}

.day-number{
    width:48px;
    height:48px;
    border-radius:14px;
    display:grid;
    place-items:center;
    background:linear-gradient(145deg,#337a48,#215e34);
    color:#fff;
    box-shadow:0 7px 15px rgba(39,96,52,.18);
}

.day-number small{
    display:block;
    font-size:7px;
    letter-spacing:1px;
}

.day-number strong{
    display:block;
    font-size:21px;
    line-height:1;
}

.day-title strong{
    display:block;
    font-size:17px;
}

.day-title small{
    display:block;
    margin-top:2px;
    font-size:10px;
    color:#4e6957;
}

.day-header-right{
    display:flex;
    align-items:center;
    gap:8px;
}

.day-count{
    padding:7px 9px;
    border-radius:999px;
    background:#edf8ef;
    color:var(--green);
    border:1px solid #c4ddc8;
    font-size:9px;
    font-weight:800;
}

.day-arrow{
    width:31px;
    height:31px;
    border-radius:9px;
    display:grid;
    place-items:center;
    background:#d5ead9;
    color:var(--green-dark);
    transition:.25s;
}

.day-card.active .day-arrow{
    background:var(--green);
    color:#fff;
    transform:rotate(180deg);
}

.day-content{
    max-height:0;
    overflow:hidden;
    transition:max-height .45s ease;
}

.day-inner{
    padding:4px 16px 17px;
    background:linear-gradient(180deg,#e5f3e7,#deefe1);
}

.timeline{
    position:relative;
}

.timeline:before{
    content:"";
    position:absolute;
    left:74px;
    top:15px;
    bottom:15px;
    width:2px;
    background:linear-gradient(#9bcaa5,#65a870,#9bcaa5);
}

.timeline-row{
    display:grid;
    grid-template-columns:58px 32px minmax(0,1fr);
    gap:9px;
    position:relative;
    margin-bottom:10px;
}

.timeline-time{
    padding-top:12px;
    text-align:right;
    font-size:10px;
    font-weight:800;
    color:#2d6d40;
}

.timeline-icon-wrap{
    display:flex;
    justify-content:center;
    padding-top:7px;
    position:relative;
    z-index:2;
}

.timeline-icon{
    width:34px;
    height:34px;
    border-radius:11px;
    display:grid;
    place-items:center;
    background:#eef8ef;
    border:1px solid #bed9c3;
    color:var(--green);
    font-size:14px;
    box-shadow:0 5px 12px rgba(40,93,51,.10);
}

.timeline-icon.travel{background:#e3f2e6}
.timeline-icon.meal{background:#edf5e1;color:#5d7a2d}
.timeline-icon.arrival{background:#e0f1ec;color:#2b7461}
.timeline-icon.stay{background:#e6f0e7;color:#2d6b3f}
.timeline-icon.place{background:#e1f2e4}
.timeline-icon.return{background:#dfeee2}

.plan-card{
    background:#eaf6ec;
    border:1px solid #c6dfca;
    border-left:4px solid #4c9560;
    border-radius:14px;
    padding:11px 12px;
    min-width:0;
}

.plan-type{
    display:inline-block;
    padding:4px 7px;
    border-radius:999px;
    background:#d7ebda;
    color:#2c6e3e;
    font-size:8px;
    font-weight:800;
}

.plan-card h4{
    margin:7px 0 4px;
    font-size:15px;
    color:#143a22;
}

.plan-card p{
    margin:0;
    font-size:11px;
    line-height:1.55;
    color:#506858;
}

.place-mini{
    display:grid;
    grid-template-columns:118px minmax(0,1fr);
    gap:12px;
}

.place-photo{
    width:118px;
    height:82px;
    border-radius:12px;
    overflow:hidden;
    border:1px solid #bed9c3;
    background:#d7eadb;
}

.place-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
    display:block;
    transition:.3s ease;
}

.plan-card:hover .place-photo img{
    transform:scale(1.04);
}

.place-meta{
    display:flex;
    flex-wrap:wrap;
    gap:6px;
    margin-top:7px;
}

.mini-pill{
    padding:5px 7px;
    border-radius:999px;
    background:#e1f0e3;
    border:1px solid #c7dfcb;
    color:#40624b;
    font-size:8px;
    font-weight:700;
}

.map-btn{
    margin-top:8px;
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 9px;
    border-radius:9px;
    background:#d7ebda;
    border:1px solid #bdd8c2;
    color:#2b6e3e;
    font-size:9px;
    font-weight:800;
}

.side{
    display:grid;
    gap:12px;
    position:sticky;
    top:84px;
}

.side-card{
    background:linear-gradient(135deg,#e8f5e9,#dcefe0);
    border:1px solid #c5ddc9;
    border-radius:17px;
    padding:14px;
    box-shadow:0 9px 20px rgba(38,89,50,.08);
}

.side-head{
    display:flex;
    align-items:center;
    gap:8px;
    margin-bottom:11px;
}

.side-icon{
    width:35px;
    height:35px;
    border-radius:10px;
    display:grid;
    place-items:center;
    background:#d1e8d5;
    color:var(--green);
}

.side-head h3{
    margin:0;
    font-size:15px;
}

.side-head p{
    margin:2px 0 0;
    font-size:9px;
    color:var(--muted);
}

.weather-row{
    display:grid;
    gap:7px;
}

.weather-day{
    padding:9px 10px;
    background:#ecf7ee;
    border:1px solid #c9e0cd;
    border-radius:11px;
}

.weather-day strong{
    display:block;
    font-size:11px;
}

.weather-day span{
    display:block;
    margin-top:2px;
    font-size:9px;
    color:var(--muted);
}

.selected-card{
    display:flex;
    gap:10px;
    align-items:center;
}

.selected-photo{
    width:60px;
    height:60px;
    border-radius:11px;
    overflow:hidden;
    background:#d4e9d8;
    flex:0 0 auto;
}

.selected-photo img{
    width:100%;
    height:100%;
    object-fit:cover;
}

.selected-info strong{
    display:block;
    font-size:12px;
}

.selected-info small{
    display:block;
    margin-top:3px;
    font-size:9px;
    color:var(--muted);
}

.action-btn{
    width:100%;
    border:0;
    border-radius:11px;
    padding:12px;
    background:linear-gradient(135deg,#337c49,#25683a);
    color:#fff;
    font-size:11px;
    font-weight:800;
    cursor:pointer;
    box-shadow:0 9px 18px rgba(42,100,55,.18);
}

.action-btn:hover{
    transform:translateY(-1px);
}

.footer{
    text-align:center;
    padding:22px 18px 35px;
    color:#6a8170;
    font-size:9px;
}

@media(max-width:860px){
    .layout{grid-template-columns:1fr}
    .side{position:static}
    .side-card{max-width:none}
}

@media(max-width:600px){
    .hero h1{font-size:39px}
    .summary-pill{font-size:9px}
    .section-title h2{font-size:25px}
    .place-mini{grid-template-columns:88px minmax(0,1fr)}
    .place-photo{width:88px;height:75px}
    .timeline:before{left:65px}
    .timeline-row{grid-template-columns:50px 30px minmax(0,1fr)}
    .plan-card h4{font-size:14px}
    .plan-card p{font-size:10px}
}

/* =========================================
   FINAL COMPACT LIGHT-GREEN LAYOUT
========================================= */

body{
    background:
        linear-gradient(
            180deg,
            #e8f5ea 0%,
            #deefe1 50%,
            #d6ead9 100%
        );

    color:#12321d;
}

/* PAGE WIDTH */
.page{
    max-width:920px;
    margin:0 auto;
    padding:28px 18px 55px;
}

/* MAIN CONTENT */
.layout{
    display:block;
}

/* DAY SECTION */
.section-title{
    margin-bottom:16px;
}

.section-label{
    color:#245f34;
    font-size:11px;
    font-weight:900;
    letter-spacing:1.4px;
}

.section-title h2{
    margin:6px 0;
    font-size:32px;
    font-weight:900;
    color:#102e1a;
}

.section-title p{
    font-size:13px;
    color:#3e5b47;
    font-weight:600;
}

/* DAY LIST */
.day-list{
    display:grid;
    gap:11px;
    max-width:760px;
}

/* DAY CARD */
.day-card{
    width:100%;

    background:
        linear-gradient(
            135deg,
            #e6f4e8,
            #d8ecdc
        );

    border:1px solid #b8d5bd;
    border-radius:18px;

    box-shadow:
        0 7px 18px
        rgba(28,78,40,.09);

    overflow:hidden;

    transition:
        transform .25s ease,
        box-shadow .25s ease;
}

.day-card:hover{
    transform:translateY(-2px);

    box-shadow:
        0 12px 24px
        rgba(28,78,40,.14);
}

/* DAY HEADER */
.day-header{
    width:100%;
    min-height:76px;

    padding:12px 14px;

    background:
        linear-gradient(
            135deg,
            #d9eddd,
            #cae4cf
        );

    border:0;

    color:#12351f;

    display:flex;
    align-items:center;
    justify-content:space-between;

    cursor:pointer;
}

/* DAY NUMBER */
.day-number{
    width:48px;
    height:48px;

    border-radius:14px;

    background:
        linear-gradient(
            145deg,
            #337c49,
            #205b33
        );

    color:#fff;

    box-shadow:
        0 6px 14px
        rgba(31,88,46,.20);
}

.day-number small{
    font-size:7px;
    font-weight:800;
}

.day-number strong{
    font-size:21px;
    font-weight:900;
}

/* DAY TITLE */
.day-title strong{
    color:#102f1a;
    font-size:17px;
    font-weight:900;
}

.day-title small{
    color:#42634d;
    font-size:10px;
    font-weight:700;
}

/* PLACE COUNT */
.day-count{
    background:#edf8ef;
    border:1px solid #b6d8bd;

    color:#225d33;

    font-size:10px;
    font-weight:900;

    padding:7px 10px;
}

/* ARROW */
.day-arrow{
    width:32px;
    height:32px;

    background:#d0e8d5;
    color:#205f34;

    border-radius:10px;

    font-size:13px;
}

/* EXPANDED CONTENT */
.day-content{
    background:
        linear-gradient(
            180deg,
            #e0f0e3,
            #d8ebdc
        );
}

.day-inner{
    padding:14px 14px 18px;
}

/* TIMELINE */
.timeline:before{
    left:70px;
    width:2px;

    background:
        linear-gradient(
            180deg,
            #9bc5a2,
            #5d9b69,
            #9bc5a2
        );
}

.timeline-row{
    grid-template-columns:
        56px 28px minmax(0,1fr);

    gap:8px;

    margin-bottom:9px;
}

.timeline-time{
    color:#245e34;

    font-size:10px;
    font-weight:900;

    padding-top:12px;
}

/* ICON BOX */
.timeline-icon-wrap{
    padding-top:6px;
}

.timeline-icon{
    width:31px;
    height:31px;

    border-radius:10px;

    background:#eaf6ec;
    border:1px solid #b9d7be;

    color:#2b7040;

    font-size:13px;

    box-shadow:
        0 4px 10px
        rgba(36,90,47,.09);
}

/* PLAN CARD */
.plan-card{
    background:
        #e4f1e6;

    border:
        1px solid #bed9c3;

    border-left:
        4px solid #4b905d;

    border-radius:13px;

    padding:11px 12px;

    box-shadow:
        0 5px 13px
        rgba(38,83,47,.06);
}

/* PLAN TYPE */
.plan-type{
    background:#d3e9d7;
    color:#245f34;

    font-size:8px;
    font-weight:900;

    padding:5px 7px;
}

/* MAIN TEXT */
.plan-card h4{
    margin:7px 0 4px;

    font-size:16px;
    font-weight:900;

    color:#102f19;
}

.plan-card p{
    font-size:11px;
    line-height:1.6;

    color:#365541;

    font-weight:600;
}

/* PLACE */
.place-mini{
    grid-template-columns:
        100px minmax(0,1fr);

    gap:11px;
}

.place-photo{
    width:100px;
    height:76px;

    border-radius:11px;

    border:1px solid #b8d6bd;
}

/* META */
.mini-pill{
    background:#d9ecdc;
    border:1px solid #bfdac4;

    color:#315540;

    font-size:8px;
    font-weight:800;

    padding:5px 7px;
}

/* MAP BUTTON */
.map-btn{
    background:#d2e8d6;
    border:1px solid #b5d3ba;

    color:#245f34;

    font-size:9px;
    font-weight:900;
}

/* =========================================
   WEATHER / STAY / FOOD BELOW DAY CARDS
========================================= */

.side{
    position:static;

    display:grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:10px;

    margin-top:18px;
}

/* SIDE CARDS */
.side-card{
    background:
        linear-gradient(
            135deg,
            #e5f3e7,
            #d9ecdd
        );

    border:1px solid #bcd8c1;

    border-radius:15px;

    padding:13px;

    box-shadow:
        0 7px 16px
        rgba(35,82,45,.07);
}

/* SIDE HEAD */
.side-head{
    margin-bottom:9px;
}

.side-head h3{
    font-size:14px;
    color:#12341e;
    font-weight:900;
}

.side-head p{
    font-size:9px;
    color:#466451;
    font-weight:600;
}

/* SIDE ICON */
.side-icon{
    width:32px;
    height:32px;

    border-radius:9px;

    background:#cde6d2;
    color:#28693b;

    font-size:13px;
}

/* WEATHER */
.weather-day{
    background:#e9f6eb;

    border:1px solid #c1dbc5;

    padding:8px;

    border-radius:9px;
}

.weather-day strong{
    font-size:10px;
    color:#173d23;
}

.weather-day span{
    font-size:8px;
    color:#486553;

    font-weight:600;
}

/* SELECTED STAY / FOOD */
.selected-card{
    gap:9px;
}

.selected-photo{
    width:50px;
    height:50px;

    background:#d0e8d4;

    border-radius:9px;
}

.selected-info strong{
    font-size:10px;
    color:#173d23;

    font-weight:900;
}

.selected-info small{
    font-size:8px;
    color:#486553;

    font-weight:700;
}

/* SAVE BUTTON */
.action-btn{
    padding:11px;

    border-radius:10px;

    background:
        linear-gradient(
            135deg,
            #327849,
            #235e35
        );

    font-size:10px;
    font-weight:900;
}

/* MOBILE */
@media(max-width:850px){

    .side{
        grid-template-columns:1fr;
    }

    .day-list{
        max-width:100%;
    }
}

@media(max-width:600px){

    .section-title h2{
        font-size:27px;
    }

    .day-header{
        min-height:68px;
    }

    .day-title strong{
        font-size:15px;
    }

    .plan-card h4{
        font-size:14px;
    }
}
/* =========================================
   NAVBAR FINAL FIX
========================================= */

.navbar{
    width:100% !important;
    height:68px !important;

    background:#e5f3e8 !important;
    border-bottom:1px solid #bfd9c4 !important;

    position:sticky !important;
    top:0 !important;
    z-index:100 !important;
}

.navbar-inner{
    position:relative !important;

    width:100% !important;
    max-width:1040px !important;
    height:68px !important;

    margin:0 auto !important;
    padding:0 18px !important;
}

/* BRAND */
.brand{
    position:absolute !important;
    left:18px !important;
    top:50% !important;

    transform:translateY(-50%) !important;

    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;

    gap:10px !important;

    width:auto !important;
    height:auto !important;

    margin:0 !important;
    padding:0 !important;

    color:#153a22 !important;

    font-size:17px !important;
    font-weight:900 !important;

    white-space:nowrap !important;
}

.brand-icon{
    width:38px !important;
    height:38px !important;
    min-width:38px !important;

    display:flex !important;
    align-items:center !important;
    justify-content:center !important;

    margin:0 !important;

    border-radius:12px !important;

    background:#2f7445 !important;
    color:#fff !important;

    flex-shrink:0 !important;
}

/* BUTTON AREA */
.nav-actions{
    position:absolute !important;
    right:18px !important;
    top:50% !important;

    transform:translateY(-50%) !important;

    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;

    gap:8px !important;

    width:auto !important;
    height:auto !important;

    margin:0 !important;
    padding:0 !important;
}

/* BUTTONS */
.nav-btn{
    display:inline-flex !important;

    align-items:center !important;
    justify-content:center !important;

    gap:7px !important;

    height:36px !important;

    padding:0 13px !important;

    border-radius:10px !important;

    font-size:11px !important;
    font-weight:900 !important;

    text-decoration:none !important;

    white-space:nowrap !important;
}

.nav-btn.light{
    background:#edf8ef !important;
    border:1px solid #c1dbc5 !important;
    color:#245f34 !important;
}

.nav-btn.dark{
    background:#2f7445 !important;
    color:#fff !important;
}

/* MOBILE */
@media(max-width:600px){

    .brand{
        left:12px !important;
        font-size:14px !important;
    }

    .brand-icon{
        width:34px !important;
        height:34px !important;
        min-width:34px !important;
    }

    .nav-actions{
        right:12px !important;
    }

    .nav-btn{
        height:34px !important;
        padding:0 9px !important;
    }
}
/* =========================================
   NAV BUTTONS FINAL POSITION
========================================= */

.nav-actions{
    position:fixed !important;

    top:16px !important;
    right:20px !important;

    display:flex !important;
    align-items:center !important;
    gap:8px !important;

    z-index:9999 !important;

    width:auto !important;
    height:auto !important;

    margin:0 !important;
    padding:0 !important;
}

.nav-btn{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;

    height:36px !important;

    padding:0 13px !important;

    border-radius:10px !important;

    font-size:11px !important;
    font-weight:900 !important;

    white-space:nowrap !important;
}

.nav-btn.light{
    background:#edf8ef !important;
    color:#245f34 !important;
    border:1px solid #bfd9c4 !important;
}

.nav-btn.dark{
    background:#2f7445 !important;
    color:#ffffff !important;
}

@media(max-width:600px){

    .nav-actions{
        top:12px !important;
        right:12px !important;
    }

    .nav-btn{
        height:33px !important;
        padding:0 9px !important;
        font-size:10px !important;
    }
}
/* =========================================
   BRAND FINAL FIX
========================================= */

.brand{
    position:fixed !important;

    top:14px !important;
    left:18px !important;

    width:auto !important;
    height:40px !important;

    margin:0 !important;
    padding:0 !important;

    display:flex !important;
    flex-direction:row !important;
    align-items:center !important;

    gap:10px !important;

    white-space:nowrap !important;

    color:#153a22 !important;
    font-size:17px !important;
    font-weight:900 !important;

    z-index:10001 !important;
}

.brand-icon{
    width:38px !important;
    height:38px !important;
    min-width:38px !important;
    min-height:38px !important;

    flex:0 0 38px !important;

    display:flex !important;
    align-items:center !important;
    justify-content:center !important;

    border-radius:12px !important;

    background:#2f7445 !important;
    color:#fff !important;
}

.brand span{
    display:inline-block !important;
    width:auto !important;
    margin:0 !important;
    padding:0 !important;

    white-space:nowrap !important;

    color:#153a22 !important;
}
</style>
</head>
            <div class="brand-icon">
                <i class="fa-solid fa-route"></i>
            </div>
            Smart Budget Trip Planner
        </div>

        <div class="nav-actions">
            <a class="nav-btn light"
               href="destination.php">
                <i class="fa-solid fa-arrow-left"></i>
                Back
            </a>

            <a class="nav-btn dark"
               href="plan-trip.php">
                <i class="fa-solid fa-pen"></i>
                Edit Plan
            </a>
        </div>

    </div>
</header>

<section class="hero">
    <div class="hero-inner">

        <div class="kicker">
            <i class="fa-regular fa-compass"></i>
            YOUR FULL TRIP PLAN
        </div>

        <h1><?php echo htmlspecialchars($placeName); ?></h1>

        <p><?php echo htmlspecialchars($placeDescription); ?></p>

        <div class="summary">

            <div class="summary-pill">
                <i class="fa-solid fa-location-dot"></i>
                <?php echo htmlspecialchars($startingLocation ?: "Starting Point"); ?>
            </div>

            <div class="summary-pill">
                <i class="fa-solid fa-users"></i>
                <?php echo $members; ?> Travelers
            </div>

            <div class="summary-pill">
                <i class="fa-regular fa-calendar"></i>
                <?php echo $days; ?> Days
            </div>

            <div class="summary-pill">
                <i class="fa-solid fa-car-side"></i>
                <?php echo htmlspecialchars($transport ?: "Transport"); ?>
            </div>

            <div class="summary-pill">
                <i class="fa-solid fa-indian-rupee-sign"></i>
                ₹<?php echo number_format($budget); ?>
            </div>

        </div>

    </div>
</section>

<main class="page">

    <div class="layout">

        <section>

            <div class="section-title">
                <div class="section-label">DAY BY DAY</div>
                <h2>Your Travel Plan</h2>
                <p>Click a day to open the complete schedule.</p>
            </div>

            <div class="day-list">

                <?php foreach ($dayActivities as $dayNumber => $activities): ?>

                    <?php
                    $dateText = tripDate($startDateRaw, (int)$dayNumber);
                    $placeCount = count($dayPlaces[$dayNumber] ?? []);
                    ?>

                    <article class="day-card <?php echo $dayNumber === 1 ? "active" : ""; ?>">

                        <button class="day-header"
                                type="button"
                                aria-expanded="<?php echo $dayNumber === 1 ? "true" : "false"; ?>">

                            <span class="day-header-left">

                                <span class="day-number">
                                    <span>
                                        <small>DAY</small>
                                        <strong><?php echo (int)$dayNumber; ?></strong>
                                    </span>
                                </span>

                                <span class="day-title">
                                    <strong>Day <?php echo (int)$dayNumber; ?></strong>
                                    <small><?php echo htmlspecialchars($dateText ?: "Your itinerary"); ?></small>
                                </span>

                            </span>

                            <span class="day-header-right">

                                <span class="day-count">
                                    <?php echo $placeCount; ?> Places
                                </span>

                                <span class="day-arrow">
                                    <i class="fa-solid fa-chevron-down"></i>
                                </span>

                            </span>

                        </button>

                        <div class="day-content"
                             style="<?php echo $dayNumber === 1 ? "max-height:2800px;" : "max-height:0;"; ?>">

                            <div class="day-inner">

                                <div class="timeline">

                                    <?php foreach ($activities as $activity): ?>

                                        <div class="timeline-row">

                                            <div class="timeline-time">
                                                <?php echo htmlspecialchars($activity["time"]); ?>
                                            </div>

                                            <div class="timeline-icon-wrap">

                                                <div class="timeline-icon <?php echo htmlspecialchars($activity["type"]); ?>">

                                                    <?php
                                                    switch ($activity["type"]) {
                                                        case "travel":
                                                            echo '<i class="fa-solid fa-car-side"></i>';
                                                            break;
                                                        case "meal":
                                                            echo '<i class="fa-solid fa-utensils"></i>';
                                                            break;
                                                        case "arrival":
                                                            echo '<i class="fa-solid fa-location-dot"></i>';
                                                            break;
                                                        case "stay":
                                                            echo '<i class="fa-solid fa-hotel"></i>';
                                                            break;
                                                        case "place":
                                                            echo '<i class="fa-solid fa-camera"></i>';
                                                            break;
                                                        default:
                                                            echo '<i class="fa-solid fa-house"></i>';
                                                    }
                                                    ?>

                                                </div>

                                            </div>

                                            <div class="plan-card">

                                                <?php
                                                $labels = [
                                                    "travel" => "TRAVEL",
                                                    "meal" => "FOOD",
                                                    "arrival" => "ARRIVAL",
                                                    "stay" => "STAY",
                                                    "place" => "EXPLORE",
                                                    "return" => "RETURN"
                                                ];
                                                ?>

                                                <span class="plan-type">
                                                    <?php echo $labels[$activity["type"]] ?? "PLAN"; ?>
                                                </span>

                                                <?php if ($activity["type"] === "place"): ?>

                                                    <div class="place-mini">

                                                        <div class="place-photo">
                                                            <img src="<?php echo htmlspecialchars($activity["image"]); ?>"
                                                                 alt="<?php echo htmlspecialchars($activity["title"]); ?>">
                                                        </div>

                                                        <div>

                                                            <h4>
                                                                <?php echo htmlspecialchars($activity["title"]); ?>
                                                            </h4>

                                                            <p>
                                                                <?php echo htmlspecialchars($activity["description"]); ?>
                                                            </p>

                                                            <div class="place-meta">

                                                                <span class="mini-pill">
                                                                    <i class="fa-regular fa-clock"></i>
                                                                    <?php echo durationText((int)$activity["duration"]); ?>
                                                                </span>

                                                                <span class="mini-pill">
                                                                    <?php echo htmlspecialchars($activity["category"]); ?>
                                                                </span>

                                                            </div>

                                                            <?php if ($activity["activity"] !== ""): ?>
                                                                <div class="place-meta">
                                                                    <span class="mini-pill">
                                                                        <?php echo htmlspecialchars($activity["activity"]); ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>

                                                            <a class="map-btn"
                                                               target="_blank"
                                                               rel="noopener noreferrer"
                                                               href="https://www.google.com/maps/search/?api=1&query=<?php
                                                               echo urlencode(
                                                                   $activity["title"] . ", " .
                                                                   $placeName . ", Tamil Nadu"
                                                               );
                                                               ?>">
                                                                <i class="fa-solid fa-location-dot"></i>
                                                                Open Map
                                                            </a>

                                                        </div>

                                                    </div>

                                                <?php else: ?>

                                                    <h4>
                                                        <?php echo htmlspecialchars($activity["title"]); ?>
                                                    </h4>

                                                    <p>
                                                        <?php echo htmlspecialchars($activity["description"]); ?>
                                                    </p>

                                                <?php endif; ?>

                                            </div>

                                        </div>

                                    <?php endforeach; ?>

                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

        <aside class="side">

            <div class="side-card">

                <div class="side-head">
                    <div class="side-icon">
                        <i class="fa-solid fa-cloud-sun"></i>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($placeName); ?> Weather</h3>
                        <p>Trip-date forecast</p>
                    </div>
                </div>

                <div class="weather-row">

                    <?php foreach ($weatherDays as $weather): ?>

                        <div class="weather-day">
                            <strong>Day <?php echo $weather["day"]; ?></strong>
                            <span><?php echo htmlspecialchars($weather["date"]); ?></span>
                            <span>🌤 Forecast will appear here</span>
                        </div>

                    <?php endforeach; ?>

                </div>

            </div>

            <div class="side-card">

                <div class="side-head">
                    <div class="side-icon">
                        <i class="fa-solid fa-hotel"></i>
                    </div>
                    <div>
                        <h3>Selected Stay</h3>
                        <p><?php echo htmlspecialchars($stayPreference ?: "Stay"); ?></p>
                    </div>
                </div>

                <?php if ($selectedStay): ?>

                    <div class="selected-card">

                        <div class="selected-photo">
                            <?php if (!empty($selectedStay["image_url"])): ?>
                                <img src="<?php echo htmlspecialchars($selectedStay["image_url"]); ?>"
                                     alt="<?php echo htmlspecialchars($selectedStay["name"]); ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-hotel"
                                   style="display:grid;place-items:center;height:100%;color:#2f7445;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="selected-info">
                            <strong><?php echo htmlspecialchars($selectedStay["name"]); ?></strong>
                            <small>
                                <?php echo htmlspecialchars($selectedStay["stay_type"]); ?>
                                · ⭐ <?php echo number_format((float)$selectedStay["rating"],1); ?>
                            </small>
                        </div>

                    </div>

                <?php else: ?>

                    <a class="map-btn"
                       href="stay.php?place=<?php echo urlencode($placeName); ?>">
                        <i class="fa-solid fa-hotel"></i>
                        Choose Stay
                    </a>

                <?php endif; ?>

            </div>

            <div class="side-card">

                <div class="side-head">
                    <div class="side-icon">
                        <i class="fa-solid fa-utensils"></i>
                    </div>
                    <div>
                        <h3>Selected Food</h3>
                        <p><?php echo htmlspecialchars($foodPreference ?: "Food"); ?></p>
                    </div>
                </div>

                <?php if ($selectedRestaurant): ?>

                    <div class="selected-card">

                        <div class="selected-photo">
                            <?php if (!empty($selectedRestaurant["image_url"])): ?>
                                <img src="<?php echo htmlspecialchars($selectedRestaurant["image_url"]); ?>"
                                     alt="<?php echo htmlspecialchars($selectedRestaurant["name"]); ?>">
                            <?php else: ?>
                                <i class="fa-solid fa-utensils"
                                   style="display:grid;place-items:center;height:100%;color:#2f7445;"></i>
                            <?php endif; ?>
                        </div>

                        <div class="selected-info">
                            <strong><?php echo htmlspecialchars($selectedRestaurant["name"]); ?></strong>
                            <small>
                                <?php echo htmlspecialchars($selectedRestaurant["restaurant_type"]); ?>
                                · ⭐ <?php echo number_format((float)$selectedRestaurant["rating"],1); ?>
                            </small>
                        </div>

                    </div>

                <?php else: ?>

                    <a class="map-btn"
                       href="food.php?place=<?php echo urlencode($placeName); ?>">
                        <i class="fa-solid fa-utensils"></i>
                        Choose Food
                    </a>

                <?php endif; ?>

            </div>

            <div class="side-card">

                <button class="action-btn" type="button" onclick="window.print()">
                    <i class="fa-solid fa-file-pdf"></i>
                    &nbsp; Save / Print Full Plan
                </button>

            </div>

        </aside>

    </div>

</main>

<footer class="footer">
    Smart Budget Trip Planner · Less Expense. More Experience.
</footer>

<script>
document.addEventListener("DOMContentLoaded", function(){

    const cards = document.querySelectorAll(".day-card");

    cards.forEach(card => {

        const button = card.querySelector(".day-header");
        const content = card.querySelector(".day-content");

        button.addEventListener("click", function(){

            const open = card.classList.contains("active");

            cards.forEach(other => {
                other.classList.remove("active");

                const otherBtn = other.querySelector(".day-header");
                const otherContent = other.querySelector(".day-content");

                otherBtn.setAttribute("aria-expanded","false");
                otherContent.style.maxHeight = "0px";
            });

            if (!open) {
                card.classList.add("active");
                button.setAttribute("aria-expanded","true");
                content.style.maxHeight = content.scrollHeight + "px";
            }

        });

    });

});
</script>

</body>
</html><