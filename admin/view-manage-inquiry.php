
    <!-- ===== INQUIRIES : VIEW + MANAGE TABLE ===== -->
    <section class="adm-page" id="page-inquiries" data-module="inquiries" data-mode="manage" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-envelope"></i> <span data-slot="title">Manage Inquiries</span> <span class="adm-chip">Contact Inquiries</span></h2></div>
      <div class="adm-panel">
        <div class="adm-toolbar">
          <label class="adm-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" data-search placeholder="Search inquiries..."></label>
          <div class="adm-actions">
          <a class="adm-btn adm-btn--primary is-manage-only" href="#/inquiries/add"><i class="fa-solid fa-plus"></i> Add Inquiry</a>
          </div>
        </div>
        <div class="adm-info" data-info></div>
        <div class="adm-table-wrap">
          <table class="adm-table">
            <thead><tr>
              <th data-key="code">ID</th>
              <th data-key="name" data-type="text">Full Name</th>
              <th data-key="phone" data-type="tel">Phone / WhatsApp</th>
              <th data-key="type" data-type="select">Nature of Inquiry</th>
              <th data-key="status" data-type="select">Status</th>
              <th data-key="message" data-type="textarea">Message</th>
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
