<?php

session_start();

include "db.php";

if (file_exists("pexels-api.php")) {
    require_once "pexels-api.php";
}

date_default_timezone_set("Asia/Kolkata");


/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.html");
    exit();

}

$user_id = (int) $_SESSION["user_id"];


/* =====================================================
   DESTINATION
===================================================== */

$placeKey = trim($_GET["place"] ?? "Kodaikanal");

if ($placeKey === "") {
    $placeKey = "Kodaikanal";
}


/* =====================================================
   DESTINATION MASTER
===================================================== */

$destinationInfo = [

    "Kodaikanal" => [
        "tagline" => "Waves & Mist",
        "description" =>
            "A peaceful hill station with cool weather, beautiful lakes, waterfalls and green valleys.",
        "hero" => "image/kodaikanal.jpg",
        "weather" => "18°C",
        "condition" => "Light Rain",
        "humidity" => "82%",
        "wind" => "12 km/h",
        "rain" => "60%"
    ],

    "Palani" => [
        "tagline" => "Hills & Heritage",
        "description" =>
            "A spiritual hill destination filled with culture, scenic views and peaceful experiences.",
        "hero" => "image/palani.jpg",
        "weather" => "27°C",
        "condition" => "Partly Cloudy",
        "humidity" => "65%",
        "wind" => "10 km/h",
        "rain" => "20%"
    ],

    "Sirumalai" => [
        "tagline" => "Into The Green Hills",
        "description" =>
            "A calm hill escape surrounded by forests, viewpoints, fresh air and peaceful nature.",
        "hero" => "image/sirumalai.jpg",
        "weather" => "21°C",
        "condition" => "Cool & Pleasant",
        "humidity" => "74%",
        "wind" => "9 km/h",
        "rain" => "35%"
    ]

];


/* =====================================================
   FIND CORRECT DESTINATION
===================================================== */

$placeName = "Kodaikanal";

foreach ($destinationInfo as $name => $info) {

    if (strcasecmp($name, $placeKey) === 0) {
        $placeName = $name;
        break;
    }

}

$currentInfo = $destinationInfo[$placeName] ?? $destinationInfo["Kodaikanal"];


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

$stmt->bind_param(
    "i",
    $user_id
);

$stmt->execute();

$result = $stmt->get_result();

$trip = [];

if ($result->num_rows > 0) {

    $trip = $result->fetch_assoc();

}

$stmt->close();


/* =====================================================
   TRIP VALUES
===================================================== */

$totalBudget = (float) ($trip["total_budget"] ?? 15000);

$members = max(
    1,
    (int) ($trip["members"] ?? 5)
);

$days = max(
    1,
    (int) ($trip["days"] ?? 3)
);

$transport = trim(
    $trip["transport"] ?? "Car"
);

if ($transport === "") {
    $transport = "Car";
}

$startDateRaw = trim(
    $trip["start_date"] ?? ""
);

if ($startDateRaw === "") {
    $startDateRaw = date("Y-m-d");
}


/* =====================================================
   SELECTED STAY
===================================================== */

$selectedStay =
    $_SESSION["selected_stay"] ?? [];


/* =====================================================
   SELECTED RESTAURANT
===================================================== */

$selectedRestaurant =
    $_SESSION["selected_restaurant"] ?? [];


/* =====================================================
   SELECTED TRANSPORT
===================================================== */

$selectedTransport =
    $_SESSION["selected_transport"] ?? [];


/* =====================================================
   SAFE VALUES
===================================================== */

$stayName =
    $selectedStay["name"]
    ?? "Selected Stay";

$stayPrice =
    (float) (
        $selectedStay["estimated_price"]
        ?? 1000
    );

$stayImage =
    $selectedStay["image_url"]
    ?? "";

$restaurantName =
    $selectedRestaurant["name"]
    ?? "Recommended Restaurant";

$restaurantPrice =
    (float) (
        $selectedRestaurant["estimated_price"]
        ?? 250
    );

$restaurantImage =
    $selectedRestaurant["image_url"]
    ?? "";


/* =====================================================
   PEXELS HELPER
===================================================== */

function getPexelsImage(
    string $query,
    string $fallback
): string {

    if (
        function_exists("searchPexelsPhoto")
    ) {

        try {

            $photo =
                searchPexelsPhoto($query);

            if (
                $photo &&
                isset(
                    $photo["src"]["large2x"]
                )
            ) {

                return
                    $photo["src"]["large2x"];

            }

            if (
                $photo &&
                isset(
                    $photo["src"]["large"]
                )
            ) {

                return
                    $photo["src"]["large"];

            }

        } catch (Throwable $e) {

        }

    }

    return $fallback;

}


/* =====================================================
   HERO IMAGE
===================================================== */

$heroImage = getPexelsImage(
    $placeName .
    " Tamil Nadu mountain lake forest travel",
    $currentInfo["hero"]
);


/* =====================================================
   GET DESTINATION PLACES
===================================================== */

$allPlaces = [];

$stmt = $conn->prepare(
    "SELECT
        place_name,
        category,
        description,
        visit_duration,
        best_time,
        activity,
        estimated_cost,
        priority,
        image_url
     FROM destination_places
     WHERE destination = ?
     ORDER BY priority ASC, id ASC"
);

if ($stmt) {

    $stmt->bind_param(
        "s",
        $placeName
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while (
        $row =
        $result->fetch_assoc()
    ) {

        $allPlaces[] = [

            "name" =>
                $row["place_name"],

            "category" =>
                $row["category"] ?? "Sightseeing",

            "description" =>
                $row["description"] ?? "",

            "estimated_cost" =>
                (float) (
                    $row["estimated_cost"]
                    ?? 0
                ),

            "image" =>
                trim(
                    $row["image_url"]
                    ?? ""
                )

        ];

    }

    $stmt->close();

}


/* =====================================================
   FALLBACK PLACES
===================================================== */

$fallbackPlaces = [

    "Kodaikanal" => [

        [
            "name" => "Silver Cascade Falls",
            "description" =>
                "Enjoy the beautiful waterfall and cool mountain views.",
            "estimated_cost" => 150,
            "image" => ""
        ],

        [
            "name" => "Pillar Rocks",
            "description" =>
                "Explore the famous granite pillars and scenic viewpoint.",
            "estimated_cost" => 400,
            "image" => ""
        ],

        [
            "name" => "Kodaikanal Lake",
            "description" =>
                "Relax near the lake and enjoy boating and cycling.",
            "estimated_cost" => 200,
            "image" => ""
        ],

        [
            "name" => "Coaker's Walk",
            "description" =>
                "Walk through beautiful valleys and misty viewpoints.",
            "estimated_cost" => 100,
            "image" => ""
        ],

        [
            "name" => "Pine Forest",
            "description" =>
                "Spend peaceful time surrounded by tall pine trees.",
            "estimated_cost" => 0,
            "image" => ""
        ],

        [
            "name" => "Bryant Park",
            "description" =>
                "Enjoy colourful flowers and peaceful gardens.",
            "estimated_cost" => 100,
            "image" => ""
        ]

    ],

    "Palani" => [

        [
            "name" => "Palani Murugan Temple",
            "description" =>
                "Visit the famous hill temple and enjoy the spiritual atmosphere.",
            "estimated_cost" => 100,
            "image" => ""
        ],

        [
            "name" => "Idumban Hill",
            "description" =>
                "Explore peaceful hill views and scenic surroundings.",
            "estimated_cost" => 50,
            "image" => ""
        ],

        [
            "name" => "Palani Hills View",
            "description" =>
                "Enjoy beautiful panoramic views from the hills.",
            "estimated_cost" => 0,
            "image" => ""
        ]

    ],

    "Sirumalai" => [

        [
            "name" => "Sirumalai View Point",
            "description" =>
                "Enjoy peaceful mountain scenery and green valleys.",
            "estimated_cost" => 0,
            "image" => ""
        ],

        [
            "name" => "Sirumalai Forest",
            "description" =>
                "Explore refreshing nature and peaceful forest surroundings.",
            "estimated_cost" => 100,
            "image" => ""
        ],

        [
            "name" => "Hill Garden",
            "description" =>
                "Relax and enjoy the fresh air and green surroundings.",
            "estimated_cost" => 50,
            "image" => ""
        ]

    ]

];


if (empty($allPlaces)) {

    $allPlaces =
        $fallbackPlaces[$placeName]
        ?? [];

}


/* =====================================================
   FETCH UNIQUE PEXELS PLACE IMAGES
===================================================== */

$usedPlaceImages = [];

function getUniquePlaceImage(
    string $place,
    string $destination,
    string $fallback,
    array &$usedImages
): string {

    $queries = [
        $place . " " . $destination . " Tamil Nadu tourism",
        $place . " " . $destination . " travel photography",
        $place . " " . $destination . " scenic view",
        $place . " landmark India"
    ];

    foreach ($queries as $query) {

        $image = getPexelsImage($query, "");

        if ($image !== "" && !in_array($image, $usedImages, true)) {
            $usedImages[] = $image;
            return $image;
        }
    }

    // Use a destination/place specific fallback instead of reusing the hero.
    $fallbackKey = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $place . '-' . $destination));
    return $fallback !== "" ? $fallback : "https://images.pexels.com/photos/417074/pexels-photo-417074.jpeg?auto=compress&cs=tinysrgb&w=900&h=600&fit=crop&" . $fallbackKey;
}

foreach ($allPlaces as $index => $place) {

    $existingImage = trim($allPlaces[$index]["image"] ?? "");

    if ($existingImage !== "" && !in_array($existingImage, $usedPlaceImages, true)) {
        $usedPlaceImages[] = $existingImage;
        continue;
    }

    $allPlaces[$index]["image"] = getUniquePlaceImage(
        $place["name"],
        $placeName,
        "",
        $usedPlaceImages
    );
}


/* =====================================================
   ENSURE ENOUGH PLACES
===================================================== */

if (count($allPlaces) < 2) {

    $allPlaces =
        $fallbackPlaces[$placeName]
        ?? $fallbackPlaces["Kodaikanal"];

}


/* =====================================================
   FOOD IMAGES
===================================================== */

$breakfastImage = getPexelsImage(
    "South Indian breakfast idli dosa restaurant",
    $restaurantImage
);

$lunchImage = getPexelsImage(
    "South Indian lunch thali restaurant",
    $restaurantImage
);

$dinnerImage = getPexelsImage(
    "Indian dinner food restaurant",
    $restaurantImage
);

$shoppingImages = [

    getPexelsImage(
        "homemade chocolates shop",
        $heroImage
    ),

    getPexelsImage(
        "eucalyptus oil bottles",
        $heroImage
    ),

    getPexelsImage(
        "Indian tea spices shop",
        $heroImage
    ),

    getPexelsImage(
        "Indian handicraft souvenirs",
        $heroImage
    )

];


/* =====================================================
   DAY BUDGET
===================================================== */

$dailyBudget =
    $totalBudget / $days;


/* =====================================================
   HELPER FORMAT MONEY
===================================================== */

function money(
    float $amount
): string {

    return "₹" .
        number_format(
            round($amount)
        );

}


/* =====================================================
   GET DATE FOR DAY
===================================================== */

function getDayDate(
    string $startDate,
    int $dayNumber
): string {

    try {

        $date =
            new DateTime($startDate);

        $date->modify(
            "+" .
            ($dayNumber - 1) .
            " days"
        );

        return
            $date->format(
                "d M Y, l"
            );

    } catch (Throwable $e) {

        return "";

    }

}


/* =====================================================
   DISTRIBUTE PLACES
===================================================== */

$dayPlaces = [];

for (
    $day = 1;
    $day <= $days;
    $day++
) {

    $dayPlaces[$day] = [];

}

$placeIndex = 0;

for (
    $day = 1;
    $day <= $days;
    $day++
) {

    for (
        $slot = 0;
        $slot < 2;
        $slot++
    ) {

        if (
            isset(
                $allPlaces[$placeIndex]
            )
        ) {

            $dayPlaces[$day][] =
                $allPlaces[$placeIndex];

            $placeIndex++;

        } else {

            $placeIndex = 0;

            if (
                isset(
                    $allPlaces[$placeIndex]
                )
            ) {

                $dayPlaces[$day][] =
                    $allPlaces[$placeIndex];

                $placeIndex++;

            }

        }

    }

}


/* =====================================================
   USER NAME
===================================================== */

$userName =
    $_SESSION["user_name"]
    ?? "Traveler";

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
    My Trip | Smart Budget Trip Planner
</title>

<link
    rel="preconnect"
    href="https://fonts.googleapis.com"
>

<link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
>

<link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,700;0,800;1,700&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
>

<link
    rel="stylesheet"
    href="my-trips.css"
>

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<header class="topbar">

    <a
        href="dashboard.php"
        class="brand"
    >

        <div class="brand-icon">

            <i class="fa-solid fa-route"></i>

        </div>

        <div>

            <strong>
                Smart Budget
            </strong>

            <span>
                Trip Planner
            </span>

        </div>

    </a>


    <nav class="nav-menu">

        <a href="dashboard.php">
            Home
        </a>

        <a href="destination.php">
            Explore
        </a>

        <a
            href="my-trips.php?place=<?php echo urlencode($placeName); ?>"
            class="active"
        >
            My Trips
        </a>

        <a href="saved-places.php">
            Saved
        </a>

        <a href="budget.php">
            Budget
        </a>

        <a href="profile.php">
            Profile
        </a>

    </nav>


    <div class="profile-area">

        <button class="notification-btn">

            <i class="fa-regular fa-bell"></i>

            <span>3</span>

        </button>

        <div class="user-mini">

            <div class="avatar">

                <i class="fa-solid fa-user"></i>

            </div>

            <strong>
                <?php
                echo htmlspecialchars($userName);
                ?>
            </strong>

            <i class="fa-solid fa-chevron-down"></i>

        </div>

    </div>

</header>



<!-- =====================================================
     HERO
===================================================== -->

<section
    class="trip-hero"
    style="
        background-image:
        linear-gradient(
            90deg,
            rgba(4,35,22,.92),
            rgba(7,50,31,.70),
            rgba(0,0,0,.15)
        ),
        url('<?php echo htmlspecialchars($heroImage); ?>');
    "
>

    <div class="hero-overlay"></div>


    <div class="hero-content">

        <div class="hero-left">

            <div class="trip-badge">

                <i class="fa-solid fa-location-dot"></i>

                YOUR TRIP PLAN

                <span>
                    <i class="fa-solid fa-xmark"></i>
                </span>

            </div>


            <h1>

                <?php
                echo htmlspecialchars($placeName);
                ?>

                <span>🌿</span>

            </h1>


            <h2>
                <?php
                echo htmlspecialchars(
                    $currentInfo["tagline"]
                );
                ?>
            </h2>


            <p>

                <?php
                echo htmlspecialchars(
                    $currentInfo["description"]
                );
                ?>

            </p>


            <div class="trip-pills">

                <div>

                    <i class="fa-solid fa-location-dot"></i>

                    Tamil Nadu

                </div>

                <div>

                    <i class="fa-regular fa-calendar"></i>

                    <?php echo $days; ?> Days

                </div>

                <div>

                    <i class="fa-solid fa-users"></i>

                    <?php echo $members; ?> Travelers

                </div>

                <div>

                    <i class="fa-solid fa-car"></i>

                    <?php
                    echo htmlspecialchars(
                        ucfirst($transport)
                    );
                    ?>

                </div>

            </div>

        </div>



        <!-- BUDGET CARD -->

        <div class="budget-card glass-card">

            <div class="budget-title">

                <div>

                    <span>Total Trip Budget</span>

                    <h3>
                        <?php
                        echo money(
                            $totalBudget
                        );
                        ?>
                    </h3>

                </div>

                <div class="budget-icon">

                    <i class="fa-solid fa-wallet"></i>

                </div>

            </div>


            <div class="budget-progress">

                <span></span>

            </div>


            <div class="budget-lines">

                <div>

                    <span>
                        <i class="fa-solid fa-bed"></i>
                        Stay
                    </span>

                    <strong>
                        <?php
                        echo money(
                            $stayPrice * $days
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>
                        <i class="fa-solid fa-utensils"></i>
                        Food
                    </span>

                    <strong>
                        <?php
                        echo money(
                            $restaurantPrice *
                            3 *
                            $days
                        );
                        ?>
                    </strong>

                </div>


                <div>

                    <span>
                        <i class="fa-solid fa-camera"></i>
                        Activities
                    </span>

                    <strong>
                        ₹2,500
                    </strong>

                </div>


                <div>

                    <span>
                        <i class="fa-solid fa-bag-shopping"></i>
                        Shopping
                    </span>

                    <strong>
                        ₹1,500
                    </strong>

                </div>

            </div>


           <a href="budget.php" class="budget-btn">
    View Full Budget Details
    <i class="fa-solid fa-arrow-right"></i>
</a>

        </div>



        <!-- WEATHER CARD -->

        <div class="weather-card glass-card">

            <div class="weather-main">

                <div class="weather-icon">

                    <i class="fa-solid fa-cloud-rain"></i>

                </div>

                <h3>

                    <?php
                    echo htmlspecialchars(
                        $currentInfo["weather"]
                    );
                    ?>

                </h3>

            </div>


            <h4>

                <?php
                echo htmlspecialchars(
                    $currentInfo["condition"]
                );
                ?>

            </h4>


            <p>
                Feels like 17°C
            </p>


            <div class="weather-divider"></div>


            <div class="weather-stat">

                <span>
                    <i class="fa-solid fa-droplet"></i>
                    Humidity
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $currentInfo["humidity"]
                    );
                    ?>
                </strong>

            </div>


            <div class="weather-stat">

                <span>
                    <i class="fa-solid fa-wind"></i>
                    Wind
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $currentInfo["wind"]
                    );
                    ?>
                </strong>

            </div>


            <div class="weather-stat">

                <span>
                    <i class="fa-solid fa-cloud-showers-heavy"></i>
                    Rain Chance
                </span>

                <strong>
                    <?php
                    echo htmlspecialchars(
                        $currentInfo["rain"]
                    );
                    ?>
                </strong>

            </div>


            <button class="forecast-btn">

                View Forecast

                <i class="fa-solid fa-arrow-right"></i>

            </button>

        </div>

    </div>


    <div class="hero-wave"></div>

</section>



<!-- =====================================================
     WEATHER ALERT
===================================================== -->

<div class="page-wrap">

    <div class="weather-alert">

        <div class="alert-icon">

            <i class="fa-solid fa-umbrella"></i>

        </div>

        <strong>
            Weather Alert:
        </strong>

        Light rain expected in the afternoon.
        Carry an umbrella / raincoat.

    </div>


    <!-- =================================================
         MAIN CONTENT
    ================================================= -->

    <main class="main-layout">


        <!-- LEFT SIDE -->

        <section class="plan-column">


            <!-- DAY PLAN CARD -->

            <div class="plan-card">

                <div class="plan-header">

                    <div class="plan-title">

                        <div class="section-icon">

                            <i class="fa-regular fa-calendar-days"></i>

                        </div>

                        <div>

                            <h2>
                                DAY <span id="currentDayNumber">1</span> PLAN
                            </h2>

                            <p id="currentDateText">

                                <?php
                                echo getDayDate(
                                    $startDateRaw,
                                    1
                                );
                                ?>

                            </p>

                        </div>

                    </div>


                    <div class="day-tabs">

                        <?php
                        for (
                            $day = 1;
                            $day <= $days;
                            $day++
                        ) {
                        ?>

                        <button
                            class="day-tab <?php echo $day === 1 ? "active" : ""; ?>"
                            data-day="<?php echo $day; ?>"
                        >

                            Day <?php echo $day; ?>

                        </button>

                        <?php
                        }
                        ?>

                    </div>

                </div>



                <!-- DAY PANELS -->

                <?php
                for (
                    $day = 1;
                    $day <= $days;
                    $day++
                ) {

                    $places =
                        $dayPlaces[$day]
                        ?? [];

                    $place1 =
                        $places[0]
                        ?? $allPlaces[0];

                    $place2 =
                        $places[1]
                        ?? $allPlaces[1];

                    $breakfastCost =
                        max(
                            100,
                            round(
                                $restaurantPrice * .80
                            )
                        );

                    $lunchCost =
                        max(
                            150,
                            round(
                                $restaurantPrice
                            )
                        );

                    $dinnerCost =
                        max(
                            150,
                            round(
                                $restaurantPrice
                            )
                        );

                    $shoppingCost =
                        $day === 1
                        ? 500
                        : 300;

                    $stayDaily =
                        $stayPrice;

                    $placeCost =
                        (float)(
                            $place1["estimated_cost"]
                            ?? 0
                        )
                        +
                        (float)(
                            $place2["estimated_cost"]
                            ?? 0
                        );

                    $otherCost = 250;

                    $dayTotal =
                        $breakfastCost +
                        $lunchCost +
                        $dinnerCost +
                        $shoppingCost +
                        $stayDaily +
                        $placeCost +
                        $otherCost;

                    $remaining =
                        max(
                            0,
                            $dailyBudget -
                            $dayTotal
                        );

                    $usedPercent =
                        min(
                            100,
                            round(
                                (
                                    $dayTotal /
                                    max(
                                        1,
                                        $dailyBudget
                                    )
                                ) * 100
                            )
                        );
                ?>

                <div
                    class="day-panel <?php echo $day === 1 ? "show" : ""; ?>"
                    id="day-<?php echo $day; ?>"
                    data-date="<?php echo getDayDate($startDateRaw, $day); ?>"
                >


                    <div class="timeline">



                        <!-- START -->

                        <div class="timeline-row">

                            <div class="time">
                                06:00 AM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon">

                                    <i class="fa-solid fa-car"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Start Your Journey
                                    </h3>

                                    <p>
                                        Start your journey towards
                                        <?php echo htmlspecialchars($placeName); ?>
                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($heroImage); ?>"
                                        alt="Journey"
                                    >

                                </div>

                                <div class="cost-pill free">
                                    ₹0
                                </div>

                            </div>

                        </div>



                        <!-- BREAKFAST -->

                        <div class="timeline-row">

                            <div class="time">
                                08:30 AM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon food">

                                    <i class="fa-solid fa-mug-hot"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Breakfast
                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $restaurantName
                                        );
                                        ?>

                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($breakfastImage); ?>"
                                        alt="Breakfast"
                                    >

                                </div>

                                <div class="cost-pill">
                                    <?php echo money($breakfastCost); ?>
                                </div>

                            </div>

                        </div>



                        <!-- PLACE 1 -->

                        <div class="timeline-row">

                            <div class="time">
                                10:00 AM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon">

                                    <i class="fa-solid fa-camera"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $place1["name"]
                                        );
                                        ?>

                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $place1["description"]
                                            ?? "Explore this beautiful destination."
                                        );
                                        ?>

                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($place1["image"]); ?>"
                                        alt="<?php echo htmlspecialchars($place1["name"]); ?>"
                                    >

                                </div>

                                <div class="cost-pill">

                                    <?php
                                    echo money(
                                        (float)(
                                            $place1["estimated_cost"]
                                            ?? 0
                                        )
                                    );
                                    ?>

                                </div>

                            </div>

                        </div>



                        <!-- LUNCH -->

                        <div class="timeline-row">

                            <div class="time">
                                01:00 PM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon food">

                                    <i class="fa-solid fa-utensils"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Lunch
                                    </h3>

                                    <p>
                                        Recommended local meals
                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($lunchImage); ?>"
                                        alt="Lunch"
                                    >

                                </div>

                                <div class="cost-pill">
                                    <?php echo money($lunchCost); ?>
                                </div>

                            </div>

                        </div>



                        <!-- PLACE 2 -->

                        <div class="timeline-row">

                            <div class="time">
                                03:30 PM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon">

                                    <i class="fa-solid fa-mountain-sun"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>

                                        <?php
                                        echo htmlspecialchars(
                                            $place2["name"]
                                        );
                                        ?>

                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $place2["description"]
                                            ?? "Enjoy the scenic experience."
                                        );
                                        ?>

                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($place2["image"]); ?>"
                                        alt="<?php echo htmlspecialchars($place2["name"]); ?>"
                                    >

                                </div>

                                <div class="cost-pill">

                                    <?php
                                    echo money(
                                        (float)(
                                            $place2["estimated_cost"]
                                            ?? 0
                                        )
                                    );
                                    ?>

                                </div>

                            </div>

                        </div>



                        <!-- SHOPPING -->

                        <div class="timeline-row">

                            <div class="time">
                                05:30 PM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon shopping">

                                    <i class="fa-solid fa-bag-shopping"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Local Shopping
                                    </h3>

                                    <p>
                                        Chocolates, local products, souvenirs and gifts
                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($shoppingImages[0]); ?>"
                                        alt="Shopping"
                                    >

                                </div>

                                <div class="cost-pill">
                                    <?php echo money($shoppingCost); ?>
                                </div>

                            </div>

                        </div>



                        <!-- DINNER -->

                        <div class="timeline-row">

                            <div class="time">
                                08:00 PM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon food">

                                    <i class="fa-solid fa-utensils"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Dinner
                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $restaurantName
                                        );
                                        ?>

                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars($dinnerImage); ?>"
                                        alt="Dinner"
                                    >

                                </div>

                                <div class="cost-pill">
                                    <?php echo money($dinnerCost); ?>
                                </div>

                            </div>

                        </div>



                        <!-- STAY -->

                        <div class="timeline-row">

                            <div class="time">
                                09:30 PM
                            </div>

                            <div class="timeline-dot"></div>

                            <div class="timeline-content">

                                <div class="timeline-icon stay">

                                    <i class="fa-solid fa-bed"></i>

                                </div>

                                <div class="timeline-text">

                                    <h3>
                                        Back To Stay
                                    </h3>

                                    <p>

                                        <?php
                                        echo htmlspecialchars(
                                            $stayName
                                        );
                                        ?>

                                    </p>

                                </div>

                                <div class="timeline-image">

                                    <img
                                        src="<?php echo htmlspecialchars(
                                            $stayImage !== ""
                                            ? $stayImage
                                            : getPexelsImage(
                                                $placeName . " resort hotel",
                                                $heroImage
                                            )
                                        ); ?>"
                                        alt="Stay"
                                    >

                                </div>

                                <div class="cost-pill">
                                    <?php echo money($stayDaily); ?>
                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- EXPENSE SUMMARY -->

                    <aside class="expense-summary">

                        <div class="expense-head">

                            <div class="section-icon">

                                <i class="fa-solid fa-wallet"></i>

                            </div>

                            <h3>
                                DAY <?php echo $day; ?> EXPENSE SUMMARY
                            </h3>

                        </div>


                        <div class="expense-list">

                            <div>
                                <span>
                                    <i class="fa-solid fa-mug-hot orange"></i>
                                    Breakfast
                                </span>

                                <strong>
                                    <?php echo money($breakfastCost); ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-ticket green"></i>
                                    Entry Fees
                                </span>

                                <strong>
                                    <?php
                                    echo money(
                                        (float)(
                                            $place1["estimated_cost"]
                                            ?? 0
                                        )
                                    );
                                    ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-utensils"></i>
                                    Lunch
                                </span>

                                <strong>
                                    <?php echo money($lunchCost); ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-camera"></i>
                                    Activities
                                </span>

                                <strong>
                                    <?php
                                    echo money(
                                        (float)(
                                            $place2["estimated_cost"]
                                            ?? 0
                                        )
                                    );
                                    ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-bag-shopping"></i>
                                    Shopping
                                </span>

                                <strong>
                                    <?php echo money($shoppingCost); ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-utensils"></i>
                                    Dinner
                                </span>

                                <strong>
                                    <?php echo money($dinnerCost); ?>
                                </strong>
                            </div>


                            <div>
                                <span>
                                    <i class="fa-solid fa-bed"></i>
                                    Stay
                                </span>

                                <strong>
                                    <?php echo money($stayDaily); ?>
                                </strong>
                            </div>

                        </div>


                        <div class="expense-total">

                            <span>
                                Total Today
                            </span>

                            <strong>
                                <?php echo money($dayTotal); ?>
                            </strong>

                        </div>


                        <!-- DAY BUDGET TRIGGER -->

                        <button
                            type="button"
                            class="day-budget-trigger"
                            data-budget-day="<?php echo $day; ?>"
                            data-budget-total="<?php echo (float)$dayTotal; ?>"
                            data-budget-limit="<?php echo (float)$dailyBudget; ?>"
                            data-budget-remaining="<?php echo (float)$remaining; ?>"
                            data-budget-used="<?php echo (int)$usedPercent; ?>"
                            data-budget-breakfast="<?php echo (float)$breakfastCost; ?>"
                            data-budget-entry="<?php echo (float)($place1["estimated_cost"] ?? 0); ?>"
                            data-budget-lunch="<?php echo (float)$lunchCost; ?>"
                            data-budget-activity="<?php echo (float)($place2["estimated_cost"] ?? 0); ?>"
                            data-budget-shopping="<?php echo (float)$shoppingCost; ?>"
                            data-budget-dinner="<?php echo (float)$dinnerCost; ?>"
                            data-budget-stay="<?php echo (float)$stayDaily; ?>"
                        >
                            <span class="budget-trigger-icon"><i class="fa-solid fa-wallet"></i></span>
                            <span class="budget-trigger-text">
                                <small>DAY <?php echo $day; ?> BUDGET</small>
                                <strong>View Day Budget</strong>
                                <em><?php echo $usedPercent; ?>% used · Remaining <?php echo money($remaining); ?></em>
                            </span>
                            <span class="budget-trigger-arrow"><i class="fa-solid fa-chevron-right"></i></span>
                        </button>

                        <div class="track-box">

                            <i class="fa-solid fa-circle-check"></i>

                            <div>

                                <strong>
                                    You're on track!
                                </strong>

                                <span>
                                    Enjoy your trip within your budget.
                                </span>

                            </div>

                        </div>

                    </aside>

                    <!-- DAY NAVIGATION -->
                    <div class="day-navigation-row">

                        <?php if ($day > 1): ?>
                            <button
                                type="button"
                                class="day-nav-btn prev-day-btn"
                                data-target-day="<?php echo $day - 1; ?>"
                            >
                                <i class="fa-solid fa-arrow-left"></i>
                                Previous Day
                            </button>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>

                        <span class="day-progress-label">
                            Day <?php echo $day; ?> of <?php echo $days; ?>
                        </span>

                        <?php if ($day < $days): ?>
                            <button
                                type="button"
                                class="day-nav-btn next-day-btn"
                                data-target-day="<?php echo $day + 1; ?>"
                            >
                                Next Day
                                <i class="fa-solid fa-arrow-right"></i>
                            </button>
                        <?php else: ?>
                            <button
                                type="button"
                                class="day-nav-btn finish-trip-btn"
                                onclick="showTripFinished()"
                            >
                                <i class="fa-solid fa-flag-checkered"></i>
                                Finish Trip
                            </button>
                        <?php endif; ?>

                    </div>

                </div>

                <?php
                }
                ?>

            </div>


        </section>



        <!-- =================================================
             RIGHT COLUMN
        ================================================= -->

        <aside class="right-column">


            <!-- QUICK WEATHER -->

            <div class="side-card live-weather">

                <div class="small-label">
                    LIVE WEATHER
                </div>

                <h3>
                    <?php
                    echo htmlspecialchars(
                        $placeName
                    );
                    ?>
                </h3>

                <div class="weather-big">

                    <i class="fa-solid fa-cloud-sun"></i>

                    <strong>

                        <?php
                        echo htmlspecialchars(
                            $currentInfo["weather"]
                        );
                        ?>

                    </strong>

                </div>

                <p>

                    <?php
                    echo htmlspecialchars(
                        $currentInfo["condition"]
                    );
                    ?>

                </p>

                <button>
                    View Forecast
                    <i class="fa-solid fa-arrow-right"></i>
                </button>

            </div>

            <!-- QUICK INFO -->

            <div class="side-card quick-info">

                <div class="small-label">
                    QUICK INFO
                </div>


                <div class="quick-grid emergency-links">

                    <a href="https://www.google.com/maps/search/Emergency+Hospital+near+<?php echo rawurlencode($placeName); ?>" target="_blank" rel="noopener" aria-label="Find emergency hospital">
                        <i class="fa-solid fa-truck-medical red"></i>
                        <span>Emergency</span>
                        <strong>108 / 112</strong>
                    </a>

                    <a href="https://www.google.com/maps/search/Hospital+near+<?php echo rawurlencode($placeName); ?>" target="_blank" rel="noopener" aria-label="Find hospital">
                        <i class="fa-solid fa-hospital"></i>
                        <span>Hospital</span>
                        <strong>Open Map</strong>
                    </a>

                    <a href="https://www.google.com/maps/search/Police+Station+near+<?php echo rawurlencode($placeName); ?>" target="_blank" rel="noopener" aria-label="Find police station">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Police</span>
                        <strong>Open Map</strong>
                    </a>

                    <a href="https://www.google.com/maps/search/ATM+near+<?php echo rawurlencode($placeName); ?>" target="_blank" rel="noopener" aria-label="Find ATM">
                        <i class="fa-solid fa-building-columns"></i>
                        <span>ATM</span>
                        <strong>Open Map</strong>
                    </a>

                </div>


                    <div>

                        <i class="fa-solid fa-hospital"></i>

                        <span>
                            Hospital
                        </span>

                        <strong>
                            Nearby
                        </strong>

                    </div>


                    <div>

                        <i class="fa-solid fa-shield-halved"></i>

                        <span>
                            Police
                        </span>

                        <strong>
                            Help
                        </strong>

                    </div>


                    <div>

                        <i class="fa-solid fa-building-columns"></i>

                        <span>
                            ATM
                        </span>

                        <strong>
                            Nearby
                        </strong>

                    </div>

                </div>

            </div>

            <!-- TRIP ACTIONS -->
            <div class="side-card trip-actions-card">
                <div class="small-label">TRIP ACTIONS</div>
                <h3 style="margin-top:6px;font-size:17px;">Keep Your Trip Handy</h3>
                <p class="actions-subtitle">Share your plan or save a printable copy of your complete itinerary.</p>

                <div class="trip-action-grid">
                    <button type="button" class="trip-action-btn" onclick="shareTripWhatsApp()">
                        <i class="fa-brands fa-whatsapp"></i>
                        <span>WhatsApp</span>
                        <small>Share trip</small>
                    </button>
                    <button type="button" class="trip-action-btn" onclick="copyTripLink()">
                        <i class="fa-solid fa-link"></i>
                        <span>Copy Link</span>
                        <small>Save link</small>
                    </button>
                    <button type="button" class="trip-action-btn" onclick="downloadTripPDF()">
                        <i class="fa-solid fa-file-pdf"></i>
                        <span>Save PDF</span>
                        <small>Print / PDF</small>
                    </button>
                </div>

                <div class="save-trip-status" id="tripActionStatus">
                    <i class="fa-solid fa-circle-check"></i> Ready to use
                </div>
            </div>

        </aside>

    </main>



    <!-- =================================================
         BOTTOM RECOMMENDATIONS
    ================================================= -->

    <section class="bottom-grid">

        <!-- SHOPPING PREVIEW -->
        <div class="recommend-card shopping-preview-card">
            <div class="recommend-title">
                <i class="fa-solid fa-bag-shopping"></i>
                <h3>SHOPPING YOU'LL LOVE</h3>
            </div>

            <p class="section-mini-copy">Explore local products and souvenirs from <?php echo htmlspecialchars($placeName); ?>.</p>

            <div class="shopping-row">
                <div><img src="<?php echo htmlspecialchars($shoppingImages[0]); ?>" alt="Homemade Chocolates"><span>Homemade Chocolates</span></div>
                <div><img src="<?php echo htmlspecialchars($shoppingImages[1]); ?>" alt="Eucalyptus Oil"><span>Eucalyptus Oil</span></div>
                <div><img src="<?php echo htmlspecialchars($shoppingImages[2]); ?>" alt="Spices and Tea"><span>Spices &amp; Tea</span></div>
                <div><img src="<?php echo htmlspecialchars($shoppingImages[3]); ?>" alt="Souvenirs"><span>Souvenirs</span></div>
            </div>

            <a href="shopping.php?place=<?php echo rawurlencode($placeName); ?>" class="recommend-btn shopping-link">
                Explore Shopping <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <!-- FINAL TRIP SUMMARY -->
        <div class="recommend-card final-summary-card">
            <div class="recommend-title">
                <i class="fa-solid fa-flag-checkered"></i>
                <h3>TRIP AT A GLANCE</h3>
            </div>

            <div class="final-summary-grid">
                <div><span>Destination</span><strong><?php echo htmlspecialchars($placeName); ?></strong></div>
                <div><span>Duration</span><strong><?php echo $days; ?> Day<?php echo $days > 1 ? 's' : ''; ?></strong></div>
                <div><span>Members</span><strong><?php echo $members; ?></strong></div>
                <div><span>Total Budget</span><strong><?php echo money($totalBudget); ?></strong></div>
            </div>

            <div class="final-summary-note">
                <i class="fa-solid fa-circle-info"></i>
                Your detailed day plan, expenses and selected stay are shown above.
            </div>
        </div>

    </section>

</div>



<!-- =====================================================
     DAY BUDGET MODAL
===================================================== -->
<div class="day-budget-modal" id="dayBudgetModal" aria-hidden="true">
    <div class="day-budget-modal-backdrop" data-close-budget></div>
    <div class="day-budget-modal-card" role="dialog" aria-modal="true" aria-labelledby="modalBudgetTitle">
        <button type="button" class="day-budget-close" id="closeBudgetModal" aria-label="Close">&times;</button>
        <div class="modal-eyebrow" id="modalBudgetEyebrow">DAY 1 BUDGET</div>
        <h2 id="modalBudgetTitle">Day 1 Budget</h2>
        <p id="modalBudgetDescription">Here is the budget breakdown for this day.</p>

        <div class="modal-budget-stats">
            <div><span>Daily Limit</span><strong id="modalBudgetLimit">₹0</strong></div>
            <div><span>Planned Spend</span><strong id="modalBudgetTotal">₹0</strong></div>
            <div><span>Remaining</span><strong id="modalBudgetRemaining">₹0</strong></div>
        </div>

        <div class="modal-progress"><span id="modalBudgetProgress"></span></div>
        <div class="modal-progress-label"><span>Budget used</span><strong id="modalBudgetUsed">0%</strong></div>

        <div class="budget-detail-grid">
            <div><span>Breakfast</span><strong id="modalBreakfast">₹0</strong></div>
            <div><span>Entry Fees</span><strong id="modalEntry">₹0</strong></div>
            <div><span>Lunch</span><strong id="modalLunch">₹0</strong></div>
            <div><span>Activities</span><strong id="modalActivity">₹0</strong></div>
            <div><span>Shopping</span><strong id="modalShopping">₹0</strong></div>
            <div><span>Dinner</span><strong id="modalDinner">₹0</strong></div>
            <div><span>Stay</span><strong id="modalStay">₹0</strong></div>
        </div>

        <div class="modal-navigation">
            <button type="button" id="modalPrevDay"><i class="fa-solid fa-arrow-left"></i> Previous</button>
            <button type="button" id="modalNextDay">Next <i class="fa-solid fa-arrow-right"></i></button>
        </div>
    </div>
</div>

<!-- =====================================================
     BOTTOM TIP
===================================================== -->

<div class="bottom-tip">

    <div>

        <i class="fa-solid fa-lightbulb"></i>

        <span>

            <strong>Tip:</strong>

            Start early to enjoy your sightseeing comfortably and keep some buffer time for traffic.

        </span>

    </div>


    <button
        class="save-heart"
        id="saveHeart"
        type="button"
    >

        <i class="fa-solid fa-heart"></i>

    </button>

</div>



<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

const dayTabs = document.querySelectorAll('.day-tab');
const dayPanels = document.querySelectorAll('.day-panel');
const currentDayNumber = document.getElementById('currentDayNumber');
const currentDateText = document.getElementById('currentDateText');

function activateDay(dayNumber, shouldScroll = true){
    const selectedDay = String(dayNumber);

    dayTabs.forEach(tab => tab.classList.toggle('active', tab.dataset.day === selectedDay));
    dayPanels.forEach(panel => panel.classList.toggle('show', panel.id === 'day-' + selectedDay));

    const selectedPanel = document.getElementById('day-' + selectedDay);
    if (!selectedPanel) return;

    currentDayNumber.textContent = selectedDay;
    currentDateText.textContent = selectedPanel.dataset.date || '';

    if (shouldScroll) {
        const planCard = document.querySelector('.plan-card');
        if (planCard) {
            window.scrollTo({ top: planCard.offsetTop - 80, behavior: 'smooth' });
        }
    }
}

dayTabs.forEach(tab => {
    tab.addEventListener('click', () => activateDay(tab.dataset.day));
});

document.querySelectorAll('.day-nav-btn[data-target-day]').forEach(btn => {
    btn.addEventListener('click', () => activateDay(btn.dataset.targetDay));
});

/* DAY BUDGET MODAL */
const budgetModal = document.getElementById('dayBudgetModal');
const closeBudgetModal = document.getElementById('closeBudgetModal');
let activeBudgetButton = null;

function moneyText(value){
    return '₹' + Math.round(Number(value || 0)).toLocaleString('en-IN');
}

function fillBudgetModal(button){
    activeBudgetButton = button;
    const d = button.dataset;
    document.getElementById('modalBudgetEyebrow').textContent = 'DAY ' + d.budgetDay + ' BUDGET';
    document.getElementById('modalBudgetTitle').textContent = 'Day ' + d.budgetDay + ' Budget';
    document.getElementById('modalBudgetDescription').textContent = 'A clear breakdown of your planned spending for Day ' + d.budgetDay + '.';
    document.getElementById('modalBudgetLimit').textContent = moneyText(d.budgetLimit);
    document.getElementById('modalBudgetTotal').textContent = moneyText(d.budgetTotal);
    document.getElementById('modalBudgetRemaining').textContent = moneyText(d.budgetRemaining);
    document.getElementById('modalBudgetUsed').textContent = d.budgetUsed + '%';
    document.getElementById('modalBudgetProgress').style.width = Math.min(100, Number(d.budgetUsed || 0)) + '%';

    const fields = ['breakfast','entry','lunch','activity','shopping','dinner','stay'];
    fields.forEach(field => {
        const el = document.getElementById('modal' + field.charAt(0).toUpperCase() + field.slice(1));
        if (el) el.textContent = moneyText(d['budget' + field.charAt(0).toUpperCase() + field.slice(1)]);
    });

    const current = Number(d.budgetDay);
    const totalDays = dayTabs.length;
    document.getElementById('modalPrevDay').style.visibility = current > 1 ? 'visible' : 'hidden';
    document.getElementById('modalNextDay').style.visibility = current < totalDays ? 'visible' : 'hidden';

    budgetModal.classList.add('active');
    budgetModal.setAttribute('aria-hidden','false');
    document.body.classList.add('modal-open');
}

document.querySelectorAll('.day-budget-trigger').forEach(button => {
    button.addEventListener('click', () => fillBudgetModal(button));
});

function closeBudget(){
    if (!budgetModal) return;
    budgetModal.classList.remove('active');
    budgetModal.setAttribute('aria-hidden','true');
    document.body.classList.remove('modal-open');
    activeBudgetButton = null;
}

closeBudgetModal?.addEventListener('click', closeBudget);
budgetModal?.querySelector('[data-close-budget]')?.addEventListener('click', closeBudget);
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeBudget(); });

document.getElementById('modalPrevDay')?.addEventListener('click', () => {
    if (!activeBudgetButton) return;
    const target = Number(activeBudgetButton.dataset.budgetDay) - 1;
    const button = document.querySelector('.day-budget-trigger[data-budget-day="' + target + '"]');
    if (button) { activateDay(target, false); fillBudgetModal(button); }
});

document.getElementById('modalNextDay')?.addEventListener('click', () => {
    if (!activeBudgetButton) return;
    const target = Number(activeBudgetButton.dataset.budgetDay) + 1;
    const button = document.querySelector('.day-budget-trigger[data-budget-day="' + target + '"]');
    if (button) { activateDay(target, false); fillBudgetModal(button); }
});

/* SHARE */
function tripShareText(){
    return 'My ' + <?php echo json_encode($placeName); ?> + ' trip plan: ' + <?php echo json_encode($days); ?> + ' days, ' + <?php echo json_encode($members); ?> + ' traveller(s), total budget ' + <?php echo json_encode(money($totalBudget)); ?> + '. View my trip plan: ' + window.location.href;
}

function shareTripWhatsApp(){
    const url = 'https://wa.me/?text=' + encodeURIComponent(tripShareText());
    window.open(url, '_blank', 'noopener');
    showTripStatus('WhatsApp share opened');
}

async function copyTripLink(){
    try {
        await navigator.clipboard.writeText(window.location.href);
        showTripStatus('Trip link copied');
    } catch(e) {
        showTripStatus('Copy not available — copy the address bar link');
    }
}

function downloadTripPDF(){
    showTripStatus('Print dialog opened — choose Save as PDF');
    setTimeout(() => window.print(), 250);
}

function showTripStatus(message){
    const status = document.getElementById('tripActionStatus');
    if (status) status.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + message;
}

function showTripFinished(){
    showTripStatus('Trip completed — great journey!');
    const tip = document.querySelector('.bottom-tip span');
    if (tip) tip.innerHTML = '<strong>Trip Complete:</strong> Hope you had a wonderful journey in ' + <?php echo json_encode($placeName); ?> + '!';
    window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
}

/* RED HEART */
const saveHeart = document.getElementById('saveHeart');
if (saveHeart) {
    saveHeart.addEventListener('click', function(){
        this.classList.toggle('saved');
    });
}

/* SCROLL REVEAL */
const revealElements = document.querySelectorAll('.plan-card, .side-card, .recommend-card');
if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
            if (entry.isIntersecting) entry.target.classList.add('visible');
        });
    }, {threshold:.12});
    revealElements.forEach(element => observer.observe(element));
} else {
    revealElements.forEach(element => element.classList.add('visible'));
}

</script>

</body>

</html>