
    <!-- ===== BACKUP : RESTORE ===== -->
    <section class="adm-page" id="page-backup-restore" hidden>
      <div class="adm-page-head"><h2><i class="fa-solid fa-database"></i> Restore Data <span class="adm-chip">Backup &amp; Recovery</span></h2></div>
      <div class="adm-panel"><div class="adm-panel__head"><h3>Restore from a backup file</h3></div><div class="adm-panel__body">
        <p class="adm-note"><i class="fa-solid fa-triangle-exclamation"></i> Restoring replaces all current records. Select a .json file downloaded earlier from this page.</p>
        <label class="adm-drop"><i class="fa-solid fa-cloud-arrow-up"></i>Click to choose a backup file (.json)<input type="file" id="restoreFile" accept=".json,application/json"></label>
      </div></div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Backup history <small>latest 10 backups are kept</small></h3></div>
        <div class="adm-table-wrap"><table class="adm-table">
          <thead><tr><th>Name</th><th>Created</th><th>Type</th><th>Contents</th><th>Size</th><th>Action</th></tr></thead>
          <tbody data-backup-body></tbody>
        </table></div>
      </div>
    </section>

    </div></main>
  </div>