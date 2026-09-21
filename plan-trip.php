<?php

session_start();
include "db.php";
require_once "ors-config.php";

date_default_timezone_set("Asia/Kolkata");


/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$username = isset($_SESSION["username"])
    ? htmlspecialchars($_SESSION["username"])
    : "Traveler";


/* =====================================================
   HELPER: GET LOCATION COORDINATES FROM MYSQL
===================================================== */

function getLocationCoordinates(
    mysqli $conn,
    string $locationName
): ?array {

    $sql = "SELECT latitude, longitude
            FROM locations
            WHERE location_name = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $locationName);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $stmt->close();
        return null;
    }

    $row = $result->fetch_assoc();

    $stmt->close();

    return [
        "latitude" => (float) $row["latitude"],
        "longitude" => (float) $row["longitude"]
    ];
}


/* =====================================================
   HELPER: OPENROUTESERVICE ROUTE
===================================================== */

function getORSRoute(
    array $start,
    array $end,
    string $profile,
    string $apiKey
): ?array {

    $url =
        "https://api.heigit.org/" .
        "openrouteservice/" .
        "v2/directions/" .
        urlencode($profile);

    /*
       ORS expects:
       [longitude, latitude]
    */

    $requestData = [
        "coordinates" => [
            [
                $start["longitude"],
                $start["latitude"]
            ],
            [
                $end["longitude"],
                $end["latitude"]
            ]
        ]
    ];

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 30,

        CURLOPT_HTTPHEADER => [
            "Authorization: " . $apiKey,
            "Content-Type: application/json",
            "Accept: application/json"
        ],

        CURLOPT_POSTFIELDS =>
            json_encode($requestData)
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);
        return null;
    }

    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }

    $data =
        json_decode(
            $response,
            true
        );

    if (
        !isset(
            $data["routes"][0]["summary"]
        )
    ) {
        return null;
    }

    return $data["routes"][0]["summary"];
}


/* =====================================================
   FORM SUBMIT
===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    

    /* =================================================
       GET FORM VALUES
    ================================================= */

    $district = trim(
        $_POST["district"] ?? ""
    );

    $startingLocation = trim(
        $_POST["starting_location"] ?? ""
    );

    $persons = (int) (
        $_POST["persons"] ?? 0
    );

    $budget = (float) (
        $_POST["budget"] ?? 0
    );

    $days = (int) (
        $_POST["days"] ?? 0
    );

    $travelDate = trim(
        $_POST["travelDate"] ?? ""
    );

    $transport = trim(
        $_POST["transport"] ?? ""
    );

    $departureTime = trim(
        $_POST["departure_time"] ?? ""
    );

    $selectedService = trim(
        $_POST["selected_service"] ?? ""
    );

    $arrivalTime = trim(
        $_POST["arrival_time"] ?? ""
    );

    $stay = trim(
        $_POST["stay"] ?? ""
    );

    $food = trim(
        $_POST["food"] ?? ""
    );

    $tripType = trim(
        $_POST["trip_type"] ?? ""
    );


    /* =================================================
       VALIDATION
    ================================================= */

    if (
        $district === "" ||
        $startingLocation === "" ||
        $persons <= 0 ||
        $budget <= 0 ||
        $days <= 0 ||
        $travelDate === "" ||
        $transport === "" ||
        $stay === "" ||
        $food === "" ||
        $tripType === ""
    ) {

        $error =
            "Please complete all trip details.";
    }


    /* =================================================
       CAR / BIKE DEPARTURE TIME
    ================================================= */

    if (
        !isset($error) &&
        (
            $transport === "Car" ||
            $transport === "Bike"
        )
    ) {

        if ($departureTime === "") {

            $error =
                "Please select your departure time.";
        }
    }


    /* =================================================
       DATE VALIDATION
    ================================================= */

    if (!isset($error)) {

        try {

            $startDate =
                new DateTime(
                    $travelDate
                );

            $endDate =
                clone $startDate;

            $endDate->modify(
                "+" . ($days - 1) . " days"
            );

            $endDateValue =
                $endDate->format("Y-m-d");

        } catch (Exception $e) {

            $error =
                "Invalid travel date.";
        }
    }


    /* =================================================
       ROUTE DESTINATION
    ================================================= */

    /*
       For our first Kodaikanal model:
       User selects Dindigul district,
       but route destination is Kodaikanal.
    */

    $routeDestination = $district;

    if (
        strtolower($district)
        === "dindigul"
    ) {

        $routeDestination =
            "Kodaikanal";
    }


    /* =================================================
       CALCULATE CAR / BIKE ROUTE
    ================================================= */

    $routeDistanceKm = null;

    $routeDurationMinutes = null;


    if (
        !isset($error) &&
        (
            $transport === "Car" ||
            $transport === "Bike"
        )
    ) {


        /* ---------------------------------------------
           GET START COORDINATES
        --------------------------------------------- */

        $startCoordinates =
            getLocationCoordinates(
                $conn,
                $startingLocation
            );


        if (!$startCoordinates) {

            $error =
                "Starting location '" .
                htmlspecialchars(
                    $startingLocation
                ) .
                "' is not available yet. " .
                "Please use a location added to our system.";

        }


        /* ---------------------------------------------
           GET DESTINATION COORDINATES
        --------------------------------------------- */

        if (!isset($error)) {

            $endCoordinates =
                getLocationCoordinates(
                    $conn,
                    $routeDestination
                );


            if (!$endCoordinates) {

                $error =
                    "Route destination '" .
                    htmlspecialchars(
                        $routeDestination
                    ) .
                    "' is not available yet.";

            }
        }


        /* ---------------------------------------------
           CALL ORS
        --------------------------------------------- */

        if (!isset($error)) {


            /*
               Car:
               driving-car

               Bike:
               We are using the road route profile
               temporarily for the first working model.
               A separate motorcycle routing profile
               will be reviewed later.
            */

            $profile = "driving-car";


            $routeSummary =
                getORSRoute(
                    $startCoordinates,
                    $endCoordinates,
                    $profile,
                    $ORS_API_KEY
                );


            if (!$routeSummary) {

                $error =
                    "Unable to calculate the route right now. " .
                    "Please try again.";

            } else {

                $routeDistanceKm =
                    round(
                        $routeSummary["distance"] / 1000,
                        2
                    );


                $routeDurationMinutes =
                    (int) round(
                        $routeSummary["duration"] / 60
                    );


                /* -----------------------------------------
                   CALCULATE ARRIVAL TIME
                ----------------------------------------- */

                try {

                    $departureDateTime =
                        new DateTime(
                            $travelDate .
                            " " .
                            $departureTime
                        );


                    $arrivalDateTime =
                        clone $departureDateTime;


                    $arrivalDateTime->modify(
                        "+" .
                        $routeDurationMinutes .
                        " minutes"
                    );


                    $arrivalTime =
                        $arrivalDateTime->format(
                            "H:i:s"
                        );

                } catch (Exception $e) {

                    $error =
                        "Unable to calculate arrival time.";

                }


                /*
                   Save route summary as service
                   for now.
                */

                if (!isset($error)) {

                    $selectedService =
                        $routeDistanceKm .
                        " km route / " .
                        $routeDurationMinutes .
                        " min";
                }

            }
        }
    }


    /* =================================================
       BUS / TRAIN
    ================================================= */

    /*
       We DO NOT create fake timings.
       Real transit API will be connected later.

       Until a service is selected:
       departure_time and arrival_time remain NULL.
    */

    if (
        !isset($error) &&
        (
            $transport === "Bus" ||
            $transport === "Train"
        )
    ) {

        $departureTime = "";
        $arrivalTime = "";
        $selectedService = "";

    }


    /* =================================================
       INSERT INTO TRIPS
    ================================================= */

    if (!isset($error)) {

        $sql = "INSERT INTO trips
                (
                    user_id,
                    destination,
                    starting_location,
                    start_date,
                    departure_time,
                    arrival_time,
                    selected_service,
                    end_date,
                    days,
                    members,
                    total_budget,
                    transport,
                    stay,
                    food,
                    trip_type,
                    status
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    NULLIF(?, ''),
                    NULLIF(?, ''),
                    NULLIF(?, ''),
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?
                )";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $status =
                "Planned";


            /*
               Types:
               i = user_id
               7 s = destination -> end_date
               ii = days, persons
               d = budget
               5 s = transport -> status
            */

            $stmt->bind_param(
                "isssssssiidsssss",
                $user_id,
                $district,
                $startingLocation,
                $travelDate,
                $departureTime,
                $arrivalTime,
                $selectedService,
                $endDateValue,
                $days,
                $persons,
                $budget,
                $transport,
                $stay,
                $food,
                $tripType,
                $status
            );


            if ($stmt->execute()) {

                $stmt->close();

                header(
                    "Location: destination.php"
                );

                exit();

            } else {

                $error =
                    "Unable to save your trip. " .
                    "Database Error: " .
                    $stmt->error;

                $stmt->close();

            }

        }

    }

}

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
        Plan Your Trip | Smart Budget Trip Planner
    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >


    <link
        rel="stylesheet"
        href="plan-trip.css"
    >

</head>


<body>


<div class="page-background"></div>

<div class="background-overlay"></div>



<header class="top-header">

    <div class="logo">

        <div class="logo-circle">

            <i
                class="fa-solid fa-route"
            ></i>

        </div>


        <div class="logo-text">

            <h2>
                Smart Budget
            </h2>

            <span>
                Trip Planner
            </span>

        </div>

    </div>

</header>



<main class="planner-wrapper">


    <!-- =================================================
         INTRO
    ================================================== -->

    <section class="intro-section">


        <div class="intro-badge">

            <i
                class="fa-regular fa-compass"
            ></i>

            PLAN LESS. TRAVEL MORE.

        </div>


        <h1>

            Plan Your

            <span>
                Perfect Trip
            </span>

        </h1>


        <p class="intro-description">

            Tell us your preferences and we'll create
            the best budget-friendly itinerary for you.

        </p>


        <div class="feature-list">


            <div class="feature-item">

                <div class="feature-icon">

                    <i
                        class="fa-solid fa-location-dot"
                    ></i>

                </div>


                <div>

                    <h3>
                        Explore Best Places
                    </h3>

                    <p>
                        Handpicked destinations in Tamil Nadu
                    </p>

                </div>

            </div>


            <div class="feature-item">

                <div class="feature-icon">

                    <i
                        class="fa-solid fa-wallet"
                    ></i>

                </div>


                <div>

                    <h3>
                        Stay Within Budget
                    </h3>

                    <p>
                        Smart planning for your budget
                    </p>

                </div>

            </div>


            <div class="feature-item">

                <div class="feature-icon">

                    <i
                        class="fa-solid fa-route"
                    ></i>

                </div>


                <div>

                    <h3>
                        Smart Travel Planning
                    </h3>

                    <p>
                        Plan, calculate and enjoy your journey
                    </p>

                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         FORM
    ================================================== -->

    <section class="glass-form">


        <div class="form-header">

            <div class="form-title-icon">

                <i
                    class="fa-solid fa-map-location-dot"
                ></i>

            </div>


            <div>

                <h2>
                    Plan Your Trip
                </h2>

                <p>
                    Fill in your trip details to get started
                </p>

            </div>

        </div>



        <?php if (isset($error)): ?>

            <div
                style="
                    background: rgba(255,80,80,.15);
                    border: 1px solid rgba(255,100,100,.4);
                    color: #ffd0d0;
                    padding: 12px 15px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    font-size: 12px;
                "
            >

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>



        <form
            id="tripForm"
            method="POST"
            action="plan-trip.php"
        >


            <!-- =================================================
                 DESTINATION + STARTING LOCATION
            ================================================== -->

            <div class="form-grid">


                <div class="input-box">

                    <label>

                        <i
                            class="fa-solid fa-location-dot"
                        ></i>

                        Select District

                    </label>


                    <div class="input-wrapper">

                        <input
                            type="text"
                            id="district"
                            name="district"
                            list="districtList"
                            placeholder="Search your destination..."
                            required
                        >

                        <i
                            class="fa-solid fa-chevron-down"
                        ></i>

                    </div>


                    <datalist id="districtList">

                        <option value="Ariyalur">
                        <option value="Chengalpattu">
                        <option value="Chennai">
                        <option value="Coimbatore">
                        <option value="Cuddalore">
                        <option value="Dharmapuri">

                        <option value="Dindigul">

                        <option value="Erode">
                        <option value="Kallakurichi">
                        <option value="Kancheepuram">
                        <option value="Kanyakumari">
                        <option value="Karur">
                        <option value="Krishnagiri">
                        <option value="Madurai">
                        <option value="Mayiladuthurai">
                        <option value="Nagapattinam">
                        <option value="Namakkal">
                        <option value="Nilgiris">
                        <option value="Perambalur">
                        <option value="Pudukkottai">
                        <option value="Ramanathapuram">
                        <option value="Ranipet">
                        <option value="Salem">
                        <option value="Sivagangai">
                        <option value="Tenkasi">
                        <option value="Thanjavur">
                        <option value="Theni">
                        <option value="Thoothukudi">
                        <option value="Tiruchirappalli">
                        <option value="Tirunelveli">
                        <option value="Tirupathur">
                        <option value="Tiruppur">
                        <option value="Tiruvallur">
                        <option value="Tiruvannamalai">
                        <option value="Tiruvarur">
                        <option value="Vellore">
                        <option value="Viluppuram">
                        <option value="Virudhunagar">

                    </datalist>

                </div>

                <div class="input-box">

    <label>
        <i class="fa-solid fa-location-crosshairs"></i>
        Starting Location
    </label>

    <div class="input-wrapper">

        <input
            type="text"
            id="starting_location"
            name="starting_location"
            list="startingLocationList"
            placeholder="Example: Chennai"
            autocomplete="off"
            required
        >

    </div>

    <datalist id="startingLocationList">

        <option value="Ariyalur">
        <option value="Chengalpattu">
        <option value="Chennai">
        <option value="Coimbatore">
        <option value="Cuddalore">
        <option value="Dharmapuri">
        <option value="Dindigul">
        <option value="Erode">
        <option value="Kallakurichi">
        <option value="Kancheepuram">
        <option value="Kanyakumari">
        <option value="Karur">
        <option value="Krishnagiri">
        <option value="Madurai">
        <option value="Mayiladuthurai">
        <option value="Nagapattinam">
        <option value="Namakkal">
        <option value="Nilgiris">
        <option value="Perambalur">
        <option value="Pudukkottai">
        <option value="Ramanathapuram">
        <option value="Ranipet">
        <option value="Salem">
        <option value="Sivagangai">
        <option value="Tenkasi">
        <option value="Thanjavur">
        <option value="Theni">
        <option value="Thoothukudi">
        <option value="Tiruchirappalli">
        <option value="Tirunelveli">
        <option value="Tirupathur">
        <option value="Tiruppur">
        <option value="Tiruvallur">
        <option value="Tiruvannamalai">
        <option value="Tiruvarur">
        <option value="Vellore">
        <option value="Viluppuram">
        <option value="Virudhunagar">

    </datalist>

</div>

        <!-- =================================================
                 PERSONS + BUDGET
            ================================================== -->

            <div class="form-grid">


                <div class="input-box">

                    <label>

                        <i
                            class="fa-solid fa-users"
                        ></i>

                        Number of Persons

                    </label>


                    <div class="input-wrapper">

                        <input
                            type="number"
                            id="persons"
                            name="persons"
                            min="1"
                            placeholder="How many people?"
                            required
                        >

                    </div>

                </div>



                <div
                    class="
                    input-box
                    budget-section
                    "
                >

                    <label>

                        <i
                            class="fa-solid fa-wallet"
                        ></i>

                        Travel Budget

                        <strong id="budgetValue">
                            ₹ 5,000
                        </strong>

                    </label>


                    <div
                        class="amount-input-wrapper"
                    >

                        <input
                            type="number"
                            id="budgetInput"
                            name="budget"
                            value="5000"
                            min="1000"
                            max="50000"
                            step="500"
                            placeholder="Enter your budget"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <input
                        type="range"
                        id="budgetSlider"
                        min="1000"
                        max="50000"
                        step="500"
                        value="5000"
                    >


                    <div class="budget-range">

                        <span>
                            ₹1,000
                        </span>

                        <span>
                            ₹50,000
                        </span>

                    </div>


                    <div
                        id="budgetLabel"
                        class="budget-label low"
                    >
                        🟢 Low Budget
                    </div>

                </div>

            </div>



            <!-- =================================================
                 DAYS + DATE
            ================================================== -->

            <div class="form-grid">


                <div class="input-box">

                    <label>

                        <i
                            class="
                            fa-regular
                            fa-calendar
                            "
                        ></i>

                        Number of Days

                    </label>


                    <div
                        class="
                        input-wrapper
                        select-wrapper
                        "
                    >

                        <select
                            id="days"
                            name="days"
                            required
                        >

                            <option
                                value=""
                                selected
                                disabled
                            >
                                Select duration
                            </option>

                            <option value="1">
                                1 Day
                            </option>

                            <option value="2">
                                2 Days
                            </option>

                            <option value="3">
                                3 Days
                            </option>

                            <option value="4">
                                4 Days
                            </option>

                            <option value="5">
                                5 Days
                            </option>

                            <option value="6">
                                6 Days
                            </option>

                            <option value="7">
                                7 Days
                            </option>

                        </select>


                        <i
                            class="
                            fa-solid
                            fa-chevron-down
                            "
                        ></i>

                    </div>

                </div>



                <div class="input-box">

                    <label>

                        <i
                            class="
                            fa-regular
                            fa-calendar-days
                            "
                        ></i>

                        Journey Starts On

                    </label>


                    <div class="input-wrapper">

                        <input
                            type="date"
                            id="travelDate"
                            name="travelDate"
                            required
                        >

                    </div>

                </div>

            </div>



            <!-- =================================================
                 TRANSPORT
            ================================================== -->

            <div class="input-box">

                <label>

                    <i
                        class="
                        fa-solid
                        fa-route
                        "
                    ></i>

                    Transport Mode

                </label>


                <div
                    class="
                    choice-grid
                    transport-choice
                    "
                >


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="transport"
                            value="Bus"
                            required
                        >

                        <i
                            class="
                            fa-solid
                            fa-bus
                            "
                        ></i>

                        <span>
                            Bus
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="transport"
                            value="Train"
                        >

                        <i
                            class="
                            fa-solid
                            fa-train
                            "
                        ></i>

                        <span>
                            Train
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="transport"
                            value="Car"
                        >

                        <i
                            class="
                            fa-solid
                            fa-car
                            "
                        ></i>

                        <span>
                            Car
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="transport"
                            value="Bike"
                        >

                        <i
                            class="
                            fa-solid
                            fa-motorcycle
                            "
                        ></i>

                        <span>
                            Bike
                        </span>

                    </label>

                </div>

            </div>



            <!-- =================================================
                 TRANSPORT DETAILS
            ================================================== -->

            <div
                id="transportExtra"
                style="display:none;"
            >


                <!-- CAR / BIKE -->

                <div
                    id="departureBox"
                    class="input-box"
                    style="display:none;"
                >

                    <label>

                        <i
                            class="
                            fa-regular
                            fa-clock
                            "
                        ></i>

                        Departure Time

                    </label>


                    <div class="input-wrapper">

                        <input
                            type="time"
                            id="departure_time"
                            name="departure_time"
                        >

                    </div>


                    <small
                        style="
                            display:block;
                            margin-top:7px;
                            color:#718778;
                            font-size:10px;
                        "
                    >

                        Your arrival time will be calculated automatically.

                    </small>

                </div>



                <!-- BUS / TRAIN -->

                <div
                    id="liveOptionsBox"
                    class="input-box"
                    style="display:none;"
                >

                    <label>

                        <i
                            class="
                            fa-solid
                            fa-tower-broadcast
                            "
                        ></i>

                        Live Transport Options

                    </label>


                    <small
                        id="liveOptionsText"
                        style="
                            display:block;
                            margin-top:5px;
                            color:#718778;
                            font-size:10px;
                        "
                    >

                        Live service lookup will be connected here.

                    </small>


                    <button
                        type="button"
                        id="checkLiveOptions"
                        class="live-option-btn"
                    >

                        Check Live Options

                    </button>


                    <div
                        id="liveResults"
                        style="
                            display:none;
                            margin-top:14px;
                        "
                    ></div>


                    <input
                        type="hidden"
                        id="selected_service"
                        name="selected_service"
                        value=""
                    >


                    <input
                        type="hidden"
                        id="arrival_time"
                        name="arrival_time"
                        value=""
                    >

                </div>

            </div>



            <!-- =================================================
                 STAY + FOOD
            ================================================== -->

            <div class="form-grid">


                <div class="input-box">

                    <label>

                        <i
                            class="
                            fa-solid
                            fa-bed
                            "
                        ></i>

                        Stay Preference

                    </label>


                    <div
                        class="
                        input-wrapper
                        select-wrapper
                        "
                    >

                        <select
                            id="stay"
                            name="stay"
                            required
                        >

                            <option
                                value=""
                                selected
                                disabled
                            >
                                Choose stay type
                            </option>

                            <option value="Hotel">
                                Hotel
                            </option>

                            <option value="Resort">
                                Resort
                            </option>

                            <option value="Homestay">
                                Homestay
                            </option>

                            <option value="No Stay">
                                No Stay
                            </option>

                        </select>


                        <i
                            class="
                            fa-solid
                            fa-chevron-down
                            "
                        ></i>

                    </div>

                </div>



                <div class="input-box">

                    <label>

                        <i
                            class="
                            fa-solid
                            fa-utensils
                            "
                        ></i>

                        Food Preference

                    </label>


                    <div
                        class="
                        choice-grid
                        food-choice
                        "
                    >


                        <label class="choice-card">

                            <input
                                type="radio"
                                name="food"
                                value="Veg"
                                required
                            >

                            <i
                                class="
                                fa-solid
                                fa-leaf
                                "
                            ></i>

                            <span>
                                Veg
                            </span>

                        </label>


                        <label class="choice-card">

                            <input
                                type="radio"
                                name="food"
                                value="Non-Veg"
                            >

                            <i
                                class="
                                fa-solid
                                fa-drumstick-bite
                                "
                            ></i>

                            <span>
                                Non-Veg
                            </span>

                        </label>


                        <label class="choice-card">

                            <input
                                type="radio"
                                name="food"
                                value="Both"
                            >

                            <i
                                class="
                                fa-solid
                                fa-circle-half-stroke
                                "
                            ></i>

                            <span>
                                Both
                            </span>

                        </label>


                    </div>

                </div>

            </div>



            <!-- =================================================
                 TRIP TYPE
            ================================================== -->

            <div
                class="
                input-box
                trip-type
                "
            >

                <label>

                    <i
                        class="
                        fa-solid
                        fa-user-group
                        "
                    ></i>

                    Trip Type

                </label>


                <div
                    class="
                    choice-grid
                    trip-choice
                    "
                >


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="trip_type"
                            value="Solo"
                            required
                        >

                        <i
                            class="
                            fa-solid
                            fa-user
                            "
                        ></i>

                        <span>
                            Solo
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="trip_type"
                            value="Friends"
                        >

                        <i
                            class="
                            fa-solid
                            fa-user-group
                            "
                        ></i>

                        <span>
                            Friends
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="trip_type"
                            value="Family"
                        >

                        <i
                            class="
                            fa-solid
                            fa-people-roof
                            "
                        ></i>

                        <span>
                            Family
                        </span>

                    </label>


                    <label class="choice-card">

                        <input
                            type="radio"
                            name="trip_type"
                            value="Couple"
                        >

                        <i
                            class="
                            fa-regular
                            fa-heart
                            "
                        ></i>

                        <span>
                            Couple
                        </span>

                    </label>


                </div>

            </div>



           
            <!-- =================================================
                 GENERATE
            ================================================== -->

            <button
                type="submit"
                class="generate-btn"
            >

                <span>
                    Generate My Trip
                </span>

                <i
                    class="
                    fa-solid
                    fa-paper-plane
                    "
                ></i>

            </button>


        </form>

    </section>

</main>



<!-- =====================================================
     EXISTING PROJECT JAVASCRIPT
===================================================== -->

<script src="plan-trip.js?v=12"></script>



<!-- =====================================================
     TRANSPORT UI
===================================================== -->

<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        const transportInputs =
            document.querySelectorAll(
                'input[name="transport"]'
            );

        const transportExtra =
            document.getElementById(
                "transportExtra"
            );

        const departureBox =
            document.getElementById(
                "departureBox"
            );

        const liveOptionsBox =
            document.getElementById(
                "liveOptionsBox"
            );

        const departureInput =
            document.getElementById(
                "departure_time"
            );

        const selectedService =
            document.getElementById(
                "selected_service"
            );

        const arrivalTime =
            document.getElementById(
                "arrival_time"
            );

        const liveResults =
            document.getElementById(
                "liveResults"
            );

        const liveOptionsText =
            document.getElementById(
                "liveOptionsText"
            );


        function updateTransportUI() {

            let selectedTransport = "";


            transportInputs.forEach(
                function (input) {

                    if (input.checked) {

                        selectedTransport =
                            input.value;

                    }

                }
            );


            transportExtra.style.display =
                selectedTransport
                    ? "block"
                    : "none";


            departureBox.style.display =
                "none";

            liveOptionsBox.style.display =
                "none";


            departureInput.required =
                false;


            if (
                selectedTransport === "Car" ||
                selectedTransport === "Bike"
            ) {

                departureBox.style.display =
                    "block";

                departureInput.required =
                    true;

            }


            if (
                selectedTransport === "Bus" ||
                selectedTransport === "Train"
            ) {

                liveOptionsBox.style.display =
                    "block";


                liveOptionsText.textContent =
                    "Live " +
                    selectedTransport.toLowerCase() +
                    " service lookup will be connected here.";

            }

        }


        transportInputs.forEach(
            function (input) {

                input.addEventListener(
                    "change",
                    updateTransportUI
                );

            }
        );


        updateTransportUI();


        /*
           No fake Bus/Train timings.
        */

        const liveButton =
            document.getElementById(
                "checkLiveOptions"
            );


        if (liveButton) {

            liveButton.addEventListener(
                "click",
                function () {

                    const district =
                        document.getElementById(
                            "district"
                        ).value;

                    const start =
                        document.getElementById(
                            "starting_location"
                        ).value;

                    const date =
                        document.getElementById(
                            "travelDate"
                        ).value;


                    if (
                        district === "" ||
                        start === "" ||
                        date === ""
                    ) {

                        alert(
                            "Please select destination, starting location and travel date first."
                        );

                        return;
                    }


                    const selectedTransport =
                        document.querySelector(
                            'input[name="transport"]:checked'
                        );


                    const mode =
                        selectedTransport
                            ? selectedTransport.value
                            : "";


                    liveResults.style.display =
                        "block";


                    liveResults.innerHTML =
                        `
                        <div style="
                            padding:14px;
                            border-radius:12px;
                            background:#eef7eb;
                            border:1px solid #d5e8d1;
                            color:#315f38;
                            font-size:12px;
                        ">

                            <strong>
                                ${mode} service lookup
                            </strong>

                            <p style="margin-top:6px;">
                                ${start} → ${district}
                                <br>
                                Travel date: ${date}
                                <br><br>

                                We are not showing fake timings.
                                The real public-transit data source
                                will be connected here.
                            </p>

                        </div>
                        `;

                }
            );

        }

    }
);

</script>


</body>

</html>