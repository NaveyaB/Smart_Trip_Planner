<?php

session_start();
include "db.php";
require_once "geoapify-config.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];

/* =====================================================
   DESTINATION
===================================================== */

$place = trim($_GET["place"] ?? "Kodaikanal");

if ($place === "") {
    $place = "Kodaikanal";
}


/* =====================================================m
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
   TRIP VALUES
===================================================== */

$totalBudget = (float)($trip["total_budget"] ?? 0);

$days = max(
    1,
    (int)($trip["days"] ?? 1)
);

$members = max(
    1,
    (int)($trip["members"] ?? 1)
);

$stayPreference = trim(
    $trip["stay"] ?? "Hotel"
);

$foodPreference = trim(
    $trip["food"] ?? "Both"
);


/* =====================================================
   RECOMMENDED BUDGET SPLIT
===================================================== */

$recommendedStayBudget =
    round($totalBudget * 0.35);

$recommendedFoodBudget =
    round($totalBudget * 0.20);

$stayPerNightBudget =
    $days > 0
        ? $recommendedStayBudget / $days
        : 0;

$foodPerDayBudget =
    $days > 0
        ? $recommendedFoodBudget / $days
        : 0;


/* =====================================================
   HELPERS
===================================================== */

function safeValue(
    array $row,
    array $keys,
    string $default = ""
): string {

    foreach ($keys as $key) {

        if (
            array_key_exists($key, $row) &&
            $row[$key] !== null &&
            $row[$key] !== ""
        ) {
            return (string)$row[$key];
        }
    }

    return $default;
}
/* =====================================================
   SELECTED STAY TYPE
===================================================== */

$selectedStayType = strtolower(
    trim($stayPreference)
);


/* =====================================================
   GET STAYS
===================================================== */

$stayOptions = [];
$stayIndex = 0;

/* Get ALL stay types for this destination */
$sql = "
    SELECT *
    FROM stay_options
    WHERE destination = ?
";

$params = [$place];
$types = "s";


$sql .= "
    ORDER BY
        CASE
            WHEN image_url IS NOT NULL
            AND image_url <> ''
            THEN 0
            ELSE 1
        END,
        id DESC
";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Stay database error: " . $conn->error);
}

if (count($params) === 2) {

    $stmt->bind_param(
        $types,
        $params[0],
        $params[1]
    );

} else {

    $stmt->bind_param(
        $types,
        $params[0]
    );
}

$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $type = safeValue(
        $row,
        ["stay_type"],
        "Stay"
    );

    $rating = (float)safeValue(
    $row,
    ["rating"],
    "0"
);

$price = (float)safeValue(
    $row,
    ["estimated_price", "price"],
    "0"
);
     
    if ($price <= 0) {

    $stayMultipliers = [
        0.75,
        0.90,
        1.10
    ];

    $multiplier =
        $stayMultipliers[
            $stayIndex % count($stayMultipliers)
        ];

    $price = round(
        $stayPerNightBudget * $multiplier
    );

    $stayIndex++;
}
    $stayOptions[] = [
        "id" => (int)($row["id"] ?? 0),

        "type" => $type,

        "name" => safeValue(
            $row,
            ["name", "stay_name"],
            "Stay Option"
        ),

        "description" => safeValue(
            $row,
            ["description"],
            "Comfortable stay option for your trip."
        ),

        "price" => $price,

        "rating" => $rating,

        "image" => safeValue(
            $row,
            ["image_url", "image"],
            ""
        ),

        "map" => safeValue(
            $row,
            ["map_url"],
            ""
        )
    ];
}

$stmt->close();


/* =====================================================
   GET RESTAURANTS
===================================================== */

$foodOptions = [];

$sql = "
    SELECT *
    FROM restaurant_options
    WHERE destination = ?
    ORDER BY
    (description LIKE '%[GEOAPIFY_SYNC]%') DESC,
    rating DESC,
    estimated_price ASC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Food database error: " . $conn->error);
}

$stmt->bind_param("s", $place);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $price = (float)safeValue(
        $row,
        [
            "estimated_price",
            "price",
            "average_price",
            "price_per_meal"
        ],
        "0"
    );
    if ($price <= 0) {
    $price = round($foodPerDayBudget / 2);
}

    $rating = (float)safeValue(
        $row,
        ["rating"],
        "0"
    );

    $foodOptions[] = [
        "id" => (int)($row["id"] ?? 0),

        "type" => safeValue(
            $row,
            [
                "restaurant_type",
                "food_type",
                "category"
            ],
            "Restaurant"
        ),

        "name" => safeValue(
            $row,
            [
                "name",
                "restaurant_name"
            ],
            "Restaurant Option"
        ),

        "description" => safeValue(
            $row,
            ["description"],
            "A convenient food option for your trip."
        ),

        "price" => $price,

        "rating" => $rating,

        "image" => safeValue(
            $row,
            ["image_url", "image"],
            ""
        ),

        "map" => safeValue(
            $row,
            ["map_url"],
            ""
        )
    ];
}

$stmt->close();


/* =====================================================
   SELECT STAY + FOOD
===================================================== */

$error = "";


/*
   We use one form for both selections.
   User can choose one stay and one food option.
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["continue_plan"])
) {

    $stayId = (int)($_POST["stay_id"] ?? 0);

    


/* ---------------- STAY ---------------- */

    if ($stayId > 0) {

        $stmt = $conn->prepare(
            "SELECT *
             FROM stay_options
             WHERE id = ?
             AND destination = ?
             LIMIT 1"
        );

        if (!$stmt) {
            die("Stay selection error: " . $conn->error);
        }

        $stmt->bind_param(
            "is",
            $stayId,
            $place
        );

        $stmt->execute();

        $result = $stmt->get_result();

        if ($result->num_rows > 0) {

            $stay = $result->fetch_assoc();

            $_SESSION["selected_stay"] = [

                "id" =>
                    (int)$stay["id"],

                "destination" =>
                    $place,

                "stay_type" =>
                    safeValue(
                        $stay,
                        ["stay_type"],
                        "Stay"
                    ),

                "name" =>
                    safeValue(
                        $stay,
                        ["name", "stay_name"],
                        "Selected Stay"
                    ),

                "description" =>
                    safeValue(
                        $stay,
                        ["description"],
                        ""
                    ),

                "estimated_price" =>
                    (float)safeValue(
                        $stay,
                        [
                            "estimated_price",
                            "price"
                        ],
                        "0"
                    ),

                "rating" =>
                    (float)safeValue(
                        $stay,
                        ["rating"],
                        "0"
                    ),

                "image_url" =>
                    safeValue(
                        $stay,
                        ["image_url", "image"],
                        ""
                    ),

                "map_url" =>
                    safeValue(
                        $stay,
                        ["map_url"],
                        ""
                    )
            ];

        }

        $stmt->close();
    }




/* ---------------- CONTINUE ---------------- */

    if (!isset($_SESSION["selected_stay"])) {

    $error = "Please select one stay.";

} else {

    unset($_SESSION["selected_restaurant"]);

    header(
        "Location: transport.php?place=" .
        urlencode($place)
    );

    exit();
}
}

/* FOOD - leave as it is */

/* ==========================================
   SMART FOOD RECOMMENDATION
   3 BEST OPTIONS FOR EACH FOOD TYPE
========================================== */
/* =====================================================
   SMART RECOMMENDATION
===================================================== */

/* ---------- NORMALIZE STAY ---------- */

$selectedStayType = strtolower(trim($stayPreference));


if (str_contains($selectedStayType, "hotel")) {
    $selectedStayType = "hotel";
}
elseif (str_contains($selectedStayType, "resort")) {
    $selectedStayType = "resort";
}
elseif (
    str_contains($selectedStayType, "homestay") ||
    str_contains($selectedStayType, "home stay")
) {
    $selectedStayType = "homestay";
}


/* ---------- FILTER SELECTED STAY ONLY ---------- */

$filteredStayOptions = [];

foreach ($stayOptions as $stay) {
    

    $type = strtolower(trim($stay["type"] ?? ''));

    if (str_contains($type, "hotel")) {
        $type = "hotel";
    }
    elseif (str_contains($type, "resort")) {
        $type = "resort";
    }
    elseif (
        str_contains($type, "homestay") ||
        str_contains($type, "home stay")
    ) {
        $type = "homestay";
    }
    

    if ($type === $selectedStayType) {
        $filteredStayOptions[] = $stay;
    }

}


/* ---------- SORT STAY BY BUDGET ---------- */

usort(
    $filteredStayOptions,
    function ($a, $b) use ($stayPerNightBudget) {

        $aDiff = abs(
            $a["price"] - $stayPerNightBudget
        );

        $bDiff = abs(
            $b["price"] - $stayPerNightBudget
        );

        return $aDiff <=> $bDiff;
    }
);


/* SHOW BEST 3 */

$stayDisplay = array_slice(
    $filteredStayOptions,
    0,
    3
);
/* ---------- STAY AVAILABILITY MESSAGE ---------- */

$stayAvailableCount = count($filteredStayOptions);

if ($stayAvailableCount === 0) {

    $stayMessageTitle = "Let's find another option ✨";
    $stayMessageText =
        "We couldn't find a stay of this type in this area right now. "
        . "You can explore nearby areas for more comfortable options.";

} elseif ($stayAvailableCount === 1) {

    $stayMessageTitle = "A peaceful stay awaits you 🌿";
    $stayMessageText =
        "We found 1 " . ucfirst($selectedStayType) .
        " option nearby. Take a look and choose what suits your trip.";

} elseif ($stayAvailableCount === 2) {

    $stayMessageTitle = "A couple of lovely options for you ✨";
    $stayMessageText =
        "We found 2 " . ucfirst($selectedStayType) .
        " options nearby. Explore them and choose your favourite.";

} else {

    $stayMessageTitle = "Comfortable stays, picked for your journey ✨";
    $stayMessageText =
        "We found " . $stayAvailableCount . " " .
        ucfirst($selectedStayType) .
        " options. Here are 3 recommendations for your trip.";
}


/* =====================================================
   FOOD
===================================================== */

$selectedFoodType = strtolower(
    trim($foodPreference)
);


/* Normalize selected food */

if (
    $selectedFoodType === "veg" ||
    $selectedFoodType === "vegetarian"
) {
    $selectedFoodType = "veg";
}
elseif (
    $selectedFoodType === "nonveg" ||
    $selectedFoodType === "non-veg" ||
    $selectedFoodType === "non veg"
) {
    $selectedFoodType = "nonveg";
}
else {
    $selectedFoodType = "both";
}


/* ---------- FILTER FOOD BY PREFERENCE ---------- */

$filteredFoodOptions = [];

foreach ($foodOptions as $food) {

    $type = strtolower(
        trim($food["type"] ?? "")
    );

    /* Normalize food type */

    if (
        str_contains($type, "nonveg") ||
        str_contains($type, "non-veg") ||
        str_contains($type, "non veg")
    ) {

        $type = "nonveg";

    } elseif (
        str_contains($type, "veg") ||
        str_contains($type, "vegetarian")
    ) {

        $type = "veg";

    } else {

        $type = "both";
    }


    /* User preference */

    if ($selectedFoodType === "veg") {

        if ($type === "veg" || $type === "both") {
            $filteredFoodOptions[] = $food;
        }

    } elseif ($selectedFoodType === "nonveg") {

        if ($type === "nonveg" || $type === "both") {
            $filteredFoodOptions[] = $food;
        }

    } else {

        /* Both = show everything */

        $filteredFoodOptions[] = $food;
    }
}

   



/* ---------- SORT FOOD BY BUDGET ---------- */

$foodPerMealBudget =
    $foodPerDayBudget / 3;


usort(
    $filteredFoodOptions,
    function ($a, $b) use ($foodPerMealBudget) {

        $aDiff = abs(
            $a["price"] - $foodPerMealBudget
        );

        $bDiff = abs(
            $b["price"] - $foodPerMealBudget
        );

        return $aDiff <=> $bDiff;
    }
);


/* SHOW BEST 3 */

$foodDisplay = array_slice(
    $filteredFoodOptions,
    0,
    3
);

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>
    Stay & Food |
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

/* ===============================
   BASE
================================ */

*{
    box-sizing:border-box;
}

body{
    margin:0;
    font-family:"Manrope", Arial, sans-serif;
    color:#253047;

    background:
        radial-gradient(
            circle at 10% 15%,
            rgba(255,145,88,.20),
            transparent 28%
        ),
        radial-gradient(
            circle at 90% 20%,
            rgba(122,92,255,.18),
            transparent 30%
        ),
        radial-gradient(
            circle at 50% 85%,
            rgba(63,190,137,.16),
            transparent 30%
        ),
        linear-gradient(
            135deg,
            #f8f7ff,
            #eef0fb,
            #f7f5f3
        );
}

.page{
    max-width:1140px;
    margin:auto;
    padding:28px 20px 60px;
}

/* ===============================
   TOP BAR
================================ */

.topbar{
    display:flex;
    align-items:center;
    justify-content:space-between;

    margin-bottom:22px;
}

.back-btn{
    display:flex;
    align-items:center;
    gap:8px;

    text-decoration:none;

    color:#384155;

    font-size:12px;
    font-weight:900;

    transition:.25s;
}

.back-btn:hover{
    color:#7c5ce6;
    transform:translateX(-3px);
}

.brand{
    font-size:14px;
    font-weight:900;

    color:#394052;
}


/* ===============================
   HERO
================================ */

.hero{
    position:relative;

    overflow:hidden;

    padding:32px;

    border-radius:28px;

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #f7f3ff
        );

    border:1px solid #ebe6f5;

    box-shadow:
        0 18px 45px
        rgba(35,40,70,.10);
}

.hero::before{
    content:"";

    position:absolute;

    width:240px;
    height:240px;

    border-radius:50%;

    background:#ff9d62;

    opacity:.12;

    top:-120px;
    right:-70px;
}

.hero::after{
    content:"";

    position:absolute;

    width:180px;
    height:180px;

    border-radius:50%;

    background:#7c5ce6;

    opacity:.10;

    bottom:-100px;
    left:35%;
}

.hero-kicker{
    position:relative;
    z-index:2;

    color:#7c5ce6;

    font-size:10px;
    font-weight:900;

    letter-spacing:1.8px;
}

.hero h1{
    position:relative;
    z-index:2;

    margin:8px 0 6px;

    font-size:38px;
    font-weight:900;

    color:#1b2130;
}

.hero p{
    position:relative;
    z-index:2;

    margin:0;

    max-width:600px;

    color:#697386;

    font-size:12px;
    font-weight:600;

    line-height:1.7;
}


/* ===============================
   BUDGET
================================ */

.budget-grid{
    position:relative;
    z-index:2;

    margin-top:22px;

    display:grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:13px;
}

.budget-card{
    padding:15px;

    border-radius:17px;

    background:
        rgba(255,255,255,.82);

    border:
        1px solid rgba(220,220,235,.8);

    backdrop-filter:blur(8px);

    transition:.3s;
}

.budget-card:hover{
    transform:translateY(-4px);

    box-shadow:
        0 14px 30px
        rgba(40,45,80,.10);
}

.budget-card:nth-child(1){
    border-top:4px solid #ff8b4c;
}

.budget-card:nth-child(2){
    border-top:4px solid #7c5ce6;
}

.budget-card:nth-child(3){
    border-top:4px solid #22a65a;
}

.budget-card span{
    display:block;

    color:#7a8294;

    font-size:8px;
    font-weight:900;

    letter-spacing:.6px;
}

.budget-card strong{
    display:block;

    margin-top:5px;

    color:#202637;

    font-size:21px;
    font-weight:900;
}


/* ===============================
   SECTION
================================ */

.section{
    margin-top:38px;
}

.section-title{
    margin-bottom:18px;
}

.section-title span{
    color:#7c5ce6;

    font-size:10px;
    font-weight:900;

    letter-spacing:1.6px;
}

.section-title h2{
    margin:6px 0;

    color:#202637;

    font-size:28px;
    font-weight:900;
}

.section-title p{
    margin:0;

    color:#737d90;

    font-size:11px;
    font-weight:600;
}


/* ===============================
   TYPE FILTER
================================ */

.type-filter{
    display:flex;
    flex-wrap:wrap;

    gap:10px;

    margin-bottom:18px;
}

.filter-chip{
    border:0;

    padding:10px 15px;

    border-radius:999px;

    cursor:pointer;

    font-family:inherit;

    font-size:10px;
    font-weight:900;

    transition:.25s;
}

.filter-chip.all{
    background:#242b3c;
    color:#fff;
}

.filter-chip.hotel{
    background:#fff0e7;
    color:#e56d25;
}

.filter-chip.resort{
    background:#f0ecff;
    color:#7253d8;
}

.filter-chip.home{
    background:#e7f7ed;
    color:#238c4c;
}

.filter-chip.veg{
    background:#e8f8ed;
    color:#238c4c;
}

.filter-chip.nonveg{
    background:#fff0eb;
    color:#d85d35;
}

.filter-chip.both{
    background:#f1edff;
    color:#7155d8;
}

.filter-chip:hover{
    transform:translateY(-3px);
}


/* ===============================
   GRID
================================ */

.option-grid{
    display:grid;

    grid-template-columns:
        repeat(3,minmax(0,1fr));

    gap:18px;
}


/* ===============================
   PREMIUM CARD
================================ */

.option-card{
    position:relative;
    overflow:hidden;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,.96),
            rgba(248,248,255,.90)
        );

    border:1px solid rgba(130,120,180,.18);

    border-radius:22px;

    box-shadow:
        0 15px 35px
        rgba(64,55,110,.10);

    transition:
        transform .35s ease,
        box-shadow .35s ease;

    animation:
        cardReveal .6s ease both;
}

.option-card::before{
    content:"";

    position:absolute;

    top:0;
    left:0;

    width:100%;
    height:5px;

    background:
        linear-gradient(
            90deg,
            #f28c5b,
            #7459d9,
            #2f8b67
        );
}

.option-card::after{
    content:"";

    position:absolute;

    width:130px;
    height:130px;

    border-radius:50%;

    top:-70px;
    left:-45px;

    background:
        rgba(242,140,91,.12);

    pointer-events:none;
}
.option-card:hover{
    transform:
        translateY(-8px)
        scale(1.01);

    box-shadow:
        0 24px 45px
        rgba(92,72,160,.18);
}
@keyframes cardReveal{

    from{
        opacity:0;

        transform:
            translateY(25px);
    }

    to{
        opacity:1;

        transform:
            translateY(0);
    }
}


/* ===============================
   NO PHOTO VISUAL HEADER
================================ */

.card-visual{
    position:relative;

    height:105px;

    overflow:hidden;

    display:flex;
    align-items:center;
    justify-content:center;

    background:
        linear-gradient(
            135deg,
            #fff7f2,
            #f4f0ff,
            #eefaf2
        );
}

.card-visual::before{
    content:"";

    position:absolute;

    width:150px;
    height:150px;

    border-radius:50%;

    background:#ff8b4c;

    opacity:.12;

    left:-50px;
    top:-65px;
}

.card-visual::after{
    content:"";

    position:absolute;

    width:140px;
    height:140px;

    border-radius:50%;

    background:#7c5ce6;

    opacity:.10;

    right:-45px;
    bottom:-75px;
}

.visual-title{
    position:relative;
    z-index:2;

    padding:12px 18px;

    border-radius:14px;

    background:rgba(255,255,255,.86);

    box-shadow:
        0 10px 25px
        rgba(40,40,70,.10);

    color:#2a3142;

    font-size:12px;
    font-weight:900;

    letter-spacing:.7px;

    text-transform:uppercase;
}


/* ===============================
   CARD BODY
================================ */

.option-body{
    padding:18px;
}

.option-type{
    display:inline-flex;

    padding:6px 11px;

    border-radius:999px;

    background:#f0edff;

    color:#7154d8;

    font-size:8px;
    font-weight:900;

    letter-spacing:.6px;
}

.option-body h3{
    margin:11px 0 7px;

    color:#202637;

    font-size:18px;
    font-weight:900;

    line-height:1.35;
}

.option-body p{
    margin:0;

    min-height:46px;

    color:#737d90;

    font-size:10px;
    font-weight:600;

    line-height:1.65;
}


/* ===============================
   PRICE + RATING
================================ */

.option-meta{
    display:flex;

    align-items:center;
    justify-content:space-between;

    margin-top:15px;

    padding-top:14px;

    border-top:
        1px dashed #dfe2ea;
}

.price{
    color:#e66f28;

    font-size:20px;
    font-weight:900;
}

.price small{
    display:block;

    margin-top:2px;

    color:#8992a2;

    font-size:8px;
    font-weight:800;
}

.rating{
    padding:7px 10px;

    border-radius:10px;

    background:#fff6df;

    color:#d28a00;

    font-size:10px;
    font-weight:900;
}


/* ===============================
   NOTE
================================ */

.option-note{
    margin-top:13px;

    padding:10px 11px;

    border-radius:12px;

    background:#eef9f1;

    color:#248448;

    font-size:9px;
    font-weight:800;

    line-height:1.5;
}


/* ===============================
   RADIO
================================ */

.select-radio{
    display:none;
}
.select-label{
    display:flex;
    align-items:center;
    justify-content:center;
    gap:8px;

    margin-top:14px;
    padding:12px;

    border-radius:13px;

    background:#ffffff;
    color:#28663a;

    border:1px solid #8fbc98;

    cursor:pointer;

    font-size:10px;
    font-weight:900;

    transition:.25s;
}

.select-label:hover{
    transform:translateY(-2px);

    box-shadow:
        0 10px 23px
        rgba(95,70,200,.15);
}

.select-radio:checked + .select-label{
    background:
        linear-gradient(
            135deg,
            #347e49,
            #205d34
        );

    color:#fff;

    border-color:#28693c;

    box-shadow:
        0 0 0 3px #acd1b3,
        0 0 20px rgba(51,126,70,.22);
}


.select-label:hover{
    transform:translateY(-2px);

    box-shadow:
        0 10px 23px
        rgba(95,70,200,.25);
}

.select-radio:checked + .select-label{
    background:
        linear-gradient(
            135deg,
            #22a65a,
            #147a3b
        );

    box-shadow:
        0 0 0 4px
        rgba(34,166,90,.14);
}


/* ===============================
   CONTINUE
================================ */

.continue-box{
    margin-top:35px;

    padding:18px;

    display:flex;
    justify-content:flex-end;

    border-radius:20px;

    background:
        linear-gradient(
            135deg,
            #fff,
            #f4f1ff
        );

    border:1px solid #e5e2ef;
}

.continue-btn{
    display:inline-flex;

    align-items:center;
    gap:10px;

    border:0;

    padding:14px 20px;

    border-radius:13px;

    cursor:pointer;

    font-family:inherit;

    background:
        linear-gradient(
            135deg,
            #22a65a,
            #147a3b
        );

    color:#fff;

    font-size:11px;
    font-weight:900;

    transition:.25s;
}

.continue-btn:hover{
    transform:translateY(-3px);

    box-shadow:
        0 12px 25px
        rgba(25,140,70,.24);
}


/* ===============================
   ERROR / EMPTY
================================ */

.error,
.empty{
    margin-top:20px;

    padding:16px;

    border-radius:15px;

    font-size:11px;
    font-weight:800;
}

.error{
    background:#fff0f0;
    color:#b33c3c;
}

.empty{
    background:#f3f5f8;
    color:#667085;
}


/* ===============================
   RESPONSIVE
================================ */

@media(max-width:900px){

    .option-grid{
        grid-template-columns:
            repeat(2,1fr);
    }

}

@media(max-width:600px){

    .page{
        padding:20px 14px 45px;
    }

    .brand{
        display:none;
    }

    .hero{
        padding:24px 20px;
    }

    .hero h1{
        font-size:30px;
    }

    .budget-grid{
        grid-template-columns:1fr;
    }

    .option-grid{
        grid-template-columns:1fr;
    }

    .continue-box{
        justify-content:stretch;
    }

    .continue-btn{
        width:100%;
        justify-content:center;
    }

}
/* FOOD CARD - COMPACT LIKE STAY CARD */

.food-card{
    min-height:0;
}

.food-card .card-visual{
    height:105px;
}

.food-card .option-body{
    padding:18px;
}

.food-card .option-body p{
    min-height:46px;
}

.food-card:hover{
    transform:translateY(-8px) scale(1.01);
}

/* SMALL DELAY FOR EACH CARD */
.food-card:nth-child(1){
    animation-delay:.05s;
}

.food-card:nth-child(2){
    animation-delay:.15s;
}

.food-card:nth-child(3){
    animation-delay:.25s;
}
/* =====================================================
   SMART BUDGET GREEN THEME OVERRIDE
===================================================== */

body{
    color:#102d18 !important;
    background:
        linear-gradient(
            135deg,
            #f3faf4,
            #e8f5ea
        ) !important;
}

.back-btn{
    color:#28663a !important;
}

.back-btn:hover{
    color:#1b5d31 !important;
}

.brand{
    color:#1b5d31 !important;
}

.hero{
    background:
        linear-gradient(
            135deg,
            #ffffff,
            #e8f5ea
        ) !important;
    border-color:#9ec5a5 !important;
    box-shadow:0 16px 36px rgba(28,82,42,.14) !important;
}

.hero::before{
    background:#4d965d !important;
    opacity:.10 !important;
}

.hero::after{
    background:#2d7544 !important;
    opacity:.08 !important;
}

.hero-kicker{
    color:#28693d !important;
}

.hero h1{
    color:#102d18 !important;
}

.hero p{
    color:#49634f !important;
}

.budget-card{
    background:rgba(255,255,255,.92) !important;
    border-color:#b1d1b7 !important;
}

.budget-card:nth-child(1),
.budget-card:nth-child(2),
.budget-card:nth-child(3){
    border-top-color:#4d965d !important;
}

.budget-card span{
    color:#526e5a !important;
}

.budget-card strong{
    color:#143820 !important;
}

.section-title span{
    color:#28693d !important;
}

.section-title h2{
    color:#102d18 !important;
}

.section-title p{
    color:#496650 !important;
}

.option-card{
    background:
        linear-gradient(
            145deg,
            #ffffff,
            #f3faf4
        ) !important;
    border-color:#a8cbaa !important;
    box-shadow:0 15px 35px rgba(31,83,43,.11) !important;
}

.option-card::before{
    background:
        linear-gradient(
            90deg,
            #4f955d,
            #2d7544,
            #8fbd98
        ) !important;
}

.option-card::after{
    background:#4f955d !important;
    opacity:.08 !important;
}

.card-visual{
    background:
        linear-gradient(
            135deg,
            #e8f5ea,
            #d9eddc
        ) !important;
}

.card-visual::before{
    background:#4d965d !important;
}

.card-visual::after{
    background:#2d7544 !important;
}

.visual-title{
    color:#1b4f2b !important;
}

.option-type{
    background:#e8f5ea !important;
    color:#28683b !important;
}

.option-body h3{
    color:#102d18 !important;
}

.option-body p{
    color:#536b59 !important;
}

.price{
    color:#286b3d !important;
}

.rating{
    background:#eef8ef !important;
    color:#2c7840 !important;
}

.option-note{
    background:#eef8ef !important;
    color:#286d3c !important;
}

.select-label{
    background:
        linear-gradient(
            135deg,
            #347e49,
            #205d34
        ) !important;
}

.select-radio:checked + .select-label{
    background:
        linear-gradient(
            135deg,
            #2d7544,
            #164b28
        ) !important;
}

.continue-box{
    background:
        linear-gradient(
            135deg,
            #ffffff,
            #e8f5ea
        ) !important;
    border-color:#a8cbaa !important;
}

.continue-btn{
    background:
        linear-gradient(
            135deg,
            #347e49,
            #205d34
        ) !important;
}
/* FINAL STAY SELECT BUTTON FIX */

.select-label{
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:8px !important;

    margin-top:14px !important;
    padding:12px !important;

    border-radius:13px !important;

    background:#ffffff !important;
    color:#28663a !important;

    border:1px solid #8fbc98 !important;

    cursor:pointer !important;

    font-size:10px !important;
    font-weight:900 !important;

    transition:.25s !important;
}

.select-radio:checked + .select-label{
    background:linear-gradient(135deg,#347e49,#205d34) !important;
    color:#ffffff !important;
    border-color:#28693c !important;

    box-shadow:
        0 0 0 3px #acd1b3,
        0 0 20px rgba(51,126,70,.22) !important;
}
/* =========================================
   FRIENDLY STAY AVAILABILITY MESSAGE
========================================= */

.stay-message{
    display:flex;
    align-items:center;
    gap:14px;

    margin-bottom:20px;
    padding:16px 18px;

    border-radius:18px;

    background:
        linear-gradient(
            135deg,
            #ffffff,
            #eef8f0
        );

    border:1px solid #b8d7bd;

    box-shadow:
        0 10px 25px rgba(35,90,48,.08);

    animation:stayMessageIn .45s ease both;
}

.stay-message-icon{
    width:46px;
    height:46px;

    flex-shrink:0;

    display:flex;
    align-items:center;
    justify-content:center;

    border-radius:14px;

    background:#e2f3e5;

    font-size:21px;
}

.stay-message strong{
    display:block;

    color:#174b28;

    font-size:13px;
    font-weight:900;

    margin-bottom:4px;
}

.stay-message p{
    margin:0;

    color:#5b7061;

    font-size:10px;
    font-weight:600;

    line-height:1.6;
}

@keyframes stayMessageIn{

    from{
        opacity:0;
        transform:translateY(8px);
    }

    to{
        opacity:1;
        transform:translateY(0);
    }
}

.stay-message.empty{
    background:#f5faf6;
    border-color:#c7ddca;
}

</style>

</head>


<body>

<div class="page">

    <div class="topbar">

        <a
            href="destination.php"
            class="back-btn"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Back
        </a>

        <div class="brand">
            Smart Budget Trip Planner
        </div>

    </div>


    <!-- HERO -->

    <section class="hero">

        <div class="hero-kicker">
            SMART TRIP SETUP
        </div>

        <h1>
            <?php echo htmlspecialchars($place); ?>
        </h1>

        <p>
            Choose one stay for your trip. Food options are shown as recommendations based on your preference and budget.
        </p>


        <div class="budget-grid">

            <div class="budget-card">

                <span>
                    TOTAL TRIP BUDGET
                </span>

                <strong>
                    ₹<?php echo number_format($totalBudget); ?>
                </strong>

            </div>


            <div class="budget-card">

                <span>
                    RECOMMENDED STAY
                </span>

                <strong>
                    ₹<?php echo number_format($recommendedStayBudget); ?>
                </strong>

            </div>


            <div class="budget-card">

                <span>
                    RECOMMENDED FOOD
                </span>

                <strong>
                    ₹<?php echo number_format($recommendedFoodBudget); ?>
                </strong>

            </div>

        </div>

    </section>


    <?php if ($error !== ""): ?>

        <div class="error">
            <?php echo htmlspecialchars($error); ?>
        </div>

    <?php endif; ?>


    <form method="POST" action="">

        <!-- =========================
             STAY
        ========================== -->

        <section class="section">

            <div class="section-title">

                <span>
                    STEP 1 · STAY
                </span>

                <h2>
                    Choose your stay
                </h2>

                <p>
                    Hotel, resort or homestay — choose what suits your trip.
                </p>

            </div>


            

            <?php if (empty($stayDisplay)): ?>

    <div class="stay-message empty">
        <div class="stay-message-icon">✨</div>

        <div>
            <strong>
                <?php echo htmlspecialchars($stayMessageTitle); ?>
            </strong>

            <p>
                <?php echo htmlspecialchars($stayMessageText); ?>
            </p>
        </div>
    </div>

<?php else: ?>

    <div class="stay-message">
        <div class="stay-message-icon">
            <?php
            if ($stayAvailableCount === 1) {
                echo "🌿";
            } elseif ($stayAvailableCount === 2) {
                echo "✨";
            } else {
                echo "🏨";
            }
            ?>
        </div>

        <div>
            <strong>
                <?php echo htmlspecialchars($stayMessageTitle); ?>
            </strong>

            <p>
                <?php echo htmlspecialchars($stayMessageText); ?>
            </p>
        </div>
    </div>

    


                <div class="option-grid">

                    <?php foreach (
                        $stayDisplay
                        as $stayIndex => $stay
                    ): ?>
                    <?php

/* Use the actual estimated price from database */

/* Total stay cost */

$stayTotal =
    $stay["price"] * $days;


/* Budget status */

$stayStatus =
    $stay["price"] <= $stayPerNightBudget
    ? "Good match for your budget"
    : "Above recommended stay budget";

?>

                       
                       <article
    class="option-card stay-card"
    data-type="<?php
        echo strtolower(
            str_replace(
                " ",
                "",
                trim($stay["type"])
            )
        );
    ?>"
>
                        

                            <!-- NO IMAGE -->

                            <div class="card-visual">

                                <div class="visual-title">
                                    <?php
                                    echo htmlspecialchars(
                                        $stay["type"]
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="option-body">

                                <span class="option-type">
                                    <?php
                                    echo htmlspecialchars(
                                        $stay["type"]
                                    );
                                    ?>
                                </span>


                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $stay["name"]
                                    );
                                    ?>
                                </h3>


                                <p>
                                    <?php
                                    echo htmlspecialchars(
                                        str_replace(
                                            "[GEOAPIFY_SYNC]",
                                            "",
                                            $stay["description"]
                                        )
                                    );
                                    ?>
                                </p>


                                <div class="option-meta">

                                    <div class="price">
                                        

                                        <?php if (
                                            $stay["price"] > 0
                                        ): ?>

                                            ₹<?php
                                            echo number_format(
                                                $stay["price"]
                                            );
                                            ?>

                                            <small>
                                                estimated / night
                                            </small>

                                        <?php else: ?>

                                            <span
                                                style="
                                                font-size:13px;
                                                color:#6d7584;
                                                "
                                            >
                                                Price unavailable
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <div class="rating">

                                        <?php if (
                                            $stay["rating"] > 0
                                        ): ?>

                                            ⭐
                                            <?php
                                            echo number_format(
                                                $stay["rating"],
                                                1
                                            );
                                            ?>

                                        <?php else: ?>

                                            Rating unavailable

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="option-note">

                                    <?php
                                    echo htmlspecialchars(
                                        $stayStatus
                                    );
                                    ?>

                                    ·

                                    <?php echo $days; ?>
                                    days ≈ ₹<?php
                                    echo number_format(
                                        $stayTotal
                                    );
                                    ?>

                                </div>


                                <input
                                    type="radio"
                                    class="select-radio"
                                    name="stay_id"
                                    value="<?php
                                        echo (int)$stay["id"];
                                    ?>"
                                    id="stay_<?php
                                        echo (int)$stay["id"];
                                    ?>"
                                >


                                <label
                                    for="stay_<?php
                                        echo (int)$stay["id"];
                                    ?>"
                                    class="select-label"
                                >

                                    <i
                                        class="fa-solid fa-check"
                                    ></i>

                                    Select This Stay

                                </label>

                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <!-- =========================
             FOOD
        ========================== -->

        <section class="section">

            <div class="section-title">

                <span>
                    FOOD RECOMMENDATIONS
                </span>

                <h2>
                    Food Recommendations
                </h2>

                <p>
                    Here are 3 food recommendations based on your food preference and trip budget.
                </p>

            </div>


        <?php if (empty($foodDisplay)): ?>

                <div class="empty">
                    No restaurant options found for
                    <?php echo htmlspecialchars($place); ?>.
                </div>

            <?php else: ?>


                <div class="option-grid">

                    <?php foreach (
                        $foodDisplay
                        as $foodIndex => $food
                    ): ?>

                        <?php

                        $foodMultipliers = [
                            0.75,
                            0.90,
                            1.10
                        ];

                        

                        $foodTotal =
                            $food["price"]
                            * $days
                            * 3;

                        $foodStatus =
                            $food["price"]
                            <= ($foodPerDayBudget / 3)
                            ? "Good match for your food budget"
                            : "Higher food estimate";

                        ?>


                        <article
                            class="option-card food-card"
                            data-type="<?php
                                echo htmlspecialchars(
                                    strtolower(
                                        str_replace(
                                            " ",
                                            "",
                                            $food["type"]
                                        )
                                    )
                                );
                            ?>"
                        >

                            <!-- NO IMAGE -->

                            <div class="card-visual">

                                <div class="visual-title">
                                    <?php
                                    echo htmlspecialchars(
                                        $food["type"]
                                    );
                                    ?>
                                </div>

                            </div>


                            <div class="option-body">

                                <span class="option-type">
                                    <?php
                                    echo htmlspecialchars(
                                        $food["type"]
                                    );
                                    ?>
                                </span>


                                <h3>
                                    <?php
                                    echo htmlspecialchars(
                                        $food["name"]
                                    );
                                    ?>
                                </h3>


                                <p>
                                    <?php
                                    echo htmlspecialchars(
                                        str_replace(
                                            "[GEOAPIFY_SYNC]",
                                            "",
                                            $food["description"]
                                        )
                                    );
                                    ?>
                                </p>


                                <div class="option-meta">

                                    <div class="price">

                                        ₹<?php
                                        echo number_format(
                                            $food["price"]
                                        );
                                        ?>

                                        <small>
                                            approx / meal
                                        </small>

                                    </div>


                                    <div class="rating">

                                        <?php if (
                                            $food["rating"] > 0
                                        ): ?>

                                            ⭐
                                            <?php
                                            echo number_format(
                                                $food["rating"],
                                                1
                                            );
                                            ?>

                                        <?php else: ?>

                                            Rating unavailable

                                        <?php endif; ?>

                                    </div>

                                </div>


                                <div class="option-note">

                                    <?php
                                    echo htmlspecialchars(
                                        $foodStatus
                                    );
                                    ?>

                                    · 3 meals ×
                                    <?php echo $days; ?>
                                    days ≈ ₹<?php
                                    echo number_format(
                                        $foodTotal
                                    );
                                    ?>

                                </div>


                                

                                
                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>

        </section>


        <!-- CONTINUE -->

        <div class="continue-box">

            <button
                type="submit"
                name="continue_plan"
                class="continue-btn"
            >

                Continue to Transport

                <i
                    class="fa-solid fa-arrow-right"
                ></i>

            </button>

        </div>

    </form>

</div>

<script>

function filterCards(section, type){

    let selector = "";

    if(section === "stay"){
        selector = ".stay-card";
    }
    else if(section === "food"){
        selector = ".food-card";
    }

    const cards = document.querySelectorAll(selector);

    cards.forEach(function(card){

        const cardType = card.dataset.type;

        if(cardType === type){
            card.style.display = "";
        }
        else{
            card.style.display = "none";
        }

    });

}



</script>

</body>
</html>