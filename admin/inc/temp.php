<!-- ===== BLOOD REQUESTS: ADD PAGE ===== -->
<section class="adm-page" id="page-blood-add" data-module="blood" hidden>
  <div class="adm-page-head">
    <h2>
      <i class="fa-solid fa-droplet"></i>
      Add New Blood Request
      <span class="adm-chip">Blood Emergency Hub</span>
    </h2>
  </div>

  <div class="adm-panel">
    <div class="adm-panel__head">
      <h3>Blood Request Details</h3>
      <a class="adm-btn adm-btn--sm" href="#/blood/list">
        <i class="fa-solid fa-table-list"></i> View all Blood Requests
      </a>
    </div>

    <div class="adm-panel__body">
      <form class="adm-form" id="form-blood" data-module="blood" novalidate>
        <div class="adm-field">
          <label for="blood-patient">Patient Name *</label>
          <input id="blood-patient" name="patient" type="text" required>
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-group">Blood Group *</label>
          <select id="blood-group" name="group" required>
            <option value="">Select...</option>
            <option>O-</option><option>O+</option>
            <option>A-</option><option>A+</option>
            <option>B-</option><option>B+</option>
            <option>AB-</option><option>AB+</option>
          </select>
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-units">Units Needed *</label>
          <input id="blood-units" name="units" type="number" required min="1" max="10">
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-hospital">Hospital *</label>
          <select id="blood-hospital" name="hospital" required>
            <option value="">Select...</option>
            <option>Morbi Civil Hospital</option>
            <option>Krishna Multispeciality</option>
            <option>Ayush Hospital</option>
            <option>Other Hospital</option>
          </select>
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-ward">Ward / Bed</label>
          <input id="blood-ward" name="ward" type="text">
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-contact">Attendant Phone *</label>
          <input id="blood-contact" name="contact" type="tel" required>
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-runner">Runner / Coordinator</label>
          <input id="blood-runner" name="runner" type="text">
          <small></small>
        </div>

        <div class="adm-field">
          <label for="blood-status">Status *</label>
          <select id="blood-status" name="status" required>
            <option value="">Select...</option>
            <option>Active</option>
            <option>In-Transit</option>
            <option>Fulfilled</option>
          </select>
          <small></small>
        </div>

        <div class="adm-form__actions">
          <button type="submit" class="adm-btn adm-btn--primary">
            <i class="fa-solid fa-floppy-disk"></i> Save Record
          </button>
          <button type="reset" class="adm-btn">Reset</button>
        </div>
      </form>
    </div>
  </div>
</section>