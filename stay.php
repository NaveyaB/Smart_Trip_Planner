<?php

session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* =====================================================
   GET TRIP DETAILS
===================================================== */

$place = trim($_GET["place"] ?? "Kodaikanal");

if ($place === "") {
    $place = "Kodaikanal";
}


/* Get latest trip */

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

$stayPreference = trim(
    $trip["stay"] ?? "Hotel"
);

$members = max(
    1,
    (int)($trip["members"] ?? 1)
);


/* =====================================================
   BUDGET ALLOCATION
   Stay gets around 35% of total trip budget
===================================================== */

$stayBudget = $totalBudget * 0.35;

$maxStayPerNight = 0;

if ($days > 0) {
    $maxStayPerNight =
        $stayBudget / $days;
}


/* =====================================================
   GET STAY OPTIONS
===================================================== */

$stayOptions = [];


/*
   Prefer selected stay type.
   Example:
   Hotel
   Resort
   Homestay
*/

$sql = "SELECT
            id,
            destination,
            stay_type,
            name,
            description,
            estimated_price,
            rating,
            image_url,
            map_url
        FROM stay_options
        WHERE destination = ?
        AND stay_type = ?
        AND estimated_price <= ?
        ORDER BY rating DESC,
                 estimated_price ASC
        LIMIT 3";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param(
    "ssd",
    $place,
    $stayPreference,
    $maxStayPerNight
);

$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {

    $stayOptions[] = $row;
}

$stmt->close();


/* =====================================================
   FALLBACK
   If no exact budget match is found,
   show cheaper available options.
===================================================== */

if (empty($stayOptions)) {

    $sql = "SELECT
                id,
                destination,
                stay_type,
                name,
                description,
                estimated_price,
                rating,
                image_url,
                map_url
            FROM stay_options
            WHERE destination = ?
            AND estimated_price <= ?
            ORDER BY estimated_price ASC,
                     rating DESC
            LIMIT 3";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database error: " . $conn->error);
    }

    $stmt->bind_param(
        "sd",
        $place,
        $maxStayPerNight
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $stayOptions[] = $row;
    }

    $stmt->close();
}


/* =====================================================
   SELECT STAY
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["stay_id"])
) {

    $stayId =
        (int)$_POST["stay_id"];


    $sql = "SELECT
                id,
                destination,
                stay_type,
                name,
                description,
                estimated_price,
                rating,
                image_url,
                map_url
            FROM stay_options
            WHERE id = ?
            AND destination = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database error: " . $conn->error);
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


        /* Save selected stay */

        $_SESSION["selected_stay"] = [

            "id" =>
                (int)$stay["id"],

            "destination" =>
                $stay["destination"],

            "stay_type" =>
                $stay["stay_type"],

            "name" =>
                $stay["name"],

            "description" =>
                $stay["description"],

            "estimated_price" =>
                (float)$stay["estimated_price"],

            "rating" =>
                (float)$stay["rating"],

            "image_url" =>
                $stay["image_url"] ?? "",

            "map_url" =>
                $stay["map_url"] ?? ""
        ];


        $stmt->close();


        /*
           Next step = Food
        */

        header(
            "Location: food.php?place=" .
            urlencode($place)
        );

        exit();
    }

    $stmt->close();

    $error =
        "Selected stay could not be found.";
}


/* =====================================================
   CALCULATED TOTAL STAY COST
===================================================== */

function calculateStayTotal(
    float $price,
    int $days
): float {

    /*
       Assume checkout after final night.
       For 3 days = 3 nights in this project logic.
    */

    return $price * $days;
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
    Smart Stay |
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

* {
    box-sizing: border-box;
}

body {

    margin: 0;

    font-family:
        "Manrope",
        Arial,
        sans-serif;

    background:
        linear-gradient(
            180deg,
            #e8f5ea,
            #dcefe0
        );

    color:
        #163a23;
}


/* =====================================================
   PAGE
===================================================== */

.page {

    max-width:
        1050px;

    margin:
        0 auto;

    padding:
        30px 20px 60px;

}


/* =====================================================
   TOP
===================================================== */

.top {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        25px;

}

.back {

    color:
        #286d3f;

    text-decoration:
        none;

    font-size:
        12px;

    font-weight:
        800;

}


/* =====================================================
   HEADER
===================================================== */

.hero {

    background:
        linear-gradient(
            135deg,
            #d8eedc,
            #c3e3ca
        );

    border:
        1px solid #b5d3bb;

    border-radius:
        24px;

    padding:
        28px;

    margin-bottom:
        25px;

    box-shadow:
        0 14px 30px
        rgba(35,87,47,.12);

}

.hero-tag {

    color:
        #2c6f40;

    font-size:
        10px;

    font-weight:
        900;

    letter-spacing:
        1.3px;

}

.hero h1 {

    margin:
        7px 0;

    font-size:
        34px;

    color:
        #102e1a;

}

.hero p {

    margin:
        0;

    color:
        #42604b;

    font-size:
        13px;

    line-height:
        1.7;

}


/* =====================================================
   BUDGET STRIP
===================================================== */

.budget-strip {

    display:
        grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:
        12px;

    margin-top:
        20px;

}


.budget-item {

    background:
        #e9f6eb;

    border:
        1px solid #bfd9c4;

    border-radius:
        14px;

    padding:
        13px;

}


.budget-item span {

    display:
        block;

    font-size:
        9px;

    color:
        #557060;

}


.budget-item strong {

    display:
        block;

    margin-top:
        3px;

    color:
        #153a22;

    font-size:
        17px;

}


/* =====================================================
   SECTION
===================================================== */

.section-head {

    margin:
        28px 0 16px;

}

.section-head span {

    font-size:
        10px;

    font-weight:
        900;

    letter-spacing:
        1.3px;

    color:
        #2a7040;

}

.section-head h2 {

    margin:
        5px 0;

    font-size:
        28px;

    color:
        #102e1a;

}

.section-head p {

    margin:
        0;

    color:
        #4e6957;

    font-size:
        12px;

}


/* =====================================================
   CARDS
===================================================== */

.grid {

    display:
        grid;

    grid-template-columns:
        repeat(3,1fr);

    gap:
        16px;

}


.card {

    background:
        linear-gradient(
            145deg,
            #e6f4e8,
            #cee6d3
        );

    border:
        1px solid #9fc6a7;

    border-radius:
        18px;

    overflow:
        hidden;

    box-shadow:
        0 10px 24px
        rgba(35,87,47,.11);

    transition:
        .25s ease;

}


.card:hover {

    transform:
        translateY(-5px);

    box-shadow:
        0 16px 32px
        rgba(35,87,47,.19);

}


.card-photo {

    height:
        170px;

    background:
        #c6dfca;

    overflow:
        hidden;

    display:
        grid;

    place-items:
        center;

}


.card-photo img {

    width:
        100%;

    height:
        100%;

    object-fit:
        cover;

}


.no-photo {

    font-size:
        42px;

    color:
        #3e7d4e;

}


.card-body {

    padding:
        17px;

}


.badge {

    display:
        inline-block;

    padding:
        6px 9px;

    background:
        #eaf7ec;

    color:
        #28683b;

    border:
        1px solid #afd0b5;

    border-radius:
        999px;

    font-size:
        9px;

    font-weight:
        900;

}


.card h3 {

    margin:
        10px 0 6px;

    font-size:
        17px;

    color:
        #102e1a;

}


.card p {

    margin:
        0;

    min-height:
        58px;

    font-size:
        11px;

    line-height:
        1.6;

    color:
        #3f5f49;

}


.meta {

    margin:
        14px 0;

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

}


.price strong {

    display:
        block;

    color:
        #1c5e31;

    font-size:
        19px;

}


.price small {

    color:
        #587261;

    font-size:
        8px;

}


.rating {

    color:
        #2c6c3d;

    font-size:
        11px;

    font-weight:
        900;

}


.total {

    padding:
        9px;

    margin-bottom:
        12px;

    border-radius:
        10px;

    background:
        #d4ead8;

    border:
        1px solid #b7d5bc;

    font-size:
        10px;

    color:
        #214f2d;

    font-weight:
        800;

}


.select-btn {

    width:
        100%;

    border:
        none;

    padding:
        12px;

    border-radius:
        11px;

    background:
        linear-gradient(
            135deg,
            #347c49,
            #235e35
        );

    color:
        #fff;

    font-size:
        11px;

    font-weight:
        900;

    cursor:
        pointer;

}


.select-btn:hover {

    box-shadow:
        0 9px 20px
        rgba(35,95,52,.24);

}


/* =====================================================
   NO DATA
===================================================== */

.notice {

    background:
        #dceee0;

    border:
        1px solid #a9c9af;

    border-radius:
        15px;

    padding:
        18px;

    color:
        #254d30;

    font-size:
        12px;

    font-weight:
        700;

}


/* =====================================================
   MOBILE
===================================================== */

@media(max-width:850px){

    .grid {

        grid-template-columns:
            1fr 1fr;

    }

}

@media(max-width:580px){

    .grid {

        grid-template-columns:
            1fr;

    }

    .budget-strip {

        grid-template-columns:
            1fr;

    }

}

</style>

</head>


<body>

<div class="page">


    <div class="top">

        <a
            href="place-details.php?place=<?php
                echo urlencode($place);
            ?>"
            class="back"
        >
            <i class="fa-solid fa-arrow-left"></i>
            Back to Trip
        </a>

    </div>


    <!-- HERO -->

    <section class="hero">

        <div class="hero-tag">
            SMART STAY RECOMMENDATION
        </div>

        <h1>
            Where will you stay?
        </h1>

        <p>
            We picked stay options that match your
            <?php echo htmlspecialchars($stayPreference); ?>
            preference and your trip budget.
        </p>


        <div class="budget-strip">

            <div class="budget-item">

                <span>
                    TOTAL TRIP BUDGET
                </span>

                <strong>
                    ₹<?php
                    echo number_format($totalBudget);
                    ?>
                </strong>

            </div>


            <div class="budget-item">

                <span>
                    STAY BUDGET
                </span>

                <strong>
                    ₹<?php
                    echo number_format($stayBudget);
                    ?>
                </strong>

            </div>


            <div class="budget-item">

                <span>
                    MAX / NIGHT
                </span>

                <strong>
                    ₹<?php
                    echo number_format($maxStayPerNight);
                    ?>
                </strong>

            </div>

        </div>

    </section>


    <!-- TITLE -->

    <section class="section-head">

        <span>
            RECOMMENDED FOR YOU
        </span>

        <h2>
            Choose your stay
        </h2>

        <p>
            These options are selected according to your
            budget and stay preference.
        </p>

    </section>


    <?php if (empty($stayOptions)): ?>

        <div class="notice">

            <i class="fa-solid fa-circle-info"></i>

            No suitable stay was found within the
            current stay budget.

            Try increasing your trip budget or choose
            another stay type.

        </div>

    <?php else: ?>


        <div class="grid">


            <?php foreach ($stayOptions as $stay): ?>

                <?php

                $totalStayCost =
                    calculateStayTotal(
                        (float)$stay["estimated_price"],
                        $days
                    );

                $budgetRemaining =
                    $totalBudget -
                    $totalStayCost;

                ?>

                <article class="card">


                    <div class="card-photo">

                        <?php if (!empty($stay["image_url"])): ?>

                            <img
                                src="<?php
                                echo htmlspecialchars(
                                    $stay["image_url"]
                                );
                                ?>"
                                alt="<?php
                                echo htmlspecialchars(
                                    $stay["name"]
                                );
                                ?>"
                            >

                        <?php else: ?>

                            <div class="no-photo">

                                <i
                                    class="fa-solid fa-hotel"
                                ></i>

                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="card-body">


                        <span class="badge">

                            <?php
                            echo htmlspecialchars(
                                $stay["stay_type"]
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
                                $stay["description"]
                            );
                            ?>

                        </p>


                        <div class="meta">


                            <div class="price">

                                <strong>
                                    ₹<?php
                                    echo number_format(
                                        (float)
                                        $stay["estimated_price"]
                                    );
                                    ?>
                                </strong>

                                <small>
                                    per night
                                </small>

                            </div>


                            <div class="rating">

                                ⭐
                                <?php
                                echo number_format(
                                    (float)
                                    $stay["rating"],
                                    1
                                );
                                ?>

                            </div>

                        </div>


                        <div class="total">

                            <i class="fa-solid fa-wallet"></i>

                            <?php echo $days; ?> day stay:

                            ₹<?php
                            echo number_format(
                                $totalStayCost
                            );
                            ?>

                        </div>


                        <form method="POST">

                            <input
                                type="hidden"
                                name="stay_id"
                                value="<?php
                                echo (int)
                                    $stay["id"];
                                ?>"
                            >

                            <button
                                type="submit"
                                class="select-btn"
                            >

                                <i
                                    class="
                                    fa-solid
                                    fa-check
                                    "
                                ></i>

                                Select This Stay

                            </button>

                        </form>


                    </div>

                </article>

            <?php endforeach; ?>


        </div>

    <?php endif; ?>


</div>

</body>

</html>