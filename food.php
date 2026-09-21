<?php

session_start();
include "db.php";


/* =====================================================
   LOGIN CHECK
===================================================== */

if (!isset($_SESSION["user_id"])) {

    header("Location: login.html");
    exit();

}

$user_id = (int) $_SESSION["user_id"];


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
   SELECT RESTAURANT
===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["restaurant_id"])
) {

    $restaurant_id =
        (int) $_POST["restaurant_id"];


    $sql = "SELECT
                id,
                destination,
                restaurant_type,
                name,
                description,
                estimated_price,
                rating,
                image_url,
                map_url
            FROM restaurant_options
            WHERE id = ?
            AND destination = ?
            LIMIT 1";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        die(
            "Database error: " .
            $conn->error
        );

    }


    $stmt->bind_param(
        "is",
        $restaurant_id,
        $place
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    if (
        $result->num_rows > 0
    ) {

        $restaurant =
            $result->fetch_assoc();


        /*
           Save selected restaurant
           temporarily in session.
        */

        $_SESSION[
            "selected_restaurant"
        ] = [

            "id" =>
                (int) $restaurant["id"],

            "destination" =>
                $restaurant[
                    "destination"
                ],

            "restaurant_type" =>
                $restaurant[
                    "restaurant_type"
                ],

            "name" =>
                $restaurant["name"],

            "description" =>
                $restaurant[
                    "description"
                ],

            "estimated_price" =>
                (float) $restaurant[
                    "estimated_price"
                ],

            "rating" =>
                (float) $restaurant[
                    "rating"
                ],

            "image_url" =>
                $restaurant[
                    "image_url"
                ] ?? "",

            "map_url" =>
                $restaurant[
                    "map_url"
                ] ?? ""

        ];


        $stmt->close();


        /*
           Continue to full trip plan.
        */

        header(
            "Location: place-details.php?place=" .
            urlencode($place)
        );

        exit();

    }


    $stmt->close();


    $error =
        "Selected restaurant was not found.";

}


/* =====================================================
   GET RESTAURANT OPTIONS
===================================================== */

$restaurantOptions = [];


$sql = "SELECT
            id,
            destination,
            restaurant_type,
            name,
            description,
            estimated_price,
            rating,
            image_url,
            map_url
        FROM restaurant_options
        WHERE destination = ?
        ORDER BY rating DESC,
                 estimated_price ASC";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        $conn->error
    );

}


$stmt->bind_param(
    "s",
    $place
);


$stmt->execute();


$result =
    $stmt->get_result();


while (
    $row =
    $result->fetch_assoc()
) {

    $restaurantOptions[] =
        $row;

}


$stmt->close();


/* =====================================================
   HERO IMAGE
===================================================== */

$heroImage =
    "image/kodaikanal.jpg";

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

        Choose Food |

        <?php
        echo htmlspecialchars(
            $place
        );
        ?>

    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap"
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
                #f7faf6;

            color:
                #193524;

        }


        .page {

            max-width:
                1180px;

            margin:
                0 auto;

            padding:
                30px 22px 60px;

        }


        .topbar {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            margin-bottom:
                25px;

        }


        .back-btn {

            text-decoration:
                none;

            color:
                #28623b;

            font-weight:
                700;

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

        }


        .brand {

            font-weight:
                800;

            color:
                #1f6237;

        }


        .hero {

            min-height:
                220px;

            border-radius:
                24px;

            background:
                linear-gradient(
                    rgba(15,45,27,.72),
                    rgba(15,45,27,.45)
                ),
                url("<?php
                    echo htmlspecialchars(
                        $heroImage
                    );
                ?>")
                center / cover;

            display:
                flex;

            align-items:
                end;

            padding:
                32px;

            color:
                #fff;

            margin-bottom:
                32px;

        }


        .hero h1 {

            margin:
                0 0 8px;

            font-size:
                38px;

        }


        .hero p {

            margin:
                0;

            opacity:
                .92;

        }


        .section-title {

            margin-bottom:
                22px;

        }


        .section-title span {

            font-size:
                12px;

            font-weight:
                800;

            letter-spacing:
                1.4px;

            color:
                #2c7a45;

        }


        .section-title h2 {

            margin:
                6px 0;

            font-size:
                30px;

        }


        .section-title p {

            margin:
                0;

            color:
                #688070;

        }


        .error {

            background:
                #ffe9e9;

            color:
                #9b2020;

            border:
                1px solid #f2bbbb;

            padding:
                14px;

            border-radius:
                12px;

            margin-bottom:
                20px;

        }


        .restaurant-grid {

            display:
                grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap:
                22px;

        }


        .restaurant-card {

            background:
                #ffffff;

            border:
                1px solid #dfe9df;

            border-radius:
                20px;

            overflow:
                hidden;

            box-shadow:
                0 10px 28px
                rgba(37,76,49,.08);

            transition:
                .2s ease;

        }


        .restaurant-card:hover {

            transform:
                translateY(-4px);

            box-shadow:
                0 16px 32px
                rgba(37,76,49,.14);

        }


        .restaurant-image {

            height:
                190px;

            background:
                linear-gradient(
                    135deg,
                    #e5f2e6,
                    #f0f7f0
                );

            display:
                flex;

            justify-content:
                center;

            align-items:
                center;

            overflow:
                hidden;

        }


        .restaurant-image img {

            width:
                100%;

            height:
                100%;

            object-fit:
                cover;

        }


        .no-image {

            font-size:
                44px;

            color:
                #5f906d;

        }


        .restaurant-body {

            padding:
                20px;

        }


        .restaurant-type {

            display:
                inline-block;

            padding:
                6px 10px;

            border-radius:
                999px;

            background:
                #edf7ed;

            color:
                #2a6b3e;

            font-size:
                11px;

            font-weight:
                800;

        }


        .restaurant-body h3 {

            margin:
                12px 0 8px;

            font-size:
                20px;

        }


        .restaurant-body p {

            margin:
                0;

            color:
                #697b6e;

            font-size:
                13px;

            line-height:
                1.6;

            min-height:
                62px;

        }


        .restaurant-meta {

            display:
                flex;

            justify-content:
                space-between;

            align-items:
                center;

            gap:
                10px;

            margin:
                16px 0;

        }


        .price {

            font-size:
                19px;

            font-weight:
                800;

            color:
                #1f6035;

        }


        .price small {

            display:
                block;

            font-size:
                10px;

            color:
                #7b8b80;

            font-weight:
                600;

        }


        .rating {

            color:
                #d28a00;

            font-size:
                13px;

            font-weight:
                800;

        }


        .select-btn {

            width:
                100%;

            border:
                none;

            background:
                #2d7a43;

            color:
                #ffffff;

            padding:
                13px;

            border-radius:
                12px;

            font-size:
                14px;

            font-weight:
                800;

            cursor:
                pointer;

        }


        .select-btn:hover {

            background:
                #225f34;

        }


        .bottom-note {

            text-align:
                center;

            margin-top:
                30px;

            color:
                #708074;

            font-size:
                12px;

        }


        @media (
            max-width: 850px
        ) {

            .restaurant-grid {

                grid-template-columns:
                    1fr 1fr;

            }

        }


        @media (
            max-width: 560px
        ) {

            .restaurant-grid {

                grid-template-columns:
                    1fr;

            }

            .hero h1 {

                font-size:
                    30px;

            }

        }

    </style>

</head>


<body>


<div class="page">


    <!-- =================================================
         TOPBAR
    ================================================= -->

    <div class="topbar">


        <a
            href="stay.php?place=<?php
                echo urlencode(
                    $place
                );
            ?>"
            class="back-btn"
        >

            <i
                class="fa-solid fa-arrow-left"
            ></i>

            Back to Stay

        </a>


        <div class="brand">

            Smart Budget Trip Planner

        </div>

    </div>



    <!-- =================================================
         HERO
    ================================================= -->

    <section class="hero">

        <div>

            <h1>
                Choose Your Food
            </h1>


            <p>

                Select a suitable restaurant
                for your
                <?php
                echo htmlspecialchars(
                    $place
                );
                ?>
                trip.

            </p>

        </div>

    </section>



    <!-- =================================================
         TITLE
    ================================================= -->

    <section class="section-title">

        <span>
            FOOD OPTIONS
        </span>


        <h2>
            Where would you like to eat?
        </h2>


        <p>

            Choose one option. Your selection
            will be carried into your final trip plan.

        </p>

    </section>



    <!-- =================================================
         ERROR
    ================================================= -->

    <?php
    if (
        isset($error)
    ):
    ?>

        <div class="error">

            <?php
            echo htmlspecialchars(
                $error
            );
            ?>

        </div>

    <?php
    endif;
    ?>



    <!-- =================================================
         RESTAURANTS
    ================================================= -->

    <?php
    if (
        empty(
            $restaurantOptions
        )
    ):
    ?>

        <div class="error">

            No restaurant options found for

            <?php
            echo htmlspecialchars(
                $place
            );
            ?>.

        </div>

    <?php
    else:
    ?>


        <div
            class="restaurant-grid"
        >


            <?php
            foreach (
                $restaurantOptions
                as $restaurant
            ):
            ?>


                <article
                    class="
                    restaurant-card
                    "
                >


                    <div
                        class="
                        restaurant-image
                        "
                    >

                        <?php
                        if (
                            !empty(
                                $restaurant[
                                    "image_url"
                                ]
                            )
                        ):
                        ?>

                            <img
                                src="<?php
                                echo htmlspecialchars(
                                    $restaurant[
                                        "image_url"
                                    ]
                                );
                                ?>"
                                alt="<?php
                                echo htmlspecialchars(
                                    $restaurant[
                                        "name"
                                    ]
                                );
                                ?>"
                            >

                        <?php
                        else:
                        ?>

                            <div
                                class="no-image"
                            >

                                <i
                                    class="
                                    fa-solid
                                    fa-utensils
                                    "
                                ></i>

                            </div>

                        <?php
                        endif;
                        ?>

                    </div>



                    <div
                        class="
                        restaurant-body
                        "
                    >


                        <span
                            class="
                            restaurant-type
                            "
                        >

                            <?php
                            echo htmlspecialchars(
                                $restaurant[
                                    "restaurant_type"
                                ]
                            );
                            ?>

                        </span>


                        <h3>

                            <?php
                            echo htmlspecialchars(
                                $restaurant[
                                    "name"
                                ]
                            );
                            ?>

                        </h3>


                        <p>

                            <?php
                            echo htmlspecialchars(
                                $restaurant[
                                    "description"
                                ]
                            );
                            ?>

                        </p>


                        <div
                            class="
                            restaurant-meta
                            "
                        >

                            <div
                                class="price"
                            >

                                ₹
                                <?php
                                echo number_format(
                                    (float)
                                    $restaurant[
                                        "estimated_price"
                                    ]
                                );
                                ?>

                                <small>
                                    estimated / meal
                                </small>

                            </div>


                            <div
                                class="rating"
                            >

                                <i
                                    class="
                                    fa-solid
                                    fa-star
                                    "
                                ></i>

                                <?php
                                echo number_format(
                                    (float)
                                    $restaurant[
                                        "rating"
                                    ],
                                    1
                                );
                                ?>

                            </div>

                        </div>


                        <form
                            method="POST"
                        >

                            <input
                                type="hidden"
                                name="restaurant_id"
                                value="<?php
                                    echo (int)
                                    $restaurant[
                                        "id"
                                    ];
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

                                Select This Restaurant

                            </button>

                        </form>

                    </div>

                </article>


            <?php
            endforeach;
            ?>


        </div>


    <?php
    endif;
    ?>


    <div
        class="bottom-note"
    >

        Your selected restaurant will be
        used in your final trip plan.

    </div>


</div>


</body>

</html>