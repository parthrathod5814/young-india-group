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
   Blood Emergency Hub  |  blood-hub.js  (blood-hub.html only)

   - Load AFTER script.js (menu, header shadow, number counters)
   - Features: SOS + donor forms, live stream with auto-refresh,
     blood compatibility checker, scroll animation
   ========================================================= */


/* ---------- 0. SETTINGS & DATA ---------- */
const BLOOD_GROUPS = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"];
const REFRESH_SECONDS = 30;     // auto-refresh interval
const MAX_REQUESTS = 6;         // cards kept on screen
const PHONE_PATTERN = /^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/;

const STATUS = {
  active:    { label: "ACTIVE SOS",                 icon: "fa-heart-pulse" },
  enroute:   { label: "DONOR EN ROUTE",             icon: "fa-motorcycle" },
  fulfilled: { label: "FULFILLED (સફળ રક્તદાન)",    icon: "fa-circle-check" }
};

// Demo data. To use a real server, replace refreshStream() with a fetch() call.
const requests = [
  { id: 1, status: "active",    patient: "Govindbhai Bharwad",  hospital: "Morbi Civil Hospital", ward: "ICU Bed 7",
    group: "O-", units: "3 Units (Whole Blood)",  note: ["Condition", "Multiple Trauma Accident"], coord: "Ramesh R.",   createdAt: Date.now() - 8 * 60000 },
  { id: 2, status: "enroute",   patient: "Kailasben Prajapati", hospital: "Krishna Hospital",     ward: "Maternity Wing",
    group: "B+", units: "2 Units (Packed Cells)", note: ["Status", "Donor Chirag M. reached lab"], coord: "Paresh Desai", createdAt: Date.now() - 22 * 60000 },
  { id: 3, status: "fulfilled", patient: "Child Meet (Age 6)",  hospital: "Ayush Hospital",       ward: "Thalassemia",
    group: "A+", units: "1 Unit Provided",        note: ["Volunteer Donor", "Nilesh Rabari (Morbi YIG)"], coord: "Dr. V. Trivedi", createdAt: Date.now() - 90 * 60000, doneAt: "11:20 AM" }
];

// New requests that "arrive" during auto-refresh (demo only)
const incomingPool = [
  { patient: "Hasmukhbhai Patel", hospital: "Morbi Civil Hospital", ward: "Emergency Ward", group: "AB-", units: "2 Units (Whole Blood)", note: ["Condition", "Road Accident"], coord: "Ramesh R." },
  { patient: "Rekhaben Solanki",  hospital: "Krishna Hospital",     ward: "Surgery Wing",   group: "A-",  units: "1 Unit (Packed Cells)", note: ["Condition", "Planned Surgery"], coord: "Paresh Desai" },
  { patient: "Baby of Meenaben",  hospital: "Ayush Hospital",       ward: "NICU",           group: "O+",  units: "1 Unit (Whole Blood)",  note: ["Condition", "Newborn Care"], coord: "Dr. V. Trivedi" }
];

let nextId = 10;
let poolIndex = 0;
let autoRefresh = true;
let secondsLeft = REFRESH_SECONDS;
let selectedGroup = "";


/* ---------- 1. SMALL HELPERS ---------- */
const activityById = (id) => document.getElementById(id);

function escapeHtml(text) {
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
}

function timeNow(withSeconds) {
  return new Date().toLocaleTimeString("en-IN", {
    hour: "2-digit", minute: "2-digit", second: withSeconds ? "2-digit" : undefined
  });
}

function minutesAgo(request) {
  return Math.max(0, Math.floor((Date.now() - request.createdAt) / 60000));
}

let toastTimer;
function showToast(message) {
  const toast = byId("toast");
  toast.textContent = message;
  toast.classList.add("is-show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove("is-show"), 3200);
}

function setStatus(element, message, type) {
  element.textContent = message;
  element.className = "blood-form__status " + (type || "");
}

function markField(input, hasError) {
  const field = input.closest(".blood-field");
  if (field) field.classList.toggle("has-error", hasError);
  return !hasError;
}


/* ---------- 2. DROPDOWNS & BLOOD GROUP BUTTONS ---------- */
function initBloodSelects() {
  document.querySelectorAll("select[data-blood]").forEach((select) => {
    BLOOD_GROUPS.forEach((group) => select.add(new Option(group, group)));
  });
}

function initGroupPicker() {
  const picker = byId("groupPicker");

  picker.innerHTML = ["A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"]
    .map((group) => `<button type="button" class="blood-group-btn" data-group="${group}">${group}</button>`)
    .join("");

  picker.addEventListener("click", (event) => {
    const button = event.target.closest(".blood-group-btn");
    if (!button) return;

    selectedGroup = button.dataset.group;
    picker.querySelectorAll(".blood-group-btn").forEach((b) => b.classList.toggle("is-selected", b === button));
    picker.closest(".blood-field").classList.remove("has-error");
  });
}


/* ---------- 3. SOS REQUEST FORM ---------- */
function initSosForm() {
  const form = byId("sosForm");
  const status = byId("sosStatus");

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const checks = [
      markField(form.patient, form.patient.value.trim().length < 2),
      markField(byId("groupPicker"), !selectedGroup),
      markField(form.attendant, !PHONE_PATTERN.test(form.attendant.value.trim()))
    ];

    if (checks.includes(false)) {
      return setStatus(status, "Please fill the patient name, blood group and a valid contact number.", "is-error");
    }

    const request = {
      id: nextId++,
      status: "active",
      patient: form.patient.value.trim(),
      hospital: form.hospital.value,
      ward: "Reported via website",
      group: selectedGroup,
      units: form.units.value,
      note: ["Attendant", form.attendant.value.trim()],
      coord: "Field Desk",
      createdAt: Date.now()
    };

    // TODO: send `request` to your server / WhatsApp API here.
    requests.unshift(request);
    renderStream(request.id);

    form.reset();
    selectedGroup = "";
    byId("groupPicker").querySelectorAll(".blood-group-btn").forEach((b) => b.classList.remove("is-selected"));

    setStatus(status, "SOS broadcast sent. Field coordinators are being alerted.", "is-success");
    showToast("Blood SOS broadcast — see the live stream below.");
  });
}


/* ---------- 4. DONOR REGISTRATION FORM ---------- */
function initDonorForm() {
  const form = byId("donorForm");
  const status = byId("donorStatus");

  form.addEventListener("submit", (event) => {
    event.preventDefault();

    const age = Number(form.age.value);
    const checks = [
      markField(form.fullname, form.fullname.value.trim().length < 2),
      markField(form.age, !(age >= 18 && age <= 60)),
      markField(form.whatsapp, !PHONE_PATTERN.test(form.whatsapp.value.trim())),
      markField(form.consent, !form.consent.checked)
    ];

    if (checks.includes(false)) {
      return setStatus(status, "Please check the highlighted fields (age 18–60, weight confirmation).", "is-error");
    }

    // TODO: send the donor details to your server here.
    form.reset();
    setStatus(status, "Thank you! You are now on the Morbi donor roster.", "is-success");
    showToast("Donor registration received. Thank you for saving lives!");
  });
}


/* ---------- 5. LIVE STREAM (RENDER + AUTO-REFRESH) ---------- */
function cardHtml(request, isNew) {
  const info = STATUS[request.status];
  const reported = request.status === "fulfilled"
    ? "Today, " + request.doneAt
    : (minutesAgo(request) === 0 ? "Reported: just now" : "Reported: " + minutesAgo(request) + " mins ago");

  const footAction = {
    active:    `<button class="blood-dispatch" data-dispatch="${request.id}"><i class="fa-solid fa-phone"></i> Dispatch Donor</button>`,
    enroute:   `<strong style="color: var(--orange-dark)"><i class="fa-solid fa-clock"></i> In Verification</strong>`,
    fulfilled: `<strong><i class="fa-solid fa-circle-check"></i> Life Saved</strong>`
  }[request.status];

  return `
    <article class="blood-card blood-card--${request.status}${isNew ? " is-new" : ""}">
      <div class="blood-card__top">
        <span class="blood-status"><i class="fa-solid ${info.icon}"></i> ${info.label}</span>
        <span>${reported}</span>
      </div>

      <div class="blood-card__main">
        <div>
          <h3>${escapeHtml(request.patient)}</h3>
          <p class="blood-card__where">${request.hospital} • ${request.ward}</p>
        </div>
        <span class="blood-type">${request.group}</span>
      </div>

      <div class="blood-card__details">
        <p><span>Units Needed:</span><span>${request.units}</span></p>
        <p><span>${request.note[0]}:</span><span>${escapeHtml(request.note[1])}</span></p>
      </div>

      <div class="blood-card__foot">
        <span>Coord: ${request.coord}</span>
        ${footAction}
      </div>
    </article>`;
}

function renderStream(newId) {
  byId("streamList").innerHTML = requests.map((r) => cardHtml(r, r.id === newId)).join("")
    || `<p class="blood-empty">No emergency requests right now.</p>`;

  const activeCount = requests.filter((r) => r.status !== "fulfilled").length;
  byId("activeCount").textContent = activeCount + " Active In Morbi";

  renderTicker();
}

function renderTicker() {
  const urgent = requests.filter((r) => r.status === "active");

  byId("tickerTrack").innerHTML = urgent.length
    ? urgent.map((r) => `<span><i class="fa-solid fa-droplet"></i>${r.hospital}: Need ${r.units.split(" (")[0]} ${r.group}</span>`).join("")
    : `<span>No critical demand right now — thank you, donors!</span>`;
}

// Demo simulation of server updates. Replace with fetch("/api/requests") for real data.
function refreshStream() {
  const toggleIcon = byId("autoToggle").querySelector("i");
  toggleIcon.parentElement.classList.add("is-spinning");
  setTimeout(() => toggleIcon.parentElement.classList.remove("is-spinning"), 900);

  requests.forEach((r) => {
    if (r.status === "enroute" && Math.random() < 0.4) {
      r.status = "fulfilled";
      r.doneAt = timeNow(false);
      r.note = ["Volunteer Donor", "Verified by Field Desk"];
    } else if (r.status === "active" && minutesAgo(r) >= 10 && Math.random() < 0.5) {
      r.status = "enroute";
      r.note = ["Status", "Donor on the way to hospital"];
    }
  });

  let newId;
  if (Math.random() < 0.5) {
    const item = incomingPool[poolIndex++ % incomingPool.length];
    newId = nextId++;
    requests.unshift({ ...item, id: newId, status: "active", createdAt: Date.now() });
  }

  while (requests.length > MAX_REQUESTS) {
    const oldDone = requests.map((r) => r.status).lastIndexOf("fulfilled");
    requests.splice(oldDone > -1 ? oldDone : requests.length - 1, 1);
  }

  secondsLeft = REFRESH_SECONDS;
  byId("lastUpdated").textContent = "Updated " + timeNow(true);
  renderStream(newId);
}

function updateToggleLabel() {
  const toggle = byId("autoToggle");
  toggle.classList.toggle("is-off", !autoRefresh);
  toggle.setAttribute("aria-pressed", autoRefresh);
  byId("toggleText").textContent = autoRefresh ? `Auto-Refresh (Every ${REFRESH_SECONDS}s)` : "Auto-Refresh Off";
  byId("countdown").textContent = autoRefresh ? `Next refresh in ${secondsLeft}s` : "Paused";
}

function initLiveStream() {
  renderStream();
  byId("lastUpdated").textContent = "Updated " + timeNow(true);
  updateToggleLabel();

  // ON / OFF switch
  byId("autoToggle").addEventListener("click", () => {
    autoRefresh = !autoRefresh;
    secondsLeft = REFRESH_SECONDS;
    updateToggleLabel();
    showToast(autoRefresh ? "Auto-refresh turned on." : "Auto-refresh paused.");
  });

  // Manual refresh
  byId("refreshNow").addEventListener("click", () => {
    refreshStream();
    updateToggleLabel();
  });

  // "Dispatch Donor" buttons (event delegation, cards are re-rendered)
  byId("streamList").addEventListener("click", (event) => {
    const button = event.target.closest("[data-dispatch]");
    if (!button) return;

    const request = requests.find((r) => r.id === Number(button.dataset.dispatch));
    request.status = "enroute";
    request.note = ["Status", "Donor dispatched by field desk"];
    renderStream();
    showToast("Donor dispatch started for " + request.patient);
  });

  // 1-second timer
  setInterval(() => {
    if (!autoRefresh) return;
    secondsLeft -= 1;
    if (secondsLeft <= 0) refreshStream();
    updateToggleLabel();
  }, 1000);
}


/* ---------- 6. BLOOD COMPATIBILITY CHECKER ---------- */
function canDonate(donor, recipient) {
  const abo = (group) => group.replace(/[+-]/, "");
  const rh  = (group) => group.slice(-1);

  const aboOk = abo(donor) === "O" || abo(donor) === abo(recipient) || abo(recipient) === "AB";
  const rhOk  = rh(donor) === "-" || rh(recipient) === "+";
  return aboOk && rhOk;
}

function groupName(group) {
  const label = group.slice(-1) === "+" ? "Positive" : "Negative";
  const extra = { "O-": " (Universal Donor)", "AB+": " (Universal Recipient)" }[group] || "";
  return group.replace(/[+-]/, "") + " " + label + extra;
}

function buildMatrix() {
  const head = BLOOD_GROUPS.map((g) => `<th data-col="${g}">${g}</th>`).join("");

  const rows = BLOOD_GROUPS.map((recipient) => {
    const cells = BLOOD_GROUPS.map((donor) => {
      const yes = canDonate(donor, recipient);
      return `<td class="${yes ? "is-yes" : ""}" data-donor="${donor}">${yes ? "YES" : "NO"}</td>`;
    }).join("");

    return `<tr data-recipient="${recipient}" tabindex="0">
      <td><span class="blood-tag">${recipient}</span>${groupName(recipient)}</td>${cells}
    </tr>`;
  }).join("");

  byId("matrixTable").innerHTML = `
    <thead><tr><th>Recipient Blood Type (દર્દી) ↓ / Donor →</th>${head}</tr></thead>
    <tbody>${rows}</tbody>`;
}

function runCheck() {
  const patient = byId("checkPatient").value;
  const donor = byId("checkDonor").value;
  const ok = canDonate(donor, patient);
  const result = byId("checkResult");

  const donors = BLOOD_GROUPS.filter((g) => canDonate(g, patient));
  result.className = "blood-result " + (ok ? "is-yes" : "is-no");
  result.innerHTML = `
    <i class="fa-solid ${ok ? "fa-circle-check" : "fa-circle-xmark"}"></i>
    <strong>${donor} donor → ${patient} patient: ${ok ? "Compatible" : "NOT compatible"}</strong><br>
    Patient ${patient} can receive from: ${donors.map((g) => `<span>${g}</span>`).join("")}`;

  // Highlight the matrix
  const table = byId("matrixTable");
  table.querySelectorAll(".is-picked, .is-hit").forEach((el) => el.classList.remove("is-picked", "is-hit"));
  table.querySelector(`tr[data-recipient="${patient}"]`).classList.add("is-picked");
  table.querySelector(`th[data-col="${donor}"]`).classList.add("is-picked");
  table.querySelector(`tr[data-recipient="${patient}"] td[data-donor="${donor}"]`).classList.add("is-hit");
}

function initCompatibility() {
  buildMatrix();

  byId("checkPatient").value = "A+";
  byId("checkDonor").value = "O-";

  ["checkPatient", "checkDonor"].forEach((id) => byId(id).addEventListener("change", runCheck));
  byId("checkBtn").addEventListener("click", runCheck);

  // Click (or Enter on) a table row to pick that patient group
  const pickRow = (event) => {
    const row = event.target.closest("tr[data-recipient]");
    if (!row || (event.type === "keydown" && event.key !== "Enter")) return;
    byId("checkPatient").value = row.dataset.recipient;
    runCheck();
  };
  byId("matrixTable").addEventListener("click", pickRow);
  byId("matrixTable").addEventListener("keydown", pickRow);

  runCheck();
}


/* ---------- 7. SCROLL REVEAL ANIMATION ---------- */
function initBloodReveal() {
  if (!("IntersectionObserver" in window)) return;

  // [ selector, direction, delay between siblings (seconds) ]
  const targets = [
    [".blood-hero .container > *", "", 0.10],
    [".blood-stat",           "",      0.12],
    [".blood-form",           "",      0.15],
    [".blood-stream__head",   "",      0],
    [".blood-card",           "",      0.12],
    [".blood-matrix",         "",      0],
    [".blood-note",           "",      0.12]
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
  initBloodSelects();
  initGroupPicker();
  initSosForm();
  initDonorForm();
  initLiveStream();
  initCompatibility();
  initBloodReveal();
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