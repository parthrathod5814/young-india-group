
    <!-- ===== ADMINS : VIEW + MANAGE TABLE ===== -->
    <section class="adm-page" id="page-admins" data-module="admins" data-mode="manage" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-user-shield"></i> <span data-slot="title">Manage Admins</span> <span class="adm-chip">Admin Controls</span></h2></div>
      <div class="adm-panel">
        <div class="adm-toolbar">
          <label class="adm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" data-search placeholder="Search admins..."></label>
          <div class="adm-actions">
          <button class="adm-btn is-manage-only" data-lock><i class="fa-solid fa-lock"></i> Lock</button>
          <button class="adm-btn adm-btn--primary is-manage-only" data-open="modal-admin-add"><i class="fa-solid fa-plus"></i> Add Admin</button>
          </div>
        </div>
        <div class="adm-info" data-info></div>
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr>
              <th data-key="code">ID</th>
              <th data-key="name" data-type="text">Full Name</th>
              <th data-key="email" data-type="email">Email Address</th>
              <th data-key="phone" data-type="tel">Phone / WhatsApp</th>
              <th data-key="role" data-type="select">Access Role</th>
              <th data-key="status" data-type="select">Status</th>
              <th data-key="actions" class="is-manage-only">Action</th>
            </tr></thead>
            <tbody data-rows></tbody>
          </table>
        </div>
        <div class="adm-info adm-info--bottom">
          <span data-info></span>
          <div class="adm-pager">
            <button class="adm-btn adm-btn--sm" data-page="prev"><i class="fa-solid fa-chevron-left"></i> Previous</button>
            <span data-pageno></span>
            <button class="adm-btn adm-btn--sm" data-page="next">Next <i class="fa-solid fa-chevron-right"></i></button>
          </div>
        </div>
      </div>
    </section>

    <!-- ===== MANAGE ADMINS : PASS KEY GATE ===== -->
    <section class="adm-page" id="page-admins-gate" hidden>
      <div class="adm-page-head"><h2><i class="fa-solid fa-user-shield"></i> Manage Admins <span class="adm-chip">Admin Controls</span></h2></div>
      <div class="adm-panel"><div class="adm-panel__body adm-gate">
        <i class="fa-solid fa-lock adm-gate__icon"></i>
        <h3>Pass key required</h3>
        <p>This section is restricted. Enter the administrator pass key to add, update or delete admins.</p>
        <div class="adm-field">
          <label for="passKey">Pass key</label>
          <input type="password" id="passKey" autocomplete="off" placeholder="Enter pass key">
          <small>Incorrect pass key. Please try again.</small>
        </div>
        <button class="adm-btn adm-btn--primary" id="unlockBtn"><i class="fa-solid fa-unlock"></i> Unlock</button>
        <p class="adm-gate__hint"><i class="fa-regular fa-eye"></i> Need to see the admin list only? Use <a href="#/admins/view">View Admins</a>.</p>
      </div></div>
    </section>
