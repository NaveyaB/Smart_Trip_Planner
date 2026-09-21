<?php

session_start();
include "db.php";

/* =========================================================
   LOGGED-IN USER
========================================================= */

$username = isset($_SESSION["username"])
    ? htmlspecialchars($_SESSION["username"])
    : "Traveler";

$user_id = $_SESSION["user_id"] ?? 0;


/* =========================================================
   CURRENT DATE
========================================================= */

$currentMonth = date("F");
$currentYear  = date("Y");


/* =========================================================
   DEFAULT DASHBOARD VALUES
========================================================= */

$tripsPlanned   = 0;
$savedPlaces    = 0;
$placesExplored = 0;
$totalBudget    = 0;


/* =========================================================
   DASHBOARD DATABASE DATA
========================================================= */

if ($user_id > 0) {

    /* -----------------------------------------------------
       1. TOTAL TRIPS PLANNED
    ----------------------------------------------------- */

    $sql = "SELECT COUNT(*) AS total
            FROM trips
            WHERE user_id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $tripsPlanned = $row["total"] ?? 0;

        $stmt->close();
    }


    /* -----------------------------------------------------
       2. SAVED PLACES
    ----------------------------------------------------- */

    $sql = "SELECT COUNT(*) AS total
            FROM saved_places
            WHERE user_id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $savedPlaces = $row["total"] ?? 0;

        $stmt->close();
    }


    /* -----------------------------------------------------
       3. PLACES EXPLORED
    ----------------------------------------------------- */

    $sql = "SELECT COUNT(DISTINCT destination) AS total
            FROM trips
            WHERE user_id = ?
            AND destination IS NOT NULL
            AND destination != ''";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $placesExplored = $row["total"] ?? 0;

        $stmt->close();
    }


    /* -----------------------------------------------------
       4. TOTAL BUDGET
    ----------------------------------------------------- */

    $sql = "SELECT COALESCE(SUM(total_budget), 0) AS total
            FROM trips
            WHERE user_id = ?";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        $totalBudget = $row["total"] ?? 0;

        $stmt->close();
    }
}

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>
        Dashboard | Smart Budget Trip Planner
    </title>


    <!-- Google Font -->

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">


    <!-- Font Awesome -->

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">


    <!-- Dashboard CSS -->

    <link rel="stylesheet"
          href="dashboard.css">

</head>


<body>


<!-- =====================================================
     SIDEBAR
===================================================== -->

<aside class="sidebar" id="sidebar">


    <div class="brand">

        <div class="brand-icon">

            <i class="fa-solid fa-route"></i>

        </div>


        <div class="brand-text">

            <h2>
                Smart Budget
            </h2>

            <span>
                TRIP PLANNER
            </span>

        </div>

    </div>


    <nav class="sidebar-nav">


        <a href="dashboard.php"
           class="nav-item active">

            <i class="fa-solid fa-house"></i>

            <span>
                Dashboard
            </span>

        </a>


        <a href="plan-trip.php"
           class="nav-item">

            <i class="fa-solid fa-plane-departure"></i>

            <span>
                Plan My Trip
            </span>

        </a>


        <a href="explore.php"
           class="nav-item">

            <i class="fa-solid fa-compass"></i>

            <span>
                Explore Destinations
            </span>

        </a>


        <a href="my-trips.php"
           class="nav-item">

            <i class="fa-solid fa-map-location-dot"></i>

            <span>
                My Trips
            </span>

        </a>


        <a href="saved-places.php"
           class="nav-item">

            <i class="fa-solid fa-heart"></i>

            <span>
                Saved Places
            </span>

        </a>


        <a href="budget.php"
           class="nav-item">

            <i class="fa-solid fa-wallet"></i>

            <span>
                Budget
            </span>

        </a>


        <a href="profile.php"
           class="nav-item">

            <i class="fa-solid fa-user"></i>

            <span>
                Profile
            </span>

        </a>

    </nav>


    <div class="sidebar-bottom">


        <div class="sidebar-tip">

            <i class="fa-solid fa-lightbulb"></i>

            <div>

                <strong>
                    Travel Smart
                </strong>

                <span>
                    Save more. Explore more.
                </span>

            </div>

        </div>


        <a href="login.html"
           class="logout">

            <i class="fa-solid fa-right-from-bracket"></i>

            <span>
                Logout
            </span>

        </a>


    </div>

</aside>



<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<main class="main-content">


    <!-- =================================================
         TOPBAR
    ================================================= -->

    <header class="topbar">


        <button class="menu-btn"
                id="menuBtn">

            <i class="fa-solid fa-bars"></i>

        </button>


        <div class="topbar-right">


            <span class="topbar-date">

                <?php echo $currentMonth . " " . $currentYear; ?>

            </span>


            <div class="profile-mini">


                <div class="profile-avatar">

                    <i class="fa-solid fa-user"></i>

                </div>


                <strong>

                    <?php echo $username; ?>

                </strong>


            </div>

        </div>

    </header>



    <!-- =================================================
         WELCOME SECTION
    ================================================= -->

    <section class="welcome-section">


        <div class="welcome-content">


            <span class="welcome-label">

                YOUR TRAVEL DASHBOARD

            </span>


            <h1>

                Welcome back,

                <span>

                    <?php echo $username; ?>

                </span>

                <span class="wave">
                    👋
                </span>

            </h1>


            <p>

                Plan smart, explore beautiful places,
                and make every journey count.

            </p>


        </div>


        <div class="welcome-decoration">

            <i class="fa-solid fa-earth-americas"></i>

        </div>


    </section>



    <!-- =================================================
         PLAN MY TRIP
    ================================================= -->

    <section class="plan-trip-section">


        <div class="plan-overlay"></div>


        <div class="plan-content">


            <span class="section-tag">

                YOUR NEXT ADVENTURE

            </span>


            <h2>

                Plan My Trip ✈️

            </h2>


            <p>

                Create a smart travel plan based on your
                destination, number of days, travelers
                and budget.

            </p>


            <a href="plan-trip.php"
               class="primary-btn">

                Start Planning

                <i class="fa-solid fa-arrow-right"></i>

            </a>


        </div>


    </section>



    <!-- =================================================
         TRAVEL OVERVIEW
    ================================================= -->

    <section class="dashboard-section">


        <div class="section-heading">


            <div>

                <span class="small-heading">

                    YOUR TRAVEL ACTIVITY

                </span>


                <h2>

                    Travel Overview

                </h2>

            </div>

        </div>


        <div class="overview-grid">


            <!-- Trips Planned -->

            <div class="overview-card">


                <div class="card-icon green-icon">

                    <i class="fa-solid fa-suitcase-rolling"></i>

                </div>


                <div class="card-info">

                    <span>
                        Trips Planned
                    </span>


                    <h3>

                        <?php echo $tripsPlanned; ?>

                    </h3>

                </div>

            </div>



            <!-- Saved Places -->

            <div class="overview-card">


                <div class="card-icon red-icon">

                    <i class="fa-solid fa-heart"></i>

                </div>


                <div class="card-info">

                    <span>
                        Saved Places
                    </span>


                    <h3>

                        <?php echo $savedPlaces; ?>

                    </h3>

                </div>

            </div>



            <!-- Places Explored -->

            <div class="overview-card">


                <div class="card-icon blue-icon">

                    <i class="fa-solid fa-location-dot"></i>

                </div>


                <div class="card-info">

                    <span>
                        Places Explored
                    </span>


                    <h3>

                        <?php echo $placesExplored; ?>

                    </h3>

                </div>

            </div>



            <!-- Total Budget -->

            <div class="overview-card">


                <div class="card-icon yellow-icon">

                    <i class="fa-solid fa-wallet"></i>

                </div>


                <div class="card-info">

                    <span>
                        Total Budget
                    </span>


                    <h3>

                        ₹<?php echo number_format($totalBudget); ?>

                    </h3>

                </div>

            </div>


        </div>

    </section>



    <!-- =================================================
         SMART DISCOVERY
    ================================================= -->

    <section class="smart-discovery">


        <div class="discovery-content">


            <span class="discovery-label">

                ✨ SMART TRAVEL DISCOVERY

            </span>


            <h2>

                Today's Travel Discovery

            </h2>


            <p class="discovery-description">

                Discover a destination worth exploring
                in Tamil Nadu.

            </p>


            <div class="discovery-place">


                <div class="place-icon">

                    <i class="fa-solid fa-mountain-sun"></i>

                </div>


                <div>

                    <h3>
                        Kodaikanal
                    </h3>


                    <p>
                        Dindigul District • Tamil Nadu
                    </p>

                </div>


            </div>


            <div class="discovery-tags">


                <span>
                    🌿 Nature
                </span>


                <span>
                    🗓️ 2–3 Days
                </span>


                <span>
                    💰 Budget Friendly
                </span>


            </div>


        </div>


        <div class="discovery-image">

            <div class="image-glow"></div>

        </div>


    </section>



    <!-- =================================================
         SEASONAL RECOMMENDATIONS
    ================================================= -->

    <section class="dashboard-section monthly-picks-section">


        <div class="section-heading">


            <div>

                <span class="small-heading">

                    SEASONAL RECOMMENDATIONS

                </span>


                <h2>

                    <?php echo htmlspecialchars($currentMonth); ?>

                    Travel Picks

                </h2>

            </div>


            <span class="section-subtitle">

                Places worth exploring this month

            </span>


        </div>



        <div class="monthly-picks-grid">


            <!-- =================================================
                 KODAIKANAL
            ================================================= -->

            <div class="travel-pick-card">


                <div class="travel-pick-image kodaikanal-pick">


                    <span class="pick-badge">

                        🌧️ Monsoon Pick

                    </span>


                </div>


                <div class="travel-pick-content">


                    <div class="pick-title-row">


                        <div>

                            <h3>
                                Kodaikanal
                            </h3>


                            <p>
                                Dindigul • Tamil Nadu
                            </p>

                        </div>


                        <div class="pick-icon">

                            <i class="fa-solid fa-mountain-sun"></i>

                        </div>


                    </div>


                    <p class="pick-description">

                        Misty hills, cool weather and peaceful
                        nature make it a beautiful seasonal escape.

                    </p>


                    <div class="pick-tags">

                        <span>
                            🌿 Nature
                        </span>

                        <span>
                            💰 Budget Friendly
                        </span>

                    </div>


                </div>

            </div>



            <!-- =================================================
                 VALPARAI
            ================================================= -->

            <div class="travel-pick-card">


                <div class="travel-pick-image valparai-pick">


                    <span class="pick-badge">

                        🌿 Green Escape

                    </span>


                </div>


                <div class="travel-pick-content">


                    <div class="pick-title-row">


                        <div>

                            <h3>
                                Valparai
                            </h3>


                            <p>
                                Coimbatore • Tamil Nadu
                            </p>

                        </div>


                        <div class="pick-icon">

                            <i class="fa-solid fa-tree"></i>

                        </div>


                    </div>


                    <p class="pick-description">

                        Lush tea estates, forests and refreshing
                        mountain views for a calm getaway.

                    </p>


                    <div class="pick-tags">

                        <span>
                            🍃 Greenery
                        </span>

                        <span>
                            📸 Scenic
                        </span>

                    </div>


                </div>

            </div>



            <!-- =================================================
                 COURTALLAM
            ================================================= -->

            <div class="travel-pick-card">


                <div class="travel-pick-image courtallam-pick">


                    <span class="pick-badge">

                        💧 Waterfall Pick

                    </span>


                </div>


                <div class="travel-pick-content">


                    <div class="pick-title-row">


                        <div>

                            <h3>
                                Courtallam
                            </h3>


                            <p>
                                Tenkasi • Tamil Nadu
                            </p>

                        </div>


                        <div class="pick-icon">

                            <i class="fa-solid fa-water"></i>

                        </div>


                    </div>


                    <p class="pick-description">

                        Enjoy refreshing waterfalls and a
                        nature-filled monsoon experience.

                    </p>


                    <div class="pick-tags">

                        <span>
                            💦 Waterfalls
                        </span>

                        <span>
                            🌧️ Monsoon
                        </span>

                    </div>


                </div>

            </div>


        </div>


    </section>



    <!-- =================================================
         RECENT TRIPS
    ================================================= -->

    <section class="dashboard-section">


        <div class="section-heading">


            <div>

                <span class="small-heading">

                    YOUR JOURNEY

                </span>


                <h2>

                    Recent Trips

                </h2>

            </div>


        </div>


        <?php if ($tripsPlanned > 0): ?>


            <div class="empty-trip-card">


                <div class="empty-icon">

                    <i class="fa-solid fa-map-location-dot"></i>

                </div>


                <div>

                    <h3>

                        You have
                        <?php echo $tripsPlanned; ?>
                        planned trip(s)

                    </h3>


                    <p>

                        View your trips to see
                        complete travel details.

                    </p>

                </div>


                <a href="my-trips.php"
                   class="small-action">

                    View My Trips

                    <i class="fa-solid fa-arrow-right"></i>

                </a>


            </div>


        <?php else: ?>


            <div class="empty-trip-card">


                <div class="empty-icon">

                    <i class="fa-solid fa-map-location-dot"></i>

                </div>


                <div>

                    <h3>

                        No trips planned yet

                    </h3>


                    <p>

                        Your planned trips will appear here.

                    </p>

                </div>


                <a href="plan-trip.php"
                   class="small-action">

                    Plan a Trip

                    <i class="fa-solid fa-arrow-right"></i>

                </a>


            </div>


        <?php endif; ?>


    </section>



    <!-- =================================================
         SMART TRAVEL TIP
    ================================================= -->

    <section class="travel-tip">


        <div class="tip-icon">

            <i class="fa-solid fa-lightbulb"></i>

        </div>


        <div>


            <span>

                SMART TRAVEL TIP

            </span>


            <h3>

                Plan your route before travelling
                to save both time and money.

            </h3>


        </div>


    </section>



    <!-- =================================================
         FOOTER
    ================================================= -->

    <footer class="dashboard-footer">


        <span>

            © <?php echo $currentYear; ?>

            Smart Budget Trip Planner

        </span>


        <span>

            Explore smarter • Travel better

        </span>


    </footer>


</main>



<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script src="dashboard.js"></script>


</body>

</html>