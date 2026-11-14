<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

function escapeOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$volunteers = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, name, phone, area, role, availability, status
         FROM volunteers
         ORDER BY created_at DESC, id DESC'
    );

    $volunteers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load volunteers: ' . $e->getMessage());
    $loadError = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<?php include __DIR__ . '/inc/head.php'; ?>

<body data-app-mode="php">
  <?php include __DIR__ . '/inc/header.php'; ?>

  <div class="adm-shell">
    <?php include __DIR__ . '/inc/sidebar.php'; ?>

    <main class="adm-main">
      <div class="adm-view" id="view">
        <section class="adm-page" id="page-volunteers-view">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-hand-holding-heart"></i>
              <span>View Volunteers</span>
              <span class="adm-chip">Volunteer Network</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="volunteerSearch"
                  placeholder="Search volunteers..."
                  aria-label="Search volunteers"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-volunteers.php">
                  <i class="fa-solid fa-plus"></i> Add Volunteer
                </a>
                <a class="adm-btn" href="manage-volunteers.php">
                  <i class="fa-solid fa-table-list"></i> Manage Volunteers
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load volunteers. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($volunteers) ?> volunteer(s) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Full Name</th>
                      <th>Phone / WhatsApp</th>
                      <th>Area / Ward</th>
                      <th>Primary Role</th>
                      <th>Availability</th>
                      <th>Status</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$volunteers): ?>
                      <tr class="empty-row">
                        <td colspan="7">No volunteers have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($volunteers as $index => $volunteer): ?>
                        <tr class="volunteer-row">
                          <td class="volunteer-number"><?= $index + 1 ?></td>
                          <td><?= escapeOutput($volunteer['name']) ?></td>
                          <td><?= escapeOutput($volunteer['phone']) ?></td>
                          <td><?= escapeOutput($volunteer['area']) ?></td>
                          <td><?= escapeOutput($volunteer['role']) ?></td>
                          <td><?= escapeOutput($volunteer['availability']) ?></td>
                          <td><?= escapeOutput($volunteer['status']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noSearchResults" hidden>
                      <td colspan="7">No matching volunteers found.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="pageInfo"></span>

                <div class="adm-pager">
                  <button
                    type="button"
                    class="adm-btn adm-btn--sm"
                    id="previousPage"
                  >
                    <i class="fa-solid fa-chevron-left"></i> Previous
                  </button>

                  <span id="pageNumber"></span>

                  <button
                    type="button"
                    class="adm-btn adm-btn--sm"
                    id="nextPage"
                  >
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
      const searchInput = document.getElementById('volunteerSearch');
      const allRows = Array.from(document.querySelectorAll('.volunteer-row'));
      const noSearchResults = document.getElementById('noSearchResults');
      const pageSize = 10;
      let currentPage = 1;
      let filteredRows = allRows;

      function renderRows() {
        const pageCount = Math.max(
          1,
          Math.ceil(filteredRows.length / pageSize)
        );

        currentPage = Math.min(currentPage, pageCount);

        allRows.forEach(row => {
          row.hidden = true;
        });

        const start = (currentPage - 1) * pageSize;
        const pageRows = filteredRows.slice(start, start + pageSize);

        pageRows.forEach((row, index) => {
          row.hidden = false;
          row.querySelector('.volunteer-number').textContent = start + index + 1;
        });

        if (noSearchResults) {
          noSearchResults.hidden =
            filteredRows.length !== 0 || allRows.length === 0;
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
            `Showing ${first}–${last} of ${filteredRows.length} volunteer(s)`;
        }

        if (resultInfo) {
          resultInfo.textContent =
            `${filteredRows.length} volunteer(s) found.`;
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