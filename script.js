// Highlight active menu
const links = document.querySelectorAll(".navbar ul li a");

links.forEach(link => {
    link.addEventListener("click", () => {
        links.forEach(item => item.classList.remove("active"));
        link.classList.add("active");
    });
});

// Navbar shadow on scroll
window.addEventListener("scroll", () => {
    const header = document.querySelector("header");

    if(window.scrollY > 50){
        header.style.boxShadow = "0 5px 20px rgba(0,0,0,0.15)";
    }else{
        header.style.boxShadow = "0 5px 20px rgba(0,0,0,0.05)";
    }
});