<?php

session_start();
include "db.php";
require_once "pexels-api.php";

/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];


/* =====================================================
   GET LATEST TRIP
===================================================== */

$sql = "SELECT *
        FROM trips
        WHERE user_id = ?
        ORDER BY id DESC
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: plan-trip.php");
    exit();
}

$trip = $result->fetch_assoc();
$stmt->close();


/* =====================================================
   SAFE VALUES
===================================================== */

$destination = htmlspecialchars($trip["destination"] ?? "Dindigul");

$budgetRaw = (float)($trip["total_budget"] ?? 0);
$budget = number_format($budgetRaw);

$members = (int)($trip["members"] ?? 1);
$days    = (int)($trip["days"] ?? 1);

$transport = htmlspecialchars($trip["transport"] ?? "Not selected");
$stay      = htmlspecialchars($trip["stay"] ?? "Not selected");
$food      = htmlspecialchars($trip["food"] ?? "Not selected");
$tripType  = htmlspecialchars($trip["trip_type"] ?? "Personal Trip");

$startDate = htmlspecialchars($trip["start_date"] ?? "");
$endDate   = htmlspecialchars($trip["end_date"] ?? "");


/* =====================================================
   MASTER DESTINATION SETTINGS
===================================================== */

/* Get district from URL first.
   Otherwise use latest trip destination. */

$selectedDestination = trim(
    $_GET["place"] ?? ($trip["destination"] ?? "Dindigul")
);

$destinationKey = strtolower($selectedDestination);


/* =====================================================
   ALL DISTRICT SETTINGS
===================================================== */

$destinationSettings = [

    /* =================================================
       DINDIGUL
    ================================================= */

    "dindigul" => [

        "hero" => "image/Dindigul city.jpeg",

        "eyebrow" => "YOUR PERSONALIZED JOURNEY",

        "taglineSmall" => "LESS EXPENSE",

        "taglineMain" => "More Experience.",

        "description" =>
            "A smarter way to discover hills, heritage, spirituality and hidden escapes around Dindigul — shaped around your trip.",

        "places" => [

            [
                "name" => "Kodaikanal",
                "image" => "image/kodaikanal.jpg",
                "tag" => "Hills & Nature",
                "description" =>
                    "Cool mountain views, lakes and peaceful escapes for your getaway.",
                "link" => "stay-food.php?place=Kodaikanal"
            ],

            [
                "name" => "Palani",
                "image" => "image/palani.jpg",
                "tag" => "Spiritual",
                "description" =>
                    "A famous hill destination combining spirituality, culture and scenic views.",
                "link" => "stay-food.php?place=Palani"
            ],

            [
                "name" => "Sirumalai",
                "image" => "image/sirumalai.jpg",
                "tag" => "Nature Escape",
                "description" =>
                    "Quiet hills, greenery and a refreshing break away from busy places.",
                "link" => "stay-food.php?place=Sirumalai"
            ],

            [
                "name" => "Dindigul Fort",
                "image" => "image/dindigul-fort.jpg",
                "tag" => "Heritage",
                "description" =>
                    "Step into the history of Dindigul with panoramic views from the fort.",
                "link" => "stay-food.php?place=Dindigul%20Fort"
            ]
        ]
    ],


    /* =================================================
       MADURAI
    ================================================= */

    "madurai" => [

        "hero" => "image/madurai.jpeg",

        "eyebrow" => "DISCOVER MADURAI",

        "taglineSmall" => "HERITAGE",

        "taglineMain" => "More Experience.",

        "description" =>
            "Explore the heritage, spirituality and culture of Madurai through smart, budget-friendly travel planning.",

        "places" => [

            [
                "name" => "Madurai",
                "image" => "",
                "tag" => "Heritage & Culture",
                "description" =>
                    "Discover historic temples, heritage streets, museums and the cultural heart of Madurai.",
                "link" => "stay-food.php?place=Madurai"
            ],

            [
                "name" => "Thirupparankundram",
                "image" => "",
                "tag" => "Temple & Heritage",
                "description" =>
                    "Explore the famous hill temple, heritage surroundings and peaceful scenic views.",
                "link" => "stay-food.php?place=Thirupparankundram"
            ]
        ]
    ],


    /* =================================================
   COIMBATORE
================================================= */

"coimbatore" => [

    "hero" => "image/Coimbatore.jpeg",

    "eyebrow" => "DISCOVER COIMBATORE",

    "taglineSmall" => "NATURE & CULTURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover the natural beauty, temples and peaceful escapes around Coimbatore with smart budget planning.",

    "places" => [

        [
            "name" => "Coimbatore",
            "image" => "",
            "tag" => "City & Nature",
            "description" =>
                "Explore parks, museums, temples, lakes and cultural attractions across Coimbatore.",
            "link" => "stay-food.php?place=Coimbatore"
        ],

        [
            "name" => "Marudamalai",
            "image" => "",
            "tag" => "Temple & Hills",
            "description" =>
                "A beautiful hill destination combining temple heritage, greenery and scenic views.",
            "link" => "stay-food.php?place=Marudamalai"
        ],

        [
            "name" => "Valparai",
            "image" => "",
            "tag" => "Hills & Nature",
            "description" =>
                "Explore misty hills, tea estates, waterfalls and peaceful forest landscapes around Valparai.",
            "link" => "stay-food.php?place=Valparai"
        ]

    ]
],
/* =================================================
   KANYAKUMARI
================================================= */

"kanyakumari" => [
    "hero" => "image/Kannyakumari.jpeg",

    "eyebrow" => "DISCOVER KANYAKUMARI",

    "taglineSmall" => "COASTAL & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover the coastal beauty, cultural heritage and peaceful experiences of Kanyakumari with smart budget planning.",

    "places" => [

        [
            "name" => "Kanyakumari",
            "image" => "",
            "tag" => "Sea & Culture",
            "description" =>
                "Explore the scenic coastline, cultural attractions and peaceful surroundings of Kanyakumari.",
            "link" => "stay-food.php?place=Kanyakumari"
        ],

        [
            "name" => "Padmanabhapuram Palace",
            "image" => "",
            "tag" => "Heritage & History",
            "description" =>
                "Explore traditional architecture, heritage spaces and the historic charm of Padmanabhapuram Palace.",
            "link" => "stay-food.php?place=Padmanabhapuram%20Palace"
        ]

    ]
],
/* =================================================
   NILGIRIS
================================================= */

"nilgiris" => [

    "hero" => "image/Nilgiris.jpg",

    "eyebrow" => "DISCOVER NILGIRIS",

    "taglineSmall" => "HILLS & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore the misty hills, tea estates and peaceful mountain destinations of the Nilgiris with smart budget planning.",

    "places" => [

        [
            "name" => "Ooty",
            "image" => "",
            "tag" => "Hill Station",
            "description" =>
                "Enjoy scenic hills, gardens, lakes, tea estates and peaceful mountain experiences in Ooty.",
            "link" => "stay-food.php?place=Ooty"
        ],

        [
            "name" => "Coonoor",
            "image" => "",
            "tag" => "Tea & Hills",
            "description" =>
                "Discover tea estates, viewpoints and relaxing mountain landscapes around Coonoor.",
            "link" => "stay-food.php?place=Coonoor"
        ],

        [
            "name" => "Kotagiri",
            "image" => "",
            "tag" => "Nature Escape",
            "description" =>
                "Experience quiet hill roads, tea gardens and beautiful natural surroundings in Kotagiri.",
            "link" => "stay-food.php?place=Kotagiri"
        ]

    ]
],

/* =================================================
   SALEM
================================================= */

"salem" => [

    "hero" => "image/Selam.jpeg",

    "eyebrow" => "DISCOVER SALEM",

    "taglineSmall" => "HILLS & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover the hills, scenic escapes and cultural attractions around Salem with smart budget planning.",

    "places" => [

        [
            "name" => "Yercaud",
            "image" => "",
            "tag" => "Hill Station",
            "description" =>
                "Explore peaceful hills, viewpoints, greenery and refreshing mountain landscapes in Yercaud.",
            "link" => "stay-food.php?place=Yercaud"
        ],

        [
            "name" => "Mettur",
            "image" => "",
            "tag" => "Dam & Nature",
            "description" =>
                "Enjoy scenic surroundings, peaceful landscapes and the natural beauty around Mettur.",
            "link" => "stay-food.php?place=Mettur"
        ]

    ]
],

/* =================================================
   THANJAVUR
================================================= */

"thanjavur" => [

    "hero" => "image/Thanjavur.jpeg",

    "eyebrow" => "DISCOVER THANJAVUR",

    "taglineSmall" => "HERITAGE & CULTURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Experience the rich heritage, architecture, temples and cultural destinations of Thanjavur with smart budget planning.",

    "places" => [

        [
            "name" => "Thanjavur",
            "image" => "",
            "tag" => "Heritage & Culture",
            "description" =>
                "Explore historic architecture, cultural attractions, temples and the artistic heritage of Thanjavur.",
            "link" => "stay-food.php?place=Thanjavur"
        ],

        [
            "name" => "Kumbakonam",
            "image" => "",
            "tag" => "Temple Town",
            "description" =>
                "Discover temples, traditional culture and peaceful heritage experiences around Kumbakonam.",
            "link" => "stay-food.php?place=Kumbakonam"
        ]

    ]
],

/* =================================================
   RAMANATHAPURAM
================================================= */

"ramanathapuram" => [

    "hero" => "image/Ramanathapuram.jpg",

    "eyebrow" => "DISCOVER RAMANATHAPURAM",

    "taglineSmall" => "COASTAL & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore coastal landscapes, spiritual destinations and unique travel experiences around Ramanathapuram.",

    "places" => [

        [
            "name" => "Rameswaram",
            "image" => "",
            "tag" => "Spiritual & Coastal",
            "description" =>
                "Explore the coastal beauty, heritage attractions and peaceful surroundings of Rameswaram.",
            "link" => "stay-food.php?place=Rameswaram"
        ],

        [
            "name" => "Dhanushkodi",
            "image" => "",
            "tag" => "Coastal Escape",
            "description" =>
                "Experience the unique coastal landscape and peaceful atmosphere of Dhanushkodi.",
            "link" => "stay-food.php?place=Dhanushkodi"
        ]

    ]
],
/* =================================================
   TIRUCHIRAPPALLI
================================================= */

"tiruchirappalli" => [

    "hero" => "",

    "eyebrow" => "DISCOVER TIRUCHIRAPPALLI",

    "taglineSmall" => "HERITAGE & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover the heritage, temples, riverside landscapes and peaceful attractions of Tiruchirappalli with smart budget planning.",

    "places" => [

        [
            "name" => "Tiruchirappalli",
            "image" => "",
            "tag" => "Heritage & Culture",
            "description" =>
                "Explore the historic city, Rockfort, temples, museums and cultural attractions of Tiruchirappalli.",
            "link" => "stay-food.php?place=Tiruchirappalli"
        ],

        [
            "name" => "Mukkombu",
            "image" => "",
            "tag" => "Nature & Water",
            "description" =>
                "Enjoy scenic river surroundings and a peaceful natural atmosphere at Mukkombu.",
            "link" => "stay-food.php?place=Mukkombu"
        ]

    ]
],


/* =================================================
   TIRUNELVELI
================================================= */

"tirunelveli" => [

    "hero" => "",

    "eyebrow" => "DISCOVER TIRUNELVELI",

    "taglineSmall" => "TEMPLE & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore temples, rivers, waterfalls and peaceful Western Ghats landscapes around Tirunelveli with smart budget planning.",

    "places" => [

        [
            "name" => "Tirunelveli",
            "image" => "",
            "tag" => "Heritage & Culture",
            "description" =>
                "Discover historic temples, local culture, markets and major attractions across Tirunelveli.",
            "link" => "stay-food.php?place=Tirunelveli"
        ],

        [
            "name" => "Papanasam",
            "image" => "",
            "tag" => "Waterfalls & Nature",
            "description" =>
                "Enjoy scenic river landscapes, waterfalls and peaceful hill surroundings around Papanasam.",
            "link" => "stay-food.php?place=Papanasam"
        ]

    ]
],


/* =================================================
   THOOTHUKUDI
================================================= */

"thoothukudi" => [

    "hero" => "",

    "eyebrow" => "DISCOVER THOOTHUKUDI",

    "taglineSmall" => "COASTAL & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover coastal landscapes, temples, heritage sites and peaceful beach experiences around Thoothukudi with smart budget planning.",

    "places" => [

        [
            "name" => "Thoothukudi",
            "image" => "",
            "tag" => "Coastal City",
            "description" =>
                "Explore beaches, churches, markets, heritage attractions and the coastal culture of Thoothukudi.",
            "link" => "stay-food.php?place=Thoothukudi"
        ],

        [
            "name" => "Thiruchendur",
            "image" => "",
            "tag" => "Temple & Beach",
            "description" =>
                "Experience the famous temple town, coastal views and peaceful surroundings of Thiruchendur.",
            "link" => "stay-food.php?place=Thiruchendur"
        ]

    ]
],


/* =================================================
   SIVAGANGA
================================================= */

"sivaganga" => [

    "hero" => "",

    "eyebrow" => "DISCOVER SIVAGANGA",

    "taglineSmall" => "HERITAGE & CULTURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore Chettinad architecture, heritage mansions, temples and traditional culture around Sivaganga with smart budget planning.",

    "places" => [

        [
            "name" => "Karaikudi",
            "image" => "",
            "tag" => "Chettinad Heritage",
            "description" =>
                "Discover Chettinad architecture, heritage mansions, local food and cultural attractions in Karaikudi.",
            "link" => "stay-food.php?place=Karaikudi"
        ],

        [
            "name" => "Kanadukathan",
            "image" => "",
            "tag" => "Heritage Village",
            "description" =>
                "Experience grand Chettinad mansions, traditional streets and the heritage character of Kanadukathan.",
            "link" => "stay-food.php?place=Kanadukathan"
        ]

    ]
],


/* =================================================
   VIRUDHUNAGAR
================================================= */

"virudhunagar" => [

    "hero" => "",

    "eyebrow" => "DISCOVER VIRUDHUNAGAR",

    "taglineSmall" => "TEMPLE & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover temples, hill landscapes, local culture and peaceful nature experiences around Virudhunagar with smart budget planning.",

    "places" => [

        [
            "name" => "Srivilliputhur",
            "image" => "",
            "tag" => "Temple & Heritage",
            "description" =>
                "Explore the historic temple town, traditional streets and cultural attractions of Srivilliputhur.",
            "link" => "stay-food.php?place=Srivilliputhur"
        ],

        [
            "name" => "Rajapalayam",
            "image" => "",
            "tag" => "Hills & Nature",
            "description" =>
                "Enjoy scenic views, local culture and access to the Western Ghats around Rajapalayam.",
            "link" => "stay-food.php?place=Rajapalayam"
        ]

    ]
],

/* =================================================
   ERODE
================================================= */

"erode" => [

    "hero" => "",

    "eyebrow" => "DISCOVER ERODE",

    "taglineSmall" => "HERITAGE & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore the heritage, temples, riverside landscapes and peaceful attractions around Erode with smart budget planning.",

    "places" => [

        [
            "name" => "Erode",
            "image" => "",
            "tag" => "City & Heritage",
            "description" =>
                "Discover markets, temples, heritage attractions and the cultural character of Erode.",
            "link" => "stay-food.php?place=Erode"
        ],

        [
            "name" => "Bhavani",
            "image" => "",
            "tag" => "Temple & River",
            "description" =>
                "Explore the sacred temples, river landscapes and peaceful surroundings of Bhavani.",
            "link" => "stay-food.php?place=Bhavani"
        ]

    ]
],


/* =================================================
   NAMAKKAL
================================================= */

"namakkal" => [

    "hero" => "",

    "eyebrow" => "DISCOVER NAMAKKAL",

    "taglineSmall" => "HILLS & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover temples, hill landscapes and peaceful natural escapes around Namakkal with smart budget planning.",

    "places" => [

        [
            "name" => "Namakkal",
            "image" => "",
            "tag" => "Heritage & Temple",
            "description" =>
                "Explore the famous hilltop temple, forts and cultural attractions across Namakkal.",
            "link" => "stay-food.php?place=Namakkal"
        ],

        [
            "name" => "Kolli Hills",
            "image" => "",
            "tag" => "Hills & Nature",
            "description" =>
                "Enjoy waterfalls, viewpoints, greenery and refreshing mountain landscapes in Kolli Hills.",
            "link" => "stay-food.php?place=Kolli%20Hills"
        ]

    ]
],


/* =================================================
   TIRUPPUR
================================================= */

"tiruppur" => [

    "hero" => "",

    "eyebrow" => "DISCOVER TIRUPPUR",

    "taglineSmall" => "CITY & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore the city attractions, temples and peaceful natural destinations around Tiruppur with smart budget planning.",

    "places" => [

        [
            "name" => "Tiruppur",
            "image" => "",
            "tag" => "City & Culture",
            "description" =>
                "Discover the vibrant city, temples, local markets and cultural attractions of Tiruppur.",
            "link" => "stay-food.php?place=Tiruppur"
        ],

        [
            "name" => "Udumalpet",
            "image" => "",
            "tag" => "Nature Escape",
            "description" =>
                "Experience scenic landscapes, greenery and peaceful natural surroundings around Udumalpet.",
            "link" => "stay-food.php?place=Udumalpet"
        ]

    ]
],


/* =================================================
   KARUR
================================================= */

"karur" => [

    "hero" => "",

    "eyebrow" => "DISCOVER KARUR",

    "taglineSmall" => "RIVER & HERITAGE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover temples, riverside attractions and cultural destinations around Karur with smart budget planning.",

    "places" => [

        [
            "name" => "Karur",
            "image" => "",
            "tag" => "Heritage & Culture",
            "description" =>
                "Explore historic temples, local markets and cultural attractions across Karur.",
            "link" => "stay-food.php?place=Karur"
        ],

        [
            "name" => "Mayanur",
            "image" => "",
            "tag" => "River & Nature",
            "description" =>
                "Enjoy peaceful river views, scenic surroundings and relaxing nature experiences at Mayanur.",
            "link" => "stay-food.php?place=Mayanur"
        ]

    ]
],


/* =================================================
   PUDUKKOTTAI
================================================= */

"pudukkottai" => [

    "hero" => "",

    "eyebrow" => "DISCOVER PUDUKKOTTAI",

    "taglineSmall" => "HERITAGE & HISTORY",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore historic monuments, temples, palaces and peaceful heritage destinations around Pudukkottai.",

    "places" => [

        [
            "name" => "Pudukkottai",
            "image" => "",
            "tag" => "Heritage & Culture",
            "description" =>
                "Discover historic sites, museums, temples and cultural attractions across Pudukkottai.",
            "link" => "stay-food.php?place=Pudukkottai"
        ],

        [
            "name" => "Thirumayam",
            "image" => "",
            "tag" => "Fort & Heritage",
            "description" =>
                "Explore historic forts, temples and beautiful heritage surroundings around Thirumayam.",
            "link" => "stay-food.php?place=Thirumayam"
        ]

    ]
],


/* =================================================
   TENKASI
================================================= */

"tenkasi" => [

    "hero" => "",

    "eyebrow" => "DISCOVER TENKASI",

    "taglineSmall" => "WATERFALLS & HILLS",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover waterfalls, temples, hills and refreshing natural landscapes around Tenkasi with smart budget planning.",

    "places" => [

        [
            "name" => "Tenkasi",
            "image" => "",
            "tag" => "Temple & Culture",
            "description" =>
                "Explore historic temples, local attractions and the cultural beauty of Tenkasi.",
            "link" => "stay-food.php?place=Tenkasi"
        ],

        [
            "name" => "Courtallam",
            "image" => "",
            "tag" => "Waterfalls & Nature",
            "description" =>
                "Enjoy famous waterfalls, greenery and refreshing mountain surroundings at Courtallam.",
            "link" => "stay-food.php?place=Courtallam"
        ]

    ]
],


/* =================================================
   THENI
================================================= */

"theni" => [

    "hero" => "",

    "eyebrow" => "DISCOVER THENI",

    "taglineSmall" => "HILLS & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore scenic hills, waterfalls, plantations and peaceful nature destinations around Theni.",

    "places" => [

        [
            "name" => "Theni",
            "image" => "",
            "tag" => "Nature & Culture",
            "description" =>
                "Discover local markets, temples, scenic surroundings and attractions across Theni.",
            "link" => "stay-food.php?place=Theni"
        ],

        [
            "name" => "Meghamalai",
            "image" => "",
            "tag" => "Misty Hills",
            "description" =>
                "Experience misty hills, tea plantations, forests and peaceful mountain landscapes in Meghamalai.",
            "link" => "stay-food.php?place=Meghamalai"
        ]

    ]
],


/* =================================================
   DHARMAPURI
================================================= */

"dharmapuri" => [

    "hero" => "",

    "eyebrow" => "DISCOVER DHARMAPURI",

    "taglineSmall" => "WATERFALLS & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Discover waterfalls, riverside landscapes and peaceful natural escapes around Dharmapuri.",

    "places" => [

        [
            "name" => "Dharmapuri",
            "image" => "",
            "tag" => "Culture & Nature",
            "description" =>
                "Explore temples, local attractions and the scenic surroundings of Dharmapuri.",
            "link" => "stay-food.php?place=Dharmapuri"
        ],

        [
            "name" => "Hogenakkal",
            "image" => "",
            "tag" => "Waterfalls & River",
            "description" =>
                "Experience spectacular waterfalls, river landscapes and natural beauty at Hogenakkal.",
            "link" => "stay-food.php?place=Hogenakkal"
        ]

    ]
],


/* =================================================
   KRISHNAGIRI
================================================= */

"krishnagiri" => [

    "hero" => "",

    "eyebrow" => "DISCOVER KRISHNAGIRI",

    "taglineSmall" => "FORTS & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore forts, hills, lakes and peaceful natural destinations around Krishnagiri with smart budget planning.",

    "places" => [

        [
            "name" => "Krishnagiri",
            "image" => "",
            "tag" => "Heritage & Nature",
            "description" =>
                "Discover the fort, dam, lakes and scenic attractions across Krishnagiri.",
            "link" => "stay-food.php?place=Krishnagiri"
        ],

        [
            "name" => "Hosur",
            "image" => "",
            "tag" => "City & Nature",
            "description" =>
                "Enjoy parks, lakes, temples and peaceful green spaces around Hosur.",
            "link" => "stay-food.php?place=Hosur"
        ]

    ]
],


/* =================================================
   KALLAKURICHI
================================================= */

"kallakurichi" => [

    "hero" => "",

    "eyebrow" => "DISCOVER KALLAKURICHI",

    "taglineSmall" => "HILLS & NATURE",

    "taglineMain" => "More Experience.",

    "description" =>
        "Explore scenic hills, waterfalls, forests and peaceful natural escapes around Kallakurichi.",

    "places" => [

        [
            "name" => "Kallakurichi",
            "image" => "",
            "tag" => "Culture & Nature",
            "description" =>
                "Discover temples, local attractions and peaceful surroundings across Kallakurichi.",
            "link" => "stay-food.php?place=Kallakurichi"
        ],

        [
            "name" => "Kalvarayan Hills",
            "image" => "",
            "tag" => "Hills & Forest",
            "description" =>
                "Experience scenic hills, waterfalls, forests and peaceful nature around Kalvarayan Hills.",
            "link" => "stay-food.php?place=Kalvarayan%20Hills"
        ]

    ]
],
];


/* =====================================================
   FALLBACK
===================================================== */

if (!isset($destinationSettings[$destinationKey])) {
    $destinationKey = "dindigul";
}

$currentDestination = $destinationSettings[$destinationKey];

$heroImage = $currentDestination["hero"];
$eyebrow = $currentDestination["eyebrow"];
$taglineSmall = $currentDestination["taglineSmall"];
$taglineMain = $currentDestination["taglineMain"];
$description = $currentDestination["description"];
$places = $currentDestination["places"];

/* Display selected district name */
$destination = htmlspecialchars(
    ucfirst($destinationKey)
);

/* =====================================================
   PEXELS IMAGES FOR DESTINATION CARDS
===================================================== */

$placeQueries = [

    "Kodaikanal" =>
        "Kodaikanal Tamil Nadu hills lake tourism",

    "Palani" =>
        "Palani Tamil Nadu hill temple tourism",

    "Sirumalai" =>
        "Sirumalai Dindigul Tamil Nadu hill nature tourism",

    "Dindigul Fort" =>
        "Dindigul Fort Tamil Nadu heritage tourism",

    "Madurai" =>
        "Madurai Tamil Nadu temple heritage tourism",

    "Thirupparankundram" =>
        "Thirupparankundram Madurai Tamil Nadu temple tourism",

    "Coimbatore" =>
        "Coimbatore Tamil Nadu city nature tourism",

    "Marudamalai" =>
        "Marudamalai Coimbatore Tamil Nadu temple hills tourism",
    "Valparai" => 
        "Valparai Coimbatore Tamil Nadu hills tea estates waterfalls tourism",

     "Kanyakumari" =>
            "Kanyakumari Tamil Nadu beach tourism scenic",

        "Padmanabhapuram Palace" =>
            "Padmanabhapuram Palace Tamil Nadu heritage tourism",

        "Ooty" =>
            "Ooty Tamil Nadu hill station tourism tea gardens",

        "Coonoor" =>
            "Coonoor Tamil Nadu hill station tea gardens tourism",

        "Kotagiri" =>
            "Kotagiri Tamil Nadu hills tea estate tourism",

        "Yercaud" =>
            "Yercaud Salem Tamil Nadu hill station tourism",

        "Mettur" =>
            "Mettur Salem Tamil Nadu dam tourism",

        "Thanjavur" =>
            "Thanjavur Tamil Nadu heritage temple tourism",

        "Kumbakonam" =>
            "Kumbakonam Tamil Nadu temple heritage tourism",

        "Rameswaram" =>
            "Rameswaram Tamil Nadu temple coastal tourism",

        "Dhanushkodi" =>
            "Dhanushkodi Tamil Nadu coastal tourism",      


    "Erode" =>
        "Erode Tamil Nadu city heritage tourism",

    "Bhavani" =>
        "Bhavani Erode Tamil Nadu temple river tourism",

    "Namakkal" =>
        "Namakkal Tamil Nadu temple fort tourism",

    "Kolli Hills" =>
        "Kolli Hills Namakkal Tamil Nadu hills waterfalls tourism",

    "Tiruppur" =>
        "Tiruppur Tamil Nadu city tourism",

    "Udumalpet" =>
        "Udumalpet Tamil Nadu nature tourism",

    "Karur" =>
        "Karur Tamil Nadu temple river tourism",

    "Mayanur" =>
        "Mayanur Karur Tamil Nadu river tourism",

    "Pudukkottai" =>
        "Pudukkottai Tamil Nadu heritage tourism",

    "Thirumayam" =>
        "Thirumayam Pudukkottai Tamil Nadu fort temple tourism",

    "Tenkasi" =>
        "Tenkasi Tamil Nadu temple tourism",

    "Courtallam" =>
        "Courtallam Tamil Nadu waterfalls tourism",

    "Theni" =>
        "Theni Tamil Nadu nature tourism",

    "Meghamalai" =>
        "Meghamalai Theni Tamil Nadu hills tea plantations tourism",

    "Dharmapuri" =>
        "Dharmapuri Tamil Nadu tourism",

    "Hogenakkal" =>
        "Hogenakkal Dharmapuri Tamil Nadu waterfalls tourism",

    "Krishnagiri" =>
        "Krishnagiri Tamil Nadu fort dam tourism",

    "Hosur" =>
        "Hosur Tamil Nadu parks lakes tourism",

    "Kallakurichi" =>
        "Kallakurichi Tamil Nadu tourism",

    "Kalvarayan Hills" =>
        "Kalvarayan Hills Kallakurichi Tamil Nadu hills waterfalls tourism",
];

/* =====================================================
   FETCH API IMAGE FOR EACH CARD
===================================================== */

foreach ($places as $index => $place) {

    $placeName = $place["name"];

    $query = $placeQueries[$placeName]
        ?? ($placeName . " Tamil Nadu tourism");

    $photo = searchPexelsPhoto($query);


    /*
       Use Pexels image if available.
       Otherwise keep existing local image.
    */

    if (
        $photo &&
        isset($photo["src"]["large2x"])
    ) {

        $places[$index]["image"] =
            $photo["src"]["large2x"];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo $destination; ?> | Smart Budget Trip Planner
    </title>

    <!-- Google Font -->
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Manrope:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Font Awesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="destination.css">

</head>


<body>


<!-- =====================================================
     NAVBAR
===================================================== -->

<header class="navbar">

    <a href="dashboard.php" class="brand">

        <span class="brand-mark">
            <i class="fa-solid fa-route"></i>
        </span>

        <span class="brand-copy">
            <strong>Smart Budget</strong>
            <small>Trip Planner</small>
        </span>

    </a>


    <nav class="nav-links">

        <a href="dashboard.php">
            <i class="fa-solid fa-house"></i>
            Dashboard
        </a>

        <a href="plan-trip.php" class="nav-new">
            <i class="fa-solid fa-plus"></i>
            New Trip
        </a>

    </nav>

</header>



<!-- =====================================================
     HERO
===================================================== -->

<section
    class="hero"
    style="background-image:url('<?php echo htmlspecialchars($heroImage); ?>');"
>

    <div class="hero-image-overlay"></div>
    <div class="hero-glow glow-one"></div>
    <div class="hero-glow glow-two"></div>


    <div class="hero-inner">

        <div class="hero-content">

            <span class="eyebrow">
                <i class="fa-solid fa-sparkles"></i>
                <?php echo $eyebrow; ?>
            </span>


            <div class="hero-tagline">

                <span class="tagline-small">
                    <?php echo $taglineSmall; ?>
                </span>

                <h1>
                    <?php echo $taglineMain; ?>
                </h1>

                <div class="destination-name">
                    <?php echo $destination; ?>
                </div>

            </div>


            <p class="hero-description">
                <?php echo $description; ?>
            </p>


            <!-- TRIP SNAPSHOT -->

            <div class="trip-snapshot">

                <div class="snapshot-card">

                    <span class="snapshot-icon">
                        <i class="fa-solid fa-wallet"></i>
                    </span>

                    <span class="snapshot-text">
                        <small>YOUR BUDGET</small>
                        <strong>₹ <?php echo $budget; ?></strong>
                    </span>

                </div>


                <div class="snapshot-card">

                    <span class="snapshot-icon">
                        <i class="fa-solid fa-users"></i>
                    </span>

                    <span class="snapshot-text">
                        <small>TRAVELERS</small>
                        <strong><?php echo $members; ?> Persons</strong>
                    </span>

                </div>


                <div class="snapshot-card">

                    <span class="snapshot-icon">
                        <i class="fa-regular fa-calendar"></i>
                    </span>

                    <span class="snapshot-text">
                        <small>DURATION</small>
                        <strong><?php echo $days; ?> Days</strong>
                    </span>

                </div>

            </div>


            <!-- HERO ACTION -->

            <div class="hero-actions">

                <a href="plan-trip.php" class="edit-plan-btn">

                    <span class="btn-icon">
                        <i class="fa-solid fa-pen"></i>
                    </span>

                    Edit Plan

                    <i class="fa-solid fa-arrow-right btn-arrow"></i>

                </a>

                <span class="hero-note">
                    Personalized around your trip
                </span>

            </div>

        </div>


        <!-- HERO SIDE DETAIL -->

        <div class="hero-side-card">

            <span class="side-label">
                YOUR TRIP STYLE
            </span>

            <h3>
                <?php echo $tripType; ?>
            </h3>

            <div class="side-line"></div>

            <div class="side-row">
                <span>Transport</span>
                <strong><?php echo $transport; ?></strong>
            </div>

            <div class="side-row">
                <span>Stay</span>
                <strong><?php echo $stay; ?></strong>
            </div>

            <div class="side-row">
                <span>Food</span>
                <strong><?php echo $food; ?></strong>
            </div>

        </div>

    </div>


    <!-- HERO BOTTOM CURVE -->

    <div class="hero-bottom"></div>

</section>



<!-- =====================================================
     RECOMMENDED PLACES
===================================================== -->

<main>

<section class="recommend-section">

    <div class="section-top">

        <div>

            <span class="section-kicker">
                <i class="fa-solid fa-compass"></i>
                HANDPICKED FOR YOU
            </span>

            <h2>
                Start Your <em><?php echo $destination; ?></em> Journey
            </h2>

            <p>
                Explore the destinations that can shape your trip.
                More places and smart recommendations will be added here.
            </p>

        </div>


        <div class="section-count">
            <strong><?php echo str_pad(count($places), 2, "0", STR_PAD_LEFT); ?></strong>
            <span>Featured<br>Destinations</span>
        </div>

    </div>


    <!-- PLACE GRID -->

    <div class="places-grid">

        <?php foreach ($places as $index => $place): ?>

            <a
                href="<?php echo $place["link"]; ?>"
                class="place-card"
            >

                <div class="place-image">

                    <img
                        src="<?php echo htmlspecialchars($place["image"]); ?>"
                        alt="<?php echo htmlspecialchars($place["name"]); ?>"
                    >

                    <span class="place-tag">
                        <?php echo htmlspecialchars($place["tag"]); ?>
                    </span>

                    <span class="place-number">
                        0<?php echo $index + 1; ?>
                    </span>

                    <span class="image-shine"></span>

                </div>


                <div class="place-body">

                    <span class="match-pill">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        Smart Match
                    </span>

                    <h3>
                        <?php echo htmlspecialchars($place["name"]); ?>
                    </h3>

                    <p>
                        <?php echo htmlspecialchars($place["description"]); ?>
                    </p>

                    <span class="explore-btn">
                        Explore
                        <i class="fa-solid fa-arrow-right"></i>
                    </span>

                </div>

            </a>

        <?php endforeach; ?>

    </div>

</section>



<!-- =====================================================
     SMART RECOMMENDATION STRIP
===================================================== -->

<section class="smart-strip">

    <div class="smart-glow"></div>

    <div class="smart-icon">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
    </div>

    <div class="smart-content">

        <span>
            COMING NEXT
        </span>

        <h2>
            Your trip, intelligently arranged.
        </h2>

        <p>
            Based on your budget, days, travelers, transport and food,
            we'll recommend the places that fit your journey best.
        </p>

    </div>


    <div class="smart-badge">
        <i class="fa-solid fa-leaf"></i>
        Smart Planning
    </div>

</section>

</main>



<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <strong>Smart Budget Trip Planner</strong>

    <span>•</span>

    <small>
        Less Expense. More Experience.
    </small>

</footer>


</body>
</html>