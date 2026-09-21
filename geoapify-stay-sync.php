<?php

require_once "db.php";
require_once "geoapify-config.php";
require_once "pexels-api.php";
/*
|--------------------------------------------------------------------------
| Destination
|--------------------------------------------------------------------------
| Example:
| geoapify-stay-sync.php?place=Kodaikanal
*/

$place = trim($_GET["place"] ?? "Kodaikanal");

if ($place === "") {
    $place = "Kodaikanal";
}


/*
|--------------------------------------------------------------------------
| Step 1: Geocode destination
|--------------------------------------------------------------------------
*/

$geocodeUrl =
    "https://api.geoapify.com/v1/geocode/search"
    . "?text="
    . urlencode($place . ", Tamil Nadu, India")
    . "&limit=1"
    . "&apiKey="
    . urlencode($GEOAPIFY_API_KEY);


$ch = curl_init($geocodeUrl);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20
]);

$geocodeResponse = curl_exec($ch);

if ($geocodeResponse === false) {

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


if ($geocodeHttpCode !== 200) {

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
        $geocodeResult["features"][0]["geometry"]["coordinates"]
    )
) {

    die(
        "Location not found for: " .
        htmlspecialchars($place)
    );
}


/*
|--------------------------------------------------------------------------
| GeoJSON coordinates are:
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


/*
|--------------------------------------------------------------------------
| Step 2: Search hotels + guest houses
|--------------------------------------------------------------------------
| We search multiple accommodation categories.
|--------------------------------------------------------------------------
*/

$categories = [
    "accommodation.hotel",
    "accommodation.guest_house"
];
/*
|--------------------------------------------------------------------------
| Clear old API records for this destination
|--------------------------------------------------------------------------
| We use a small marker in description to identify API-synced rows.
|--------------------------------------------------------------------------
*/

$deleteStmt = $conn->prepare(
    "DELETE FROM stay_options
     WHERE destination = ?
     AND (
         description LIKE '%[GEOAPIFY_SYNC]%'
         OR name LIKE '%Hotel Option%'
     )"
);

if ($deleteStmt) {

    $deleteStmt->bind_param(
        "s",
        $place
    );

    $deleteStmt->execute();

    $deleteStmt->close();
}


/*
|--------------------------------------------------------------------------
| Counters
|--------------------------------------------------------------------------
*/

$totalSaved = 0;


/*
|--------------------------------------------------------------------------
| Step 3: Get places
|--------------------------------------------------------------------------
*/

foreach ($categories as $category) {

    $placesUrl =
        "https://api.geoapify.com/v2/places"
        . "?categories="
        . urlencode($category)
        . "&filter=circle:"
        . $longitude
        . ","
        . $latitude
        . ",15000"
        . "&limit=10"
        . "&apiKey="
        . urlencode($GEOAPIFY_API_KEY);


    $ch = curl_init($placesUrl);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20
    ]);

    $placesResponse =
        curl_exec($ch);

    if ($placesResponse === false) {

        curl_close($ch);

        continue;
    }

    $placesHttpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);


    if ($placesHttpCode !== 200) {
        continue;
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

        continue;
    }


    /*
    |--------------------------------------------------------------------------
    | Step 4: Save each place
    |--------------------------------------------------------------------------
    */

    foreach (
        $placesResult["features"]
        as $feature
    ) {


        $properties =
            $feature["properties"]
            ?? [];


        /*
        ------------------------------------------------------------
        | Name
        ------------------------------------------------------------
        */

        $name =
            trim(
                $properties["name"]
                ?? ""
            );


        /*
        ------------------------------------------------------------
        | Skip unnamed places
        ------------------------------------------------------------
        */

        if ($name === "") {
            continue;
        }


        /*
        ------------------------------------------------------------
        | Coordinates
        ------------------------------------------------------------
        */

        $placeLat =
            $properties["lat"]
            ?? $latitude;

        $placeLon =
            $properties["lon"]
            ?? $longitude;


        /*
        ------------------------------------------------------------
        | Address
        ------------------------------------------------------------
        */

        $address =
            trim(
                $properties["formatted"]
                ?? ""
            );


        if ($address === "") {

            $address =
                $place . ", Tamil Nadu";
        }


        /*
        ------------------------------------------------------------
        | Category
        ------------------------------------------------------------
        */

        
      $categoryText = strtolower(
    $category . " " .
    $name . " " .
    ($properties["categories"][0] ?? "")
);

if (
    str_contains(
        $categoryText,
        "resort"
    )
) {

    $stayType = "Resort";

} elseif (
    str_contains(
        $categoryText,
        "guest_house"
    ) ||
    str_contains(
        $categoryText,
        "guest house"
    ) ||
    str_contains(
        $categoryText,
        "homestay"
    )
) {

    $stayType = "Homestay";

} else {

    $stayType = "Hotel";
}


        /*
        ------------------------------------------------------------
        | Rating
        ------------------------------------------------------------
        */

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


        /*
        ------------------------------------------------------------
        | Price
        ------------------------------------------------------------
        |
        | Geoapify Places API does NOT guarantee
        | hotel booking price.
        |
        | So we intentionally keep estimated_price
        | as 0 for API records instead of making up
        | a live hotel price.
        |
        ------------------------------------------------------------
        */

        $estimatedPrice = 0;


        /*
        ------------------------------------------------------------
        | Image
        ------------------------------------------------------------
        |
        | Geoapify may have media information for some places.
        | Otherwise image_url remains empty.
        |
        ------------------------------------------------------------
        */
/* ------------------------------------------------------------
   | Description fallback
------------------------------------------------------------ */

$description =
    "Comfortable " .
    strtolower($stayType) .
    " option for your trip. [GEOAPIFY_SYNC]";
        /* ------------------------------------------------------------
   | Image from Pexels
------------------------------------------------------------ */

$imageUrl = "";

/* Different photo for each stay */
$photoIndex = abs(crc32($name)) % 10;

/* Search based on stay type */

/* Search photo using exact place name */
$imageQuery = $name;

$photoIndex = 0;

$photo = searchPexelsPhoto(
    $imageQuery,
    $photoIndex
);

if (
    $photo &&
    isset($photo["src"]["large2x"])
) {
    $imageUrl = $photo["src"]["large2x"];
}
$photo = searchPexelsPhoto(
    $imageQuery,
    $photoIndex
);

if (
    $photo &&
    isset($photo["src"]["large2x"])
) {
    $imageUrl =
        $photo["src"]["large2x"];
}
/* Update missing images for existing stays */
$getStays = $conn->prepare("
    SELECT id, name, stay_type
    FROM stay_options
    WHERE destination = ?
    AND (image_url IS NULL OR image_url = '')
");

$getStays->bind_param("s", $place);
$getStays->execute();

$result = $getStays->get_result();

while ($stay = $result->fetch_assoc()) {

    $stayName = $stay["name"];
    $stayType = $stay["stay_type"];

    $photoIndex = abs(crc32($stayName)) % 10;

    if ($stayType === "Resort") {
        $imageQuery = "beautiful luxury resort swimming pool";
    }
    elseif ($stayType === "Hotel") {
        $imageQuery = "luxury hotel building";
    }
    elseif ($stayType === "Homestay") {
        $imageQuery = "beautiful homestay cottage";
    }
    else {
        $imageQuery = "travel accommodation";
    }

    $photo = searchPexelsPhoto(
        $imageQuery,
        $photoIndex
    );

    if (
        $photo &&
        isset($photo["src"]["large2x"])
    ) {

        $imageUrl = $photo["src"]["large2x"];

        $updateImage = $conn->prepare("
            UPDATE stay_options
            SET image_url = ?
            WHERE id = ?
        ");

        $updateImage->bind_param(
            "si",
            $imageUrl,
            $stay["id"]
        );

        $updateImage->execute();
    }
}


/* Backup search */
if ($imageUrl === "") {

    $photo = searchPexelsPhoto(
        strtolower($stayType)
        . " accommodation travel",
        $photoIndex
    );

    if (
        $photo &&
        isset($photo["src"]["large2x"])
    ) {
        $imageUrl =
            $photo["src"]["large2x"];
    }
}
/* ------------------------------------------------------------
   | Check if this stay already exists
------------------------------------------------------------ */

$checkStmt = $conn->prepare(
    "SELECT id
     FROM stay_options
     WHERE destination = ?
     AND name = ?
     LIMIT 1"
);

$existing = null;

if ($checkStmt) {

    $checkStmt->bind_param(
        "ss",
        $place,
        $name
    );

    $checkStmt->execute();

    $result = $checkStmt->get_result();

    $existing = $result->fetch_assoc();
}
if ($existing) {

                 $existingId =
                (int)$existing["id"];

            $checkStmt->close();


            $updateStmt = $conn->prepare(
                "UPDATE stay_options
                 SET stay_type = ?,
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
                    $stayType,
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

         }  else {

            /*
            --------------------------------------------------------
            | INSERT
            --------------------------------------------------------
            */

            $checkStmt->close();


            $insertStmt = $conn->prepare(
                "INSERT INTO stay_options
                (
                    destination,
                    stay_type,
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
                $stayType,
                $name,
                $description,
                $estimatedPrice,
                $rating,
                $imageUrl,
                $mapUrl
            );


            if ($insertStmt->execute()) {
                $totalSaved++;
            }


            $insertStmt->close();
        }
    }
}
/* =========================================================
   AUTO FETCH REAL RESORTS
   OpenStreetMap / Overpass
========================================================= */

$resortQuery = '
[out:json];
(
  node["tourism"="resort"](around:25000,' . $latitude . ',' . $longitude . ');
  way["tourism"="resort"](around:25000,' . $latitude . ',' . $longitude . ');
  relation["tourism"="resort"](around:25000,' . $latitude . ',' . $longitude . ');
);
out center 10;
';

$ch = curl_init(
    "https://overpass-api.de/api/interpreter"
);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => [
        "data" => $resortQuery
    ],
    CURLOPT_TIMEOUT => 30
]);

$resortResponse = curl_exec($ch);

curl_close($ch);

$resortData = json_decode(
    $resortResponse,
    true
);


/* Save real resorts */

if (
    isset($resortData["elements"])
) {

    foreach (
        $resortData["elements"]
        as $resort
    ) {

        $resortName =
            trim(
                $resort["tags"]["name"]
                ?? ""
            );

        if ($resortName === "") {
            continue;
        }


        $stayType = "Resort";

        $description =
            "Resort option for your trip. [RESORT_SYNC]";


        $estimatedPrice = 0;

        $rating = 0;


        /* Resort photo */

        $imageUrl = "";

        $photoIndex =
            abs(crc32($resortName)) % 10;

        $photo = searchPexelsPhoto(
            $resortName . " resort hotel",
            $photoIndex
        );

        if (
            $photo &&
            isset($photo["src"]["large2x"])
        ) {

            $imageUrl =
                $photo["src"]["large2x"];

        } else {

            $photo = searchPexelsPhoto(
                "beautiful luxury resort swimming pool",
                $photoIndex
            );

            if (
                $photo &&
                isset($photo["src"]["large2x"])
            ) {

                $imageUrl =
                    $photo["src"]["large2x"];
            }
        }


        /* Map */

        $mapUrl =
            "https://www.google.com/maps/search/?api=1&query="
            . urlencode(
                $resortName .
                ", " .
                $place
            );


        /* Check existing */

        $checkStmt =
            $conn->prepare(
                "SELECT id
                 FROM stay_options
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
            $resortName
        );


        $checkStmt->execute();

        $result =
            $checkStmt->get_result();

        $existing =
            $result->fetch_assoc();

        $checkStmt->close();


        if ($existing) {

            $existingId =
                (int)$existing["id"];


            $updateStmt =
                $conn->prepare(
                    "UPDATE stay_options
                     SET stay_type = ?,
                         description = ?,
                         image_url = ?,
                         map_url = ?
                     WHERE id = ?"
                );


            if ($updateStmt) {

                $updateStmt->bind_param(
                    "ssssi",
                    $stayType,
                    $description,
                    $imageUrl,
                    $mapUrl,
                    $existingId
                );

                $updateStmt->execute();

                $updateStmt->close();
            }

        } else {

            $insertStmt =
                $conn->prepare(
                    "INSERT INTO stay_options
                    (
                        destination,
                        stay_type,
                        name,
                        description,
                        estimated_price,
                        rating,
                        image_url,
                        map_url
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );


            if (!$insertStmt) {
                continue;
            }


            $insertStmt->bind_param(
                "ssssddss",
                $place,
                $stayType,
                $resortName,
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
}



/*
|--------------------------------------------------------------------------
| Result
|--------------------------------------------------------------------------
*/

echo "<!DOCTYPE html>";

echo "<html>";

echo "<head>";

echo "<meta charset='UTF-8'>";

echo "<title>Geoapify Stay Sync</title>";

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

echo "<h1>Stay Sync Complete ✅</h1>";

echo "<div class='success'>";

echo htmlspecialchars($totalSaved);

echo " new stay records were saved for ";

echo htmlspecialchars($place);

echo ".</div>";

echo "<div class='info'>";

echo "<strong>Destination:</strong> ";

echo htmlspecialchars($place);

echo "<br>";

echo "<strong>Source:</strong> Geoapify Places API";

echo "<br>";

echo "<strong>Database:</strong> stay_options";

echo "<br><br>";

echo "Open your Stay & Food page to see the updated data.";

echo "</div>";

echo "</div>";

echo "</body>";

echo "</html>";

?>