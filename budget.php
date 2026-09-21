<?php

session_start();
include "db.php";

/* =========================
   LOGIN CHECK
========================= */

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];


/* =========================
   GET LATEST TRIP
========================= */

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


/* =========================
   TRIP VALUES
========================= */

$destination = trim($_GET["place"] ?? ($trip["destination"] ?? "Dindigul"));
$members     = max(1, (int)($trip["members"] ?? 1));
$days        = max(1, (int)($trip["days"] ?? 1));
$totalBudget = max(0, (float)($trip["total_budget"] ?? 0));


/* =========================
   SMART BUDGET ALLOCATION

   35% Stay
   20% Food
   10% Activities
    5% Shopping
    5% Others
   25% Safe Balance
========================= */

$stay       = $totalBudget * 0.35;
$food       = $totalBudget * 0.20;
$activities = $totalBudget * 0.10;
$shopping   = $totalBudget * 0.05;
$others     = $totalBudget * 0.05;

$totalPlanned = $stay + $food + $activities + $shopping + $others;
$remaining   = max(0, $totalBudget - $totalPlanned);

$usedPercent = $totalBudget > 0
    ? min(100, max(0, ($totalPlanned / $totalBudget) * 100))
    : 0;

$remainingPercent = max(0, 100 - $usedPercent);


/* =========================
   SMART DAY-WISE BUDGET

   The old version used:
   total budget / days
   which made every day identical.

   Now the budget is weighted by
   the trip flow: first and last day
   are slightly lighter, while a
   central/high-activity day gets
   a little more budget.

   The weights are normalized, so
   the sum of all day budgets still
   equals the total trip budget.
========================= */

$dayTitles = [
    "Arrival & Explore",
    "Nature & Adventure",
    "Culture & Discovery",
    "Scenic Experiences",
    "Local Highlights",
    "Relax & Explore",
    "Final Day & Return"
];

$dayWeights = [];

if ($days === 1) {
    $dayWeights = [1.0];
} elseif ($days === 2) {
    $dayWeights = [0.9, 1.1];
} else {

    for ($i = 1; $i <= $days; $i++) {

        if ($i === 1 || $i === $days) {
            $dayWeights[] = 0.9;
        } elseif ($i === (int)ceil($days / 2)) {
            $dayWeights[] = 1.2;
        } else {
            $dayWeights[] = 1.0;
        }
    }
}

$weightTotal = array_sum($dayWeights);

/* Make the average weight exactly 1 */
$dayWeights = array_map(
    function ($weight) use ($weightTotal, $days) {
        return ($weight / $weightTotal) * $days;
    },
    $dayWeights
);

/* Food split */
$foodBreakfast = $food * 0.25;
$foodLunch     = $food * 0.35;
$foodDinner    = $food * 0.40;

$dayExpenses = [];
$dailyBudgetsTotal = 0;

for ($i = 1; $i <= $days; $i++) {

    $weight = $dayWeights[$i - 1];

    $dayBudget  = ($totalBudget / $days) * $weight;
    $dayPlanned = ($totalPlanned / $days) * $weight;

    $dayStay       = ($stay / $days) * $weight;
    $dayBreakfast  = ($foodBreakfast / $days) * $weight;
    $dayLunch      = ($foodLunch / $days) * $weight;
    $dayDinner     = ($foodDinner / $days) * $weight;
    $dayActivities = ($activities / $days) * $weight;
    $dayShopping   = ($shopping / $days) * $weight;
    $dayOthers     = ($others / $days) * $weight;

    $dailyBudgetsTotal += $dayBudget;

    $title = $dayTitles[$i - 1] ?? "Explore & Enjoy";

    $dayExpenses[] = [
        "day" => "Day " . $i,
        "title" => $title,
        "spent" => round($dayPlanned),
        "budget" => round($dayBudget),
        "items" => [
            ["name" => "Stay",       "amount" => round($dayStay),       "icon" => "🏨"],
            ["name" => "Breakfast",  "amount" => round($dayBreakfast),  "icon" => "🍳"],
            ["name" => "Lunch",      "amount" => round($dayLunch),      "icon" => "🍛"],
            ["name" => "Dinner",     "amount" => round($dayDinner),     "icon" => "🍽️"],
            ["name" => "Activities",  "amount" => round($dayActivities), "icon" => "🏞️"],
            ["name" => "Shopping",   "amount" => round($dayShopping),   "icon" => "🛍️"],
            ["name" => "Others",     "amount" => round($dayOthers),     "icon" => "✨"]
        ]
    ];
}

/* Used by the floating budget button on other pages */
$dailyBudget = $days > 0 ? ($totalBudget / $days) : $totalBudget;

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Budget Details | Smart Budget Trip Planner</title>

    <link rel="stylesheet" href="budget.css">

</head>

<body>

<!-- =========================
     BACKGROUND EFFECTS
========================= -->

<div class="bg-orb orb-1"></div>
<div class="bg-orb orb-2"></div>
<div class="bg-orb orb-3"></div>


<!-- =========================
     NAVBAR
========================= -->

<nav class="navbar">

    <div class="brand">

        <div class="brand-icon">
            ✦
        </div>

        <div>
            <h2>Smart Budget</h2>
            <span>Trip Planner</span>
        </div>

    </div>


    <div class="nav-links">

        <a href="dashboard.php">Home</a>

        <a href="destination.php">Explore</a>

        <a href="my-trips.php">My Trips</a>

        <a href="saved-places.php">Saved</a>

        <a href="budget.php"
           class="active">
            Budget
        </a>

        <a href="profile.php">Profile</a>

    </div>


    <div class="profile-area">

        <div class="notification">
            🔔
            <span>3</span>
        </div>

        <div class="profile">
            👤
            <span>Traveler</span>
            <b>⌄</b>
        </div>

    </div>

</nav>


<!-- =========================
     HERO
========================= -->

<section class="budget-hero">

    <div class="hero-left">

        <div class="small-tag">
            💰 YOUR TRIP FINANCES
        </div>

        <h1>
            Smart Budget
            <span>Overview</span>
        </h1>

        <p>
            Track your trip expenses, manage your daily budget
            and enjoy your journey without financial stress.
        </p>

        <div class="trip-info">

            <div class="info-pill">
                📍 <?= htmlspecialchars($destination) ?>
            </div>

            <div class="info-pill">
                📅 <?= $days ?> Days
            </div>

            <div class="info-pill">
                👥 <?= $members ?> Travelers
            </div>

        </div>

    </div>


    <!-- TOTAL BUDGET CARD -->

    <div class="hero-budget-card">

        <div class="card-top">

            <div>
                <p>Total Trip Budget</p>

                <h2>
                    ₹<?= number_format($totalBudget) ?>
                </h2>
            </div>

            <div class="wallet">
                💳
            </div>

        </div>


        <div class="budget-progress">

            <div class="progress-fill"
                 style="width: <?= $usedPercent ?>%">
            </div>

        </div>


        <div class="budget-numbers">

            <div>
                <span>Planned</span>
                <strong>
                    ₹<?= number_format($totalPlanned) ?>
                </strong>
            </div>

            <div>
                <span>Remaining</span>
                <strong class="green-text">
                    ₹<?= number_format($remaining) ?>
                </strong>
            </div>

        </div>

    </div>

</section>



<!-- =========================
     MAIN CONTENT
========================= -->

<main class="budget-container">


    <!-- PAGE HEADING -->

    <div class="section-heading">

        <div>

            <span class="eyebrow">
                ✦ SMART MONEY MANAGEMENT
            </span>

            <h2>
                Your Budget Breakdown
            </h2>

            <p>
                See exactly where your travel budget is going.
            </p>

        </div>


        <div class="status-badge">

            <span class="pulse-dot"></span>

            Budget On Track

        </div>

    </div>



    <!-- =========================
         TOP SUMMARY CARDS
    ========================= -->

    <section class="summary-grid">


        <div class="summary-card">

            <div class="summary-icon stay-icon">
                🏨
            </div>

            <div>
                <span>Stay Budget</span>
                <h3>₹<?= number_format($stay) ?></h3>
            </div>

            <div class="mini-bar">
                <span style="width: 35%"></span>
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-icon food-icon">
                🍽️
            </div>

            <div>
                <span>Food Budget</span>
                <h3>₹<?= number_format($food) ?></h3>
            </div>

            <div class="mini-bar">
                <span style="width: 20%"></span>
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-icon activity-icon">
                🏔️
            </div>

            <div>
                <span>Activities</span>
                <h3>₹<?= number_format($activities) ?></h3>
            </div>

            <div class="mini-bar">
                <span style="width: 10%"></span>
            </div>

        </div>



        <div class="summary-card">

            <div class="summary-icon shop-icon">
                🛍️
            </div>

            <div>
                <span>Shopping</span>
                <h3>₹<?= number_format($shopping) ?></h3>
            </div>

            <div class="mini-bar">
                <span style="width: 5%"></span>
            </div>

        </div>


    </section>



    <!-- =========================
     SMART BUDGET INSIGHT SECTION
========================= -->

<section class="budget-main-grid">

    <!-- LEFT: TRIP BUDGET SNAPSHOT -->

    <div class="budget-snapshot-card">

        <div class="snapshot-heading">

            <div>
                <span class="eyebrow">
                    ✦ TRIP BUDGET SNAPSHOT
                </span>

                <h2>
                    Your Budget at a Glance
                </h2>

                <p>
                    A quick and simple view of your total trip money.
                </p>
            </div>

            <div class="snapshot-icon">
                💰
            </div>

        </div>


        <!-- SNAPSHOT BOXES -->

        <div class="snapshot-grid">

            <div class="snapshot-box total-box">

                <span class="snapshot-label">
                    Total Budget
                </span>

                <strong>
                    ₹<?= number_format($totalBudget) ?>
                </strong>

                <small>
                    Your complete trip budget
                </small>

            </div>


            <div class="snapshot-box planned-box">

                <span class="snapshot-label">
                    Planned Amount
                </span>

                <strong>
                    ₹<?= number_format($totalPlanned) ?>
                </strong>

                <small>
                    Already planned for your trip
                </small>

            </div>


            <div class="snapshot-box remaining-box">

                <span class="snapshot-label">
                    Available Balance
                </span>

                <strong>
                    ₹<?= number_format($remaining) ?>
                </strong>

                <small>
                    Still available to use
                </small>

            </div>

        </div>


        <!-- BUDGET USAGE -->

        <div class="budget-usage-box">

            <div class="usage-top">

                <div>
                    <span>
                        Budget Usage
                    </span>

                    <strong>
                        <?= round($usedPercent) ?>% Planned
                    </strong>
                </div>

                <div class="usage-value">
                    ₹<?= number_format($totalPlanned) ?>
                    /
                    ₹<?= number_format($totalBudget) ?>
                </div>

            </div>


            <div class="usage-progress">

                <span
                    style="width: <?= $usedPercent ?>%">
                </span>

            </div>


            <div class="usage-bottom">

                <span>
                    Your trip planning is within budget
                </span>

                <strong>
                    ₹<?= number_format($remaining) ?> Safe Balance
                </strong>

            </div>

        </div>


        <!-- SMART TIP -->

        <div class="budget-smart-tip">

            <div class="tip-icon">
                💡
            </div>

            <div>

                <strong>
                    Smart Planning Tip
                </strong>

                <p>
                    Keep your remaining amount available for unexpected
                    expenses, extra activities or emergency needs.
                </p>

            </div>

        </div>

    </div>



   
    
    
<!-- RIGHT: SMART BUDGET CARD -->

        <div class="smart-budget-card">

            <div class="smart-glow"></div>

            <div class="smart-icon">
                ✦
            </div>

            <span class="eyebrow">
                SMART INSIGHT
            </span>

            <h2>
                You're Managing Your Budget Well!
            </h2>

            <p>
                You still have money available for unexpected
                expenses or extra experiences.
            </p>


            <div class="remaining-circle">

                <div class="circle-inner">

                    <span>Remaining</span>

                    <strong>
                        ₹<?= number_format($remaining) ?>
                    </strong>

                </div>

            </div>


            <div class="smart-tip">

                💡 Tip: Keep some amount reserved for
                emergency expenses.

            </div>

        </div>


    </section>



    <!-- =========================
         DAY WISE SECTION
    ========================= -->

    <section class="day-budget-section">


        <div class="section-heading day-heading">

            <div>

                <span class="eyebrow">
                    DAILY EXPENSE TRACKER
                </span>

                <h2>
                    Day-by-Day Budget
                </h2>

                <p>
                    Track how much you plan to spend each day.
                </p>

            </div>

        </div>



        <div class="day-grid">

            <?php foreach ($dayExpenses as $index => $day): ?>

                <?php
                    $percent =
                    ($day["spent"] / $day["budget"]) * 100;

                    $left =
                    $day["budget"] - $day["spent"];
                ?>

                <div class="day-card">

                    <div class="day-card-top">

                        <div>

                            <span class="day-number">
                                <?= $day["day"] ?>
                            </span>

                            <h3>
                                <?= $day["title"] ?>
                            </h3>

                        </div>

                        <div class="day-status">
                            On Track ✓
                        </div>

                    </div>


                    <div class="day-money">

                        <div>

                            <span>Planned Expense</span>

                            <strong>
                                ₹<?= number_format($day["spent"]) ?>
                            </strong>

                        </div>


                        <div>

                            <span>Day Budget</span>

                            <strong>
                                ₹<?= number_format($day["budget"]) ?>
                            </strong>

                        </div>

                    </div>


                    <div class="day-progress">

                        <span style="width: <?= $percent ?>%">
                        </span>

                    </div>


                    <div class="day-progress-text">

                        <span>
                            <?= round($percent) ?>% Used
                        </span>

                        <strong>
                            ₹<?= number_format($left) ?> Left
                        </strong>

                    </div>


                    <!-- EXPENSE PREVIEW -->

                    <div class="expense-preview">

                        <?php foreach (
                            array_slice($day["items"], 0, 4)
                            as $item
                        ): ?>

                            <div class="preview-item">

                                <span>
                                    <?= $item["icon"] ?>
                                    <?= $item["name"] ?>
                                </span>

                                <strong>
                                    ₹<?= $item["amount"] ?>
                                </strong>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <button class="view-day-btn"
                            onclick="showDay(<?= $index ?>)">

                        View Full Day Expenses →

                    </button>

                </div>

            <?php endforeach; ?>

        </div>

    </section>



    <!-- =========================
         EXPENSE MODAL
    ========================= -->

    <div class="expense-modal"
         id="expenseModal">

        <div class="modal-box">

            <button class="close-modal"
                    onclick="closeDay()">
                ×
            </button>

            <div id="modalContent"></div>

        </div>

    </div>



    <!-- =========================
         BOTTOM SUMMARY
    ========================= -->

    <section class="final-summary">

        <div class="final-left">

            <div class="final-icon">
                ✓
            </div>

            <div>

                <h3>
                    Your Budget is Looking Good!
                </h3>

                <p>
                    You have
                    ₹<?= number_format($remaining) ?>
                    remaining from your total trip budget.
                </p>

            </div>

        </div>


        <a href="my-trips.php"
           class="back-trip-btn">

            ← Back to My Trip

        </a>

    </section>


</main>



<!-- =========================
     FOOTER
========================= -->

<footer>

    <div>
        ✦ Smart Budget Trip Planner
    </div>

    <div>
        Plan Smart • Travel Happy • Spend Wisely
    </div>

</footer>



<script>

const dayData = <?php echo json_encode($dayExpenses); ?>;


/* =========================
   SHOW DAY EXPENSES
========================= */

function showDay(index) {

    const day = dayData[index];

    let itemsHTML = "";

    day.items.forEach(item => {

        itemsHTML += `

            <div class="modal-expense-row">

                <span>
                    ${item.icon}
                    ${item.name}
                </span>

                <strong>
                    ₹${item.amount}
                </strong>

            </div>

        `;

    });


    const remaining =
        day.budget - day.spent;


    document.getElementById("modalContent").innerHTML = `

        <div class="modal-header">

            <span class="eyebrow">
                DAILY EXPENSE DETAILS
            </span>

            <h2>
                ${day.day} - ${day.title}
            </h2>

        </div>


        <div class="modal-total">

            <div>

                <span>Total Planned</span>

                <h2>
                    ₹${day.spent.toLocaleString()}
                </h2>

            </div>


            <div>

                <span>Remaining</span>

                <h3>
                    ₹${remaining.toLocaleString()}
                </h3>

            </div>

        </div>


        <div class="modal-expenses">

            ${itemsHTML}

        </div>

    `;


    document
        .getElementById("expenseModal")
        .classList.add("show");

}



/* =========================
   CLOSE MODAL
========================= */

function closeDay() {

    document
        .getElementById("expenseModal")
        .classList.remove("show");

}



/* CLICK OUTSIDE CLOSE */

document
    .getElementById("expenseModal")
    .addEventListener("click", function(e) {

        if (e.target === this) {

            closeDay();

        }

    });


/* =========================
   SCROLL ANIMATION
========================= */

const cards =
    document.querySelectorAll(
        ".summary-card, .category-card, .smart-budget-card, .day-card"
    );


const observer =
    new IntersectionObserver(entries => {

        entries.forEach(entry => {

            if (entry.isIntersecting) {

                entry.target.classList.add("show-card");

            }

        });

    }, {
        threshold: 0.12
    });


cards.forEach(card => {

    observer.observe(card);

});

</script>


</body>

</html>