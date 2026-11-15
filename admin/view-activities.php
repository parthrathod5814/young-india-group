<?php
require_once __DIR__ . '/inc/auth.php';
require_login();

require_once __DIR__ . '/inc/config.php';
$pdo = db();

function escapeActivityOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$activities = [];
$loadError = false;

try {
    $stmt = $pdo->query(
        "SELECT
            a.id,
            a.category,
            a.area,
            a.activity_date,
            a.status,
            COALESCE(NULLIF(t_en.title, ''), '(English translation not added)') AS title
         FROM activities AS a
         LEFT JOIN activity_translations AS t_en
           ON t_en.activity_id = a.id
          AND t_en.language = 'en'
         ORDER BY a.activity_date DESC, a.id DESC"
    );

    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load activities: ' . $e->getMessage());
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
        <section class="adm-page" id="page-activities-view">
          <div class="adm-page-head">
            <h2>
              <i class="fa-solid fa-bullhorn"></i>
              <span>View Activities</span>
              <span class="adm-chip">Activities &amp; News</span>
            </h2>
          </div>

          <div class="adm-panel">
            <div class="adm-toolbar">
              <label class="adm-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input
                  type="search"
                  id="activitiesSearch"
                  placeholder="Search activities..."
                  aria-label="Search activities"
                >
              </label>

              <div class="adm-actions">
                <a class="adm-btn adm-btn--primary" href="add-activity.php">
                  <i class="fa-solid fa-plus"></i> Add Activity
                </a>
              </div>
            </div>

            <?php if ($loadError): ?>
              <div class="adm-info" role="alert">
                Could not load activities. Please try again later.
              </div>
            <?php else: ?>
              <div class="adm-info">
                <span id="resultInfo"><?= count($activities) ?> activity/activities found.</span>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>Title (English)</th>
                      <th>Category</th>
                      <th>Area / Taluka</th>
                      <th>Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>

                  <tbody id="activitiesRows">
                    <?php if (!$activities): ?>
                      <tr class="empty-row">
                        <td colspan="6">No activities have been added yet.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($activities as $index => $activity): ?>
                        <?php
                        $dateDisplay = (string) ($activity['activity_date'] ?? '');

                        if ($dateDisplay !== '') {
                            $dateObject = DateTime::createFromFormat('Y-m-d', $dateDisplay);
                            if ($dateObject) {
                                $dateDisplay = $dateObject->format('d M Y');
                            }
                        }
                        ?>
                        <tr class="activity-row">
                          <td><?= $index + 1 ?></td>
                          <td><?= escapeActivityOutput($activity['title']) ?></td>
                          <td><?= escapeActivityOutput($activity['category']) ?></td>
                          <td><?= escapeActivityOutput($activity['area']) ?></td>
                          <td><?= escapeActivityOutput($dateDisplay) ?></td>
                          <td><?= escapeActivityOutput($activity['status']) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>

                    <tr id="noActivitySearchResults" hidden>
                      <td colspan="6">No matching activities.</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span id="activitiesPageInfo"></span>

                <div class="adm-pager">
                  <button type="button" class="adm-btn adm-btn--sm" id="activitiesPreviousPage">
                    <i class="fa-solid fa-chevron-left"></i> Previous
                  </button>

                  <span id="activitiesPageNumber"></span>

                  <button type="button" class="adm-btn adm-btn--sm" id="activitiesNextPage">
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
      const searchInput = document.getElementById('activitiesSearch');
      const allRows = Array.from(document.querySelectorAll('.activity-row'));
      const noSearchResults = document.getElementById('noActivitySearchResults');
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

        const pageNumber = document.getElementById('activitiesPageNumber');
        const pageInfo = document.getElementById('activitiesPageInfo');
        const resultInfo = document.getElementById('resultInfo');
        const previousButton = document.getElementById('activitiesPreviousPage');
        const nextButton = document.getElementById('activitiesNextPage');

        if (pageNumber) {
          pageNumber.textContent = `Page ${currentPage} of ${pageCount}`;
        }

        if (pageInfo) {
          const first = filteredRows.length === 0 ? 0 : start + 1;
          const last = start + pageRows.length;
          pageInfo.textContent = `Showing ${first}–${last} of ${filteredRows.length} activitie(s)`;
        }

        if (resultInfo) {
          resultInfo.textContent = `${filteredRows.length} activitie(s) found.`;
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

      document.getElementById('activitiesPreviousPage')?.addEventListener('click', () => {
        if (currentPage > 1) {
          currentPage--;
          renderRows();
        }
      });

      document.getElementById('activitiesNextPage')?.addEventListener('click', () => {
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