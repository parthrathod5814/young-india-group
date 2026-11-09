<?php
require_once __DIR__ . '/inc/config.php';
$pdo = db();

function escapeOutput($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$perPage = 10;
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$search = trim((string) ($_GET['q'] ?? ''));

$requests = [];
$totalRecords = 0;
$totalPages = 1;
$loadError = false;

try {
    // Count matching records for the pagination controls.
    if ($search !== '') {
        $countStmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM blood_requests
             WHERE patient LIKE :patient
                OR blood_group LIKE :blood_group
                OR patient_condition LIKE :patient_condition
                OR hospital_address LIKE :hospital_address
                OR ward LIKE :ward
                OR contact LIKE :contact
                OR runner LIKE :runner
                OR status LIKE :status'
        );

        $like = '%' . $search . '%';
        $countStmt->execute([
            ':patient' => $like,
            ':blood_group' => $like,
            ':patient_condition' => $like,
            ':hospital_address' => $like,
            ':ward' => $like,
            ':contact' => $like,
            ':runner' => $like,
            ':status' => $like,
        ]);
    } else {
        $countStmt = $pdo->query('SELECT COUNT(*) FROM blood_requests');
    }

    $totalRecords = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRecords / $perPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $perPage;

    // Fetch only the rows for the current page.
    if ($search !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, patient, blood_group, patient_condition, units,
                    hospital_address, ward, contact, runner, status
             FROM blood_requests
             WHERE patient LIKE :patient
                OR blood_group LIKE :blood_group
                OR patient_condition LIKE :patient_condition
                OR hospital_address LIKE :hospital_address
                OR ward LIKE :ward
                OR contact LIKE :contact
                OR runner LIKE :runner
                OR status LIKE :status
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $like = '%' . $search . '%';
        $stmt->bindValue(':patient', $like, PDO::PARAM_STR);
        $stmt->bindValue(':blood_group', $like, PDO::PARAM_STR);
        $stmt->bindValue(':patient_condition', $like, PDO::PARAM_STR);
        $stmt->bindValue(':hospital_address', $like, PDO::PARAM_STR);
        $stmt->bindValue(':ward', $like, PDO::PARAM_STR);
        $stmt->bindValue(':contact', $like, PDO::PARAM_STR);
        $stmt->bindValue(':runner', $like, PDO::PARAM_STR);
        $stmt->bindValue(':status', $like, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, patient, blood_group, patient_condition, units,
                    hospital_address, ward, contact, runner, status
             FROM blood_requests
             ORDER BY created_at DESC
             LIMIT :limit OFFSET :offset'
        );

        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load blood requests: ' . $e->getMessage());
    $loadError = true;
}

$startRecord = $totalRecords > 0 ? $offset + 1 : 0;
$endRecord = min($offset + $perPage, $totalRecords);

$previousUrl = '?' . http_build_query([
    'q' => $search,
    'page' => max(1, $currentPage - 1),
]);

$nextUrl = '?' . http_build_query([
    'q' => $search,
    'page' => min($totalPages, $currentPage + 1),
]);
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
                <input type="search" id="bloodSearch" placeholder="Search blood requests...">
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
                <?php if ($search !== ''): ?>
                  <?= $totalRecords ?> matching request(s) found.
                <?php else: ?>
                  <?= $totalRecords ?> total request(s).
                <?php endif; ?>
              </div>

              <div class="adm-table-wrap">
                <table class="adm-table">
                  <thead>
                    <tr>
                      <th>ID</th>
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

                  <tbody>
                    <?php if (!$requests): ?>
                      <tr>
                        <td colspan="10">
                          <?= $search !== ''
                            ? 'No matching blood requests found.'
                            : 'No blood requests have been added yet.' ?>
                        </td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($requests as $request): ?>
                        <tr>
                          <td><?= escapeOutput($request['id']) ?></td>
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
                  </tbody>
                </table>
              </div>

              <div class="adm-info adm-info--bottom">
                <span>
                  Showing <?= $startRecord ?>–<?= $endRecord ?>
                  of <?= $totalRecords ?> request(s)
                </span>

                <div class="adm-pager">
                  <?php if ($currentPage > 1): ?>
                    <a class="adm-btn adm-btn--sm" href="<?= escapeOutput($previousUrl) ?>">
                      <i class="fa-solid fa-chevron-left"></i> Previous
                    </a>
                  <?php else: ?>
                    <button class="adm-btn adm-btn--sm" type="button" disabled>
                      <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                  <?php endif; ?>

                  <span>Page <?= $currentPage ?> of <?= $totalPages ?></span>

                  <?php if ($currentPage < $totalPages): ?>
                    <a class="adm-btn adm-btn--sm" href="<?= escapeOutput($nextUrl) ?>">
                      Next <i class="fa-solid fa-chevron-right"></i>
                    </a>
                  <?php else: ?>
                    <button class="adm-btn adm-btn--sm" type="button" disabled>
                      Next <i class="fa-solid fa-chevron-right"></i>
                    </button>
                  <?php endif; ?>
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
    // Keep the mobile sidebar menu usable without starting admin.js's demo table renderer.
    const menuButton = document.getElementById('menuBtn');
    const sidebar = document.getElementById('sidebar');

    if (menuButton && sidebar) {
      menuButton.addEventListener('click', () => {
        sidebar.classList.toggle('is-open');
      });
    }

    document.querySelectorAll('.adm-group__btn').forEach((button) => {
      button.addEventListener('click', () => {
        const group = button.closest('.adm-group');
        if (!group) return;

        const wasOpen = group.classList.contains('is-open');
        document.querySelectorAll('.adm-group').forEach((item) => {
          item.classList.remove('is-open');
        });
        group.classList.toggle('is-open', !wasOpen);
      });
    });
  </script>
</body>
</html>