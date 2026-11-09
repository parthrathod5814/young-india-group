<!-- ===== DONORS : ADD PAGE ===== -->
    <section class="adm-page" id="page-donors-add" data-module="donors" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-user-group"></i> Add New Donor <span class="adm-chip">Donor Registry</span></h2></div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Donor Details</h3><a class="adm-btn adm-btn--sm" href="#/donors/list"><i class="fa-solid fa-table-list"></i> View all Donors</a></div>
        <div class="adm-panel__body">
      <form class="adm-form" id="form-donors" data-module="donors" novalidate>
        <div class="adm-field">
          <label for="donors-name">Full Name *</label>
          <input id="donors-name" name="name" type="text" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-group">Blood Group *</label>
          <select id="donors-group" name="group" required><option value="">Select...</option><option>O-</option><option>O+</option><option>A-</option><option>A+</option><option>B-</option><option>B+</option><option>AB-</option><option>AB+</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-phone">Phone / WhatsApp *</label>
          <input id="donors-phone" name="phone" type="tel" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-age">Age (18-60) *</label>
          <input id="donors-age" name="age" type="number" required min="18" max="60">
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-area">Area / Taluka *</label>
          <select id="donors-area" name="area" required><option value="">Select...</option><option>Morbi City</option><option>Shanala Road</option><option>Wankaner</option><option>Maliya</option><option>Tankara</option><option>Halvad</option><option>Ravapar</option><option>Other</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-lastDonation">Last Donation</label>
          <input id="donors-lastDonation" name="lastDonation" type="date">
          <small></small>
        </div>
        <div class="adm-field">
          <label for="donors-status">Status *</label>
          <select id="donors-status" name="status" required><option value="">Select...</option><option>Active</option><option>Inactive</option></select>
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