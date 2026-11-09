<?php
require_once __DIR__ . '/inc/auth.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">

<?php include ('inc/head.php'); ?>

<body>

  <?php include ('inc/header.php'); ?>
  
  <div class="adm-shell">

  <?php include ('inc/sidebar.php'); ?>


    <!-- ===== PAGE CONTENT: every page is plain HTML below; admin.js only shows / hides / fills them ===== -->
    <main class="adm-main"><div class="adm-view" id="view">

    <!-- ===== DASHBOARD ===== -->
    <section class="adm-page" id="page-dashboard">
      <div class="adm-page-head">
        <h2>Operations Command Overview <span class="adm-chip"> Live Sync</span></h2>
        <small><i class="fa-solid fa-bolt"></i> High Alert Ready</small>
      </div>

      <div class="adm-grid">
      <div class="adm-stat"><div><small>TOTAL VOLUNTEERS</small><strong data-stat="volunteers">0</strong><em>Morbi District Active Cadre</em></div><span class="adm-stat__icon"><i class="fa-solid fa-people-group"></i></span></div>
      <div class="adm-stat"><div><small>VERIFIED DONORS</small><strong data-stat="donors">0</strong><em>Across 8 Talukas Network</em></div><span class="adm-stat__icon"><i class="fa-solid fa-hand-holding-heart"></i></span></div>
      <div class="adm-stat adm-stat--alert"><div><small>URGENT BLOOD</small><strong data-stat="urgent">0</strong><em>Morbi Civil &amp; Private ICU</em></div><span class="adm-stat__icon"><i class="fa-solid fa-heart-pulse"></i></span></div>
      <div class="adm-stat"><div><small>LIVES SAVED</small><strong data-stat="saved">0</strong><em>Fulfilled requests</em></div><span class="adm-stat__icon"><i class="fa-solid fa-heart"></i></span></div>
      <div class="adm-stat"><div><small>CIVIC DRIVES</small><strong data-stat="drives">0</strong><em>Camps, Relief &amp; Green Morbi</em></div><span class="adm-stat__icon"><i class="fa-solid fa-bullhorn"></i></span></div>
      <div class="adm-stat"><div><small>TALUKA CENTERS</small><strong data-stat="talukas">0</strong><em>Grassroots Base</em></div><span class="adm-stat__icon"><i class="fa-solid fa-circle-nodes"></i></span></div>
      </div>

      <div class="adm-inq">
        <button class="adm-inq__btn" data-inq="donors">
          <span class="adm-inq__icon"><i class="fa-solid fa-hand-holding-droplet"></i></span>
          <div><strong>Donors Inquiry</strong><small>Review new donor sign-ups</small></div>
          <span class="adm-inq__count" data-count="donors">0</span>
        </button>
        <button class="adm-inq__btn" data-inq="volunteers">
          <span class="adm-inq__icon adm-inq__icon--green"><i class="fa-solid fa-people-group"></i></span>
          <div><strong>Volunteers Inquiry</strong><small>Review new volunteer applications</small></div>
          <span class="adm-inq__count" data-count="volunteers">0</span>
        </button>
        <button class="adm-inq__btn" data-inq="contact">
          <span class="adm-inq__icon adm-inq__icon--orange"><i class="fa-solid fa-envelope-open-text"></i></span>
          <div><strong>Contact Inquiry</strong><small>Open all contact messages</small></div>
          <span class="adm-inq__count" data-count="contact">0</span>
        </button>
      </div>

      <div class="adm-panel">
        <div class="adm-panel__head">
          <h3>Live Blood Request Dispatch <small>Morbi Taluka Emergency Desk</small></h3>
          <div class="adm-actions"><span class="adm-badge adm-badge--red">Live Feed</span><button class="adm-btn adm-btn--sm" id="feedRefresh" title="Refresh"><i class="fa-solid fa-rotate"></i></button></div>
        </div>
        <div id="feedArea"></div>
      </div>
    </section>

</div></main>

  <!-- ===== POPUPS ===== -->
  <div class="adm-modal" id="modal-confirm" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-triangle-exclamation" data-slot="icon"></i> <span data-slot="title">Confirm</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
        <p data-slot="message"></p>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Cancel</button>
        <button type="button" class="adm-btn adm-btn--danger" data-slot="confirm">Yes</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-message" hidden>
    <div class="adm-modal__box" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-circle-check" data-slot="icon"></i> <span data-slot="title">Done</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
        <p data-slot="message"></p>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-slot="secondary" hidden></button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="primary">OK</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-edit" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-pen-to-square" data-slot="icon"></i> <span data-slot="title">Update</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
        <div data-slot="form"></div>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Cancel</button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="save"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-admin-add" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-user-plus" data-slot="icon"></i> <span data-slot="title">Add Admin</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
      <form class="adm-form" id="form-admins" data-module="admins" novalidate>
        <div class="adm-field">
          <label for="admins-name">Full Name *</label>
          <input id="admins-name" name="name" type="text" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="admins-email">Email Address *</label>
          <input id="admins-email" name="email" type="email" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="admins-phone">Phone / WhatsApp *</label>
          <input id="admins-phone" name="phone" type="tel" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="admins-role">Access Role *</label>
          <select id="admins-role" name="role" required><option value="">Select...</option><option>Super Admin</option><option>Secretariat Admin</option><option>Blood Desk Admin</option><option>Content Editor</option><option>Viewer</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="admins-status">Status *</label>
          <select id="admins-status" name="status" required><option value="">Select...</option><option>Active</option><option>Suspended</option></select>
          <small></small>
        </div>
      </form>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Cancel</button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="save"><i class="fa-solid fa-floppy-disk"></i> Save</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-notifications" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-bell" data-slot="icon"></i> <span data-slot="title">Notifications</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
        <div class="adm-notes" data-slot="list"></div>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Close</button>
        <button type="button" class="adm-btn adm-btn--primary" data-slot="markall"><i class="fa-solid fa-check-double"></i> Mark all as read</button>
      </div>
    </div>
  </div>

  <div class="adm-modal" id="modal-pending" hidden>
    <div class="adm-modal__box is-wide" role="dialog" aria-modal="true">
      <div class="adm-modal__head"><h3><i class="fa-solid fa-hand-holding-droplet" data-slot="icon"></i> <span data-slot="title">Inquiries</span></h3><button type="button" class="adm-x" data-close aria-label="Close">&times;</button></div>
      <div class="adm-modal__body">
        <div class="adm-pending" data-slot="list"></div>
      </div>
      <div class="adm-modal__foot">
        <button type="button" class="adm-btn" data-close>Close</button>
      </div>
    </div>
  </div>


  <!-- ===== ROW / ITEM TEMPLATES (JavaScript only copies these and fills in the data) ===== -->
  <template id="tpl-actions">
    <button class="adm-btn adm-btn--sm" data-act="edit"><i class="fa-solid fa-pen"></i> Update</button>
    <button class="adm-btn adm-btn--danger adm-btn--sm" data-act="delete"><i class="fa-solid fa-trash"></i> Delete</button>
  </template>

  <template id="tpl-feed-row">
    <div class="adm-feed__row">
      <div class="adm-feed__main">
        <span class="adm-group-badge" data-f="group"></span>
        <div>
          <strong data-f="patient"></strong> <span class="adm-badge" data-f="badge"></span>
          <small data-f="where"></small>
        </div>
      </div>
      <div class="adm-feed__side">
        <div>Runner: <b data-f="runner"></b></div>
        <button class="adm-btn adm-btn--orange adm-btn--sm" data-for="Active" data-act="alert"><i class="fa-solid fa-tower-broadcast"></i> Alert Donors</button>
        <button class="adm-btn adm-btn--sm" data-for="In-Transit" data-act="track"><i class="fa-solid fa-location-dot"></i> Track</button>
        <button class="adm-btn adm-btn--green adm-btn--sm" data-for="In-Transit" data-act="done">Mark Done</button>
        <span class="adm-badge adm-badge--green" data-for="Fulfilled"><i class="fa-solid fa-circle-check"></i> Completed</span>
      </div>
    </div>
  </template>

  <template id="tpl-notice">
    <a class="adm-notice">
      <span class="adm-notice__icon"><i class="fa-solid"></i></span>
      <div><strong data-f="title"></strong><small data-f="text"></small></div>
      <time data-f="time"></time>
    </a>
  </template>

  <template id="tpl-pending">
    <div class="adm-pending__item">
      <div class="adm-pending__head">
        <div><strong data-f="name"></strong><small data-f="meta"></small></div>
        <span class="adm-badge adm-badge--orange">Pending</span>
      </div>
      <dl class="adm-pending__grid" data-f="grid"></dl>
      <div class="adm-pending__foot">
        <span><i class="fa-solid fa-hourglass-half"></i> Held in the temporary table until approved</span>
        <button class="adm-btn adm-btn--green" data-approve><i class="fa-solid fa-check"></i> Approve</button>
      </div>
    </div>
  </template>

  <template id="tpl-backup-row">
    <tr>
      <td><b data-f="name"></b><br><small data-f="note"></small></td>
      <td data-f="created"></td>
      <td><span class="adm-badge" data-f="type"></span></td>
      <td data-f="records"></td>
      <td data-f="size"></td>
      <td class="is-actions">
        <button class="adm-btn adm-btn--sm" data-bk="download" title="Download"><i class="fa-solid fa-download"></i></button>
        <button class="adm-btn adm-btn--green adm-btn--sm is-restore-only" data-bk="restore"><i class="fa-solid fa-clock-rotate-left"></i> Restore</button>
        <button class="adm-btn adm-btn--danger adm-btn--sm" data-bk="delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
      </td>
    </tr>
  </template>

  <div class="adm-toast" id="toast" role="status" aria-live="polite"></div>

  <!-- Export libraries (Excel + PDF) -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

  <!-- <script src="admin-data.js"></script> -->
  <script src="admin.js"></script>
</body>
</html>
