<?php

header("Content-Type: application/json");

require_once "geoapify-config.php";


$lat = isset($_GET["lat"]) ? (float)$_GET["lat"] : 0;
$lon = isset($_GET["lon"]) ? (float)$_GET["lon"] : 0;

$service = $_GET["service"] ?? "";


/* Geoapify category */

$categories = [

    "hospital" => "healthcare.hospital",

    "atm" => "service.financial.atm",

    "police" => "service.police"

];


if (
    !$lat ||
    !$lon ||
    !isset($categories[$service])
) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);

    exit();

}


$category = $categories[$service];


/* Geoapify API URL */

$url = "https://api.geoapify.com/v2/places?" . http_build_query([

    "categories" => $category,

    "bias" => "proximity:$lon,$lat",

    "limit" => 1,

    "apiKey" => $GEOAPIFY_API_KEY

]);


/* API Request */

$ch = curl_init($url);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_TIMEOUT => 15

]);


$response = curl_exec($ch);

curl_close($ch);


$data = json_decode($response, true);


if (
    empty($data["features"])
) {

    echo json_encode([
        "success" => false,
        "message" => "No nearby service found"
    ]);

    exit();

}


$place = $data["features"][0]["properties"];


echo json_encode([

    "success" => true,

    "name" =>
        $place["name"] ?? "Nearby Service",

    "address" =>
        $place["formatted"] ?? "Address unavailable",

    "distance" =>
        $place["distance"] ?? 0,

    "lat" =>
        $place["lat"] ?? 0,

    "lon" =>
        $place["lon"] ?? 0

]);

?>