
    <!-- ===== ACTIVITIES : ADD PAGE ===== -->
    <section class="adm-page" id="page-activities-add" data-module="activities" hidden>
    <div class="adm-page-head"><h2><i class="fa-solid fa-bullhorn"></i> Add New Activity <span class="adm-chip">Activities &amp; News</span></h2></div>
      <div class="adm-panel">
        <div class="adm-panel__head"><h3>Activity Details</h3><a class="adm-btn adm-btn--sm" href="#/activities/list"><i class="fa-solid fa-table-list"></i> View all Activities</a></div>
        <div class="adm-panel__body">
      <form class="adm-form" id="form-activities" data-module="activities" novalidate>
        <div class="adm-field is-wide">
          <label for="activities-title">Title *</label>
          <input id="activities-title" name="title" type="text" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="activities-category">Category *</label>
          <select id="activities-category" name="category" required><option value="">Select...</option><option>Blood Drive</option><option>Medical Camp</option><option>Education</option><option>Environment</option><option>Youth Leadership</option><option>Disaster Relief</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="activities-area">Area / Taluka *</label>
          <select id="activities-area" name="area" required><option value="">Select...</option><option>Morbi City</option><option>Shanala Road</option><option>Wankaner</option><option>Maliya</option><option>Tankara</option><option>Halvad</option><option>Ravapar</option><option>Other</option></select>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="activities-date">Date *</label>
          <input id="activities-date" name="date" type="date" required>
          <small></small>
        </div>
        <div class="adm-field">
          <label for="activities-metric">Impact Metric (e.g. 1,420 Units)</label>
          <input id="activities-metric" name="metric" type="text">
          <small></small>
        </div>
        <div class="adm-field">
          <label for="activities-status">Status *</label>
          <select id="activities-status" name="status" required><option value="">Select...</option><option>Published</option><option>Draft</option></select>
          <small></small>
        </div>
        <div class="adm-field is-wide">
          <label for="activities-description">Description *</label>
          <div class="adm-editor">
          <div class="adm-editor__bar">
            <button type="button" data-cmd="bold" title="Bold"><i class="fa-solid fa-bold"></i></button>
            <button type="button" data-cmd="italic" title="Italic"><i class="fa-solid fa-italic"></i></button>
            <button type="button" data-cmd="underline" title="Underline"><i class="fa-solid fa-underline"></i></button>
            <button type="button" data-cmd="strikeThrough" title="Strikethrough"><i class="fa-solid fa-strikethrough"></i></button>
            <span></span>
            <button type="button" data-cmd="formatBlock:h2" title="Heading"><i class="fa-solid fa-heading"></i></button>
            <button type="button" data-cmd="formatBlock:p" title="Normal paragraph"><i class="fa-solid fa-paragraph"></i></button>
            <button type="button" data-cmd="formatBlock:blockquote" title="Quote"><i class="fa-solid fa-quote-right"></i></button>
            <span></span>
            <button type="button" data-cmd="insertUnorderedList" title="Bullet list"><i class="fa-solid fa-list-ul"></i></button>
            <button type="button" data-cmd="insertOrderedList" title="Numbered list"><i class="fa-solid fa-list-ol"></i></button>
            <span></span>
            <button type="button" data-cmd="justifyLeft" title="Align left"><i class="fa-solid fa-align-left"></i></button>
            <button type="button" data-cmd="justifyCenter" title="Center"><i class="fa-solid fa-align-center"></i></button>
            <button type="button" data-cmd="justifyRight" title="Align right"><i class="fa-solid fa-align-right"></i></button>
            <span></span>
            <button type="button" data-cmd="createLink" title="Insert link"><i class="fa-solid fa-link"></i></button>
            <button type="button" data-cmd="unlink" title="Remove link"><i class="fa-solid fa-link-slash"></i></button>
            <span></span>
            <button type="button" data-cmd="undo" title="Undo"><i class="fa-solid fa-rotate-left"></i></button>
            <button type="button" data-cmd="redo" title="Redo"><i class="fa-solid fa-rotate-right"></i></button>
            <button type="button" data-cmd="removeFormat" title="Clear formatting"><i class="fa-solid fa-eraser"></i></button>
            <select data-cmd="fontSize" title="Font size"><option value="">Size</option><option value="2">Small</option><option value="3">Normal</option><option value="5">Large</option><option value="6">X-Large</option></select>
            <input type="color" data-cmd="foreColor" value="#4a1480" title="Text colour">
          </div>
          <div class="adm-editor__area" contenteditable="true" data-editor="description" data-required></div>
        </div>
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