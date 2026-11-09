
    <!-- ===== VOLUNTEERS : ADD PAGE ===== -->
    <section class="adm-page" id="page-volunteers-add" data-module="volunteers" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-hand-holding-heart"></i> Add New Volunteer <span class="adm-chip">Volunteer Network</span></h2></div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Volunteer Details</h3><a class="adm-btn adm-btn--sm" href="#/volunteers/list"><i class="fa-solid fa-table-list"></i> View all Volunteers</a></div>
        <div class="adm-panel__body">
      <form class="adm-form" id="form-volunteers" data-module="volunteers" novalidate>
        <div class="adm-field">
          <label for="volunteers-name">Full Name *</label>
          <input id="volunteers-name" name="name" type="text" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="volunteers-phone">Phone / WhatsApp *</label>
          <input id="volunteers-phone" name="phone" type="tel" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="volunteers-area">Area / Ward *</label>
          <select id="volunteers-area" name="area" required><option value="">Select...</option><option>Morbi City</option><option>Shanala Road</option><option>Wankaner</option><option>Maliya</option><option>Tankara</option><option>Halvad</option><option>Ravapar</option><option>Other</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="volunteers-role">Primary Role *</label>
          <select id="volunteers-role" name="role" required><option value="">Select...</option><option>Blood Donor &amp; Mobilizer</option><option>Emergency Responder</option><option>Education &amp; Mentorship</option><option>Medical Camp Support</option><option>Tree Plantation</option><option>Disaster Relief Team</option><option>Digital &amp; Media</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="volunteers-availability">Availability *</label>
          <select id="volunteers-availability" name="availability" required><option value="">Select...</option><option>Weekend</option><option>Emergency SOS</option><option>4-8 hrs / week</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="volunteers-status">Status *</label>
          <select id="volunteers-status" name="status" required><option value="">Select...</option><option>Pending</option><option>Approved</option><option>Rejected</option></select>
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