<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$place = trim($_GET["place"] ?? "Kodaikanal");

if ($place === "") {
    $place = "Kodaikanal";
}

/* ---------- PEXELS HERO IMAGE ---------- */
require_once "pexels-api.php";

$heroPhoto = searchPexelsPhoto(
    $place . " Tamil Nadu travel nature landscape",
    0
);

$pexelsHero = "";

if (
    $heroPhoto &&
    isset($heroPhoto["src"]["large2x"])
) {
    $pexelsHero = $heroPhoto["src"]["large2x"];
}
if (
    $heroPhoto &&
    isset($heroPhoto["src"]["large2x"])
) {
    $pexelsHero = $heroPhoto["src"]["large2x"];
}


/* ---------- PEXELS SERVICE IMAGE ---------- */

$servicePhoto = searchPexelsPhoto(
   "hospital building exterior India",
    0
);

$serviceImageUrl = "";

if (
    $servicePhoto &&
    isset($servicePhoto["src"]["large2x"])
) {
    $serviceImageUrl = $servicePhoto["src"]["large2x"];
}
$atmPhoto = searchPexelsPhoto(
    "ATM machine bank India",
    0
);

$atmImageUrl = "";

if ($atmPhoto && isset($atmPhoto["src"]["large2x"])) {
    $atmImageUrl = $atmPhoto["src"]["large2x"];
}


$policePhoto = searchPexelsPhoto(
    "police station building India",
    0
);

$policeImageUrl = "";

if ($policePhoto && isset($policePhoto["src"]["large2x"])) {
    $policeImageUrl = $policePhoto["src"]["large2x"];
}

/* ---------- TRIP DATA ---------- */

$trip = null;

/* Current destination from URL */
$currentPlace = trim($_GET["place"] ?? "");

if ($currentPlace !== "") {

    $stmt = $conn->prepare("
        SELECT *
        FROM trips
        WHERE user_id = ?
          AND destination = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "is",
            $user_id,
            $currentPlace
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $trip = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();
    }

} else {

    /* Fallback: latest trip */
    $stmt = $conn->prepare("
        SELECT *
        FROM trips
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $user_id
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $trip = $result
            ? $result->fetch_assoc()
            : null;

        $stmt->close();
    }
}


/* Safe defaults */

$trip = $trip ?? [];

$tripType = trim(
    $trip["trip_type"] ?? "Family"
);

$startingLocation = trim(
    $trip["starting_location"] ?? ""
);

$days = max(
    1,
    min(
        7,
        (int)($trip["days"] ?? 5)
    )
);

$members = max(
    1,
    (int)($trip["members"] ?? 4)
);

$totalBudget = max(
    0,
    (float)($trip["total_budget"] ?? 15000)
);

$startDate = !empty($trip["start_date"])
    ? new DateTime($trip["start_date"])
    : new DateTime();

$endDate = clone $startDate;

$endDate->modify(
    "+" . max(0, $days - 1) . " days"
);
//* ---------- DESTINATION VISUAL ---------- */

/* Pexels image கிடைத்தால் அதைப் பயன்படுத்தும் */
if (!empty($pexelsHero)) {
    $hero = $pexelsHero;
} else {

    /* Pexels வேலை செய்யவில்லை என்றால் fallback image */
    $hero = "https://images.unsplash.com/photo-1582510003544-4d00b7f74220?auto=format&fit=crop&w=1800&q=90";
}
/* ---------- SMART RESTAURANT OPTIONS ---------- */

$restaurants = [];

/*
|--------------------------------------------------------------------------
| USER FOOD PREFERENCE
|--------------------------------------------------------------------------
| Veg     -> Veg + Both
| Non-Veg -> Non-Veg + Both
| Both    -> All
*/

$selectedFoodPreference = strtolower(
    trim((string)($trip["food"] ?? "Both"))
);

if (
    strpos($selectedFoodPreference, "non") !== false
) {
    $selectedFoodPreference = "nonveg";
} elseif (
    strpos($selectedFoodPreference, "veg") !== false &&
    strpos($selectedFoodPreference, "non") === false
) {
    $selectedFoodPreference = "veg";
} else {
    $selectedFoodPreference = "both";
}


/*
|--------------------------------------------------------------------------
| FOOD BUDGET
|--------------------------------------------------------------------------
| My Trips budget:
| 30% of daily budget -> Food
| Food is divided into 3 meals
*/

$dailyFoodBudget = ($totalBudget / $days) * 0.30;
$foodPerMealBudget = $dailyFoodBudget / 3;


/*
|--------------------------------------------------------------------------
| GET RESTAURANTS FOR DESTINATION
|--------------------------------------------------------------------------
*/

$restaurantStmt = $conn->prepare("
    SELECT
        id,
        name,
        restaurant_type,
        description,
        estimated_price,
        rating,
        image_url,
        map_url
    FROM restaurant_options
    WHERE destination = ?
    ORDER BY rating DESC, estimated_price ASC
");

if ($restaurantStmt) {

    $restaurantStmt->bind_param("s", $place);
    $restaurantStmt->execute();

    $restaurantResult = $restaurantStmt->get_result();

    $allRestaurants = [];

    while ($row = $restaurantResult->fetch_assoc()) {

        $restaurantType = strtolower(
            trim((string)($row["restaurant_type"] ?? ""))
        );

        $price = (float)($row["estimated_price"] ?? 0);

        /*
        |--------------------------------------------------------------------------
        | FOOD TYPE FILTER
        |--------------------------------------------------------------------------
        */

        $isVeg = (
            strpos($restaurantType, "veg") !== false &&
            strpos($restaurantType, "non") === false
        );

        $isNonVeg = (
            strpos($restaurantType, "non") !== false
        );

        $isBoth = (
            strpos($restaurantType, "both") !== false ||
            $restaurantType === ""
        );


        /*
        |--------------------------------------------------------------------------
        | CHECK USER FOOD PREFERENCE
        |--------------------------------------------------------------------------
        */

        $foodTypeOK = false;

        if ($selectedFoodPreference === "veg") {

            if ($isVeg || $isBoth) {
                $foodTypeOK = true;
            }

        } elseif ($selectedFoodPreference === "nonveg") {

            if ($isNonVeg || $isBoth) {
                $foodTypeOK = true;
            }

        } else {

            $foodTypeOK = true;
        }


        if (!$foodTypeOK) {
            continue;
        }


        /*
        |--------------------------------------------------------------------------
        | STORE PRICE
        |--------------------------------------------------------------------------
        */

        $row["_price"] = $price;

        $allRestaurants[] = $row;
    }

    $restaurantStmt->close();


    /*
    |--------------------------------------------------------------------------
    | STEP 1
    | Prefer restaurants within user's food budget
    |--------------------------------------------------------------------------
    */

    $budgetRestaurants = [];

    foreach ($allRestaurants as $restaurant) {

        $price = (float)$restaurant["_price"];

        if ($price <= 0 || $price <= $foodPerMealBudget) {
            $budgetRestaurants[] = $restaurant;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | STEP 2
    | Sort by closest to user's budget
    |--------------------------------------------------------------------------
    */

    usort(
        $budgetRestaurants,
        function ($a, $b) use ($foodPerMealBudget) {

            $priceA = (float)$a["_price"];
            $priceB = (float)$b["_price"];

            /*
            | If price is 0, keep it as a fallback option
            */
            if ($priceA <= 0) {
                $priceA = $foodPerMealBudget;
            }

            if ($priceB <= 0) {
                $priceB = $foodPerMealBudget;
            }

            return
                abs($priceA - $foodPerMealBudget)
                <=>
                abs($priceB - $foodPerMealBudget);
        }
    );


    /*
    |--------------------------------------------------------------------------
    | STEP 3
    | EXACTLY 3 RECOMMENDED RESTAURANTS
    |--------------------------------------------------------------------------
    */

    $restaurants = array_slice(
        $budgetRestaurants,
        0,
        3
    );


    /*
    |--------------------------------------------------------------------------
    | STEP 4
    | If budget filter gives less than 3,
    | fill remaining slots from all matching restaurants
    |--------------------------------------------------------------------------
    */

    if (count($restaurants) < 3) {

        foreach ($allRestaurants as $restaurant) {

            $alreadyAdded = false;

            foreach ($restaurants as $selectedRestaurant) {

                if (
                    isset($selectedRestaurant["id"]) &&
                    isset($restaurant["id"]) &&
                    $selectedRestaurant["id"] == $restaurant["id"]
                ) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if ($alreadyAdded) {
                continue;
            }

            $restaurants[] = $restaurant;

            if (count($restaurants) >= 3) {
                break;
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | FINAL CLEANUP
    |--------------------------------------------------------------------------
    */

    $restaurants = array_values($restaurants);

    foreach ($restaurants as &$restaurant) {

        if (
            !isset($restaurant["estimated_price"]) ||
            (float)$restaurant["estimated_price"] <= 0
        ) {
            $restaurant["estimated_price"] =
                round($foodPerMealBudget);
        }
    }

    unset($restaurant);
}


/* ---------- SMART DAY PLAN FROM DATABASE ---------- */

$dayPlans = [];

$placeStmt = $conn->prepare("
    SELECT
    place_name,
    category,
    estimated_cost,
    duration_minutes,
    opening_time,
    closing_time,
    visit_type,
    description,
    image_url,
    icon
    FROM trip_places
    WHERE destination = ?
      AND is_active = 1
    ORDER BY id ASC
");

if ($placeStmt) 

    $placeStmt->bind_param("s", $place);
    $placeStmt->execute();

    $placeResult = $placeStmt->get_result();

    $availablePlaces = [];

    while ($row = $placeResult->fetch_assoc()) {
        $availablePlaces[] = $row;
    }

    $placeStmt->close();


    /* Budget available for places */
$dailyBudgetForPlaces = max(
    0,
    $dailyBudget ?? ($totalBudget / $days)
) * 0.30;
$tripType = trim((string)($trip["trip_type"] ?? "Family"));
/* Trip Type based place preferences */

$tripTypeCategories = [

    "Solo" => [
        "Nature",
        "Viewpoint",
        "Waterfall"
    ],

    "Friends" => [
        "Activity",
        "Waterfall",
        "Viewpoint"
    ],

    "Family" => [
        "Nature",
        "Activity",
        "Waterfall"
    ],

    "Couple" => [
        "Nature",
        "Viewpoint"
    ]

];

$preferredCategories = $tripTypeCategories[$tripType] ?? [
    "Nature",
    "Viewpoint",
    "Waterfall"
];


/* =====================================================
   SELECT PLACES - TRIP TYPE + BUDGET
   Keep trip type preference, but always ensure
   enough places for all trip days.
===================================================== */

$selectedPlaces = [];


/* ---------- STEP 1: PREFERRED PLACES ---------- */

foreach ($availablePlaces as $p) {

    if (
        strtolower(trim($p["visit_type"] ?? "")) === "shopping"
    ) {
        continue;
    }

    $costOK =
        (float)$p["estimated_cost"] <= $dailyBudgetForPlaces;

    $visitType =
        strtolower(trim($p["visit_type"] ?? ""));

    $preferredTypes =
        array_map("strtolower", $preferredCategories);

    $typeOK =
        in_array($visitType, $preferredTypes, true);

    $openingOK =
        empty($p["opening_time"]) ||
        $p["opening_time"] <= "10:00:00";

    $closingOK =
        empty($p["closing_time"]) ||
        $p["closing_time"] >= "17:00:00";

    if (
        $costOK &&
        $typeOK &&
        $openingOK &&
        $closingOK
    ) {
        $selectedPlaces[] = $p;
    }
}


/* ---------- STEP 2: FILL MISSING PLACES ---------- */

$requiredPlaces = max(1, $days * 2);

if (count($selectedPlaces) < $requiredPlaces) {

    foreach ($availablePlaces as $p) {

        if (
            strtolower(trim($p["visit_type"] ?? "")) === "shopping"
        ) {
            continue;
        }

        $alreadyExists = false;

        foreach ($selectedPlaces as $selected) {

            if (
                $selected["place_name"] ===
                $p["place_name"]
            ) {
                $alreadyExists = true;
                break;
            }
        }

        if ($alreadyExists) {
            continue;
        }

        if (
            (float)$p["estimated_cost"]
            <= $dailyBudgetForPlaces
        ) {
            $selectedPlaces[] = $p;
        }

        if (
            count($selectedPlaces)
            >= $requiredPlaces
        ) {
            break;
        }
    }
}


/* ---------- STEP 3: FINAL FALLBACK ---------- */

if (empty($selectedPlaces)) {

    foreach ($availablePlaces as $p) {

        if (
            strtolower(trim($p["visit_type"] ?? "")) !==
            "shopping"
        ) {
            $selectedPlaces[] = $p;
        }
    }
}


/* Reset indexes */

$selectedPlaces =
    array_values($selectedPlaces);
    /* Create realistic day-wise travel plans */

$sightseeingPlaces = array_values(
    array_filter(
        $selectedPlaces,
        function ($p) {
            return strtolower($p["visit_type"] ?? "") !== "shopping";
        }
    )
);

$shoppingPlaces = array_values(
    array_filter(
        $selectedPlaces,
        function ($p) {
            return strtolower($p["visit_type"] ?? "") === "shopping";
        }
    )
);
$departureDateTime = null;
$arrivalDateTime = null;

if (!empty($trip["departure_time"]) && !empty($trip["arrival_time"])) {
    try {
        $departureDateTime = new DateTime($trip["departure_time"]);
        $arrivalDateTime = new DateTime($trip["arrival_time"]);
    } catch (Exception $e) {
        $departureDateTime = null;
        $arrivalDateTime = null;
    }
}

for ($d = 0; $d < $days; $d++) {

    $dayPlans[$d] = [];

    /* Morning */
    /* Dynamic wake-up time */

if ($d === 0 && $departureDateTime) {

    $wakeUpDateTime = clone $departureDateTime;
    $wakeUpDateTime->modify("-1 hour");

    $wakeUpTime = $wakeUpDateTime->format("h:i A");

} else {

    $wakeUpTime = "07:00 AM";
}

$dayPlans[$d][] = [
    $wakeUpTime,
    "fa-bed",
    "Wake Up & Freshen Up",
    "Get ready for the day's journey.",
    "https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=600&q=80"
];
    /* Travel based on user's selected transport */

$travelType = strtolower(trim((string)($trip["transport"] ?? "")));

if (strpos($travelType, "train") !== false) {

    $travelIcon = "fa-train";
    $travelTitle = "Travel by Train";
    $travelDescription = "Travel by your selected train option.";

} elseif (strpos($travelType, "bus") !== false) {

    $travelIcon = "fa-bus";
    $travelTitle = "Travel by Bus";
    $travelDescription = "Travel by your selected bus option.";

} elseif (strpos($travelType, "bike") !== false) {

    $travelIcon = "fa-motorcycle";
    $travelTitle = "Travel by Bike";
    $travelDescription = "Ride to your planned destination.";

} else {

    $travelIcon = "fa-car";
    $travelTitle = "Travel by Car";
    $travelDescription = "Drive to your planned destination.";
}


/* ---------- DAY 1: MAIN JOURNEY ---------- */

if ($d === 0) {

    /* =====================================================
       CAR / BIKE
       Never show LIVE for personal transport
    ===================================================== */

    if ($travelType === "car" || $travelType === "bike") {

        if ($departureDateTime) {

            $departureText =
                $departureDateTime->format("h:i A");

            $startPlace = !empty($startingLocation)
                ? $startingLocation
                : "Starting Point";

            $destinationPlace = !empty($place)
                ? $place
                : "Destination";

            /* Arrival time available */
            if ($arrivalDateTime) {

                $arrivalText =
                    $arrivalDateTime->format("h:i A");

                $travelDescription =
                    $startPlace .
                    " → " .
                    $destinationPlace .
                    " • Reach by " .
                    $arrivalText;

            } else {

                /* No arrival time, but still NOT LIVE */
                $travelDescription =
                    $startPlace .
                    " → " .
                    $destinationPlace .
                    " • Route timing calculated for your trip.";
            }

            $dayPlans[$d][] = [
                $departureText,
                $travelIcon,
                $travelTitle,
                $travelDescription,
                "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=600&q=80"
            ];

        } else {

            /* No departure time */
            $dayPlans[$d][] = [
                "Planned",
                $travelIcon,
                $travelTitle,
                "Travel from your starting point to " .
                ($place ?: "Destination") .
                ". Check the Transport page for route details.",
                "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=600&q=80"
            ];
        }

    }

    /* =====================================================
       BUS / TRAIN
       LIVE ONLY FOR PUBLIC TRANSPORT
    ===================================================== */

    elseif (
        $travelType === "bus" ||
        $travelType === "train"
    ) {

        $dayPlans[$d][] = [
            "LIVE",
            $travelIcon,
            $travelTitle,
            "Live departure and arrival timing is not available here. Check the live service before travel.",
            "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=600&q=80"
        ];
    }

}
 $selectedStayName = $_SESSION["selected_stay"]["name"] ?? "";   
 $breakfastStayName = $selectedStayName !== ""
    ? $selectedStayName
    : "your stay";
/* Rest after reaching destination */

if ($d === 0 && $arrivalDateTime instanceof DateTime) {

    $restStart = clone $arrivalDateTime;
    $restEnd = clone $restStart;
    $restEnd->modify("+30 minutes");

    $dayPlans[$d][] = [
        $restStart->format("h:i A"),
        "fa-bed",
        "Rest & Freshen Up",
        "Rest at " . ($selectedStayName !== "" ? $selectedStayName : "your stay") . " after the journey.",
        "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80"
    ];
}

    /* Breakfast after rest */
    if ($d === 0 && $arrivalDateTime) {

   $breakfastStart = new DateTime($trip["arrival_time"]);
$breakfastStart->modify("+30 minutes");

    $dayPlans[$d][] = [
        $breakfastStart->format("h:i A"),
        "fa-utensils",
       "Breakfast at " . $breakfastStayName,
"Enjoy breakfast at your selected stay and get ready for the day's sightseeing.",
        "https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=600&q=80"
    ];

}  else {

    /* Day 1 Bus / Train */
    if (
        $d === 0 &&
        (
            $travelType === "bus" ||
            $travelType === "train"
        )
    ) {

        $dayPlans[$d][] = [
            "After Arrival",
            "fa-bed",
            "Rest & Freshen Up",
            "Rest after reaching the destination and get ready for the day.",
            "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80"
        ];

        $dayPlans[$d][] = [
            "After Rest",
            "fa-utensils",
            "Breakfast at " . $breakfastStayName,
"Enjoy breakfast at your selected stay and get ready for sightseeing.",
            "https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=600&q=80"
        ];

    } else {

        /* Other days */

        $dayPlans[$d][] = [
            "08:00 AM",
            "fa-utensils",
            "Breakfast at " . $breakfastStayName,
"Enjoy breakfast at your selected stay and get ready for the day's sightseeing.",
            "https://images.unsplash.com/photo-1533089860892-a7c6f0a88666?auto=format&fit=crop&w=600&q=80"
        ];

        $dayPlans[$d][] = [
            "09:00 AM",
            $travelIcon,
            "Local Travel",
            "Travel to the next planned sightseeing location.",
            "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=600&q=80"
        ];
    }
}


/* =====================================================
   SIGHTSEEING PLACES - 2 PER DAY
   Photos are fetched automatically from Pexels
===================================================== */

$dayPlaceStartIndex = $d * 2;


/* =====================================================
   PLACE PHOTO HELPER
===================================================== */

$getPlacePhoto = function ($placeName) use ($place) {

    $queries = [
        $placeName . " " . $place . " Tamil Nadu tourism",
        $placeName . " " . $place . " travel photography",
        $placeName . " " . $place . " scenic view",
        $placeName . " India landmark"
    ];

    foreach ($queries as $query) {

        try {

            $photo = searchPexelsPhoto($query, 0);

            if (
                $photo &&
                isset($photo["src"]["large2x"])
            ) {
                return $photo["src"]["large2x"];
            }

            if (
                $photo &&
                isset($photo["src"]["large"])
            ) {
                return $photo["src"]["large"];
            }

        } catch (Throwable $e) {
            // Continue to next query
        }
    }

    /* Fallback image */
    return "https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=900&q=80";
};


/* ---------- FIRST PLACE ---------- */

if (isset($sightseeingPlaces[$dayPlaceStartIndex])) {

    $p1 = $sightseeingPlaces[$dayPlaceStartIndex];

    if ($d === 0 && $arrivalDateTime) {

        $sightseeingStart = clone $arrivalDateTime;
        $sightseeingStart->modify("+1 hour");

        $firstSightseeingTime =
            $sightseeingStart->format("h:i A");

    } else {

        $firstSightseeingTime = "09:30 AM";
    }

    /* Get FIRST place photo */
    $place1Image = !empty($p1["image_url"])
        ? $p1["image_url"]
        : $getPlacePhoto($p1["place_name"]);


    $dayPlans[$d][] = [
        $firstSightseeingTime,
        $p1["icon"] ?: "fa-location-dot",
        $p1["place_name"],
        $p1["description"],
        $place1Image
    ];
}

/* ---------- LUNCH ---------- */

if (!empty($restaurants)) {

    $lunchIndex = $d % count($restaurants);
    $lunch = $restaurants[$lunchIndex];

    $lunchImage = !empty($lunch["image_url"])
    ? $lunch["image_url"]
    : "";

if ($lunchImage === "") {

    $lunchPhoto = searchPexelsPhoto(
        "South Indian lunch thali restaurant",
        0
    );

    if (
        $lunchPhoto &&
        isset($lunchPhoto["src"]["large2x"])
    ) {
        $lunchImage = $lunchPhoto["src"]["large2x"];
    } elseif (
        $lunchPhoto &&
        isset($lunchPhoto["src"]["large"])
    ) {
        $lunchImage = $lunchPhoto["src"]["large"];
    }
}
    $dayPlans[$d][] = [
        "01:00 PM",
        "fa-utensils",
        "Lunch at " . $lunch["name"],
        $lunch["description"]
            . " • ₹"
            . number_format((float)$lunch["estimated_price"])
            . " approx.",
        $lunchImage
    ];
}

/* ---------- SECOND PLACE ---------- */

$secondPlaceIndex = $dayPlaceStartIndex + 1;

if (isset($sightseeingPlaces[$secondPlaceIndex])) {

    $p2 = $sightseeingPlaces[$secondPlaceIndex];

    $secondSightseeingTime = "03:30 PM";

    /* Get SECOND place photo */
    $place2Image = !empty($p2["image_url"])
        ? $p2["image_url"]
        : $getPlacePhoto($p2["place_name"]);


    $dayPlans[$d][] = [
        $secondSightseeingTime,
        $p2["icon"] ?: "fa-camera",
        $p2["place_name"],
        $p2["description"],
        $place2Image
    ];
}   /* Shopping */
    if (!empty($shoppingPlaces)) {

        $shopIndex = $d % count($shoppingPlaces);
        $shop = $shoppingPlaces[$shopIndex];

        $dayPlans[$d][] = [
            "06:00 PM",
            "fa-bag-shopping",
            $shop["place_name"],
            "Explore local products and souvenirs.",
            $shop["image_url"]
        ];
    }

    /* Dinner */

if (!empty($restaurants)) {

    $dinnerIndex = ($d + 1) % count($restaurants);
    $dinner = $restaurants[$dinnerIndex];

    $dayPlans[$d][] = [
        "07:30 PM",
        "fa-utensils",
        "Dinner at " . $dinner["name"],
        $dinner["description"] . " • ₹" . number_format((float)$dinner["estimated_price"]) . " approx.",
        !empty($dinner["image_url"])
            ? $dinner["image_url"]
            : "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=600&q=80"
    ];

} else {

    $dayPlans[$d][] = [
        "07:30 PM",
        "fa-utensils",
        "Dinner",
        "Choose a suitable nearby restaurant within your planned budget.",
        "https://images.unsplash.com/photo-1515003197210-e0cd71810b5f?auto=format&fit=crop&w=600&q=80"
    ];
}
    /* Return */
    $dayPlans[$d][] = [
        "09:00 PM",
        "fa-house",
        "Back to Stay",
        "Return to your stay and relax for the next day.",
        "https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=600&q=80"
    ];
}

$selectedTransport = trim((string)($trip["transport"] ?? ""));
$selectedStay = trim((string)($trip["stay"] ?? ""));
$selectedFood = trim((string)($trip["food"] ?? ""));
$weatherIcon = "fa-cloud";
$dailyBudget = $totalBudget / $days;
$transport = round($dailyBudget * .32);
$food = round($dailyBudget * .30);
$activities = round($dailyBudget * .18);
$shopping = round($dailyBudget * .12);
$stay = max(0, round($dailyBudget - $transport - $food - $activities - $shopping));
$planned = $transport + $food + $activities + $shopping + $stay;

$shoppingImages = [
    ["Handicrafts","https://images.unsplash.com/photo-1513519245088-0e12902e5a38?auto=format&fit=crop&w=500&q=80"],
    ["Homemade Chocolates","https://images.unsplash.com/photo-1575377427642-087cf684f29d?auto=format&fit=crop&w=500&q=80"],
    ["Eucalyptus Oil","https://images.unsplash.com/photo-1547887538-e3a2f32cb1cc?auto=format&fit=crop&w=500&q=80"],
    ["Spices & Tea","https://images.unsplash.com/photo-1596040033229-a9821ebd058d?auto=format&fit=crop&w=500&q=80"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>My Trip | Smart Budget Trip Planner</title>
<link rel="stylesheet" href="my-trips-final.css">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Playfair+Display:ital,wght@0,600;0,700;1,600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
</head>

<body>

<header class="topbar">
    <a class="brand" href="dashboard.php">
        <span class="brand-logo"><i class="fa-solid fa-route"></i></span>
        <span><b>Smart Budget</b><small>Trip Planner</small></span>
    </a>

    <nav class="main-nav">
        <a href="dashboard.php">Home</a>
        <a href="destination.php">Explore</a>
        <a class="active" href="my-trips.php">My Trips</a>
        <a href="saved-places.php">Saved</a>
        <a href="budget.php">Budget</a>
        <a href="profile.php">Profile</a>
    </nav>

    <div class="top-actions">
        <div class="search-box"><i class="fa-solid fa-magnifying-glass"></i><input placeholder="Search destination..."></div>
        <button class="bell"><i class="fa-regular fa-bell"></i><b>0</b></button>
        <button class="profile-round"><i class="fa-solid fa-user"></i></button>
        <span class="traveler">Traveler <i class="fa-solid fa-chevron-down"></i></span>
    </div>
</header>

<main>

<section class="hero" style="background-image:linear-gradient(90deg,rgba(4,27,23,.78) 0%,rgba(4,27,23,.48) 42%,rgba(4,27,23,.28) 100%),url('<?php echo htmlspecialchars($hero); ?>')">
    <a href="my-trips.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to My Trips</a>

    <div class="hero-content">
        <p class="script">My Trip to</p>
        <h1><?php echo htmlspecialchars($place); ?> <span>🌿</span></h1>
        <p class="hero-tag">"<?php echo $place === "Kodaikanal" ? "Mountains heal differently..." : "Collect beautiful memories..." ?> <i class="fa-regular fa-heart"></i></p>

        <div class="trip-pills">
    <span><i class="fa-solid fa-calendar-days"></i> <?php echo $days; ?> Days</span>

    <span><i class="fa-solid fa-users"></i> <?php echo $members; ?> Travelers</span>

    <span><i class="fa-solid fa-heart"></i> <?php echo htmlspecialchars($tripType); ?> Trip</span>

    <?php if ($startingLocation !== ""): ?>
        <span>
            <i class="fa-solid fa-location-crosshairs"></i>
            From <?php echo htmlspecialchars($startingLocation); ?>
        </span>
    <?php endif; ?>

    <span><i class="fa-solid fa-indian-rupee-sign"></i> Budget Friendly</span>
</div>
    </div>

    <div class="weather-card">
        <div class="weather-place"><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($place); ?></div>
        <div class="weather-main"><i class="fa-solid <?php echo $weatherIcon; ?>"></i><strong>18°C</strong></div>
        <p>Mist & Cool</p><small>Feels like 17°C</small>
    </div>

    <div class="progress-card trip-budget-card">

    <div class="trip-budget-head">
        <div>
            <small>TOTAL TRIP BUDGET</small>
            <h2>₹<?php echo number_format($totalBudget); ?></h2>
        </div>

        <span class="budget-head-icon">
            <i class="fa-solid fa-wallet"></i>
        </span>
    </div>

    <div class="trip-budget-list">

        <div>
            <span>
                <i class="fa-solid fa-bed"></i>
                Stay
            </span>
            <b>₹<?php echo number_format($stay * $days); ?></b>
        </div>

        <div>
            <span>
                <i class="fa-solid fa-utensils"></i>
                Food
            </span>
            <b>₹<?php echo number_format($food * $days); ?></b>
        </div>

        <div>
            <span>
                <i class="fa-solid fa-car"></i>
                Transport
            </span>
            <b>₹<?php echo number_format($transport * $days); ?></b>
        </div>

        <div>
            <span>
                <i class="fa-solid fa-ticket"></i>
                Activities
            </span>
            <b>₹<?php echo number_format($activities * $days); ?></b>
        </div>
        
 </div>

    <a href="budget.php" class="view-my-budget">
        View My Budget
        <i class="fa-solid fa-arrow-right"></i>
    </a>

</div>
</section>

<section class="day-tabs-wrap">
    <div class="day-tabs" id="dayTabs">
        <?php for($d=1;$d<=$days;$d++): $dt=(clone $startDate)->modify("+".($d-1)." days"); ?>
        <button class="day-tab <?php echo $d===1?'active':''; ?>" data-day="<?php echo $d; ?>">
            <b>Day <?php echo $d; ?></b>
            <small><?php echo $dt->format("d M"); ?></small>
        </button>
        <?php endfor; ?>
    </div>
</section>

<section class="time-machine">
    <div class="section-title">
        <span class="clock-circle"><i class="fa-regular fa-clock"></i></span>
        <div><h2>Trip Time Machine</h2><p>Click on any moment to explore your journey</p></div>
        <a class="map-trip-btn" target="_blank" href="https://www.google.com/maps/search/<?php echo urlencode($place); ?>"><span>View Full Trip in Map</span><i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="time-label morning">☀ Morning <small>A new beginning</small></div>
    <div class="time-label afternoon">✦ Afternoon <small>Explore & Enjoy</small></div>
    <div class="time-label evening">🌇 Evening <small>More to Explore</small></div>
    <div class="time-label night">☾ Night <small>Rest & Recharge</small></div>

    <div class="journey-path" id="journeyPath"></div>
</section>

<section class="content-grid">
    <section class="day-plan-card" id="planSection">
        <div class="plan-head">
            <div class="plan-title"><span><i class="fa-regular fa-calendar-days"></i></span><div><h2 id="planDayTitle">Day 1 Plan</h2><p id="planDate"></p></div></div>
            <div class="quote">“A beautiful start to the hills” <span>🌿</span></div>
        </div>
        <div class="plan-list" id="planList"></div>
        <div class="plan-footer">
            <button id="previousDay"><i class="fa-solid fa-arrow-left"></i> Previous Day</button>
            <button id="continueDay" class="continue-day-card">
    <div class="continue-day-copy">
        <small>NEXT DAY</small>
        <strong>Continue to Day 2</strong>
        <em>Tomorrow to Waterfalls & Scenic Views</em>
    </div>

    <div class="continue-day-arrow">
        Continue <i class="fa-solid fa-arrow-right"></i>
    </div>
</button>
        </div>
    </section>

    <aside class="right-column final-right-column">
       <button class="budget-float floating-budget-btn" id="openBudget">
    <span>
        <i class="fa-solid fa-wallet"></i>
    </span>

    <div>
        <small id="budgetDaySmall">DAY 1 BUDGET</small>
        <b>View Day 1 Budget</b>
        <em id="budgetAmount">
            ₹<?php echo number_format($dailyBudget); ?> planned today
        </em>
    </div>

    <i class="fa-solid fa-arrow-right"></i>
</button>

        <div class="shopping-card">
            <div class="shopping-title"><span><i class="fa-solid fa-bag-shopping"></i></span><div><h3>Shopping in <?php echo htmlspecialchars($place); ?></h3><p>Explore local products and souvenirs.</p></div></div>
            <div class="shopping-row">
                <?php foreach($shoppingImages as $item): ?>
                <div><img src="<?php echo htmlspecialchars($item[1]); ?>" alt=""><small><?php echo htmlspecialchars($item[0]); ?></small></div>
                <?php endforeach; ?>
            </div>
            <a href="shopping.php?place=<?php echo urlencode($place); ?>" class="explore-shopping">Explore Shopping <i class="fa-solid fa-arrow-right"></i></a>
        </div>
        <!-- TRIP ESSENTIALS CHECKLIST -->
<div class="essentials-card">

    <div class="essentials-header">

        <div class="essentials-title">
            <span class="essentials-icon">
                <i class="fa-solid fa-clipboard-list"></i>
            </span>

            <div>
                <h3>Trip Essentials Checklist</h3>
                <p>Be ready. Travel worry-free!</p>
            </div>
        </div>

        <strong id="essentialsCount">0 / 8 Ready</strong>

    </div>

    <div class="essentials-progress">
        <div id="essentialsProgress"></div>
    </div>

    <div class="essentials-list">

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            ID Proof / Tickets
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Cash / UPI
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Phone & Charger
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Medicines
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Power Bank
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Sunglasses / Cap
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Water Bottle
        </label>

        <label>
            <input type="checkbox">
            <span><i class="fa-solid fa-check"></i></span>
            Snacks
        </label>

    </div>

    <div class="essentials-tip">
        <i class="fa-solid fa-lightbulb"></i>

        <div>
            <strong>Small things make a big difference!</strong>
            <p>Check your essentials before you travel.</p>
        </div>
    </div>

</div>
    </aside>
</section>
<!-- SHARE YOUR TRIP -->
<section class="share-trip-section">

    <div class="share-trip-head">
        <span>
            <i class="fa-solid fa-share-nodes"></i>
        </span>

        <div>
            <h3>Share Your Trip</h3>
            <p>Keep your travel plan ready and share it anytime.</p>
        </div>
    </div>

    <div class="share-trip-actions">

        <button id="shareWhatsapp" type="button">
            <i class="fa-brands fa-whatsapp"></i>
            <span>
                <strong>WhatsApp</strong>
                <small>Share your trip</small>
            </span>
        </button>

        <button id="savePdf" type="button">
            <i class="fa-solid fa-file-pdf"></i>
            <span>
                <strong>Save PDF</strong>
                <small>Download trip plan</small>
            </span>
        </button>

        <button id="copyPlan" type="button">
            <i class="fa-solid fa-link"></i>
            <span>
                <strong>Copy Link</strong>
                <small>Copy trip plan link</small>
            </span>
        </button>

    </div>

</section>
<!-- QUICK HELP -->
<section class="quick-help-section">

    <div class="quick-help-head">
        <span>
            <i class="fa-solid fa-heart-pulse"></i>
        </span>

        <div>
            <h3>Emergency & Nearby Services</h3>
            <p>Find important services near your destination.</p>
        </div>
    </div>
    <div class="quick-help-layout">

    <!-- LEFT SIDE : SERVICES -->
    <div class="quick-help-grid">

        <!-- HOSPITAL -->
        <button type="button"
                class="quick-help-item hospital-help active-service"
                data-service="hospital">

            <div class="quick-help-icon">
                <i class="fa-solid fa-kit-medical"></i>
            </div>

            <div>
                <strong>Hospital</strong>
                <small>View nearby hospital</small>
            </div>

            <i class="fa-solid fa-arrow-right"></i>
        </button>


        <!-- ATM -->
        <button type="button"
                class="quick-help-item atm-help"
                data-service="atm">

            <div class="quick-help-icon">
                <i class="fa-solid fa-building-columns"></i>
            </div>

            <div>
                <strong>ATM</strong>
                <small>View nearby ATM</small>
            </div>

            <i class="fa-solid fa-arrow-right"></i>
        </button>


        <!-- POLICE -->
        <button type="button"
                class="quick-help-item police-help"
                data-service="police">

            <div class="quick-help-icon">
                <i class="fa-solid fa-building-shield"></i>
            </div>

            <div>
                <strong>Police Station</strong>
                <small>View nearby police station</small>
            </div>

            <i class="fa-solid fa-arrow-right"></i>
        </button>

    </div>


    <!-- MIDDLE : SERVICE DETAILS -->
    <div class="service-details-card" id="serviceDetails">

        <div class="service-details-info">

            <div class="service-details-icon" id="serviceIcon">
                <i class="fa-solid fa-kit-medical"></i>
            </div>

            <div>
                <small id="serviceType">NEARBY HOSPITAL</small>

                <h3 id="serviceName">
                    <?php echo htmlspecialchars($place); ?> Government Hospital
                </h3>

                <p id="serviceAddress">
                    <?php echo htmlspecialchars($place); ?>, Tamil Nadu
                </p>
            </div>

        </div>


        <div class="service-details-extra">

            <div id="serviceDistance">
                <i class="fa-solid fa-location-dot"></i>
                <span>Approximately 2.8 km away</span>
            </div>

            <div id="servicePhone">
                <i class="fa-solid fa-phone"></i>
                <span>Contact details available on Maps</span>
            </div>

        </div>


        <a id="serviceMapLink"
           target="_blank"
           href="https://www.google.com/maps/search/hospital+near+<?php echo urlencode($place); ?>">

            View on Map
            <i class="fa-solid fa-arrow-right"></i>

        </a>

    </div>


    <!-- RIGHT SIDE : IMAGE + MAP -->
    <div class="service-visual-area">

        <!-- PLACE IMAGE -->
        <img
    id="serviceImage"
    class="service-place-image"
    src="<?php echo htmlspecialchars($serviceImageUrl); ?>"
    alt="Nearby Service">
    
        <!-- GOOGLE MAP -->
        <div class="service-map-box">

            <iframe
                src="https://www.google.com/maps?q=<?php echo urlencode($place); ?>&output=embed"
                loading="lazy">
            </iframe>

        </div>

    </div>
   
</main>

<footer>
    <div class="footer-brand"><span class="brand-logo"><i class="fa-solid fa-route"></i></span> Smart Budget Trip Planner</div>
    <div>Plan Smart &nbsp;•&nbsp; Travel More &nbsp;•&nbsp; Live Better</div>
    <div class="social"><i class="fa-brands fa-instagram"></i><i class="fa-brands fa-facebook-f"></i><i class="fa-brands fa-youtube"></i></div>
    <div>Explore &nbsp;•&nbsp; Dream &nbsp;•&nbsp; Discover</div>
</footer>

<!-- BUDGET POPUP -->
<div class="budget-modal" id="budgetModal">
    <div class="budget-backdrop" id="budgetBackdrop"></div>
    <div class="budget-popup">
        <button class="close-budget" id="closeBudget">&times;</button>
        <p class="modal-label" id="modalLabel">DAY 1 BUDGET</p>
        <h2 id="modalTitle">Day 1 Budget</h2>
        <p>A clear breakdown of your planned spending for today.</p>

        <div class="budget-lines">
            <div><span><i class="fa-solid fa-bus"></i> Transport</span><b id="bTransport"></b></div>
            <div><span><i class="fa-solid fa-utensils"></i> Food</span><b id="bFood"></b></div>
            <div><span><i class="fa-solid fa-person-hiking"></i> Activities</span><b id="bActivities"></b></div>
            <div><span><i class="fa-solid fa-bag-shopping"></i> Shopping</span><b id="bShopping"></b></div>
            <div><span><i class="fa-solid fa-bed"></i> Stay</span><b id="bStay"></b></div>
        </div>

        <div class="budget-total"><span>Total (Day <span id="totalDay">1</span>)</span><b id="bTotal"></b></div>
        <div class="budget-status"><i class="fa-solid fa-chart-line"></i><div><b>You are on track!</b><span id="remainingText"></span></div></div>
        <a href="budget.php" class="full-budget-link">View Full Budget <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</div>

<script>
const PLACE_NAME = "<?php echo htmlspecialchars($place); ?>";    
const DAYS = <?php echo json_encode(array_slice($dayPlans,0,$days)); ?>;
const START_DATE = "<?php echo $startDate->format('Y-m-d'); ?>";
const DAILY_BUDGET = <?php echo json_encode($dailyBudget); ?>;
const BUDGET = {
    transport: <?php echo json_encode($transport); ?>,
    food: <?php echo json_encode($food); ?>,
    activities: <?php echo json_encode($activities); ?>,
    shopping: <?php echo json_encode($shopping); ?>,
    stay: <?php echo json_encode($stay); ?>,
    total: <?php echo json_encode($planned); ?>
};
const SERVICE_IMAGES = {
    hospital: "<?php echo htmlspecialchars($serviceImageUrl); ?>",
    atm: "<?php echo htmlspecialchars($atmImageUrl); ?>",
    police: "<?php echo htmlspecialchars($policeImageUrl); ?>"
};
</script>
<script src="my-trips-final.js"></script>
</body>
</html>
