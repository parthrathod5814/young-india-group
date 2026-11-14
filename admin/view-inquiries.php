<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

function inquiryEscape($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$inquiries = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        'SELECT id, name, phone, type, status, message, created_at
         FROM inquiries
         ORDER BY created_at DESC, id DESC'
    );

    $inquiries = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load inquiries: ' . $e->getMessage());
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
        <section class="adm-page" id="page-inquiries-view">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-envelope"></i>
              <span>View Inquiries</span>
              <span class="adm-chip">Contact Inquiries</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="inquirySearch"
                  placeholder="Search inquiries..."
                  aria-label="Search inquiries"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="manage-inquiries.php">
                  <i class="fa-solid fa-table-list"></i> Manage Inquiries
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load inquiries. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($inquiries) ?> inquiry(ies) found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Name</th>
                      <th>Phone</th>
                      <th>Inquiry Type</th>
                      <th>Status</th>
                      <th>Message</th>
                      <th>Created</th>
                    </tr>
                  </thead>

                  <tbody>
                    <?php if (!$inquiries): ?>
                      <tr class="empty-row">
                        <td colspan="7">No inquiries have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($inquiries as $index => $inquiry): ?>
                        <tr class="inquiry-row">
                          <td class="inquiry-number"><?= $index + 1 ?></td>
                          <td><?= inquiryEscape($inquiry['name']) ?></td>
                          <td><?= inquiryEscape($inquiry['phone']) ?></td>
                          <td><?= inquiryEscape($inquiry['type']) ?></td>
                          <td><?= inquiryEscape($inquiry['status']) ?></td>
                          <td><?= inquiryEscape($inquiry['message'] ?: '—') ?></td>
                          <td><?= inquiryEscape($inquiry['created_at']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noSearchResults" hidden>
                      <td colspan="7">No matching inquiries found.</td>
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
      const searchInput = document.getElementById('inquirySearch');
      const allRows = Array.from(document.querySelectorAll('.inquiry-row'));
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

        pageRows.forEach((row, index) => {
          row.hidden = false;
          row.querySelector('.inquiry-number').textContent = start + index + 1;
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
            `Showing ${first}–${last} of ${filteredRows.length} inquiry(ies)`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} inquiry(ies) found.`;
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