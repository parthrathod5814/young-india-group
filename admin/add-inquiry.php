
    <!-- ===== INQUIRIES : ADD PAGE ===== -->
    <section class="adm-page" id="page-inquiries-add" data-module="inquiries" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-envelope"></i> Add New Inquiry <span class="adm-chip">Contact Inquiries</span></h2></div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Inquiry Details</h3><a class="adm-btn adm-btn--sm" href="#/inquiries/list"><i class="fa-solid fa-table-list"></i> View all Inquiries</a></div>
        <div class="adm-panel__body">
      <form class="adm-form" id="form-inquiries" data-module="inquiries" novalidate>
        <div class="adm-field">
          <label for="inquiries-name">Full Name *</label>
          <input id="inquiries-name" name="name" type="text" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="inquiries-phone">Phone / WhatsApp *</label>
          <input id="inquiries-phone" name="phone" type="tel" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="inquiries-type">Nature of Inquiry *</label>
          <select id="inquiries-type" name="type" required><option value="">Select...</option><option>Blood Donor Verification</option><option>Urgent Blood Request</option><option>Volunteer Registration</option><option>Welfare / Scholarship Help</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="inquiries-status">Status *</label>
          <select id="inquiries-status" name="status" required><option value="">Select...</option><option>New</option><option>In Progress</option><option>Resolved</option></select>
          <small></small>
        </div>
        <div class="adm-field is-wide">
          <label for="inquiries-message">Message</label>
          <textarea id="inquiries-message" name="message"></textarea>
          <small></small>
        </div>
        <div class="adm-form__actions">
          <button type="submit" class="adm-btn adm-btn--primary"><i class="fa-solid fa-floppy-disk"></i> Save Record</button>
          <button type="reset" class="adm-btn">Reset</button>
        </div>
      </form>
        </div>
      </div>
    </section>