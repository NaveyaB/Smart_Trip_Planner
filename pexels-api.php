<?php

require_once "geoapify-config.php";

function searchPexelsPhoto($query, $photoIndex = 0)
{
    global $pexelsApiKey;

    $url = "https://api.pexels.com/v1/search?" . http_build_query([
        "query"       => $query,
        "per_page"    => 10,
        "orientation" => "landscape",
        "size"        => "large"
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,

        CURLOPT_HTTPHEADER => [
            "Authorization: " . $pexelsApiKey
        ],

        CURLOPT_TIMEOUT => 15
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

    $data = json_decode(
        $response,
        true
    );

    if (
        !isset($data["photos"]) ||
        empty($data["photos"])
    ) {
        return null;
    }

    $photos = [];

    foreach ($data["photos"] as $photo) {

        if (
            isset($photo["src"]["large2x"]) &&
            isset($photo["width"]) &&
            isset($photo["height"]) &&
            $photo["width"] >= $photo["height"]
        ) {

            $photos[] = $photo;

        }
    }

    if (empty($photos)) {
        $photos = $data["photos"];
    }

    $photoIndex =
        $photoIndex % count($photos);

    return $photos[$photoIndex];
}
