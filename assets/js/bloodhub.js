(() => {
  "use strict";

  const BLOOD_GROUPS = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"];
  const REFRESH_SECONDS = 30;
  const PHONE_PATTERN = /^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/;
  const STATUS = {
    active: { label: "ACTIVE SOS", icon: "fa-heart-pulse" },
    enroute: { label: "DONOR EN ROUTE", icon: "fa-motorcycle" },
    fulfilled: { label: "FULFILLED (સફળ રક્તદાન)", icon: "fa-circle-check" }
  };

  const byId = id => document.getElementById(id);
  let selectedGroup = "";
  let toastTimer;
  let refreshTimer;
  let secondsLeft = REFRESH_SECONDS;

  const stream = byId("streamList");
  let shownCount = Number(stream?.dataset.initialCount || 0);
  let totalCount = Number(stream?.dataset.total || 0);

  function pageUrl(action = "") {
    const url = new URL(window.location.href);
    url.hash = "";
    url.search = "";
    if (action) url.searchParams.set("action", action);
    return url;
  }

  function escapeHtml(value) {
    const span = document.createElement("span");
    span.textContent = String(value ?? "");
    return span.innerHTML;
  }

  function toast(message) {
    const el = byId("toast");
    if (!el) return;
    el.textContent = message;
    el.classList.add("is-show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => el.classList.remove("is-show"), 3200);
  }

  function setStatus(el, message, type = "") {
    if (!el) return;
    el.textContent = message;
    el.className = "blood-form__status" + (type ? ` ${type}` : "");
  }

  function markField(input, invalid) {
    const wrapper = input?.closest(".blood-field, .blood-check");
    wrapper?.classList.toggle("has-error", invalid);
    return !invalid;
  }

  async function request(url, options = {}) {
    const response = await fetch(url, {
      credentials: "same-origin",
      cache: "no-store",
      ...options
    });

    let data;
    try {
      data = await response.json();
    } catch {
      throw new Error("The server returned an invalid response.");
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.message || "The request could not be completed.");
    }
    return data;
  }

  function initBloodSelects() {
    document.querySelectorAll("select[data-blood]").forEach(select => {
      BLOOD_GROUPS.forEach(group => {
        if (![...select.options].some(option => option.value === group)) {
          select.add(new Option(group, group));
        }
      });
    });
  }

  function initGroupPicker() {
    const picker = byId("groupPicker");
    if (!picker) return;

    picker.innerHTML = ["A+", "A-", "B+", "B-", "O+", "O-", "AB+", "AB-"]
      .map(group => `<button type="button" class="blood-group-btn" data-group="${group}" aria-pressed="false">${group}</button>`)
      .join("");

    picker.addEventListener("click", event => {
      const button = event.target.closest("[data-group]");
      if (!button) return;
      selectedGroup = button.dataset.group;
      picker.querySelectorAll("[data-group]").forEach(item => {
        const active = item === button;
        item.classList.toggle("is-selected", active);
        item.setAttribute("aria-pressed", String(active));
      });
      picker.closest(".blood-field")?.classList.remove("has-error");
    });
  }

  function initSosForm() {
    const form = byId("sosForm");
    if (!form) return;

    const hospital = byId("hospital");
    const hospitalOther = byId("hospitalOther");

    hospital?.addEventListener("change", () => {
      const other = hospital.value === "Other";
      if (!hospitalOther) return;
      hospitalOther.hidden = !other;
      hospitalOther.required = other;
      if (!other) hospitalOther.value = "";
      if (other) hospitalOther.focus();
    });

    form.addEventListener("submit", async event => {
      event.preventDefault();

      const patient = form.elements.namedItem("patient");
      const contact = form.elements.namedItem("contact");
      const condition = form.elements.namedItem("patient_condition");
      const units = form.elements.namedItem("units");
      const hospitalValue = hospital?.value === "Other"
        ? (hospitalOther?.value || "").trim()
        : (hospital?.value || "").trim();

      const checks = [
        markField(patient, !patient || patient.value.trim().length < 2),
        markField(byId("groupPicker"), !selectedGroup),
        markField(contact, !contact || !PHONE_PATTERN.test(contact.value.trim())),
        markField(hospital, !hospitalValue),
        markField(condition, !condition || !["Emergency", "Normal"].includes(condition.value)),
        markField(units, !units || !["1", "2", "3", "4"].includes(units.value))
      ];

      if (checks.includes(false)) {
        setStatus(byId("sosStatus"), "Please complete the highlighted fields.", "is-error");
        return;
      }

      const data = new FormData(form);
      data.set("bloodhub_action", "submit_sos");
      data.set("blood_group", selectedGroup);
      data.set("hospital_address", hospitalValue);

      await submitForm(form, data, byId("sosStatus"), "Your blood request was submitted successfully.")
        .then(async success => {
          if (!success) return;
          form.reset();
          selectedGroup = "";
          byId("groupPicker")?.querySelectorAll("[data-group]").forEach(button => {
            button.classList.remove("is-selected");
            button.setAttribute("aria-pressed", "false");
          });
          if (hospitalOther) {
            hospitalOther.hidden = true;
            hospitalOther.required = false;
          }
          await loadRequests(true);
        });
    });
  }

  function initDonorRegistrationForm() {
    const form = byId("donorForm");
    if (!form) return;

    form.addEventListener("submit", async event => {
      event.preventDefault();

      const name = form.elements.namedItem("fullname");
      const group = form.elements.namedItem("group");
      const age = form.elements.namedItem("age");
      const phone = form.elements.namedItem("whatsapp");
      const area = form.elements.namedItem("area");
      const consent = form.elements.namedItem("consent");
      const numericAge = Number(age?.value);

      const checks = [
        markField(name, !name || name.value.trim().length < 2),
        markField(group, !group || !BLOOD_GROUPS.includes(group.value)),
        markField(age, !(numericAge >= 18 && numericAge <= 60)),
        markField(phone, !phone || !PHONE_PATTERN.test(phone.value.trim())),
        markField(area, !area || !area.value),
        markField(consent, !consent || !consent.checked)
      ];

      if (checks.includes(false)) {
        setStatus(byId("donorStatus"), "Please check the highlighted fields and confirm consent.", "is-error");
        return;
      }

      const data = new FormData(form);
      data.set("bloodhub_action", "register_donor");
      data.set("consent", "1");

      const success = await submitForm(
        form,
        data,
        byId("donorStatus"),
        "Registration received and sent to the admin team for approval."
      );

      if (success) form.reset();
    });
  }

  async function submitForm(form, data, status, successMessage) {
    const button = form.querySelector('[type="submit"]');
    const original = button?.innerHTML;

    if (button) {
      button.disabled = true;
      button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
    }

    try {
      const result = await request(pageUrl().toString(), {
        method: "POST",
        body: data
      });
      setStatus(status, result.message || successMessage, "is-success");
      toast(result.message || successMessage);
      return true;
    } catch (error) {
      setStatus(status, error.message, "is-error");
      toast(error.message);
      return false;
    } finally {
      if (button) {
        button.disabled = false;
        button.innerHTML = original;
      }
    }
  }

  function cardStatus(status) {
    if (status === "In-Transit") return "enroute";
    if (status === "Fulfilled") return "fulfilled";
    return "active";
  }

  function relativeTime(value) {
    const date = new Date(String(value || "").replace(" ", "T"));
    if (Number.isNaN(date.getTime())) return "Recently reported";
    const mins = Math.max(0, Math.floor((Date.now() - date.getTime()) / 60000));
    return mins ? `Reported ${mins} min ago` : "Reported just now";
  }

  function cardHtml(item) {
    const status = cardStatus(item.status);
    const info = STATUS[status];
    const canAcceptInterest = ["Active", "In-Transit"].includes(item.status);
    const requestLabel = `${item.patient} — ${item.blood_group} — ${item.hospital_address}`;

    return `
      <article class="blood-card blood-card--${status}" data-request-id="${Number(item.id)}">
        <div class="blood-card__top">
          <span class="blood-status"><i class="fa-solid ${info.icon}"></i> ${info.label}</span>
          <span>${escapeHtml(relativeTime(item.created_at))}</span>
        </div>
        <div class="blood-card__main">
          <div>
            <h3>${escapeHtml(item.patient)}</h3>
            <p class="blood-card__where">${escapeHtml(item.hospital_address)}</p>
          </div>
          <span class="blood-type">${escapeHtml(item.blood_group)}</span>
        </div>
        <div class="blood-card__details">
          <p><span>Patient Condition:</span><span>${escapeHtml(item.patient_condition)}</span></p>
          <p><span>Units Needed:</span><span>${Number(item.units)} ${Number(item.units) === 1 ? "Unit" : "Units"}</span></p>
          <p><span>Request Status:</span><span>${escapeHtml(item.status)}</span></p>
        </div>
        ${canAcceptInterest ? `
          <div class="blood-card__foot">
            <button type="button" class="blood-dispatch" data-become-donor
              data-request-id="${Number(item.id)}"
              data-request-label="${escapeHtml(requestLabel)}">
              <i class="fa-solid fa-hand-holding-heart"></i> Become a Donor
            </button>
          </div>` : ""}
      </article>`;
  }

  function renderRequests(items, replace = true) {
    const target = byId("streamList");
    if (!target) return;

    if (replace) {
      target.innerHTML = items.length
        ? items.map(cardHtml).join("")
        : '<p class="blood-empty">No emergency requests right now.</p>';
    } else if (items.length) {
      target.insertAdjacentHTML("beforeend", items.map(cardHtml).join(""));
    }

    const active = items.filter(item => ["Active", "In-Transit"].includes(item.status));
    const activeCount = byId("activeCount");
    if (activeCount) activeCount.textContent = `${active.length} Active In Morbi`;

    const urgent = items.filter(item => item.status === "Active");
    const ticker = byId("tickerTrack");
    if (ticker) {
      ticker.innerHTML = urgent.length
        ? urgent.map(item => `<span><i class="fa-solid fa-droplet"></i>${escapeHtml(item.hospital_address)}: Need ${Number(item.units)} ${escapeHtml(item.blood_group)}</span>`).join("")
        : "<span>No critical demand right now — thank you, donors!</span>";
    }
  }

  async function loadRequests(reset = false) {
    const offset = reset ? 0 : shownCount;
    const url = pageUrl("stream");
    url.searchParams.set("offset", String(offset));
    url.searchParams.set("limit", "9");

    try {
      const result = await request(url.toString());
      const items = Array.isArray(result.requests) ? result.requests : [];

      renderRequests(items, reset);
      totalCount = Number(result.total || 0);
      shownCount = reset ? items.length : shownCount + items.length;

      const more = byId("seeMoreRequests");
      if (more) more.hidden = shownCount >= totalCount;

      const updated = byId("lastUpdated");
      if (updated) updated.textContent = "Updated " + new Date().toLocaleTimeString("en-IN");

      return items;
    } catch (error) {
      console.error("Could not load blood requests:", error);
      setStatus(byId("streamStatus"), error.message, "is-error");
      return [];
    }
  }

  function initSeeMore() {
    byId("seeMoreRequests")?.addEventListener("click", async event => {
      const button = event.currentTarget;
      button.disabled = true;
      try {
        await loadRequests(false);
      } finally {
        button.disabled = false;
      }
    });
  }

  function initRefresh() {
    const toggle = byId("autoToggle");
    if (!toggle) return;

    let autoRefresh = true;

    function updateLabels() {
      const text = byId("toggleText");
      const countdown = byId("countdown");
      toggle.setAttribute("aria-pressed", String(autoRefresh));
      toggle.classList.toggle("is-off", !autoRefresh);
      if (text) text.textContent = autoRefresh
        ? `Auto-Refresh (Every ${REFRESH_SECONDS}s)`
        : "Auto-Refresh Off";
      if (countdown) countdown.textContent = autoRefresh
        ? `Next refresh in ${secondsLeft}s`
        : "Paused";
    }

    toggle.addEventListener("click", () => {
      autoRefresh = !autoRefresh;
      secondsLeft = REFRESH_SECONDS;
      updateLabels();
    });

    byId("refreshNow")?.addEventListener("click", async () => {
      await loadRequests(true);
      secondsLeft = REFRESH_SECONDS;
      updateLabels();
    });

    loadRequests(true);
    updateLabels();

    refreshTimer = window.setInterval(() => {
      if (!autoRefresh) return;
      secondsLeft--;
      if (secondsLeft <= 0) {
        secondsLeft = REFRESH_SECONDS;
        loadRequests(true);
      }
      updateLabels();
    }, 1000);
  }

  function initInterestModal() {
    const modal = byId("donorModal");
    const form = byId("donorInterestForm");
    if (!modal || !form) return;

    document.addEventListener("click", event => {
      const trigger = event.target.closest("[data-become-donor]");
      if (!trigger) return;

      byId("interestRequestId").value = trigger.dataset.requestId || "";
      byId("selectedRequestLabel").textContent = trigger.dataset.requestLabel || "";
      form.reset();
      setStatus(byId("donorInterestStatus"), "");
      modal.hidden = false;
      document.body.classList.add("modal-open");
      byId("interestName")?.focus();
    });

    function close() {
      modal.hidden = true;
      document.body.classList.remove("modal-open");
    }

    byId("closeDonorModal")?.addEventListener("click", close);
    modal.addEventListener("click", event => {
      if (event.target === modal) close();
    });
    document.addEventListener("keydown", event => {
      if (event.key === "Escape" && !modal.hidden) close();
    });

    form.addEventListener("submit", async event => {
      event.preventDefault();

      const requestId = form.elements.namedItem("request_id")?.value;
      const name = form.elements.namedItem("fullname");
      const group = form.elements.namedItem("group");
      const age = form.elements.namedItem("age");
      const phone = form.elements.namedItem("whatsapp");
      const area = form.elements.namedItem("area");
      const consent = form.elements.namedItem("consent");
      const ageValue = Number(age?.value);

      const checks = [
        markField(byId("interestRequestId"), !requestId),
        markField(name, !name || name.value.trim().length < 2),
        markField(group, !group || !BLOOD_GROUPS.includes(group.value)),
        markField(age, !(ageValue >= 18 && ageValue <= 60)),
        markField(phone, !phone || !PHONE_PATTERN.test(phone.value.trim())),
        markField(area, !area || !area.value),
        markField(consent, !consent || !consent.checked)
      ];

      if (checks.includes(false)) {
        setStatus(byId("donorInterestStatus"), "Please complete the highlighted fields and confirm consent.", "is-error");
        return;
      }

      const data = new FormData(form);
      data.set("bloodhub_action", "register_interest");
      data.set("consent", "1");

      const button = form.querySelector('[type="submit"]');
      const original = button?.innerHTML;
      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
      }

      try {
        const result = await request(pageUrl().toString(), {
          method: "POST",
          body: data
        });
        close();
        form.reset();
        toast(result.message || "Donation interest submitted successfully.");
      } catch (error) {
        setStatus(byId("donorInterestStatus"), error.message, "is-error");
        toast(error.message);
      } finally {
        if (button) {
          button.disabled = false;
          button.innerHTML = original;
        }
      }
    });
  }

  function canDonate(donor, recipient) {
    const abo = group => group.replace(/[+-]/, "");
    const rh = group => group.slice(-1);
    const aboOk = abo(donor) === "O" || abo(donor) === abo(recipient) || abo(recipient) === "AB";
    const rhOk = rh(donor) === "-" || rh(recipient) === "+";
    return aboOk && rhOk;
  }

  function groupName(group) {
    const label = group.endsWith("+") ? "Positive" : "Negative";
    const extra = { "O-": " (Universal Donor)", "AB+": " (Universal Recipient)" }[group] || "";
    return group.replace(/[+-]/, "") + " " + label + extra;
  }

  function initCompatibility() {
    const table = byId("matrixTable");
    const patient = byId("checkPatient");
    const donor = byId("checkDonor");
    if (!table || !patient || !donor) return;

    const headings = BLOOD_GROUPS.map(group => `<th data-col="${group}">${group}</th>`).join("");
    const rows = BLOOD_GROUPS.map(recipient => {
      const cells = BLOOD_GROUPS.map(donorGroup => {
        const yes = canDonate(donorGroup, recipient);
        return `<td class="${yes ? "is-yes" : ""}" data-donor="${donorGroup}">${yes ? "YES" : "NO"}</td>`;
      }).join("");
      return `<tr data-recipient="${recipient}" tabindex="0"><td><span class="blood-tag">${recipient}</span>${groupName(recipient)}</td>${cells}</tr>`;
    }).join("");

    table.innerHTML = `<thead><tr><th>Recipient Blood Type ↓ / Donor →</th>${headings}</tr></thead><tbody>${rows}</tbody>`;

    function runCheck() {
      const result = byId("checkResult");
      if (!result || !patient.value || !donor.value) return;

      const compatible = canDonate(donor.value, patient.value);
      const donors = BLOOD_GROUPS.filter(group => canDonate(group, patient.value));
      result.className = "blood-result " + (compatible ? "is-yes" : "is-no");
      result.innerHTML = `<i class="fa-solid ${compatible ? "fa-circle-check" : "fa-circle-xmark"}"></i>
        <strong>${donor.value} donor → ${patient.value} patient: ${compatible ? "Compatible" : "NOT compatible"}</strong><br>
        Patient ${patient.value} can receive from: ${donors.map(group => `<span>${group}</span>`).join("")}`;

      table.querySelectorAll(".is-picked, .is-hit").forEach(el => el.classList.remove("is-picked", "is-hit"));
      table.querySelector(`tr[data-recipient="${patient.value}"]`)?.classList.add("is-picked");
      table.querySelector(`th[data-col="${donor.value}"]`)?.classList.add("is-picked");
      table.querySelector(`tr[data-recipient="${patient.value}"] td[data-donor="${donor.value}"]`)?.classList.add("is-hit");
    }

    patient.value = "A+";
    donor.value = "O-";
    patient.addEventListener("change", runCheck);
    donor.addEventListener("change", runCheck);
    byId("checkBtn")?.addEventListener("click", runCheck);
    table.addEventListener("click", event => {
      const row = event.target.closest("tr[data-recipient]");
      if (!row) return;
      patient.value = row.dataset.recipient;
      runCheck();
    });
    table.addEventListener("keydown", event => {
      const row = event.target.closest("tr[data-recipient]");
      if (row && event.key === "Enter") {
        patient.value = row.dataset.recipient;
        runCheck();
      }
    });
    runCheck();
  }

  function initReveal() {
    if (!("IntersectionObserver" in window)) return;

    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
        setTimeout(() => entry.target.classList.remove("reveal", "is-visible"), 800);
      });
    }, { threshold: 0.15 });

    [".blood-hero .container > *", ".blood-stat", ".blood-form",
      ".blood-stream__head", ".blood-card", ".blood-matrix", ".blood-note"]
      .forEach(selector => document.querySelectorAll(selector).forEach(el => {
        el.classList.add("reveal");
        observer.observe(el);
      }));
  }

  document.addEventListener("DOMContentLoaded", () => {
    initBloodSelects();
    initGroupPicker();
    initSosForm();
    initDonorRegistrationForm();
    initInterestModal();
    initRefresh();
    initSeeMore();
    initCompatibility();
    initReveal();
  });
})();