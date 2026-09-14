// =========================
// MENU MOBILE
// =========================

const menuBtn = document.getElementById("menuBtn");
const mobileMenu = document.getElementById("mobileMenu");

menuBtn?.addEventListener("click", () => {
  const isOpen = mobileMenu.classList.toggle("open");

  menuBtn.setAttribute(
    "aria-expanded",
    String(isOpen)
  );
});

document.querySelectorAll(".mobile-menu a").forEach((link) => {
  link.addEventListener("click", () => {
    mobileMenu.classList.remove("open");

    menuBtn.setAttribute(
      "aria-expanded",
      "false"
    );
  });
});


// =========================
// MENU ATIVO AO ROLAR
// =========================

const sections = document.querySelectorAll("section[id]");
const navLinks = document.querySelectorAll(".desktop-nav a");

const updateActiveLink = () => {
  let current = "";

  sections.forEach((section) => {
    const sectionTop = section.offsetTop - 130;

    if (window.scrollY >= sectionTop) {
      current = section.id;
    }
  });

  navLinks.forEach((link) => {
    const linkTarget = link.getAttribute("href");

    link.classList.toggle(
      "active",
      linkTarget === `#${current}`
    );
  });
};

window.addEventListener(
  "scroll",
  updateActiveLink,
  { passive: true }
);

updateActiveLink();