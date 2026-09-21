document.addEventListener("DOMContentLoaded", function () {

    /* =========================
       SIDEBAR MENU
    ========================= */

    const menuBtn = document.getElementById("menuBtn");
    const sidebar = document.getElementById("sidebar");

    if (menuBtn) {
        menuBtn.addEventListener("click", function () {

            if (window.innerWidth <= 900) {
                document.body.classList.toggle("sidebar-open");
            } else {
                document.body.classList.toggle("sidebar-collapsed");
            }

        });
    }


    /* =========================
       CLOSE MOBILE SIDEBAR
    ========================= */

    document.addEventListener("click", function (event) {

        if (window.innerWidth <= 900) {

            if (
                sidebar &&
                !sidebar.contains(event.target) &&
                menuBtn &&
                !menuBtn.contains(event.target)
            ) {
                document.body.classList.remove("sidebar-open");
            }

        }

    });


    /* =========================
       SIDEBAR ACTIVE ITEM
    ========================= */

    const navItems = document.querySelectorAll(".nav-item");

    navItems.forEach(function (item) {

        item.addEventListener("click", function () {

            navItems.forEach(function (nav) {
                nav.classList.remove("active");
            });

            item.classList.add("active");

        });

    });


    /* =========================
       OVERVIEW CARD ANIMATION
    ========================= */

    const overviewCards =
        document.querySelectorAll(".overview-card");

    overviewCards.forEach(function (card) {

        card.addEventListener("mouseenter", function () {
            card.style.transform = "translateY(-6px)";
        });

        card.addEventListener("mouseleave", function () {
            card.style.transform = "translateY(0)";
        });

    });


    /* =========================
       MONTH CARDS
    ========================= */

    const monthCards =
        document.querySelectorAll(".month-card");

    monthCards.forEach(function (card) {

        card.addEventListener("mouseenter", function () {
            card.style.cursor = "pointer";
        });

    });


    /* =========================
       SMART DISCOVERY EFFECT
    ========================= */

    const discovery =
        document.querySelector(".smart-discovery");

    if (discovery) {

        discovery.addEventListener("mouseenter", function () {

            discovery.style.transform =
                "translateY(-4px)";

            discovery.style.transition =
                "0.3s ease";

        });

        discovery.addEventListener("mouseleave", function () {

            discovery.style.transform =
                "translateY(0)";

        });

    }


    /* =========================
       PAGE LOAD ANIMATION
    ========================= */

    document.body.style.opacity = "0";

    setTimeout(function () {

        document.body.style.transition =
            "opacity 0.45s ease";

        document.body.style.opacity = "1";

    }, 80);


    /* =========================
       WINDOW RESIZE
    ========================= */

    window.addEventListener("resize", function () {

        if (window.innerWidth > 900) {

            document.body.classList.remove(
                "sidebar-open"
            );

        }

    });

});