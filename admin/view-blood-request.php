<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

function escapeOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$requests = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT patient, blood_group, patient_condition, units,
                hospital_address, ward, contact, status
         FROM blood_requests
         ORDER BY created_at DESC'
    );

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load blood requests: ' . $e->getMessage());
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/inc/head.php'; ?>

<body>
  <?php include __DIR__ . '/inc/header.php'; ?>

  <div class="adm-shell">
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <main class="adm-main">
      <div class="adm-view" id="view">
        <section class="adm-page" id="page-blood-view">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-droplet"></i>
              <span>View Blood Requests</span>
              <span class="adm-chip">Blood Emergency Hub</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="bloodSearch"
                  placeholder="Search blood requests..."
                  aria-label="Search blood requests"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="manage-blood-request.php">
                  <i class="fa-solid fa-table-list"></i> Manage Blood Request
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load blood requests. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($requests) ?> blood request(s) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Patient Name</th>
                      <th>Blood Group</th>
                      <th>Patient Condition</th>
                      <th>Units Needed</th>
                      <th>Hospital Name / Address</th>
                      <th>Ward / Bed</th>
                      <th>Attendant Phone</th>
                      <th>Status</th>
                    </tr>
                  </thead>

                  <tbody id="bloodRows">
                    <?php if (!$requests): ?>
                      <tr class="empty-row">
                        <td colspan="9">No blood requests have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($requests as $index => $request): ?>
                        <tr class="blood-row">
                          <td><?= $index + 1 ?></td>
                          <td><?= escapeOutput($request['patient']) ?></td>
                          <td><?= escapeOutput($request['blood_group']) ?></td>
                          <td><?= escapeOutput($request['patient_condition']) ?></td>
                          <td><?= escapeOutput($request['units']) ?></td>
                          <td><?= escapeOutput($request['hospital_address']) ?></td>
                          <td><?= escapeOutput($request['ward'] ?: '—') ?></td>
                          <td><?= escapeOutput($request['contact']) ?></td>
                          <td><?= escapeOutput($request['status']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noSearchResults" hidden>
                      <td colspan="9">No matching blood requests.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="pageInfo"></span>

                <div class="adm-pager">
                  <button type="button" class="adm-btn adm-btn--sm" id="previousPage">
                    <i class="fa-solid fa-chevron-left"></i> Previous
                  </button>

                  <span id="pageNumber"></span>

                  <button type="button" class="adm-btn adm-btn--sm" id="nextPage">
                    Next <i class="fa-solid fa-chevron-right"></i>
                  </button>
                </div>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </div>
    </main>
  </div>

  <div class="adm-toast" id="toast" role="status" aria-live="polite"></div>

  <script>
    (() => {
      const searchInput = document.getElementById('bloodSearch');
      const allRows = Array.from(document.querySelectorAll('.blood-row'));
      const noSearchResults = document.getElementById('noSearchResults');
      const pageSize = 10;
      let currentPage = 1;
      let filteredRows = allRows;

      function renderRows() {
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);

        allRows.forEach(row => {
          row.hidden = true;
        });

        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);

        pageRows.forEach(row => {
          row.hidden = false;
        });

        if (noSearchResults) {
          noSearchResults.hidden = filteredRows.length !== 0 || allRows.length === 0;
        }

        const pageNumber = document.getElementById('pageNumber');
        const pageInfo = document.getElementById('pageInfo');
        const resultInfo = document.getElementById('resultInfo');
        const previousButton = document.getElementById('previousPage');
        const nextButton = document.getElementById('nextPage');

        if (pageNumber) {
          pageNumber.textContent = `Page ${currentPage} of ${pageCount}`;
        }

        if (pageInfo) {
          const first = filteredRows.length === 0 ? 0 : start + 1;
          const last = start + pageRows.length;
          pageInfo.textContent =
            `Showing ${first}–${last} of ${filteredRows.length} request(s)`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} blood request(s) found.`;
        }

        if (previousButton) {
          previousButton.disabled = currentPage <= 1;
        }

        if (nextButton) {
          nextButton.disabled = currentPage >= pageCount;
        }
      }

      searchInput?.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();

        filteredRows = allRows.filter(row =>
          row.textContent.toLowerCase().includes(query)
        );

        currentPage = 1;
        renderRows();
      });

      document.getElementById('previousPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderRows();
        }
      });

      document.getElementById('nextPage')?.addEventListener('click', () => {
        if (currentPage < Math.ceil(filteredRows.length / pageSize)) {
          currentPage++;
          renderRows();
        }
      });

     

      renderRows();
    })();
  </script>

  <script src="admin.js"></script>

  
</body>
</html>