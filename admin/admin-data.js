/* =========================================================
   Admin Dashboard  |  admin-data.js

   1. Module configuration  (IDs, status colours, export flags)
   2. Data layer "db"       (localStorage now, backend later)
   3. Demo seed data

   BACKEND LATER: every db method is async, so you only need to
   replace the body of each method with a fetch() call, for example:
     async list(col)  { return (await fetch(`/api/${col}`)).json(); }
   admin.js does not need any other change.
   ========================================================= */


/* ---------- 1. SHARED OPTION LISTS ---------- */
const GROUPS    = ["O-", "O+", "A-", "A+", "B-", "B+", "AB-", "AB+"];
const AREAS     = ["Morbi City", "Shanala Road", "Wankaner", "Maliya", "Tankara", "Halvad", "Ravapar", "Other"];
const HOSPITALS = ["Morbi Civil Hospital", "Krishna Multispeciality", "Ayush Hospital", "Other Hospital"];
const ROLES     = ["Blood Donor & Mobilizer", "Emergency Responder", "Education & Mentorship", "Medical Camp Support", "Tree Plantation", "Disaster Relief Team", "Digital & Media"];


/* ---------- 2. MODULE CONFIGURATION ----------
   Only the data rules live here. The forms (fields) and the table columns
   are written in admin.html, so edit them there.
   prefix/seq: record ID (for example REQ-882)   colors: status -> badge colour
*/
const MODULES = {

  blood: {
    menu: "Blood Emergency Hub", singular: "Blood Request", plural: "Blood Requests", icon: "fa-droplet", prefix: "REQ-", seq: 881,
    colors: { "Active": "red", "In-Transit": "orange", "Fulfilled": "green" }
  },

  donors: {
    menu: "Donor Registry", singular: "Donor", plural: "Donors", icon: "fa-user-group", prefix: "DNR-", seq: 1000, exportable: true,
    colors: { "Active": "green", "Inactive": "orange" }
  },

  volunteers: {
    menu: "Volunteer Network", singular: "Volunteer", plural: "Volunteers", icon: "fa-hand-holding-heart", prefix: "VOL-", seq: 500, exportable: true,
    colors: { "Pending": "orange", "Approved": "green", "Rejected": "red" }
  },

  activities: {
    menu: "Activities & News", singular: "Activity", plural: "Activities", icon: "fa-bullhorn", prefix: "ACT-", seq: 100,
    colors: { "Published": "green", "Draft": "orange" }
  },

  inquiries: {
    menu: "Contact Inquiries", singular: "Inquiry", plural: "Inquiries", icon: "fa-envelope", prefix: "INQ-", seq: 300,
    colors: { "New": "red", "In Progress": "orange", "Resolved": "green" }
  }
};


// Admin accounts. "gated: true" = the Manage page needs the pass key.
MODULES.admins = {
  menu: "Admin Controls", singular: "Admin", plural: "Admins", icon: "fa-user-shield", prefix: "ADM-", seq: 10, gated: true,
  colors: { "Active": "green", "Suspended": "red" }
};


/* ---------- 3. DEMO SEED DATA (created once on first visit) ---------- */
function buildSeed() {
  const first = ["Ramesh", "Savita", "Kailash", "Hasmukh", "Rekha", "Nilesh", "Jaydeep", "Meena", "Chirag", "Paresh", "Dipika", "Kishan"];
  const last  = ["Patel", "Ahir", "Rabari", "Solanki", "Bharwad", "Desai", "Trivedi", "Prajapati"];
  const person = (i) => first[i % first.length] + " " + last[(i * 3) % last.length];
  const phone  = (i) => "98250" + String(10000 + i * 137).slice(-5);
  const ago    = (i) => Date.now() - i * 3600000;
  const day    = (i) => new Date(Date.now() - i * 86400000 * 3).toISOString().slice(0, 10);

  const data = { blood: [], donors: [], volunteers: [], activities: [], inquiries: [], admins: [], pending: { donors: [], volunteers: [] }, _seq: {} };
  const add = (col, i, record) => { data[col].push({ id: col + i, code: MODULES[col].prefix + (MODULES[col].seq - i), ...record, createdAt: ago(i) }); };

  [["Rameshbhai Patel", "O-", 2, "Morbi Civil Hospital", "ICU Bed #04", "Active", "Kishan Rabari"],
   ["Savita Devi Ahir", "B+", 1, "Krishna Multispeciality", "Sanala Road", "In-Transit", "Jaydeep D."],
   ["Kailasben Prajapati", "A+", 2, "Ayush Hospital", "Maternity Wing", "Active", "Paresh Desai"],
   ["Child Meet (Age 6)", "A+", 1, "Ayush Hospital", "Thalassemia", "Fulfilled", "Dr. V. Trivedi"],
   ["Hasmukhbhai Solanki", "AB-", 3, "Morbi Civil Hospital", "Emergency Ward", "Active", "Ramesh R."]
  ].forEach((r, i) => add("blood", i, { patient: r[0], group: r[1], units: r[2], hospital: r[3], ward: r[4], contact: phone(i + 3), runner: r[6], status: r[5] }));

  for (let i = 0; i < 45; i++) {
    add("donors", i, { name: person(i), group: GROUPS[i % 8], phone: phone(i), age: 20 + (i % 35), area: AREAS[i % 8], lastDonation: day(i), status: i % 6 === 0 ? "Inactive" : "Active" });
  }
  for (let i = 0; i < 32; i++) {
    add("volunteers", i, { name: person(i + 5), phone: phone(i + 50), area: AREAS[i % 7], role: ROLES[i % 7], availability: ["Weekend", "Emergency SOS", "4-8 hrs / week"][i % 3], status: ["Approved", "Approved", "Pending"][i % 3] });
  }
  [["Mega Blood Donation Drive at Ceramic Hub", "Blood Drive", "Morbi City", "1,420 Units"],
   ["Free Eye, Dental & Rural Health Camp", "Medical Camp", "Maliya", "780 Spectacles"],
   ["Clean & Green Morbi Tree Plantation", "Environment", "Shanala Road", "2,500 Saplings"],
   ["Digital Learning Kits for Rural Schools", "Education", "Wankaner", "850 Students"],
   ["Monsoon & Flood Emergency Taskforce", "Disaster Relief", "Maliya", "3,200 Kits"],
   ["Youth Skill Building & Governance", "Youth Leadership", "Morbi City", "240 Certified"]
  ].forEach((r, i) => add("activities", i, { title: r[0], category: r[1], area: r[2], date: day(i), metric: r[3], status: "Published",
    description: `<p><b>${r[0]}</b> was organised by Young India Group volunteers.</p><ul><li>Impact: ${r[3]}</li><li>Location: ${r[2]}</li></ul>` }));

  const types = ["Blood Donor Verification", "Urgent Blood Request", "Volunteer Registration", "Welfare / Scholarship Help"];
  for (let i = 0; i < 12; i++) {
    add("inquiries", i, { name: person(i + 2), phone: phone(i + 90), type: types[i % 4], status: ["New", "In Progress", "Resolved"][i % 3], message: "Please contact me regarding the above request." });
  }
  [["Secretariat Admin", "admin@youngindiagroup.org", "Super Admin"], ["Ramesh Rabari", "ramesh@youngindiagroup.org", "Blood Desk Admin"],
   ["Paresh Desai", "paresh@youngindiagroup.org", "Content Editor"], ["Dipika Solanki", "dipika@youngindiagroup.org", "Viewer"]
  ].forEach((r, i) => add("admins", i, { name: r[0], email: r[1], phone: phone(i + 10), role: r[2], status: "Active" }));

  // Temporary table: website sign-ups wait here until the admin approves them
  ["Bhavesh Makwana|B+|Wankaner", "Komal Parmar|O+|Morbi City", "Ajay Chauhan|A-|Halvad", "Hetal Joshi|AB+|Maliya"].forEach((row, i) => {
    const [name, group, area] = row.split("|");
    data.pending.donors.push({ id: "pd" + i, code: "TMP-D-" + (i + 1), name, group, phone: phone(i + 70), age: 22 + i * 3, area, lastDonation: "", createdAt: ago(i + 1) });
  });
  ["Jignesh Rathod|Emergency Responder|Morbi City", "Nisha Vyas|Education & Mentorship|Tankara", "Mehul Dave|Digital & Media|Shanala Road", "Pooja Kotak|Medical Camp Support|Wankaner"].forEach((row, i) => {
    const [name, role, area] = row.split("|");
    data.pending.volunteers.push({ id: "pv" + i, code: "TMP-V-" + (i + 1), name, phone: phone(i + 80), area, role, availability: ["Weekend", "Emergency SOS", "4-8 hrs / week", "Weekend"][i], createdAt: ago(i + 2) });
  });

  return data;
}


/* ---------- 4. DATA LAYER ---------- */
const DB_KEY = "yig_admin_db";

const db = {

  _load() {
    try {
      const saved = JSON.parse(localStorage.getItem(DB_KEY));
      if (saved) {
        // Older saved data may miss newer collections (for example admins)
        const missing = [...Object.keys(MODULES), "pending"].filter((k) => !saved[k]);
        if (missing.length) { const seed = buildSeed(); missing.forEach((k) => (saved[k] = seed[k])); localStorage.setItem(DB_KEY, JSON.stringify(saved)); }
        return saved;
      }
    } catch (e) { /* ignore */ }
    const seed = buildSeed();
    localStorage.setItem(DB_KEY, JSON.stringify(seed));
    return seed;
  },
  _save(data) { localStorage.setItem(DB_KEY, JSON.stringify(data)); },

  async list(col) {
    return [...this._load()[col]].sort((a, b) => b.createdAt - a.createdAt);
  },

  async add(col, values) {
    const data = this._load();
    const next = (data._seq[col] || MODULES[col].seq) + 1;
    data._seq[col] = next;
    const record = { id: col + "_" + Date.now(), code: MODULES[col].prefix + next, ...values, createdAt: Date.now() };
    data[col].push(record);
    this._save(data);
    return record;
  },

  async update(col, id, values) {
    const data = this._load();
    const record = data[col].find((r) => r.id === id);
    Object.assign(record, values);
    this._save(data);
    return record;
  },

  async remove(col, id) {
    const data = this._load();
    data[col] = data[col].filter((r) => r.id !== id);
    this._save(data);
  },

  // Backup & recovery
  snapshot() { return JSON.parse(JSON.stringify(this._load())); },
  restore(snapshot) {
    const current = this._load();
    Object.keys(MODULES).forEach((k) => { if (!Array.isArray(snapshot[k])) snapshot[k] = current[k] || []; });
    snapshot._seq = snapshot._seq || {};
    snapshot.pending = snapshot.pending || current.pending;
    this._save(snapshot);
  }
};


/* ---------- 4b. TEMPORARY "PENDING" TABLES ----------
   Donor / volunteer sign-ups from the website are saved here first.
   Nothing reaches the real donors / volunteers table until approve() runs.
   BACKEND LATER: list() -> GET /api/pending/:type, approve() -> POST /api/pending/:type/:id/approve */
const APPROVED_STATUS = { donors: "Active", volunteers: "Approved" };

db.pending = {
  async list(type) {
    return [...db._load().pending[type]].sort((a, b) => b.createdAt - a.createdAt);
  },

  async add(type, values) {                       // the website form calls this
    const data = db._load();
    data.pending[type].push({ id: "p_" + Date.now(), code: "TMP-" + type[0].toUpperCase() + "-" + (data.pending[type].length + 1), ...values, createdAt: Date.now() });
    db._save(data);
  },

  async approve(type, id) {                       // moves the record into the main table
    const data = db._load();
    const item = data.pending[type].find((r) => r.id === id);
    data.pending[type] = data.pending[type].filter((r) => r.id !== id);

    const next = (data._seq[type] || MODULES[type].seq) + 1;
    data._seq[type] = next;

    const values = { ...item };                    // keep every field the form sent
    ["id", "code", "createdAt"].forEach((k) => delete values[k]);
    const record = { id: type + "_" + Date.now(), code: MODULES[type].prefix + next, ...values, status: APPROVED_STATUS[type], createdAt: Date.now() };

    data[type].push(record);
    db._save(data);
    return record;
  }
};


/* ---------- 5. ADMIN PASS KEY (placeholder for the backend) ----------
   BACKEND LATER: verifyPassKey() must call your server, for example
     POST /api/admin/verify-passkey  { passKey }  ->  { ok: true }
   and the key must never be stored in this file. The demo value below
   exists only so the screen can be tested now.                        */
const DEMO_PASS_KEY = "YIG@2026";
const UNLOCK_KEY = "yig_admin_unlock";
const UNLOCK_MINUTES = 15;

const auth = {
  async verifyPassKey(passKey) { return passKey === DEMO_PASS_KEY; },
  isUnlocked() { return Number(sessionStorage.getItem(UNLOCK_KEY) || 0) > Date.now(); },
  unlock() { sessionStorage.setItem(UNLOCK_KEY, Date.now() + UNLOCK_MINUTES * 60000); },
  lock() { sessionStorage.removeItem(UNLOCK_KEY); }
};
