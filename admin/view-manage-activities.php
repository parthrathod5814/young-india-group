
    <!-- ===== ACTIVITIES : VIEW + MANAGE TABLE ===== -->
    <section class="adm-page" id="page-activities" data-module="activities" data-mode="manage" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-bullhorn"></i> <span data-slot="title">Manage Activities</span> <span class="adm-chip">Activities &amp; News</span></h2></div>
      <div class="adm-panel">
        <div class="adm-toolbar">
          <label class="adm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" data-search placeholder="Search activities..."></label>
          <div class="adm-actions">
          <a class="adm-btn adm-btn--primary is-manage-only" href="#/activities/add"><i class="fa-solid fa-plus"></i> Add Activity</a>
          </div>
        </div>
        <div class="adm-info" data-info></div>
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr>
              <th data-key="code">ID</th>
              <th data-key="title" data-type="text">Title</th>
              <th data-key="category" data-type="select">Category</th>
              <th data-key="area" data-type="select">Area / Taluka</th>
              <th data-key="date" data-type="date">Date</th>
              <th data-key="metric" data-type="text" class="is-view-only">Impact Metric (e.g. 1,420 Units)</th>
              <th data-key="status" data-type="select">Status</th>
              <th data-key="description" data-type="richtext" class="is-view-only">Description</th>
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