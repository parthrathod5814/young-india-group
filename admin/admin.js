/* =========================================================
   Admin Dashboard  |  admin.js
   All the page layouts, forms, tables and popups are plain HTML
   in admin.html. This file only adds behaviour:
   show / hide pages, fill tables, validate forms, save data.

   Needs admin-data.js first (MODULES + db + auth).

   1. Helpers       2. Popups        3. Forms + rich text editor
   4. Tables        5. Add / Edit / Delete
   6. Dashboard     7. Notifications + pending sign-ups
   8. Export        9. Backup & recovery
   10. Router + start
   ========================================================= */

const PAGE_SIZE = 20;
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
const state = {};                                   // search text + page number for every table


/* ---------- 1. HELPERS ---------- */
const plain = (html) => { const d = document.createElement("div"); d.innerHTML = html || ""; return d.textContent.replace(/\s+/g, " ").trim(); };

// Removes scripts and inline event handlers from editor HTML before saving
function sanitize(html) {
  const doc = new DOMParser().parseFromString(html || "", "text/html");
  doc.querySelectorAll("script,style,iframe,object,embed,link,meta").forEach((n) => n.remove());
  doc.body.querySelectorAll("*").forEach((el) => [...el.attributes].forEach((a) => {
    if (/^on/i.test(a.name) || (/^(href|src)$/i.test(a.name) && /^\s*javascript:/i.test(a.value))) el.removeAttribute(a.name);
  }));
  return doc.body.innerHTML;
}

const formatDate = (ms) => new Date(ms).toLocaleString("en-IN", { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });

function timeAgo(ms) {
  const minutes = Math.max(1, Math.round((Date.now() - ms) / 60000));
  if (minutes < 60) return minutes + " min ago";
  const hours = Math.round(minutes / 60);
  return hours < 24 ? hours + " hr ago" : Math.round(hours / 24) + " days ago";
}

// Put text into every  [data-f="name"]  element inside a copied template
function fill(root, values) {
  Object.entries(values).forEach(([name, text]) => { const el = $(`[data-f="${name}"]`, root); if (el) el.textContent = text; });
}
const copyTemplate = (id) => $("#" + id).content.cloneNode(true);

let toastTimer;
function toast(message) {
  const el = $("#toast");
  el.textContent = message;
  el.classList.add("is-show");
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove("is-show"), 3000);
}


/* ---------- 2. POPUPS (the markup is in admin.html) ---------- */
const slot = (modal, name) => $(`[data-slot="${name}"]`, modal);

function closeModal() { $$(".adm-modal").forEach((m) => (m.hidden = true)); }

function openModal(id, title, icon) {
  closeModal();
  const modal = $("#" + id);
  if (title) slot(modal, "title").textContent = title;
  if (icon) slot(modal, "icon").className = "fa-solid " + icon;
  modal.hidden = false;
  return modal;
}

document.addEventListener("click", (e) => { if (e.target.classList.contains("adm-modal") || e.target.closest("[data-close]")) closeModal(); });
document.addEventListener("keydown", (e) => { if (e.key === "Escape") closeModal(); });

// "Are you sure?" popup
function confirmDialog({ title, icon = "fa-triangle-exclamation", danger = true, message, confirmText = "Yes", onConfirm }) {
  const modal = openModal("modal-confirm", title, icon);
  $("h3", modal).classList.toggle("is-danger", danger);
  slot(modal, "message").textContent = message;

  const button = slot(modal, "confirm");
  button.innerHTML = confirmText;
  button.className = "adm-btn " + (danger ? "adm-btn--danger" : "adm-btn--primary");
  button.onclick = async () => { closeModal(); await onConfirm(); };
}

// "Saved" popup with one or two buttons
function showMessage({ title, message, primary, secondary }) {
  const modal = openModal("modal-message", title, "fa-circle-check");
  slot(modal, "message").textContent = message;

  const first = slot(modal, "primary");
  first.textContent = primary.text;
  first.onclick = () => { closeModal(); primary.onClick?.(); };

  const second = slot(modal, "secondary");
  second.hidden = !secondary;
  if (secondary) { second.textContent = secondary.text; second.onclick = () => { closeModal(); secondary.onClick?.(); }; }
}


/* ---------- 3. FORMS + RICH TEXT EDITOR ---------- */
const labelOf = (el) => $("label", el.closest(".adm-field")).textContent.replace(/\s*\*$/, "");
const fieldsOf = (root) => $$("[name], [data-editor]", root);

// Checks every field using the attributes written in the HTML (required, type, min, max)
function readForm(form) {
  const data = {};
  let ok = true;

  fieldsOf(form).forEach((el) => {
    const isEditor = el.matches("[data-editor]");
    const name = isEditor ? el.dataset.editor : el.name;
    const value = isEditor ? sanitize(el.innerHTML) : el.value.trim();
    const empty = isEditor ? !plain(value) : !value;
    const required = el.hasAttribute("required") || el.hasAttribute("data-required");
    let error = "";

    if (required && empty) error = "This field is required.";
    else if (!empty && el.type === "tel" && !/^(\+91)?[\s-]?[6-9]\d{4}[\s-]?\d{5}$/.test(value)) error = "Enter a valid 10-digit mobile number.";
    else if (!empty && el.type === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) error = "Enter a valid email address.";
    else if (!empty && el.type === "number" && (Number(value) < Number(el.min) || Number(value) > Number(el.max))) error = `Enter a value between ${el.min} and ${el.max}.`;

    const wrapper = el.closest(".adm-field");
    wrapper.classList.toggle("has-error", !!error);
    $("small", wrapper).textContent = error;
    if (error) ok = false;

    data[name] = el.type === "number" && value !== "" ? Number(value) : value;
  });

  return { ok, data };
}

function fillForm(form, record) {
  fieldsOf(form).forEach((el) => {
    const value = record[el.matches("[data-editor]") ? el.dataset.editor : el.name] ?? "";
    if (el.matches("[data-editor]")) el.innerHTML = sanitize(value); else el.value = value;
  });
}

function resetForm(form) {
  form.reset();
  $$("[data-editor]", form).forEach((el) => (el.innerHTML = ""));
  $$(".has-error", form).forEach((f) => f.classList.remove("has-error"));
}

// Copy of the Add form (including the editor) used inside the Update popup
function copyForm(key) {
  const form = $("#form-" + key).cloneNode(true);
  $$("[id]", form).forEach((el) => (el.id += "-edit"));
  $$("label[for]", form).forEach((l) => (l.htmlFor += "-edit"));
  form.removeAttribute("id");
  $$(".adm-form__actions, .has-error", form).forEach((el) => (el.matches(".has-error") ? el.classList.remove("has-error") : el.remove()));
  return form;
}

// Rich text editor buttons (one set of listeners works for every editor)
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


/* ---------- 4. TABLES (View + Manage share one table per menu) ---------- */
function showPage(page) {
  $$(".adm-page").forEach((p) => (p.hidden = p !== page));
  window.scrollTo(0, 0);
}

function makeCell(key, th, record) {
  const td = document.createElement("td");
  td.className = th.className;
  const name = th.dataset.key, value = record[name];

  if (name === "actions") { td.classList.add("is-actions"); td.append(copyTemplate("tpl-actions")); }
  else if (name === "code") { td.classList.add("is-code"); td.textContent = record.code; }
  else if (name === "status") {
    const badge = document.createElement("span");
    badge.className = "adm-badge adm-badge--" + (MODULES[key].colors[value] || "purple");
    badge.textContent = value;
    td.append(badge);
  }
  else if (th.dataset.type === "richtext" || th.dataset.type === "textarea") { const t = plain(value); td.textContent = t.length > 60 ? t.slice(0, 60) + "…" : t; }
  else if (name === "group") { const b = document.createElement("b"); b.textContent = value; td.append(b); }
  else td.textContent = value ?? "";
  return td;
}

async function renderTable(key) {
  const page = $("#page-" + key);
  const st = state[key];
  const all = await db.list(key);
  const q = st.query.toLowerCase();
  const rows = q ? all.filter((r) => plain(Object.values(r).join(" ")).toLowerCase().includes(q)) : all;

  const pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
  st.page = Math.min(st.page, pages);
  const start = (st.page - 1) * PAGE_SIZE;
  st.rows = rows.slice(start, start + PAGE_SIZE);

  const headers = $$("thead th", page);
  const body = $("[data-rows]", page);
  body.replaceChildren();

  st.rows.forEach((record) => {
    const tr = document.createElement("tr");
    headers.forEach((th) => tr.append(makeCell(key, th, record)));
    tr.dataset.id = record.id;
    body.append(tr);
  });

  if (!st.rows.length) {
    const tr = body.insertRow();
    const td = tr.insertCell();
    td.colSpan = headers.length;
    td.className = "adm-empty";
    td.textContent = "No records found.";
  }

  const filtered = q ? ` (filtered from ${all.length})` : "";
  const info = rows.length ? `Showing ${start + 1}–${start + st.rows.length} of ${rows.length} records${filtered} • ${PAGE_SIZE} per page` : "Total records: 0";
  $$("[data-info]", page).forEach((el) => (el.textContent = info));
  $("[data-pageno]", page).textContent = `Page ${st.page} of ${pages}`;
  $('[data-page="prev"]', page).disabled = st.page <= 1;
  $('[data-page="next"]', page).disabled = st.page >= pages;
}

async function showList(key, mode) {
  const page = $("#page-" + key);
  page.dataset.mode = mode;
  $('[data-slot="title"]', page).textContent = (mode === "view" ? "View " : "Manage ") + MODULES[key].plural;
  state[key] = { page: 1, query: "", rows: [] };
  $("[data-search]", page).value = "";
  showPage(page);
  await renderTable(key);
}

// Admin pages stay locked until the pass key is accepted
function stillUnlocked(key) {
  if (!MODULES[key].gated || auth.isUnlocked()) return true;
  toast("Session expired. Enter the pass key again.");
  route();
  return false;
}

function initTables() {
  $$("section[data-module][data-mode]").forEach((page) => {
    const key = page.dataset.module;
    state[key] = { page: 1, query: "", rows: [] };

    $("[data-search]", page).addEventListener("input", (e) => { state[key].query = e.target.value.trim(); state[key].page = 1; renderTable(key); });

    page.addEventListener("click", (e) => {
      const pager = e.target.closest("[data-page]");
      if (pager) { state[key].page += pager.dataset.page === "next" ? 1 : -1; return renderTable(key); }

      const exportButton = e.target.closest("[data-export]");
      if (exportButton) return exportButton.dataset.export === "xlsx" ? exportExcel(key) : exportPdf(key);

      if (e.target.closest("[data-lock]")) { auth.lock(); toast("Admin section locked."); return route(); }
      if (e.target.closest("[data-open]")) { if (stillUnlocked(key)) { resetForm($("#form-admins")); openModal("modal-admin-add"); } return; }

      const action = e.target.closest("[data-act]");
      if (!action || !stillUnlocked(key)) return;
      const record = state[key].rows.find((r) => r.id === action.closest("tr").dataset.id);
      if (action.dataset.act === "edit") editRecord(key, record);
      if (action.dataset.act === "delete") deleteRecord(key, record);
    });
  });
}


/* ---------- 5. ADD / EDIT / DELETE ---------- */
function initAddForms() {
  // Full-page Add forms
  $$("section[id$='-add'] form").forEach((form) => {
    const key = form.dataset.module;

    form.addEventListener("reset", () => setTimeout(() => resetForm(form)));
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
      const { ok, data } = readForm(form);
      if (!ok) return toast("Please fix the highlighted fields.");

      const record = await db.add(key, data);                 // saved to the database
      refreshBadges();
      showMessage({
        title: "Record Added", message: `${MODULES[key].singular} saved successfully with ID ${record.code}.`,
        secondary: { text: "Add Another", onClick: () => resetForm(form) },
        primary: { text: "View List", onClick: () => { location.hash = `#/${key}/list`; } }
      });
    });
  });

  // Add Admin popup
  slot($("#modal-admin-add"), "save").onclick = async () => {
    const form = $("#form-admins");
    const { ok, data } = readForm(form);
    if (!ok) return;

    const record = await db.add("admins", data);
    refreshBadges();
    renderTable("admins");
    showMessage({ title: "Admin Added", message: `${data.name} was added with ID ${record.code}.`, primary: { text: "OK" } });
  };
}

function editRecord(key, record) {
  const modal = openModal("modal-edit", `Update ${MODULES[key].singular} (${record.code})`, "fa-pen-to-square");
  const form = copyForm(key);
  fillForm(form, record);
  slot(modal, "form").replaceChildren(form);

  slot(modal, "save").onclick = async () => {
    const { ok, data } = readForm(form);
    if (!ok) return;                                         // popup stays open to show the errors
    await db.update(key, record.id, data);
    closeModal();
    toast(MODULES[key].singular + " updated successfully.");
    refreshBadges();
    renderTable(key);
  };
}

function deleteRecord(key, record) {
  const label = record.patient || record.name || record.title || record.code;

  confirmDialog({
    title: "Confirm Delete",
    message: `Are you sure you want to delete ${record.code} — ${label}? This action cannot be undone.`,
    confirmText: '<i class="fa-solid fa-trash"></i> Yes, Delete',
    onConfirm: async () => {
      if (key === "admins" && record.role === "Super Admin" && (await db.list(key)).filter((a) => a.role === "Super Admin").length <= 1) {
        return toast("At least one Super Admin must remain.");
      }
      await db.remove(key, record.id);
      toast(MODULES[key].singular + " deleted.");
      refreshBadges();
      renderTable(key);
    }
  });
}


/* ---------- 6. DASHBOARD ---------- */
async function renderDashboard() {
  const [volunteers, donors, blood, activities, inquiries] = await Promise.all(["volunteers", "donors", "blood", "activities", "inquiries"].map((c) => db.list(c)));
  const [pendingDonors, pendingVolunteers] = await Promise.all([db.pending.list("donors"), db.pending.list("volunteers")]);

  const values = {
    volunteers: volunteers.length, donors: donors.length,
    urgent: blood.filter((r) => r.status === "Active").length + " Urgent",
    saved: blood.filter((r) => r.status === "Fulfilled").length,
    drives: activities.length + " Drives", talukas: "8 Talukas"
  };
  $$("[data-stat]").forEach((el) => (el.textContent = values[el.dataset.stat]));

  const counts = { donors: pendingDonors.length, volunteers: pendingVolunteers.length, contact: inquiries.filter((r) => r.status === "New").length };
  $$("[data-count]").forEach((el) => { el.textContent = counts[el.dataset.count]; el.classList.toggle("is-zero", !counts[el.dataset.count]); });

  // Live blood request feed
  const feed = $("#feedArea");
  feed.replaceChildren();
  blood.slice(0, 5).forEach((r) => {
    const row = copyTemplate("tpl-feed-row");
    fill(row, { group: r.group, patient: r.patient, badge: `${r.units} Unit(s) • ${r.status}`, where: `${r.hospital} • ${r.ward} • ${r.code}`, runner: r.runner || "—" });

    const badge = $('[data-f="badge"]', row);
    badge.className = "adm-badge adm-badge--" + MODULES.blood.colors[r.status];
    $('[data-f="group"]', row).classList.toggle("adm-group-badge--alt", r.status !== "Active");

    $$("[data-for]", row).forEach((el) => (el.dataset.for === r.status ? (el.dataset.id = r.id) : el.remove()));
    feed.append(row);
  });
  if (!blood.length) feed.textContent = "No blood requests yet.";
}

function initDashboard() {
  $("#feedRefresh").onclick = () => { renderDashboard(); toast("Feed refreshed."); };

  $("#feedArea").addEventListener("click", async (e) => {
    const button = e.target.closest("[data-act]");
    if (!button) return;
    if (button.dataset.act === "alert") toast("Alert sent to matching donors.");
    if (button.dataset.act === "track") toast("Runner location: en route (demo).");
    if (button.dataset.act === "done") { await db.update("blood", button.dataset.id, { status: "Fulfilled" }); toast("Marked as fulfilled."); refreshBadges(); renderDashboard(); }
  });

  $$("[data-inq]").forEach((button) => button.addEventListener("click", () => {
    if (button.dataset.inq === "contact") location.hash = "#/inquiries/view";
    else openPendingPopup(button.dataset.inq);
  }));
}


/* ---------- 7. NOTIFICATIONS + PENDING SIGN-UPS ---------- */
const READ_KEY = "yig_admin_read";
const readIds = () => new Set(JSON.parse(localStorage.getItem(READ_KEY) || "[]"));

async function getNotifications() {
  const [blood, inquiries] = await Promise.all(["blood", "inquiries"].map((c) => db.list(c)));
  const [pendingDonors, pendingVolunteers] = await Promise.all([db.pending.list("donors"), db.pending.list("volunteers")]);
  const items = [];

  blood.filter((r) => r.status === "Active").forEach((r) => items.push({
    id: "blood:" + r.id, icon: "fa-droplet", tone: "red", time: r.createdAt, link: "#/blood/list",
    title: `Urgent: ${r.units} unit(s) of ${r.group} needed`, text: `${r.patient} • ${r.hospital}` }));

  inquiries.filter((r) => r.status === "New").forEach((r) => items.push({
    id: "inq:" + r.id, icon: "fa-envelope", tone: "orange", time: r.createdAt, link: "#/inquiries/list",
    title: "New inquiry: " + r.type, text: `${r.name} • ${r.phone}` }));

  pendingDonors.forEach((r) => items.push({
    id: "pend:d:" + r.id, icon: "fa-hand-holding-droplet", tone: "red", time: r.createdAt, link: "#/dashboard",
    title: "Donor inquiry awaiting approval", text: `${r.name} • ${r.group} • ${r.area}` }));

  pendingVolunteers.forEach((r) => items.push({
    id: "pend:v:" + r.id, icon: "fa-hand-holding-heart", tone: "purple", time: r.createdAt, link: "#/dashboard",
    title: "Volunteer inquiry awaiting approval", text: `${r.name} • ${r.role}` }));

  const last = getBackups()[0];
  if (!last || Date.now() - last.createdAt > 7 * 86400000) items.push({
    id: "backup:" + (last ? last.id : "none"), icon: "fa-database", tone: "green", time: last ? last.createdAt : Date.now(), link: "#/backup/create",
    title: "Database backup recommended", text: last ? "Last backup was more than 7 days ago" : "No backup has been created yet" });

  return items.sort((a, b) => b.time - a.time);
}

async function openNotifications() {
  const items = await getNotifications();
  const read = readIds();
  const modal = openModal("modal-notifications", `Notifications (${items.length})`);
  const list = slot(modal, "list");
  list.replaceChildren();

  items.forEach((n) => {
    const node = copyTemplate("tpl-notice");
    const link = $("a", node);
    link.href = n.link;
    link.dataset.nid = n.id;
    link.classList.toggle("is-unread", !read.has(n.id));
    $(".adm-notice__icon", node).classList.add("adm-notice__icon--" + n.tone);
    $(".adm-notice__icon i", node).classList.add(n.icon);
    fill(node, { title: n.title, text: n.text, time: timeAgo(n.time) });

    link.addEventListener("click", () => {                   // opens the page and marks this one as read
      const ids = readIds(); ids.add(n.id);
      localStorage.setItem(READ_KEY, JSON.stringify([...ids]));
      closeModal(); refreshBadges();
    });
    list.append(node);
  });
  if (!items.length) list.textContent = "You are all caught up. No notifications.";

  slot(modal, "markall").onclick = () => {
    localStorage.setItem(READ_KEY, JSON.stringify(items.map((n) => n.id)));
    closeModal(); refreshBadges(); toast("All notifications marked as read.");
  };
}

// Pending donor / volunteer sign-ups. Approve = move into the real table.
async function openPendingPopup(type) {
  const items = await db.pending.list(type);
  const modal = openModal("modal-pending", `${MODULES[type].singular} Inquiries (${items.length} pending)`, type === "donors" ? "fa-hand-holding-droplet" : "fa-people-group");
  const list = slot(modal, "list");
  list.replaceChildren();

  const system = ["id", "code", "createdAt"];
  const labels = Object.fromEntries(fieldsOf($("#form-" + type)).map((el) => [el.name, labelOf(el)]));

  items.forEach((item) => {
    const node = copyTemplate("tpl-pending");
    fill(node, { name: item.name, meta: `${item.code} • Submitted ${timeAgo(item.createdAt)}` });

    const grid = $('[data-f="grid"]', node);
    Object.keys(item).filter((k) => !system.includes(k) && labels[k] && k !== "status").forEach((k) => {
      const box = document.createElement("div");
      const dt = document.createElement("dt"), dd = document.createElement("dd");
      dt.textContent = labels[k]; dd.textContent = item[k] || "—";
      box.append(dt, dd);
      grid.append(box);
    });

    $("[data-approve]", node).addEventListener("click", async (e) => {
      e.currentTarget.disabled = true;
      const record = await db.pending.approve(type, item.id);   // saved to the database only now
      toast(`${MODULES[type].singular} approved and saved as ${record.code}.`);
      refreshBadges();
      if (!location.hash || location.hash === "#/dashboard") renderDashboard();
      openPendingPopup(type);                                   // show what is still waiting
    });
    list.append(node);
  });
  if (!items.length) list.textContent = `No pending ${MODULES[type].singular.toLowerCase()} inquiries. You are all caught up.`;
}


/* ---------- 8. EXPORT (EXCEL + PDF) ---------- */
async function exportRows(key) {
  const fields = fieldsOf($("#form-" + key)).map((el) => ({ name: el.name || el.dataset.editor, label: labelOf(el), rich: el.matches("[data-editor]") }));
  const records = await db.list(key);
  return {
    head: ["ID", ...fields.map((f) => f.label)],
    body: records.map((r) => [r.code, ...fields.map((f) => (f.rich ? plain(r[f.name]) : r[f.name] ?? ""))])
  };
}

async function exportExcel(key) {
  if (!window.XLSX) return toast("Excel library not loaded. Check your internet connection.");
  const { head, body } = await exportRows(key);
  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, XLSX.utils.aoa_to_sheet([head, ...body]), MODULES[key].plural);
  XLSX.writeFile(workbook, `${key}-${new Date().toISOString().slice(0, 10)}.xlsx`);
  toast("Excel file downloaded.");
}

async function exportPdf(key) {
  if (!window.jspdf) return toast("PDF library not loaded. Check your internet connection.");
  const { head, body } = await exportRows(key);
  const doc = new window.jspdf.jsPDF({ orientation: "landscape" });
  doc.setFontSize(14);
  doc.text("Young India Group - " + MODULES[key].plural, 14, 14);
  doc.autoTable({ head: [head], body, startY: 20, styles: { fontSize: 8 }, headStyles: { fillColor: [46, 10, 92] } });
  doc.save(`${key}-${new Date().toISOString().slice(0, 10)}.pdf`);
  toast("PDF file downloaded.");
}


/* ---------- 9. BACKUP & RECOVERY ---------- */
const BACKUP_KEY = "yig_admin_backups";
const AUTO_KEY = "yig_admin_autobackup";
const getBackups = () => JSON.parse(localStorage.getItem(BACKUP_KEY) || "[]");

function saveBackups(list) {
  try { localStorage.setItem(BACKUP_KEY, JSON.stringify(list)); return true; }
  catch (e) { toast("Browser storage is full. Delete old backups first."); return false; }
}

function makeBackup(note, auto) {
  const snapshot = db.snapshot();
  const now = new Date();
  const list = getBackups();

  list.unshift({
    id: "bk_" + Date.now(),
    name: "backup-" + now.toISOString().slice(0, 16).replace(/[:T]/g, "-"),
    note: note || "", auto: !!auto, createdAt: now.getTime(),
    size: JSON.stringify(snapshot).length,
    counts: Object.fromEntries(Object.keys(MODULES).map((k) => [k, snapshot[k].length])),
    data: snapshot
  });
  return saveBackups(list.slice(0, 10));                       // keep the latest 10
}

function downloadBackup(backup) {
  const blob = new Blob([JSON.stringify(backup.data, null, 2)], { type: "application/json" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = backup.name + ".json";
  link.click();
  URL.revokeObjectURL(link.href);
}

function confirmRestore(data, label) {
  confirmDialog({
    title: "Restore Database", icon: "fa-clock-rotate-left",
    message: `Restore ${label}? All current data will be replaced. A safety backup of the current data is created first.`,
    confirmText: "Yes, Restore",
    onConfirm: () => {
      makeBackup("Automatic safety backup before restore", true);
      db.restore(data);
      refreshBadges();
      renderBackups();
      toast("Database restored successfully.");
    }
  });
}

function renderBackups() {
  const list = getBackups();

  $$("[data-backup-body]").forEach((body) => {
    body.replaceChildren();
    list.forEach((b) => {
      const row = copyTemplate("tpl-backup-row");
      fill(row, { name: b.name, note: b.note, created: formatDate(b.createdAt), type: b.auto ? "Auto" : "Manual",
                  records: Object.values(b.counts).reduce((sum, n) => sum + n, 0) + " records", size: (b.size / 1024).toFixed(1) + " KB" });
      $('[data-f="type"]', row).className = "adm-badge adm-badge--" + (b.auto ? "purple" : "green");
      $$("[data-bk]", row).forEach((button) => (button.dataset.id = b.id));
      body.append(row);
    });
    if (!list.length) { const td = body.insertRow().insertCell(); td.colSpan = 6; td.className = "adm-empty"; td.textContent = "No backups yet. Create your first backup."; }
  });

  const snapshot = db.snapshot();
  $$("[data-summary]").forEach((el) => (el.textContent = snapshot[el.dataset.summary].length));
  $("[data-last-backup]").textContent = list[0] ? formatDate(list[0].createdAt) : "never";
  $("#autoBackup").checked = localStorage.getItem(AUTO_KEY) === "on";
}

function initBackup() {
  $("#bkCreate").onclick = () => { if (makeBackup($("#bkNote").value.trim(), false)) { $("#bkNote").value = ""; toast("Backup created."); renderBackups(); refreshBadges(); } };
  $("#autoBackup").onchange = (e) => { localStorage.setItem(AUTO_KEY, e.target.checked ? "on" : "off"); toast("Automatic backup " + (e.target.checked ? "enabled." : "disabled.")); };

  $("#restoreFile").onchange = (e) => {
    const file = e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
      try {
        const data = JSON.parse(reader.result);
        if (Object.keys(MODULES).filter((k) => Array.isArray(data[k])).length < 3) throw new Error("bad file");
        data._seq = data._seq || {};
        confirmRestore(data, file.name);
      } catch (err) { toast("This is not a valid backup file."); }
      e.target.value = "";
    };
    reader.readAsText(file);
  };

  $$("[data-backup-body]").forEach((body) => body.addEventListener("click", (e) => {
    const button = e.target.closest("[data-bk]");
    if (!button) return;
    const backup = getBackups().find((b) => b.id === button.dataset.id);

    if (button.dataset.bk === "download") downloadBackup(backup);
    if (button.dataset.bk === "restore") confirmRestore(backup.data, backup.name);
    if (button.dataset.bk === "delete") confirmDialog({
      title: "Delete Backup", message: `Delete backup ${backup.name}? This cannot be undone.`, confirmText: "Yes, Delete",
      onConfirm: () => { saveBackups(getBackups().filter((b) => b.id !== backup.id)); renderBackups(); toast("Backup deleted."); }
    });
  }));
}

// Runs a backup automatically once every 24 hours when the switch is on
function runAutoBackup() {
  if (localStorage.getItem(AUTO_KEY) !== "on") return;
  const lastAuto = getBackups().find((b) => b.auto);
  if (!lastAuto || Date.now() - lastAuto.createdAt > 86400000) makeBackup("Scheduled daily backup", true);
}


/* ---------- 10. ADMIN GATE, BADGES, ROUTER, START ---------- */
function initGate() {
  const input = $("#passKey");
  const attempt = async () => {
    if (await auth.verifyPassKey(input.value)) {
      auth.unlock();
      input.value = "";
      toast(`Access granted for ${UNLOCK_MINUTES} minutes.`);
      route();
    } else {
      input.closest(".adm-field").classList.add("has-error");
      input.select();
    }
  };
  $("#unlockBtn").onclick = attempt;
  input.addEventListener("keydown", (e) => { if (e.key === "Enter") attempt(); });
}

async function refreshBadges() {
  const [blood, donors, volunteers, inquiries, notifications] = await Promise.all([
    ...["blood", "donors", "volunteers", "inquiries"].map((c) => db.list(c)), getNotifications()]);

  const set = (name, text) => { const el = $(`[data-badge="${name}"]`); if (el) el.textContent = text; };
  set("blood", blood.filter((r) => r.status === "Active").length + " Live");
  set("donors", donors.length);
  set("volunteers", volunteers.length);
  set("inquiries", inquiries.filter((r) => r.status === "New").length);

  const unread = notifications.filter((n) => !readIds().has(n.id)).length;
  $("#bellCount").textContent = unread;
  $("#bellCount").hidden = unread === 0;
}

function route() {
  const path = location.hash.replace(/^#\/?/, "") || "dashboard";
  const [key, action] = path.split("/");

  closeModal();
  $$(".adm-link, .adm-sub a").forEach((a) => a.classList.toggle("is-active", a.getAttribute("href") === "#/" + path));
  const activeSub = $(".adm-sub a.is-active");
  if (activeSub) activeSub.closest(".adm-group").classList.add("is-open");

  if (key === "backup") { showPage($("#page-backup-" + (action === "restore" ? "restore" : "create"))); renderBackups(); }
  else if (key === "admins" && action !== "view" && !auth.isUnlocked()) { showPage($("#page-admins-gate")); $("#passKey").focus(); }
  else if (key === "admins") showList("admins", action === "view" ? "view" : "manage");
  else if (MODULES[key] && action === "add") showPage($(`#page-${key}-add`));
  else if (MODULES[key]) showList(key, action === "view" ? "view" : "manage");
  else { showPage($("#page-dashboard")); renderDashboard(); }

  $("#sidebar").classList.remove("is-open");
}

// Sidebar dropdowns (one open at a time)
$$(".adm-group__btn").forEach((button) => button.addEventListener("click", () => {
  const group = button.closest(".adm-group");
  const wasOpen = group.classList.contains("is-open");
  $$(".adm-group").forEach((g) => g.classList.remove("is-open"));
  group.classList.toggle("is-open", !wasOpen);
}));

$("#menuBtn").onclick = () => $("#sidebar").classList.toggle("is-open");
$("#bellBtn").onclick = openNotifications;
$("#logoutBtn").onclick = () => confirmDialog({
  title: "Logout", icon: "fa-right-from-bracket", danger: false, message: "Do you want to log out of the Admin Command Portal?",
  confirmText: "Logout", onConfirm: () => { location.href = "../index.html"; }
});

initTables();
initAddForms();
initDashboard();
initBackup();
initGate();
window.addEventListener("hashchange", route);
runAutoBackup();
refreshBadges();
route();
