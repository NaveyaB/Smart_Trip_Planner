let currentDay = 1;

const dayTabs = document.querySelectorAll(".day-tab");
const planList = document.getElementById("planList");
const planDayTitle = document.getElementById("planDayTitle");
const planDate = document.getElementById("planDate");
const journeyPath = document.getElementById("journeyPath");

function money(v){
    return "₹" + Math.round(v).toLocaleString("en-IN");
}

function getDateForDay(day){
    const d = new Date(START_DATE + "T00:00:00");
    d.setDate(d.getDate() + day - 1);
    return d.toLocaleDateString("en-IN",{day:"numeric",month:"long",year:"numeric",weekday:"long"});
}

function renderJourney(day){
    const plan = DAYS[(day-1) % DAYS.length] || [];
    journeyPath.innerHTML = "";
    const selected = plan.slice(0,5);
    selected.forEach((item,index)=>{
        const div = document.createElement("div");
        div.className = "journey-node";
        div.innerHTML = `
            <div class="node-image"><img src="${item[4]}" alt=""></div>
            <span class="node-name">${item[2]}</span>
            <span class="node-time">${item[0]}</span>
        `;
        div.addEventListener("click",()=>document.getElementById("planSection").scrollIntoView({behavior:"smooth",block:"start"}));
        journeyPath.appendChild(div);
    });
}

function renderPlan(day){
    currentDay = day;
    const plan = DAYS[(day-1) % DAYS.length] || DAYS[0];

    dayTabs.forEach(t=>t.classList.toggle("active",Number(t.dataset.day)===day));
    planDayTitle.textContent = `Day ${day} Plan`;
    planDate.textContent = getDateForDay(day);

    planList.innerHTML = "";
    plan.forEach(item=>{
        const row = document.createElement("div");
        row.className = "plan-item";
        row.innerHTML = `
            <div class="plan-time">${item[0]}</div>
            <div class="plan-detail">
                <div class="plan-icon"><i class="fa-solid ${item[1]}"></i></div>
                <div class="plan-text"><b>${item[2]}</b><p>${item[3]}</p></div>
                <img src="${item[4]}" alt="">
                <i class="fa-solid fa-chevron-right plan-arrow"></i>
            </div>
        `;
        planList.appendChild(row);
    });

    document.getElementById("budgetDaySmall").textContent = `DAY ${day} BUDGET`;
    document.querySelector(".budget-float b").textContent = `View Day ${day} Budget`;
    document.getElementById("budgetAmount").textContent = `${money(DAILY_BUDGET)} planned today`;

    const next = document.getElementById("continueDay");
    const prev = document.getElementById("previousDay");
    prev.disabled = day === 1;
    prev.style.opacity = day === 1 ? ".45" : "1";

    if(day < DAYS.length){
    next.innerHTML = `
        <div class="continue-day-copy">
            <small>NEXT DAY</small>
            <strong>Continue to Day ${day + 1}</strong>
            <em>Tomorrow to more scenic views & memories</em>
        </div>

        <div class="continue-day-arrow">
            Continue <i class="fa-solid fa-arrow-right"></i>
        </div>
    `;
    next.style.display = "flex";
}else{
    next.innerHTML = `
        <div class="continue-day-copy">
            <small>TRIP COMPLETE</small>
            <strong>Your Journey is Complete</strong>
            <em>Your memories are ready to be remembered</em>
        </div>

        <div class="continue-day-arrow">
            Done <i class="fa-solid fa-heart"></i>
        </div>
    `;
}
    renderJourney(day);
}

dayTabs.forEach(tab=>tab.addEventListener("click",()=>{
    renderPlan(Number(tab.dataset.day));
}));

document.getElementById("continueDay").addEventListener("click",()=>{
    if(currentDay < DAYS.length){
        renderPlan(currentDay+1);
        document.querySelector(".time-machine").scrollIntoView({behavior:"smooth",block:"start"});
    }
});

document.getElementById("previousDay").addEventListener("click",()=>{
    if(currentDay > 1){
        renderPlan(currentDay-1);
        document.querySelector(".time-machine").scrollIntoView({behavior:"smooth",block:"start"});
    }
});

const modal = document.getElementById("budgetModal");
function openBudget(){
    document.getElementById("modalLabel").textContent = `DAY ${currentDay} BUDGET`;
    document.getElementById("modalTitle").textContent = `Day ${currentDay} Budget`;
    document.getElementById("totalDay").textContent = currentDay;
    document.getElementById("bTransport").textContent = money(BUDGET.transport);
    document.getElementById("bFood").textContent = money(BUDGET.food);
    document.getElementById("bActivities").textContent = money(BUDGET.activities);
    document.getElementById("bShopping").textContent = money(BUDGET.shopping);
    document.getElementById("bStay").textContent = money(BUDGET.stay);
    document.getElementById("bTotal").textContent = money(BUDGET.total);
    document.getElementById("remainingText").textContent = `${money(Math.max(0,DAILY_BUDGET-BUDGET.total))} remaining for Day ${currentDay}.`;
    modal.classList.add("show");
}
document.getElementById("openBudget").addEventListener("click",openBudget);
document.getElementById("closeBudget").addEventListener("click",()=>modal.classList.remove("show"));
document.getElementById("budgetBackdrop").addEventListener("click",()=>modal.classList.remove("show"));

document.getElementById("savePdf").addEventListener("click", () => {

    const originalHTML = document.getElementById("planList").innerHTML;
    const originalTitle = document.getElementById("planDayTitle").textContent;
    const originalDate = document.getElementById("planDate").textContent;

    let allDaysHTML = "";

    DAYS.forEach((plan, index) => {

        const dayNumber = index + 1;

        allDaysHTML += `
            <div class="pdf-day-block">

                <div class="pdf-day-title">
                    <h2>Day ${dayNumber} Plan</h2>
                </div>

                <div class="pdf-day-list">

                    ${plan.map(item => `
                        <div class="plan-item">
                            <div class="plan-time">${item[0]}</div>

                            <div class="plan-detail">
                                <div class="plan-icon">
                                    <i class="fa-solid ${item[1]}"></i>
                                </div>

                                <div class="plan-text">
                                    <b>${item[2]}</b>
                                    <p>${item[3]}</p>
                                </div>

                                <img src="${item[4]}" alt="">
                            </div>
                        </div>
                    `).join("")}

                </div>

            </div>
        `;
    });

    document.getElementById("planList").innerHTML = allDaysHTML;
    document.getElementById("planDayTitle").textContent =
        `${DAYS.length}-Day Trip Plan`;
    document.getElementById("planDate").textContent =
        "Complete trip itinerary";

    document.body.classList.add("printing-trip");

    window.print();

    setTimeout(() => {

        document.body.classList.remove("printing-trip");

        document.getElementById("planList").innerHTML = originalHTML;
        document.getElementById("planDayTitle").textContent = originalTitle;
        document.getElementById("planDate").textContent = originalDate;

    }, 1000);

});

document.getElementById("copyPlan").addEventListener("click",async()=>{
    const text = `My ${document.title} - Day ${currentDay}: ` + (DAYS[currentDay-1]||[]).map(x=>`${x[0]} - ${x[2]}`).join(" | ");
    try{
        await navigator.clipboard.writeText(text);
        const btn = document.getElementById("copyPlan");
        btn.querySelector("span").textContent = "Copied!";
        setTimeout(()=>btn.querySelector("span").textContent="Copy Plan Link",1600);
    }catch(e){ alert("Plan copied: " + text); }
});

document.getElementById("shareWhatsapp").addEventListener("click",()=>{
    const text = `My Day ${currentDay} trip plan: ` + (DAYS[currentDay-1]||[]).map(x=>`${x[0]} ${x[2]}`).join(", ");
    window.open("https://wa.me/?text="+encodeURIComponent(text),"_blank");
});

renderPlan(1);
function getCurrentUserLocation(callback) {

    if (!navigator.geolocation) {
        alert("Location is not supported by this browser.");
        return;
    }

    navigator.geolocation.getCurrentPosition(
        function (position) {

            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;

            callback(latitude, longitude);
        },
        function () {
            alert("Please allow location access to find nearby services.");
        },
        {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        }
    );
}
/* ================================
   NEARBY SERVICES INTERACTION
================================ */

const serviceButtons = document.querySelectorAll(".quick-help-item");

const serviceData = {
    hospital: {
        type: "NEARBY HOSPITAL",
        name: "Government Hospital",
        icon: "fa-kit-medical",
        address: "Tamil Nadu",
        distance: "Approximately 2.8 km away",
        phone: "Contact details available on Maps",
        map: "hospital",
        image: SERVICE_IMAGES.hospital
    },

    police: {
        type: "NEARBY POLICE STATION",
        name: "Police Station",
        icon: "fa-building-shield",
        address: "Tamil Nadu",
        distance: "Approximately 1.9 km away",
        phone: "Emergency: 112",
        map: "police station",
        image: SERVICE_IMAGES.police
    },

    atm: {
        type: "NEARBY ATM",
        name: "Nearest ATM",
        icon: "fa-building-columns",
        address: "Tamil Nadu",
        distance: "Approximately 1.2 km away",
        phone: "ATM service available nearby",
        map: "ATM",
        image: SERVICE_IMAGES.atm
    }
};


serviceButtons.forEach(button => {

    button.addEventListener("click", () => {

        const service = button.dataset.service;
        const data = serviceData[service];

        /* Active button */
        serviceButtons.forEach(btn =>
            btn.classList.remove("active-service")
        );

        button.classList.add("active-service");
        document.getElementById("serviceName").textContent =
    `Finding nearby ${data.name.toLowerCase()}...`;

document.getElementById("serviceAddress").textContent =
    "Getting your current location...";

document.getElementById("serviceDistance").innerHTML =
    `<i class="fa-solid fa-spinner fa-spin"></i>
     <span>Searching nearby...</span>`;

        /* Static UI */
        document.getElementById("serviceIcon").innerHTML =
            `<i class="fa-solid ${data.icon}"></i>`;

        document.getElementById("serviceType").textContent =
            data.type;

        document.getElementById("serviceImage").src =
            data.image;

        document.getElementById("servicePhone").innerHTML =
            `<i class="fa-solid fa-phone"></i>
             <span>${data.phone}</span>`;

        /* GET CURRENT LOCATION NOW */
        getCurrentUserLocation(function(latitude, longitude) {

            fetch(
                `nearby-service.php?service=${service}&lat=${latitude}&lon=${longitude}`
            )
            .then(response => response.json())
            .then(result => {

                if (result.success) {

                    document.getElementById("serviceName").textContent =
                        result.name;

                    document.getElementById("serviceAddress").textContent =
                        result.address;

                    document.getElementById("serviceDistance").innerHTML =
                        `<i class="fa-solid fa-location-dot"></i>
                         <span>${(result.distance / 1000).toFixed(1)} km away</span>`;

                    document.getElementById("serviceMapLink").href =
                        `https://www.google.com/maps/search/?api=1&query=${result.lat},${result.lon}`;

                    document.querySelector(".service-map-box iframe").src =
                        `https://www.google.com/maps?q=${result.lat},${result.lon}&output=embed`;

                } else {

                    document.getElementById("serviceName").textContent =
                        data.name;

                    document.getElementById("serviceAddress").textContent =
                        "No nearby service found.";

                }

            })
            .catch(error => {
                console.error("Nearby Service Error:", error);
            });

        });

    });

});
const essentialChecks = document.querySelectorAll(
    ".essentials-list input[type='checkbox']"
);

const essentialsCount = document.getElementById("essentialsCount");
const essentialsProgress = document.getElementById("essentialsProgress");

essentialChecks.forEach(check => {

    check.addEventListener("change", () => {

        const checked =
            document.querySelectorAll(
                ".essentials-list input[type='checkbox']:checked"
            ).length;

        const total = essentialChecks.length;

        essentialsCount.textContent =
            `${checked} / ${total} Ready`;

        essentialsProgress.style.width =
            `${(checked / total) * 100}%`;

        const card = document.querySelector(".essentials-card");

        /* HALF WAY */
        if (checked === 4) {
            card.classList.add("halfway-ready");

            setTimeout(() => {
                card.classList.remove("halfway-ready");
            }, 1200);
        }

        /* FULLY READY */
        if (checked === total) {

            card.classList.add("trip-ready");

            const message = document.createElement("div");
            message.className = "trip-ready-popup";
            message.innerHTML = `
                <i class="fa-solid fa-circle-check"></i>
                <div>
                    <strong>Trip Ready! 🎉</strong>
                    <span>All 8 essentials are packed.</span>
                </div>
            `;

            document.body.appendChild(message);

            setTimeout(() => {
                message.classList.add("show");
            }, 50);

            setTimeout(() => {
                message.classList.remove("show");

                setTimeout(() => {
                    message.remove();
                }, 300);

            }, 2500);

            setTimeout(() => {
                card.classList.remove("trip-ready");
            }, 1600);
        }

    });

});