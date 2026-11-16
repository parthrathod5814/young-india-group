/* =========================================================
   Admin Dashboard  |  admin.js     (interface behaviour only)

   PHP + MySQL handle ALL the data (lists, add, update, delete,
   approve, backups, login). This file never reads or saves records.
   It only handles what the browser must do:

   1. Helpers          2. Toast (incl. PHP flash message)
   3. Popups           4. Forms: checks + rich text editor
   5. Update popup     6. "Are you sure?" before delete
   7. Sidebar + header 8. Export current table (Excel / PDF)
   9. Start

   NO admin-data.js is needed any more. Remove that <script> tag.
   ========================================================= */

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];


/* ---------- 1. HELPERS ---------- */

// HTML -> plain text
const plain = (html) => { const d = document.createElement("div"); d.innerHTML = html || ""; return d.textContent.replace(/\s+/g, " ").trim(); };

// Removes scripts and inline event handlers from editor HTML before it is sent.
// (Also clean the HTML again in PHP. Never trust the browser alone.)
function sanitize(html) {
  const doc = new DOMParser().parseFromString(html || "", "text/html");
  doc.querySelectorAll("script,style,iframe,object,embed,link,meta").forEach((n) => n.remove());
  doc.body.querySelectorAll("*").forEach((el) => [...el.attributes].forEach((a) => {
    if (/^on/i.test(a.name) || (/^(href|src)$/i.test(a.name) && /^\s*javascript:/i.test(a.value))) el.removeAttribute(a.name);
  }));
  return doc.body.innerHTML;
}


/* ---------- 2. TOAST (small message bar) ---------- */
let toastTimer;

function toast(message, type = "success") {
  const el = $("#toast");
  if (!el) return;

  el.textContent = message;
  el.classList.toggle("is-error", type === "error");
  el.classList.add("is-show");

  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove("is-show", "is-error"), 3000);
}

// A PHP flash message, if the page has one:
//   <div class="adm-toast" id="toast" data-message="Record saved" data-type="success"></div>
function showFlashMessage() {
  const el = $("#toast");
  if (el && el.dataset.message) toast(el.dataset.message, el.dataset.type || "success");
}


/* ---------- 3. POPUPS (the markup is written in your PHP / HTML pages) ---------- */
const slot = (modal, name) => $(`[data-slot="${name}"]`, modal);

function closeModal() { $$(".adm-modal").forEach((m) => (m.hidden = true)); }

function openModal(id, title, icon) {
  const modal = $("#" + id);
  if (!modal) return null;

  closeModal();
  if (title && slot(modal, "title")) slot(modal, "title").textContent = title;
  if (icon && slot(modal, "icon")) slot(modal, "icon").className = "fa-solid " + icon;
  modal.hidden = false;
  return modal;
}

// "Are you sure?" popup  (needs #modal-confirm)
function ensureConfirmModal() {
  let modal = document.getElementById("modal-confirm");

  if (!modal) {
    modal = document.createElement("div");
    modal.className = "adm-modal";
    modal.id = "modal-confirm";
    modal.hidden = true;

    modal.innerHTML = `
      <div class="adm-modal__box" role="dialog" aria-modal="true">
        <div class="adm-modal__head">
          <h3>
            <i class="fa-solid fa-triangle-exclamation" data-slot="icon"></i>
            <span data-slot="title">Confirm</span>
          </h3>
          <button type="button" class="adm-x" data-close aria-label="Close">&times;</button>
        </div>
        <div class="adm-modal__body">
          <p data-slot="message"></p>
        </div>
        <div class="adm-modal__foot">
          <button type="button" class="adm-btn" data-close>Cancel</button>
          <button type="button" class="adm-btn adm-btn--danger" data-slot="confirm">Yes</button>
        </div>
      </div>
    `;

    document.body.appendChild(modal);
  }

  return modal;
}

function confirmDialog({
  title,
  icon = "fa-triangle-exclamation",
  danger = true,
  message,
  confirmText = "Yes",
  onConfirm
}) {
  const modal = ensureConfirmModal();

  closeModal();

  modal.querySelector('[data-slot="title"]').textContent = title || "Confirm";
  modal.querySelector('[data-slot="icon"]').className = "fa-solid " + icon;
  modal.querySelector('[data-slot="message"]').textContent = message;

  const button = modal.querySelector('[data-slot="confirm"]');
  button.innerHTML = confirmText;
  button.className = "adm-btn " + (
    danger ? "adm-btn--danger" : "adm-btn--primary"
  );

  button.onclick = async () => {
    closeModal();
    await onConfirm?.();
  };

  modal.hidden = false;
}

// Success popup with one or two buttons  (needs #modal-message)
function showMessage({ title, message, primary, secondary }) {
  const modal = openModal("modal-message", title, "fa-circle-check");
  if (!modal) return toast(message);

  slot(modal, "message").textContent = message;

  const first = slot(modal, "primary");
  first.textContent = primary.text;
  first.onclick = () => { closeModal(); primary.onClick?.(); };

  const second = slot(modal, "secondary");
  second.hidden = !secondary;
  if (secondary) { second.textContent = secondary.text; second.onclick = () => { closeModal(); secondary.onClick?.(); }; }
}

// Failure popup  (needs #modal-error)
function showError(title, message) {
  const modal = openModal("modal-error", title, "fa-circle-xmark");
  if (!modal) return toast(message, "error");
  slot(modal, "message").textContent = message;
}

// Close on the X button, a "Cancel" button ([data-close]), the dark background or the Esc key
document.addEventListener("click", (e) => { if (e.target.classList.contains("adm-modal") || e.target.closest("[data-close]")) closeModal(); });
document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeModal(); });

// Any button with data-open="modal-id" opens that popup.
// If the popup contains a form it is cleared first (used by "Add Admin").
document.addEventListener("click", (e) => {
  const trigger = e.target.closest("[data-open]");
  if (!trigger) return;

  const modal = openModal(trigger.dataset.open);
  const form = modal && $("form", modal);
  if (form && !trigger.hasAttribute("data-keep")) resetForm(form);
});


/* ---------- 4. FORMS: CHECKS + RICH TEXT EDITOR ---------- */
const labelOf = (el) => $("label", el.closest(".adm-field")).textContent.replace(/\s*\*$/, "");

// Fields we validate (hidden inputs are skipped)
const fieldsOf = (root) => $$("[name], [data-editor]", root).filter((el) => el.type !== "hidden");

// Checks every field using the rules written in the HTML (required, type, min, max).
// Shows the red message under each wrong field. Returns true when everything is fine.
function validateForm(form) {
  let ok = true;

  fieldsOf(form).forEach((el) => {
    const isEditor = el.matches("[data-editor]");
    const value = isEditor ? sanitize(el.innerHTML) : el.value.trim();
    const empty = isEditor ? !plain(value) : !value;
    const required = el.hasAttribute("required") || el.hasAttribute("data-required");
    let error = "";

    if (required && empty) error = "This field is required.";
    else if (!empty && el.type === "tel" && !/^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/.test(value)) error = "Enter a valid 10-digit mobile number.";
    else if (!empty && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) error = "Enter a valid email address.";
    else if (!empty && el.type === "number" && (Number(value) < Number(el.min) || Number(value) > Number(el.max))) error = `Enter a value between ${el.min} and ${el.max}.`;

    const wrapper = el.closest(".adm-field");
    if (wrapper) {
      wrapper.classList.toggle("has-error", !!error);
      const message = $("small", wrapper);
      if (message) message.textContent = error;
    }

    if (error) ok = false;
  });

  return ok;
}

// The rich text editor is a <div>, so PHP cannot receive it by itself.
// Before the form is sent, copy its HTML into a hidden input with the same name.
function syncEditors(form) {
  $$("[data-editor]", form).forEach((editor) => {
    const name = editor.dataset.editor;
    let hidden = $(`input[type="hidden"][name="${name}"]`, form);

    if (!hidden) {
      hidden = document.createElement("input");
      hidden.type = "hidden";
      hidden.name = name;
      form.append(hidden);
    }
    hidden.value = sanitize(editor.innerHTML);
  });
}

// Clears the editor text and the red error messages
function clearFormState(form) {
  $$("[data-editor]", form).forEach((el) => (el.innerHTML = ""));
  $$(".has-error", form).forEach((f) => f.classList.remove("has-error"));
  $$(".adm-field small", form).forEach((s) => (s.textContent = ""));
}

function resetForm(form) {
  form.reset();
  clearFormState(form);
}

// Sends a form the same way a Submit button would (so the checks above run first)
function submitForm(form) {
  if (typeof form.requestSubmit === "function") form.requestSubmit();
  else if (form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }))) form.submit();   // older browsers
}

// Every .adm-form is checked before it goes to PHP. A wrong form stays on the page.
// The Save button of a popup sits outside its <form>, so it is connected here too.
function initForms() {
  $$("form.adm-form").forEach((form) => {
    form.addEventListener("submit", (e) => {
      if (!validateForm(form)) {
        e.preventDefault();
        toast("Please fix the highlighted fields.", "error");
        return;
      }
      syncEditors(form);                                     // then the form goes to PHP
    });

    form.addEventListener("reset", () => setTimeout(() => clearFormState(form)));   // the Reset button also clears the editor
  });

  $$(".adm-modal").forEach((modal) => {
    const form = $("form", modal);
    const save = slot(modal, "save");
    if (form && save) save.addEventListener("click", () => submitForm(form));
  });
}

// Rich text editor buttons (one set of listeners works for every editor on the page)
document.addEventListener("mousedown", (e) => { if (e.target.closest(".adm-editor__bar button")) e.preventDefault(); });   // keep the text selection

document.addEventListener("click", (e) => {
  const button = e.target.closest(".adm-editor__bar button[data-cmd]");
  if (!button) return;
  $(".adm-editor__area", button.closest(".adm-editor")).focus();

  const [command, tag] = button.dataset.cmd.split(":");
  if (command === "createLink") { const url = prompt("Enter the link address (https://...)"); if (url) document.execCommand(command, false, url); }
  else document.execCommand(command, false, tag ? "<" + tag + ">" : null);
});

document.addEventListener("change", (e) => {
  const control = e.target.closest(".adm-editor__bar select[data-cmd], .adm-editor__bar input[data-cmd]");
  if (!control || !control.value) return;
  $(".adm-editor__area", control.closest(".adm-editor")).focus();
  document.execCommand(control.dataset.cmd, false, control.value);
});


/* ---------- 5. UPDATE POPUP ----------
   PHP prints the row's data on the button:
     <button type="button" class="adm-btn adm-btn--sm" data-edit
             data-title="Update Blood Request (REQ-0012)"
             data-action="update-blood-request.php"
             data-record='<?= e(json_encode($row)) ?>'>Update</button>
   The popup (#modal-edit) holds the form. It must contain
   <input type="hidden" name="id"> so PHP knows which record to change. */
function fillForm(form, record) {
  $$("[name], [data-editor]", form).forEach((el) => {
    const isEditor = el.matches("[data-editor]");
    const value = record[isEditor ? el.dataset.editor : el.name] ?? "";

    if (isEditor) el.innerHTML = sanitize(String(value));
    else if (el.type === "checkbox") el.checked = !!Number(value) || value === true;
    else el.value = value;
  });
}

function openEditPopup(button) {
  const modal = openModal(button.dataset.modal || "modal-edit", button.dataset.title || "Update", "fa-pen-to-square");
  const form = modal && $("form", modal);
  if (!form) return;

  let record = {};
  try { record = JSON.parse(button.dataset.record || "{}"); } catch (error) { return showError("Cannot Open", "The record data is not valid."); }

  resetForm(form);
  fillForm(form, record);
  if (button.dataset.action) form.action = button.dataset.action;
}

document.addEventListener("click", (e) => {
  const button = e.target.closest("[data-edit]");
  if (button) openEditPopup(button);
});


/* ---------- 6. "ARE YOU SURE?" BEFORE DELETE ----------
   Put data-confirm on a delete form or a delete link:
     <form method="post" action="delete-blood-request.php" data-confirm="Delete REQ-0012 — Ramesh?">
     <a href="delete.php?id=12" data-confirm="Delete this record?">Delete</a>
   Optional: data-confirm-title="Confirm Delete"  data-confirm-button="Yes, Delete" */
function askFirst(element, onConfirm) {
  confirmDialog({
    title: element.dataset.confirmTitle || "Confirm Delete",
    message: element.dataset.confirm + " This action cannot be undone.",
    confirmText: element.dataset.confirmButton || '<i class="fa-solid fa-trash"></i> Yes, Delete',
    onConfirm
  });
}

document.addEventListener("submit", (e) => {
  const form = e.target.closest("form[data-confirm]");
  if (!form) return;
  e.preventDefault();
  askFirst(form, () => form.submit());                       // submit() skips this check, so it only asks once
});

document.addEventListener("click", (e) => {
  const link = e.target.closest("a[data-confirm]");
  if (!link) return;
  e.preventDefault();
  askFirst(link, () => { location.href = link.href; });
});


/* ---------- 7. SIDEBAR + HEADER ---------- */

// Dropdown menus (only one open at a time)
function initSidebar() {
  $$(".adm-group__btn").forEach((button) => button.addEventListener("click", () => {
    const group = button.closest(".adm-group");
    const wasOpen = group.classList.contains("is-open");
    $$(".adm-group").forEach((g) => g.classList.remove("is-open"));
    group.classList.toggle("is-open", !wasOpen);
  }));

  // Highlight the link of the page you are on (by file name) and open its dropdown
  const here = location.pathname.split("/").pop() || "index.php";
  $$(".adm-link, .adm-sub a").forEach((a) => {
    const file = (a.getAttribute("href") || "").split("?")[0].split("#")[0].split("/").pop();
    const active = file !== "" && file === here;
    a.classList.toggle("is-active", active);
    if (active) a.closest(".adm-group")?.classList.add("is-open");
  });

  // Phone / tablet: open and close the sidebar
  $("#menuBtn")?.addEventListener("click", () => $("#sidebar").classList.toggle("is-open"));
  $$("#sidebar a").forEach((a) => a.addEventListener("click", () => $("#sidebar").classList.remove("is-open")));
}

function initHeader() {


  const notificationModal = $("#modal-notifications");
const deleteAll = notificationModal && slot(notificationModal, "deleteall");

deleteAll?.addEventListener("click", () => {
  confirmDialog({
    title: "Delete All Notifications",
    icon: "fa-trash",
    danger: true,
    message: "Delete all notifications? This cannot be undone.",
    confirmText: "Delete All",
    onConfirm: async () => {
      try {
        const response = await fetch(window.location.href, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
            "X-Requested-With": "XMLHttpRequest"
          },
          body: new URLSearchParams({
            header_action: "delete_all_notifications",
            csrf_token: deleteAll.dataset.csrf || ""
          })
        });

        const result = await response.json();

        if (!response.ok || !result.ok) {
          throw new Error(result.message || "Unable to delete notifications.");
        }

        const list = slot(notificationModal, "list");
        if (list) list.innerHTML = "<p>No notifications.</p>";

        const badge = $("#bellCount");
        if (badge) {
          badge.textContent = "0";
          badge.hidden = true;
        }

        const markAll = slot(notificationModal, "markall");
        if (markAll) markAll.disabled = true;
        deleteAll.disabled = true;

        closeModal();
        toast(result.message);
      } catch (error) {
        toast(error.message || "Unable to delete notifications.", "error");
      }
    }
  });
});
  // Bell: opens the notifications popup. PHP prints the list inside it.
  $("#bellBtn")?.addEventListener("click", () => openModal("modal-notifications"));

  // "Mark all as read": add data-url="mark-read.php" to the button if PHP should save it
  const markAll = $("#modal-notifications") && slot($("#modal-notifications"), "markall");
  markAll?.addEventListener("click", async () => {
    if (markAll.dataset.url) { try { await fetch(markAll.dataset.url, { method: "POST" }); } catch (error) { /* ignore */ } }
    const badge = $("#bellCount");
    if (badge) badge.hidden = true;
    $$(".adm-notice.is-unread").forEach((n) => n.classList.remove("is-unread"));
    closeModal();
    toast("All notifications marked as read.");
  });

  // Logout: ask first, then send the logout form (PHP ends the session)
  $("#logoutBtn")?.addEventListener("click", () => confirmDialog({
    title: "Logout",
    icon: "fa-right-from-bracket",
    danger: false,
    message: "Do you want to log out of the Admin Command Portal?",
    confirmText: "Logout",
    onConfirm: () => document.getElementById("logoutForm").submit()
  }));


}

// A page written with the "hidden" attribute is shown when it is the only content
function showCurrentPage() {
  const pages = $$(".adm-page");
  if (pages.length && pages.every((p) => p.hidden)) pages[0].hidden = false;
}


/* ---------- 8. EXPORT THE TABLE ON SCREEN (EXCEL / PDF) ----------
   <button data-export="xlsx">  and  <button data-export="pdf">
   Add data-url="export-donors.php?type=xlsx" to let PHP build the file
   with ALL records instead of only the rows on this page. */
function exportTable(button) {
  if (button.dataset.url) { location.href = button.dataset.url; return; }

  const table = $(".adm-table", button.closest(".adm-page") || document);
  if (!table) return toast("No table found to export.", "error");

  // Copy of the table without the Action column
  const copy = table.cloneNode(true);
  $$(".is-actions, .is-manage-only, [data-key='actions']", copy).forEach((el) => el.remove());
  const name = (button.closest(".adm-page")?.id || "export").replace("page-", "") + "-" + new Date().toISOString().slice(0, 10);

  if (button.dataset.export === "xlsx") {
    if (!window.XLSX) return toast("Excel library not loaded. Check your internet connection.", "error");
    XLSX.writeFile(XLSX.utils.table_to_book(copy), name + ".xlsx");
  } else {
    if (!window.jspdf) return toast("PDF library not loaded. Check your internet connection.", "error");
    const doc = new window.jspdf.jsPDF({ orientation: "landscape" });
    doc.setFontSize(14);
    doc.text("Young India Group", 14, 14);
    doc.autoTable({ html: copy, startY: 20, styles: { fontSize: 8 }, headStyles: { fillColor: [46, 10, 92] } });
    doc.save(name + ".pdf");
  }
  toast("File downloaded.");
}

document.addEventListener("click", (e) => {
  const button = e.target.closest("[data-export]");
  if (button) exportTable(button);
});


/* ---------- 9. START ---------- */
document.addEventListener("DOMContentLoaded", () => {
  showCurrentPage();
  initSidebar();
  initHeader();
  initForms();
  showFlashMessage();
});