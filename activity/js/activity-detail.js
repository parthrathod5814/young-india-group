/* =========================================================
   Activity page  |  activity-detail.js  (activity.html only)

   - Load AFTER script.js (menu, header shadow, counters)
   - Each feature is its own small function:
     1. Language switch (Gujarati / English / Hindi)
     2. Category chips (filter the recent reports)
     3. Photo carousel (arrows, thumbnails, slideshow, swipe)
     4. Share (WhatsApp + Instagram)
     5. Scroll animation
   ========================================================= */

const LANGS = ["gu", "en", "hi"];
const GUJARATI_DIGITS = "૦૧૨૩૪૫૬૭૮૯";
let lang = "gu";


const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

// "captionGu" for data-caption-gu, "photoHi" for data-photo-hi ...
const attr = (el, name) => el.dataset[name + lang[0].toUpperCase() + lang[1]] || "";
const digits = (text) => (lang === "gu" ? String(text).replace(/\d/g, (d) => GUJARATI_DIGITS[d]) : String(text));


/* ---------- 1. LANGUAGE SWITCH ---------- */
function setLanguage(code, save = true) {
  lang = LANGS.includes(code) ? code : "gu";

  document.documentElement.dataset.lang = lang;       // CSS shows only the chosen language
  document.documentElement.lang = lang;

  $$("[data-set-lang]").forEach((button) => {
    const active = button.dataset.setLang === lang;
    button.classList.toggle("is-active", active);
    button.setAttribute("aria-pressed", active);
  });

  const heading = $(`.actd-title .t[lang="${lang}"]`);
  if (heading) document.title = heading.textContent + " | Young India Group";

  updateWhatsappLink();
  updateGalleryText();

  if (save) {
    try { localStorage.setItem("yig_lang", lang); } catch (error) { /* private mode */ }
    const url = new URL(location.href);
    url.searchParams.set("lang", lang);
    history.replaceState(null, "", url);
  }
}

function initLanguage() {
  const fromUrl = new URLSearchParams(location.search).get("lang");
  let saved = null;
  try { saved = localStorage.getItem("yig_lang"); } catch (error) { /* ignore */ }

  setLanguage(fromUrl || saved || "gu", false);
  $$("[data-set-lang]").forEach((button) => button.addEventListener("click", () => setLanguage(button.dataset.setLang)));
}


/* ---------- 2. CATEGORY CHIPS ---------- */
// Click a chip to see only that type in "Recent News & Dispatches". Click again to see all.
function initCategoryChips() {
  const chips = $$("[data-type-filter]");
  const cards = $$(".actd-news[data-type]");
  const empty = $("#actdNoMatch");
  let active = null;

  chips.forEach((chip) => chip.addEventListener("click", () => {
    active = active === chip.dataset.typeFilter ? null : chip.dataset.typeFilter;

    chips.forEach((c) => {
      c.classList.toggle("is-active", c.dataset.typeFilter === active);
      c.classList.toggle("is-current", active === null && c.classList.contains("is-current"));
    });

    let shown = 0;
    cards.forEach((card) => {
      const show = !active || card.dataset.type === active;
      card.hidden = !show;
      if (show) shown += 1;
    });
    empty.hidden = shown > 0;

    $("#actdRelated").scrollIntoView({ behavior: "smooth", block: "start" });
  }));
}


/* ---------- 3. PHOTO CAROUSEL ---------- */
let gallery = null;

function initGallery() {
  const root = $("#actdGallery");
  if (!root) return;

  const thumbs = $$(".actd-thumb", root);
  const main = $("#actdMain");
  const stage = $("#actdStage");
  const play = $("#actdPlay");
  let index = 0;
  let timer = null;

  function show(next) {
    index = (next + thumbs.length) % thumbs.length;
    const src = thumbs[index].dataset.src;

    main.classList.add("is-fading");
    setTimeout(() => { main.src = src; main.classList.remove("is-fading"); }, 180);

    thumbs.forEach((thumb, i) => thumb.classList.toggle("is-active", i === index));
    const box = thumbs[index].parentElement;                       // keep the active thumbnail in view
    box.scrollTo({ left: thumbs[index].offsetLeft - box.clientWidth / 2 + thumbs[index].clientWidth / 2, behavior: "smooth" });

    updateGalleryText();
    new Image().src = thumbs[(index + 1) % thumbs.length].dataset.src;   // load the next picture early
  }

  function stop() { clearInterval(timer); timer = null; play.classList.remove("is-playing"); }
  function start() { timer = setInterval(() => show(index + 1), 4000); play.classList.add("is-playing"); }

  $("#actdPrev").addEventListener("click", () => { stop(); show(index - 1); });
  $("#actdNext").addEventListener("click", () => { stop(); show(index + 1); });
  thumbs.forEach((thumb, i) => thumb.addEventListener("click", () => { stop(); show(i); }));
  play.addEventListener("click", () => (timer ? stop() : start()));

  // Keyboard arrows while the picture is focused
  stage.addEventListener("keydown", (e) => {
    if (e.key === "ArrowLeft") { stop(); show(index - 1); }
    if (e.key === "ArrowRight") { stop(); show(index + 1); }
  });

  // Swipe on phones
  let startX = 0;
  stage.addEventListener("touchstart", (e) => { startX = e.touches[0].clientX; }, { passive: true });
  stage.addEventListener("touchend", (e) => {
    const distance = e.changedTouches[0].clientX - startX;
    if (Math.abs(distance) > 40) { stop(); show(index + (distance < 0 ? 1 : -1)); }
  });

  // Pause the slideshow while the mouse is over the picture
  stage.addEventListener("mouseenter", () => { if (timer) { clearInterval(timer); timer = "paused"; } });
  stage.addEventListener("mouseleave", () => { if (timer === "paused") start(); });

  gallery = { root, thumbs, getIndex: () => index };
  updateGalleryText();
}

// Caption + "Photo 1 / 5" in the chosen language
function updateGalleryText() {
  if (!gallery) return;
  const { root, thumbs, getIndex } = gallery;
  const index = getIndex();
  const label = attr(root, "photo").replace("{x}", digits(String(index + 1).padStart(2, "0"))).replace("{y}", digits(String(thumbs.length).padStart(2, "0")));

  $("#actdCaption").textContent = attr(thumbs[index], "caption");
  $("#actdCapLabel").textContent = label;
  $("#actdCounter").textContent = label;
  $("#actdMain").alt = attr(thumbs[index], "caption");
}


/* ---------- 4. SHARE (WHATSAPP + INSTAGRAM) ---------- */
function updateWhatsappLink() {
  const link = $("#shareWhatsapp");
  const heading = $(`.actd-title .t[lang="${lang}"]`);
  if (!link || !heading) return;

  const url = new URL(location.href);
  url.searchParams.set("lang", lang);
  link.href = "https://wa.me/?text=" + encodeURIComponent(heading.textContent + " " + url.href);
}

// Small message at the bottom of the screen
let activitytoastTimer;
function showToast(message) {
  let toast = $(".actd-toast");
  if (!toast) {
    toast = document.createElement("div");
    toast.className = "actd-toast";
    toast.setAttribute("role", "status");
    document.body.append(toast);
  }
  toast.textContent = message;
  toast.classList.add("is-show");
  clearTimeout(activitytoastTimer);
  activitytoastTimer = setTimeout(() => toast.classList.remove("is-show"), 3500);
}

// Instagram has no "share link" address, so we copy the link and open Instagram
function initInstagramShare() {
  const button = $("#shareInstagram");
  if (!button) return;

  button.addEventListener("click", async () => {
    const url = new URL(location.href);
    url.searchParams.set("lang", lang);

    try {
      await navigator.clipboard.writeText(url.href);
    } catch (error) {                                   // older browsers
      const box = document.createElement("textarea");
      box.value = url.href;
      document.body.append(box);
      box.select();
      document.execCommand("copy");
      box.remove();
    }

    showToast(attr(button, "copied"));
    window.open("https://www.instagram.com/", "_blank", "noopener");
  });
}


/* ---------- 5. SCROLL ANIMATION ---------- */
function initReveal() {
  if (!("IntersectionObserver" in window)) return;

  // [ selector, delay between siblings (seconds) ]
  const targets = [
    [".actd-chips > *", 0.08], [".actd-title", 0], [".actd-byline", 0], [".actd-cover", 0],
    [".actd-article .actd-card", 0.1], [".actd-gallery", 0], [".actd-related__head", 0], [".actd-news", 0.12]
  ];

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (!entry.isIntersecting) return;
      const el = entry.target;
      el.classList.add("is-visible");
      observer.unobserve(el);

      setTimeout(() => {                                // remove the helper classes so hover effects stay quick
        el.classList.remove("reveal", "is-visible");
        el.style.removeProperty("--delay");
      }, 800 + parseFloat(el.style.getPropertyValue("--delay") || 0) * 1000);
    });
  }, { threshold: 0.12 });

  targets.forEach(([selector, stagger]) => $$(selector).forEach((el) => {
    el.classList.add("reveal");
    el.style.setProperty("--delay", ([...el.parentElement.children].indexOf(el) * stagger) + "s");
    observer.observe(el);
  }));
}


/* ---------- START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  initGallery();
  initLanguage();           // after the gallery, so the captions get the right language
  initCategoryChips();
  initInstagramShare();
  initReveal();
});