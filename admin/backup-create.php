
    <!-- ===== BACKUP : CREATE ===== -->
    <section class="adm-page" id="page-backup-create" hidden>
      <div class="adm-page-head"><h2><i class="fa-solid fa-database"></i> Create Backup <span class="adm-chip">Backup &amp; Recovery</span></h2></div>
      <div class="adm-two">
        <div class="adm-panel"><div class="adm-panel__head"><h3>Create backup now</h3></div><div class="adm-panel__body">
          <div class="adm-field"><label for="bkNote">Note (optional)</label><input id="bkNote" placeholder="e.g. Before monthly update"></div>
          <label class="adm-switch"><input type="checkbox" id="autoBackup"> Automatic daily backup</label>
          <button class="adm-btn adm-btn--primary" id="bkCreate"><i class="fa-solid fa-database"></i> Create Backup Now</button>
        </div></div>
        <div class="adm-panel"><div class="adm-panel__head"><h3>Database summary</h3></div><div class="adm-panel__body">
          <p>
          Blood Requests: <b data-summary="blood">0</b><br>
          Donors: <b data-summary="donors">0</b><br>
          Volunteers: <b data-summary="volunteers">0</b><br>
          Activities: <b data-summary="activities">0</b><br>
          Inquiries: <b data-summary="inquiries">0</b><br>
          Admins: <b data-summary="admins">0</b><br>
          </p>
          <p style="margin-top:10px"><small>Last backup: <span data-last-backup>never</span></small></p>
        </div></div>
      </div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Backup history <small>latest 10 backups are kept</small></h3></div>
        <div class="adm-table-wrap"><table class="adm-table">
          <thead><tr><th>Name</th><th>Created</th><th>Type</th><th>Contents</th><th>Size</th><th>Action</th></tr></thead>
          <tbody data-backup-body></tbody>
        </table></div>
      </div>
    </section>