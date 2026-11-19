/* =========================================================
   Young India Group – Deven Rabari Foundation
   Script  |  Each feature is a separate, small function
   ========================================================= */


/* ---------- 1. MOBILE MENU ---------- */
function initMobileMenu() {
  const toggle = document.getElementById("navToggle");
  const nav = document.getElementById("nav");

  if (!toggle || !nav) return;

  toggle.addEventListener("click", () => {
    const isOpen = nav.classList.toggle("is-open");
    toggle.setAttribute("aria-expanded", isOpen);
  });

  // Close the menu after tapping a link
  nav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      nav.classList.remove("is-open");
      toggle.setAttribute("aria-expanded", "false");
    });
  });
}


/* ---------- 2. HEADER SHADOW ON SCROLL ---------- */
function initHeaderShadow() {
  const header = document.getElementById("header");
  if (!header) return;

  window.addEventListener("scroll", () => {
    header.classList.toggle("is-scrolled", window.scrollY > 10);
  });
}


/* ---------- 3. STAT COUNTERS (run once when visible) ---------- */
function initCounters() {
  const counters = document.querySelectorAll("[data-count]");
  if (!counters.length || !("IntersectionObserver" in window)) return;

  const animate = (el) => {
    const target = Number(el.dataset.count);
    const duration = 1200; // milliseconds
    const start = performance.now();

    const step = (now) => {
      const progress = Math.min((now - start) / duration, 1);
      el.textContent = Math.floor(progress * target).toLocaleString("en-IN");
      if (progress < 1) requestAnimationFrame(step);
    };

    requestAnimationFrame(step);
  };

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        animate(entry.target);
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  counters.forEach((counter) => observer.observe(counter));
}


/* ---------- 4. ACTIVE NAV LINK ON SCROLL ---------- */
function initActiveNav() {
  const links = document.querySelectorAll(".nav__link");
  const sections = [...links]
    .map((link) => document.querySelector(link.getAttribute("href")))
    .filter(Boolean);

  window.addEventListener("scroll", () => {
    const position = window.scrollY + 140;

    sections.forEach((section) => {
      const link = document.querySelector(`.nav__link[href="#${section.id}"]`);
      const inView = position >= section.offsetTop &&
                     position < section.offsetTop + section.offsetHeight;
      if (link && inView) {
        links.forEach((l) => l.classList.remove("is-active"));
        link.classList.add("is-active");
      }
    });
  });
}


/* ---------- 5. INQUIRY FORM VALIDATION ---------- */
function initInquiryForm() {
  const form = document.getElementById("inquiryForm");
  const status = document.getElementById("formStatus");
  if (!form) return;

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const name = form.name.value.trim();
    const phone = form.phone.value.trim();
    const phoneValid = /^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/.test(phone);

    // Reset previous error state
    form.name.classList.remove("has-error");
    form.phone.classList.remove("has-error");
    status.className = "form__status";

    if (!name) {
      form.name.classList.add("has-error");
      return showStatus("Please enter your full name.", "is-error");
    }

    if (!phoneValid) {
      form.phone.classList.add("has-error");
      return showStatus("Please enter a valid 10-digit mobile number.", "is-error");
    }

    // TODO: send the data to your backend / WhatsApp / email service here.
    form.reset();
    showStatus("Thank you! A Morbi field coordinator will call you shortly.", "is-success");
  });

  function showStatus(message, type) {
    status.textContent = message;
    status.classList.add(type);
  }
}


/* ---------- 6. SCROLL REVEAL ANIMATION ---------- */
function initScrollReveal() {
  if (!("IntersectionObserver" in window)) return;

  // [ CSS selector, direction, delay between siblings (seconds) ]
  const targets = [
    // ----- Home page -----
    [".hero__content > *", "",      0.10],
    [".portrait",          "right", 0],
    [".section__head",     "",      0],
    [".impact__head",      "",      0],
    [".service, .drive, .stat", "", 0.12],
    [".home-about > *",    "",      0.15],
    [".vision__card",      "left",  0],
    [".vision__side > *",  "right", 0.15],
    [".hq__info",          "left",  0],
    [".form",              "right", 0],

    // ----- About page -----
    [".about-hero__text > *", "",   0.10],
    [".about-id-card",     "right", 0],
    [".about-founder__card", "left", 0],
    [".about-founder__info > *", "", 0.12],
    [".about-center-head", "",      0],
    [".about-honour, .about-cert, .about-big-quote, .about-progress, .about-certs__head, .about-cta", "", 0.12],
    [".about-progress__item", "",   0.12],

    // ----- Both pages -----
    [".footer__grid > *",  "",      0.12]
  ];

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      const el = entry.target;
      el.classList.add("is-visible");
      observer.unobserve(el);

      // Remove reveal classes after the animation so hover effects stay fast
      const wait = 800 + parseFloat(el.style.getPropertyValue("--delay") || 0) * 1000;
      setTimeout(() => {
        el.classList.remove("reveal", "reveal--left", "reveal--right", "is-visible");
        el.style.removeProperty("--delay");
      }, wait);
    });
  }, { threshold: 0.15 });

  targets.forEach(([selector, direction, stagger]) => {
    document.querySelectorAll(selector).forEach((el) => {
      const position = Array.from(el.parentElement.children).indexOf(el);

      el.classList.add("reveal");
      if (direction) el.classList.add("reveal--" + direction);
      el.style.setProperty("--delay", (position * stagger) + "s");
      observer.observe(el);
    });
  });
}


/* ---------- START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  initMobileMenu();
  initHeaderShadow();
  initCounters();
  initActiveNav();
  initInquiryForm();
  initScrollReveal();
});


/* =========================================================
   Activities & News  |  activities.js  (activities.html only)

   - Load AFTER script.js (menu, header shadow, number counters)
   - Features: category filter, taluka filter, live search,
     result count, empty state, scroll animation
   ========================================================= */


/* ---------- 0. STATE & ELEMENTS ---------- */
const filters = { category: "all", taluka: "all", query: "" };

const volunteerById = (id) => document.getElementById(id);
const activityById = (id) => document.getElementById(id);

// Every filterable item (the flagship story + the cards) has data-activity
const items = Array.from(document.querySelectorAll("[data-activity]"));


/* ---------- 1. APPLY FILTERS ---------- */
function applyFilters() {
  let visible = 0;

  items.forEach((item) => {
    const matchCategory = filters.category === "all" || item.dataset.category === filters.category;
    const matchTaluka   = filters.taluka === "all"   || item.dataset.taluka === filters.taluka;
    const matchSearch   = !filters.query || item.dataset.search.includes(filters.query);
    const show = matchCategory && matchTaluka && matchSearch;

    // Restart the small fade-in only for items that were hidden before
    if (show && item.hidden) {
      item.classList.remove("is-shown");
      void item.offsetWidth;
      item.classList.add("is-shown");
    }

    item.hidden = !show;
    if (show) visible += 1;
  });

  activityById("resultCount").textContent = visible
    ? `Showing ${visible} dispatch${visible > 1 ? "es" : ""}`
    : "No dispatches found";

  activityById("emptyState").hidden = visible > 0;
}


/* ---------- 2. CATEGORY + TALUKA BUTTONS ---------- */
function initFilterButtons() {
  // One handler for both groups. data-group tells us which filter to change.
  document.querySelectorAll("[data-group]").forEach((button) => {
    button.addEventListener("click", () => {
      const group = button.dataset.group;            // "category" or "taluka"
      filters[group] = button.dataset.value;

      document.querySelectorAll(`[data-group="${group}"]`).forEach((b) => {
        b.classList.toggle("is-active", b === button);
        b.setAttribute("aria-pressed", b === button);
      });

      applyFilters();
    });
  });
}


/* ---------- 3. LIVE SEARCH ---------- */
function initSearch() {
  // Build the searchable text for every item once
  items.forEach((item) => {
    item.dataset.search = item.textContent.replace(/\s+/g, " ").toLowerCase();
  });

  let timer;
  activityById("activitySearch").addEventListener("input", (event) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      filters.query = event.target.value.trim().toLowerCase();
      applyFilters();
    }, 200);   // small delay so it does not run on every key press
  });
}


/* ---------- 4. RESET BUTTON (empty state) ---------- */
function initReset() {
  activityById("resetFilters").addEventListener("click", () => {
    filters.category = "all";
    filters.taluka = "all";
    filters.query = "";
    activityById("activitySearch").value = "";

    document.querySelectorAll("[data-group]").forEach((b) => {
      const isAll = b.dataset.value === "all";
      b.classList.toggle("is-active", isAll);
      b.setAttribute("aria-pressed", isAll);
    });

    applyFilters();
  });
}


/* ---------- 5. SCROLL REVEAL ANIMATION ---------- */
function initActivityReveal() {
  if (!("IntersectionObserver" in window)) return;

  // [ selector, direction, delay between siblings (seconds) ]
  const targets = [
    [".activity-hero__panel > *", "", 0.10],
    [".activity-toolbar",         "", 0],
    [".activity-feature",         "", 0],
    [".activity-head",            "", 0],
    [".activity-card",            "", 0.12],
    [".activity-stats",           "", 0]
  ];

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      const el = entry.target;
      el.classList.add("is-visible");
      observer.unobserve(el);

      const wait = 800 + parseFloat(el.style.getPropertyValue("--delay") || 0) * 1000;
      setTimeout(() => {
        el.classList.remove("reveal", "is-visible");
        el.style.removeProperty("--delay");
      }, wait);
    });
  }, { threshold: 0.15 });

  targets.forEach(([selector, direction, stagger]) => {
    document.querySelectorAll(selector).forEach((el) => {
      const position = Array.from(el.parentElement.children).indexOf(el);
      el.classList.add("reveal");
      if (direction) el.classList.add("reveal--" + direction);
      el.style.setProperty("--delay", (position * stagger) + "s");
      observer.observe(el);
    });
  });
}


/* ---------- START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  initFilterButtons();
  initSearch();
  initReset();
  applyFilters();
  initActivityReveal();
});







/* =========================================================
   Volunteer Network  |  volunteers.js  (volunteers.html only)

   - Load AFTER script.js (menu, header shadow, number counters)
   - Features: volunteer registration form, availability cards,
     citizen grievance form, success state, scroll animation
   ========================================================= */


/* ---------- 0. SETTINGS & HELPERS ---------- */
const VOLUNTEER_PHONE_PATTERN = /^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/;

const byId = (id) => document.getElementById(id);

// Turns the red error border on/off for one field and returns true when it is valid
function checkField(input, isValid) {
  const wrapper = input.closest(".volunteer-field, .volunteer-check");
  if (wrapper) wrapper.classList.toggle("has-error", !isValid);
  return isValid;
}

function showStatus(element, message) {
  element.textContent = message;
  element.className = "volunteer-status" + (message ? " is-error" : "");
}

// Fake "sending" delay. Replace with fetch() to your server later.
function fakeSend(button, onDone) {
  const original = button.innerHTML;
  button.disabled = true;
  button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

  setTimeout(() => {
    button.disabled = false;
    button.innerHTML = original;
    onDone();
  }, 700);
}


/* ---------- 1. AVAILABILITY RADIO CARDS ---------- */
function initRadioCards() {
  const cards = document.querySelectorAll(".volunteer-radio");

  cards.forEach((card) => {
    card.querySelector("input").addEventListener("change", () => {
      cards.forEach((c) => c.classList.toggle("is-checked", c.querySelector("input").checked));
    });
  });
}


/* ---------- 2. VOLUNTEER REGISTRATION FORM ---------- */
function initVolunteerForm() {
  const form = volunteerById("volunteerForm");
  const status = volunteerById("volunteerStatus");
  const success = volunteerById("volunteerSuccess");

if (!form || !status || !success) return;

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const checks = [
      checkField(form.vname,   form.vname.value.trim().length >= 2),
      checkField(form.vphone,  VOLUNTEER_PHONE_PATTERN.test(form.vphone.value.trim())),
      checkField(form.varea,   form.varea.value !== ""),
      checkField(form.vrole,   form.vrole.value !== ""),
      checkField(form.vconsent, form.vconsent.checked)
    ];

    if (checks.includes(false)) {
      return showStatus(status, "Please complete the highlighted fields to continue.");
    }
    showStatus(status, "");

    fakeSend(volunteerById("volunteerSubmit"), () => {
      // TODO: send the form data (name, phone, area, role, availability) to your server here.
      const reference = "YIG-V-" + new Date().getFullYear() + "-" + Math.floor(1000 + Math.random() * 9000);

      volunteerById("volunteerName").textContent = form.vname.value.trim();
      volunteerById("volunteerRef").textContent = reference;
      form.hidden = true;
      success.hidden = false;
    });
  });

  // "Submit another response" button
  volunteerById("volunteerAgain").addEventListener("click", () => {
    form.reset();
    document.querySelectorAll(".volunteer-radio").forEach((c) => {
      c.classList.toggle("is-checked", c.querySelector("input").checked);
    });
    success.hidden = true;
    form.hidden = false;
  });
}


/* ---------- 3. CITIZEN GRIEVANCE FORM ---------- */
function initGrievanceForm() {
  const form = volunteerById("grievanceForm");
  const status = volunteerById("grievanceStatus");

  if (!form || !status || !success) return;
  
  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const checks = [
      checkField(form.gname,    form.gname.value.trim().length >= 2),
      checkField(form.gphone,   VOLUNTEER_PHONE_PATTERN.test(form.gphone.value.trim())),
      checkField(form.gmessage, form.gmessage.value.trim().length >= 10)
    ];

    if (checks.includes(false)) {
      return showStatus(status, "Please enter your name, a valid phone number and at least 10 characters of detail.");
    }

    fakeSend(volunteerById("grievanceSubmit"), () => {
      // TODO: send the inquiry to your server / email here.
      form.reset();
      status.textContent = "Thank you. Your request has reached the Morbi desk — we will call you soon.";
      status.className = "volunteer-status";
      status.style.color = "var(--green)";
    });
  });
}


/* ---------- 4. SCROLL REVEAL ANIMATION ---------- */
function initVolunteerReveal() {
  if (!("IntersectionObserver" in window)) return;

  // [ selector, direction, delay between siblings (seconds) ]
  const targets = [
    [".volunteer-hero .container > *", "", 0.10],
    [".volunteer-stat",                "", 0.12],
    [".volunteer-layout > .volunteer-card", "left",  0],
    [".volunteer-side > *",            "right", 0.15],
    [".volunteer-recog__head",         "", 0],
    [".volunteer-recog",               "", 0.12]
  ];

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;

      const el = entry.target;
      el.classList.add("is-visible");
      observer.unobserve(el);

      const wait = 800 + parseFloat(el.style.getPropertyValue("--delay") || 0) * 1000;
      setTimeout(() => {
        el.classList.remove("reveal", "reveal--left", "reveal--right", "is-visible");
        el.style.removeProperty("--delay");
      }, wait);
    });
  }, { threshold: 0.15 });

  targets.forEach(([selector, direction, stagger]) => {
    document.querySelectorAll(selector).forEach((el) => {
      const position = Array.from(el.parentElement.children).indexOf(el);
      el.classList.add("reveal");
      if (direction) el.classList.add("reveal--" + direction);
      el.style.setProperty("--delay", (position * stagger) + "s");
      observer.observe(el);
    });
  });
}


/* ---------- START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  initRadioCards();
  initVolunteerForm();
  initGrievanceForm();
  initVolunteerReveal();
});
