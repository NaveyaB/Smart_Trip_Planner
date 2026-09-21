document.addEventListener("DOMContentLoaded", function () {

    const budgetSlider = document.getElementById("budgetSlider");
    const budgetInput = document.getElementById("budgetInput");
    const budgetValue = document.getElementById("budgetValue");
    const budgetLabel = document.getElementById("budgetLabel");


    /* =====================================================
       BUDGET UPDATE
    ===================================================== */

    function updateBudget(value, updateSlider = true) {

        let amount = parseInt(value, 10);

        if (isNaN(amount)) {
            return;
        }

        /* Minimum and maximum */

        if (amount < 1000) {
            amount = 1000;
        }

        if (amount > 50000) {
            amount = 50000;
        }


        /* Input */

        budgetInput.value = amount;


        /* Top budget amount */

        budgetValue.textContent =
            "₹ " + amount.toLocaleString("en-IN");


        /* Slider */

        if (updateSlider) {
            budgetSlider.value = amount;
        }


        /* Budget type */

        if (amount <= 8000) {

            budgetLabel.textContent =
                "🟢 Low Budget";

            budgetLabel.className =
                "budget-label low";

        }

        else if (amount <= 15000) {

            budgetLabel.textContent =
                "🟡 Moderate Budget";

            budgetLabel.className =
                "budget-label moderate";

        }

        else {

            budgetLabel.textContent =
                "🔵 Premium Budget";

            budgetLabel.className =
                "budget-label premium";

        }

    }


    /* =====================================================
       SLIDER
    ===================================================== */

    if (budgetSlider) {

        budgetSlider.addEventListener("input", function () {

            updateBudget(this.value, false);

        });

    }


    /* =====================================================
       ENTER AMOUNT
    ===================================================== */

    if (budgetInput) {

        budgetInput.addEventListener("input", function () {

            let value = this.value;

            /*
             * Don't change the input while user is typing.
             * This prevents the first digit from disappearing.
             */

            if (value === "") {

                budgetValue.textContent =
                    "Enter your budget";

                return;
            }


            let amount = parseInt(value, 10);

            if (isNaN(amount)) {
                return;
            }


            /* Update top amount */

            budgetValue.textContent =
                "₹ " + amount.toLocaleString("en-IN");


            /* Update slider only for valid range */

            if (
                amount >= 1000 &&
                amount <= 50000
            ) {

                budgetSlider.value = amount;


                if (amount <= 8000) {

                    budgetLabel.textContent =
                        "🟢 Low Budget";

                    budgetLabel.className =
                        "budget-label low";

                }

                else if (amount <= 15000) {

                    budgetLabel.textContent =
                        "🟡 Moderate Budget";

                    budgetLabel.className =
                        "budget-label moderate";

                }

                else {

                    budgetLabel.textContent =
                        "🔵 Premium Budget";

                    budgetLabel.className =
                        "budget-label premium";

                }

            }

        });


        /* When user leaves input */

        budgetInput.addEventListener("blur", function () {

            let value = parseInt(this.value, 10);

            if (isNaN(value) || value < 1000) {

                value = 1000;

            }

            if (value > 50000) {

                value = 50000;

            }

            updateBudget(value, true);

        });

    }


    /* =====================================================
       INITIAL BUDGET
    ===================================================== */

    updateBudget(5000, true);


    /* =====================================================
       DATE
    ===================================================== */

    const travelDate =
        document.getElementById("travelDate");


    if (travelDate) {

        const today = new Date();

        const year =
            today.getFullYear();

        const month =
            String(today.getMonth() + 1)
                .padStart(2, "0");

        const day =
            String(today.getDate())
                .padStart(2, "0");

        travelDate.min =
            year + "-" + month + "-" + day;

    }


    /* =====================================================
       FORM VALIDATION
    ===================================================== */

    const tripForm =
        document.getElementById("tripForm");


    if (tripForm) {

        tripForm.addEventListener(
            "submit",
            function (event) {

                const district =
                    document.getElementById("district")
                        .value.trim();

                const persons =
                    document.getElementById("persons")
                        .value;

                const budget =
                    document.getElementById("budgetInput")
                        .value;

                const days =
                    document.getElementById("days")
                        .value;

                const travelDateValue =
                    document.getElementById("travelDate")
                        .value;

                const stay =
                    document.getElementById("stay")
                        .value;

                const transport =
                    document.querySelector(
                        'input[name="transport"]:checked'
                    );

                const food =
                    document.querySelector(
                        'input[name="food"]:checked'
                    );

                const tripType =
                    document.querySelector(
                        'input[name="trip_type"]:checked'
                    );


                if (!district) {

                    event.preventDefault();

                    alert(
                        "Please select a district."
                    );

                    return;
                }


                if (
                    !persons ||
                    Number(persons) <= 0
                ) {

                    event.preventDefault();

                    alert(
                        "Please enter number of persons."
                    );

                    return;
                }


                if (
                    !budget ||
                    Number(budget) < 1000
                ) {

                    event.preventDefault();

                    alert(
                        "Please enter a budget of at least ₹1,000."
                    );

                    return;
                }


                if (!days) {

                    event.preventDefault();

                    alert(
                        "Please select number of days."
                    );

                    return;
                }


                if (!travelDateValue) {

                    event.preventDefault();

                    alert(
                        "Please select journey date."
                    );

                    return;
                }


                if (!transport) {

                    event.preventDefault();

                    alert(
                        "Please select transport mode."
                    );

                    return;
                }


                if (!stay) {

                    event.preventDefault();

                    alert(
                        "Please select stay preference."
                    );

                    return;
                }


                if (!food) {

                    event.preventDefault();

                    alert(
                        "Please select food preference."
                    );

                    return;
                }


                if (!tripType) {

                    event.preventDefault();

                    alert(
                        "Please select trip type."
                    );

                    return;
                }

                /*
                 * IMPORTANT:
                 * No preventDefault here.
                 * PHP will receive the form.
                 */

            }
        );

    }


    /* =====================================================
       CHOICE CARD CLICK EFFECT
    ===================================================== */

    const choiceCards =
        document.querySelectorAll(".choice-card");


    choiceCards.forEach(function (card) {

        card.addEventListener(
            "click",
            function () {

                card.style.transform =
                    "scale(0.97)";


                setTimeout(function () {

                    card.style.transform =
                        "scale(1)";

                }, 120);

            }
        );

    });

});