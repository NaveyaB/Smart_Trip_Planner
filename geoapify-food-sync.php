<?php

require_once "db.php";
require_once "geoapify-config.php";
require_once "pexels-api.php";


/* =====================================================
   DESTINATION
===================================================== */

$place = trim(
    $_GET["place"] ?? "Kodaikanal"
);

if ($place === "") {
    $place = "Kodaikanal";
}


/* =====================================================
   STEP 1 - GEOCODE DESTINATION
===================================================== */

$geocodeUrl =
    "https://api.geoapify.com/v1/geocode/search"
    . "?text="
    . urlencode(
        $place .
        ", Tamil Nadu, India"
    )
    . "&limit=1"
    . "&apiKey="
    . urlencode(
        $GEOAPIFY_API_KEY
    );


$ch = curl_init(
    $geocodeUrl
);

curl_setopt_array(
    $ch,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]
);


$geocodeResponse =
    curl_exec($ch);


if (
    $geocodeResponse === false
) {

    die(
        "Geocoding error: " .
        curl_error($ch)
    );
}


$geocodeHttpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


curl_close($ch);


if (
    $geocodeHttpCode !== 200
) {

    die(
        "Geoapify geocoding failed. HTTP: " .
        $geocodeHttpCode
    );
}


$geocodeResult =
    json_decode(
        $geocodeResponse,
        true
    );


if (
    !isset(
        $geocodeResult[
            "features"
        ][0][
            "geometry"
        ][
            "coordinates"
        ]
    )
) {

    die(
        "Location not found for: " .
        htmlspecialchars($place)
    );
}


/*
|--------------------------------------------------------------------------
| GeoJSON:
| [longitude, latitude]
|--------------------------------------------------------------------------
*/

$longitude =
    (float)
    $geocodeResult[
        "features"
    ][0][
        "geometry"
    ][
        "coordinates"
    ][0];


$latitude =
    (float)
    $geocodeResult[
        "features"
    ][0][
        "geometry"
    ][
        "coordinates"
    ][1];


/* =====================================================
   STEP 2 - DELETE OLD API-SYNCED RESTAURANTS
===================================================== */

$deleteStmt =
    $conn->prepare(
        "DELETE FROM restaurant_options
         WHERE destination = ?
         AND description LIKE '%[GEOAPIFY_SYNC]%'"
    );


if ($deleteStmt) {

    $deleteStmt->bind_param(
        "s",
        $place
    );

    $deleteStmt->execute();

    $deleteStmt->close();
}


/* =====================================================
   COUNTER
===================================================== */

$totalSaved = 0;


/* =====================================================
   STEP 3 - RESTAURANT CATEGORY
===================================================== */

$category =
    "catering.restaurant";


/* =====================================================
   STEP 4 - SEARCH RESTAURANTS
===================================================== */

$placesUrl =
    "https://api.geoapify.com/v2/places"
    . "?categories="
    . urlencode($category)
    . "&filter=circle:"
    . $longitude
    . ","
    . $latitude
    . ",15000"
    . "&limit=20"
    . "&apiKey="
    . urlencode(
        $GEOAPIFY_API_KEY
    );


$ch = curl_init(
    $placesUrl
);


curl_setopt_array(
    $ch,
    [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]
);


$placesResponse =
    curl_exec($ch);


if (
    $placesResponse === false
) {

    die(
        "Restaurant API error: " .
        curl_error($ch)
    );
}


$placesHttpCode =
    curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );


curl_close($ch);


if (
    $placesHttpCode !== 200
) {

    die(
        "Geoapify restaurant search failed. HTTP: " .
        $placesHttpCode
    );
}


$placesResult =
    json_decode(
        $placesResponse,
        true
    );


if (
    !isset(
        $placesResult["features"]
    )
) {

    die(
        "No restaurant data returned."
    );
}


/* =====================================================
   STEP 5 - SAVE RESTAURANTS
===================================================== */

foreach (
    $placesResult["features"]
    as $restaurantIndex => $feature
) {
    $properties =
        $feature["properties"]
        ?? [];


    /* -------------------------------------------------
       NAME
    ------------------------------------------------- */

    $name =
        trim(
            $properties["name"]
            ?? ""
        );


    if ($name === "") {
        continue;
    }


    /* -------------------------------------------------
       ADDRESS
    ------------------------------------------------- */

    $address =
        trim(
            $properties["formatted"]
            ?? ""
        );


    if ($address === "") {

        $address =
            $place .
            ", Tamil Nadu";
    }


    /* -------------------------------------------------
       COORDINATES
    ------------------------------------------------- */

    $placeLat =
        $properties["lat"]
        ?? $latitude;

    $placeLon =
        $properties["lon"]
        ?? $longitude;


    /* -------------------------------------------------
       RATING
    ------------------------------------------------- */

    $rating = 0;

    if (
        isset(
            $properties["rating"]
        )
    ) {

        $rating =
            (float)
            $properties["rating"];
    }


   /* -------------------------------------------------
   FOOD TYPE
------------------------------------------------- */

$restaurantType = "Both";

/* Convert restaurant name + categories to text */
$categories = $properties["categories"] ?? [];

$categoryText = strtolower(
    $name . " " . implode(" ", $categories)
);


/* NON-VEG KEYWORDS */

$nonVegWords = [
    "chicken",
    "mutton",
    "meat",
    "fish",
    "seafood",
    "biryani",
    "grill",
    "barbecue",
    "bbq"
];


/* VEG KEYWORDS */

$vegWords = [
    "vegetarian",
    "veg",
    "pure veg",
    "vegan",
    "udupi",
    "jain"
];


$restaurantType = "Both";


foreach ($nonVegWords as $word) {

    if (str_contains($categoryText, $word)) {

        $restaurantType = "Non-Veg";
        break;
    }
}


if ($restaurantType === "Both") {

    foreach ($vegWords as $word) {

        if (str_contains($categoryText, $word)) {

            $restaurantType = "Veg";
            break;
        }
    }
}
    /* -------------------------------------------------
       PRICE
       Geoapify does not guarantee restaurant
       real-time menu pricing.
    ------------------------------------------------- */

    $estimatedPrice = 0;


    /* -------------------------------------------------
       IMAGE
    ------------------------------------------------- */
$imageUrl = "";

if (function_exists("searchPexelsPhoto")) {

    try {

        if ($restaurantType === "Veg") {

            $foodQueries = [
                "vegetarian South Indian food thali",
                "vegetarian restaurant food dosa idli",
                "Indian vegetarian meals"
            ];

        } elseif ($restaurantType === "Non-Veg") {

            $foodQueries = [
                "Indian non vegetarian chicken food",
                "chicken biryani Indian restaurant",
                "non vegetarian Indian food meal"
            ];

        } else {

            $foodQueries = [
                "Indian restaurant food dining",
                "South Indian food meal",
                "Indian food restaurant table"
            ];
        }


       $queryIndex =
    abs(crc32($name)) % count($foodQueries);

$photoIndex =
    abs(crc32($name . $place)) % 10;

$photo = searchPexelsPhoto(
    $foodQueries[$queryIndex],
    $photoIndex
);

       

        if (
            $photo &&
            isset($photo["src"]["large2x"])
        ) {

            $imageUrl =
                $photo["src"]["large2x"];
        }

    } catch (Throwable $e) {

        $imageUrl = "";
    }
   }  
    /* -------------------------------------------------
       MAP
    ------------------------------------------------- */

    $mapUrl =
        "https://www.google.com/maps/search/?api=1&query="
        . urlencode(
            $name .
            ", " .
            $address
        );


    /* -------------------------------------------------
       DESCRIPTION
    ------------------------------------------------- */

    $description =
        "Live restaurant data from Geoapify. "
        . $address
        . " [GEOAPIFY_SYNC]";


    /* =================================================
       CHECK DUPLICATE
    ================================================= */

    $checkStmt =
        $conn->prepare(
            "SELECT id
             FROM restaurant_options
             WHERE destination = ?
             AND name = ?
             LIMIT 1"
        );


    if (!$checkStmt) {
        continue;
    }


    $checkStmt->bind_param(
        "ss",
        $place,
        $name
    );


    $checkStmt->execute();


    $checkResult =
        $checkStmt->get_result();


    if (
        $checkResult->num_rows > 0
    ) {

        /* =============================================
           UPDATE
        ============================================= */

        $existing =
            $checkResult->fetch_assoc();

        $existingId =
            (int)
            $existing["id"];


        $checkStmt->close();


        $updateStmt =
            $conn->prepare(
                "UPDATE restaurant_options
                 SET restaurant_type = ?,
                     description = ?,
                     estimated_price = ?,
                     rating = ?,
                     image_url = ?,
                     map_url = ?
                 WHERE id = ?"
            );


        if ($updateStmt) {

            $updateStmt->bind_param(
                "ssddssi",
                $restaurantType,
                $description,
                $estimatedPrice,
                $rating,
                $imageUrl,
                $mapUrl,
                $existingId
            );


            $updateStmt->execute();


            $updateStmt->close();
        }


    } else {

        /* =============================================
           INSERT
        ============================================= */

        $checkStmt->close();


        $insertStmt =
            $conn->prepare(
                "INSERT INTO restaurant_options
                (
                    destination,
                    restaurant_type,
                    name,
                    description,
                    estimated_price,
                    rating,
                    image_url,
                    map_url
                )
                VALUES
                (
                    ?, ?, ?, ?, ?, ?, ?, ?
                )"
            );


        if (!$insertStmt) {
            continue;
        }


        $insertStmt->bind_param(
            "ssssddss",
            $place,
            $restaurantType,
            $name,
            $description,
            $estimatedPrice,
            $rating,
            $imageUrl,
            $mapUrl
        );


        if (
            $insertStmt->execute()
        ) {

            $totalSaved++;
        }


        $insertStmt->close();
    }
}


/* =====================================================
   RESULT
===================================================== */

echo "<!DOCTYPE html>";

echo "<html>";

echo "<head>";

echo "<meta charset='UTF-8'>";

echo "<title>Geoapify Food Sync</title>";

echo "
<style>

body{
    font-family:Arial,sans-serif;
    background:#edf8ef;
    color:#15351f;
    padding:40px;
}

.box{
    max-width:700px;
    margin:auto;
    background:#ffffff;
    padding:30px;
    border-radius:18px;
    box-shadow:0 12px 30px rgba(30,80,40,.12);
}

h1{
    margin-top:0;
    color:#25663a;
}

.success{
    padding:15px;
    border-radius:10px;
    background:#dff2e3;
    border:1px solid #9fc8a8;
    font-weight:700;
}

.info{
    margin-top:15px;
    line-height:1.7;
}

</style>
";

echo "</head>";

echo "<body>";

echo "<div class='box'>";

echo "<h1>Food Sync Complete ✅</h1>";

echo "<div class='success'>";

echo htmlspecialchars(
    $totalSaved
);

echo " new restaurant records were saved for ";

echo htmlspecialchars(
    $place
);

echo ".</div>";

echo "<div class='info'>";

echo "<strong>Destination:</strong> ";

echo htmlspecialchars(
    $place
);

echo "<br>";

echo "<strong>Source:</strong> Geoapify Places API";

echo "<br>";

echo "<strong>Database:</strong> restaurant_options";

echo "<br><br>";

echo "Open your Stay & Food page to see the updated restaurant data.";

echo "</div>";

echo "</div>";

echo "</body>";

echo "</html>";

?>