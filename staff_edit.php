<?php
require_once __DIR__ . '/../../includes/db.php';
$conn = connectDB();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$staff = [];

if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM staffs WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
}

$staff['avatar_url'] = !empty($staff['avatar'])
    ? '/' . ltrim($staff['avatar'], '/')
    : '/public/images/default-avatar.png';

$policySubjects = [];
$res = $conn->query("SELECT id, name FROM policy_subjects ORDER BY name");
while ($row = $res->fetch_assoc()) {
    $policySubjects[] = $row;
}
// Kiểm tra quyền admin
$isAdmin = true; // Thay logic của bạn ở đây
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<form method="post" enctype="multipart/form-data" action="/modules/staffs_edit/save_staff.php" class="container mt-4">
  <input type="hidden" name="id" value="<?= htmlspecialchars($staff['id'] ?? 0) ?>">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><?= $id > 0 ? 'Sửa nhân sự' : 'Thêm nhân sự' ?></h4>
    <div>
      <button class="btn btn-primary" type="submit">Lưu</button>
      <a href="/index.php?modules=staffs" class="btn btn-secondary">Hủy</a>
    </div>
  </div>

  <div class="row">
    <!-- Avatar -->
    <div class="col-md-3 text-center">
      <div style="position:relative; display:inline-block;">
        <img id="avatarPreview"
             src="<?= htmlspecialchars($staff['avatar_url']) ?>"
             alt="Avatar"
             class="img-thumbnail rounded-circle mb-2"
             style="width:150px;height:150px;object-fit:cover;cursor:pointer;">
        <input type="file" name="avatar" id="avatarInput"
               style="opacity:0;width:150px;height:150px;position:absolute;top:0;left:0;cursor:pointer;"
               accept="image/*">
      </div>

      <?php if (!empty($staff['name'])): ?>
        <div class="fw-bold"><?= htmlspecialchars($staff['name']) ?></div>
      <?php endif; ?>
      <?php if (!empty($staff['staff_code'])): ?>
        <div class="text-secondary small">Mã: <?= htmlspecialchars($staff['staff_code']) ?></div>
      <?php endif; ?>
      <?php if (!empty($staff['status'])): 
        $statusText = [
          'working' => 'Đang làm việc',
          'leave' => 'Nghỉ phép',
          'left' => 'Nghỉ việc',
          'applying' => 'Ứng tuyển'
        ];
      ?>
        <div class="text-info small">
          Trạng thái: <?= $statusText[$staff['status']] ?? htmlspecialchars($staff['status']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($staff['id']) && $isAdmin): ?>
        <button type="button" class="btn btn-warning mt-3" id="btnResetPassword">Reset mật khẩu</button>
      <?php endif; ?>
    </div>

    <!-- Tabs -->
    <div class="col-md-9">
      <ul class="nav nav-tabs mb-3" id="staffsTabs" role="tablist">
        <?php
        $tabs = [
          1 => 'Thông tin nhân sự',
          2 => 'Phân công nhiệm vụ',
          3 => 'Thông tin hợp đồng',
          4 => 'Trình độ & Văn bằng',
          5 => 'Thông tin khác',
          6 => 'Khác'
        ];
        foreach ($tabs as $num => $title):
        ?>
          <li class="nav-item" role="presentation">
            <button class="nav-link <?= $num === 1 ? 'active' : '' ?>" 
                    id="tab<?= $num ?>-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#tab<?= $num ?>" 
                    type="button"
                    role="tab" 
                    aria-controls="tab<?= $num ?>" 
                    aria-selected="<?= $num === 1 ? 'true' : 'false' ?>"><?= $title ?></button>
          </li>
        <?php endforeach; ?>
      </ul>

      <div class="tab-content border p-3" id="staffsTabsContent">
        <div class="tab-pane fade show active" id="tab1" role="tabpanel" aria-labelledby="tab1-tab">
          <?php include __DIR__ . '/tab/basic.php'; ?>
        </div>
        <div class="tab-pane fade" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
          <?php
            $selectedSchoolYearId = $currentYearId ?? 0;
            include __DIR__ . '/tab/departments.php';
          ?>
        </div>
        <div class="tab-pane fade" id="tab3" role="tabpanel" aria-labelledby="tab3-tab">
          <?php include __DIR__ . '/tab/contract.php'; ?>
        </div>
        <div class="tab-pane fade" id="tab4" role="tabpanel" aria-labelledby="tab4-tab">
          <?php include __DIR__ . '/tab/qualification.php'; ?>
        </div>
        <div class="tab-pane fade" id="tab5" role="tabpanel" aria-labelledby="tab5-tab">
          <?php include __DIR__ . '/tab/others.php'; ?>
        </div>
        <div class="tab-pane fade" id="tab6" role="tabpanel" aria-labelledby="tab6-tab">
          <?php
            $selectedSchoolYearId = $currentYearId ?? 0;
            include __DIR__ . '/tab/note.php';
          ?>
        </div>
      </div>
    </div>
  </div>
</form>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function () {
  // Initialize Bootstrap tabs
  var triggerTabList = [].slice.call(document.querySelectorAll('#staffsTabs button'))
  triggerTabList.forEach(function (triggerEl) {
    var tabTrigger = new bootstrap.Tab(triggerEl)
    
    triggerEl.addEventListener('click', function (event) {
      event.preventDefault()
      tabTrigger.show()
    })
  })

  const avatarInput = document.getElementById('avatarInput');
  const avatarPreview = document.getElementById('avatarPreview');

  if (avatarInput && avatarPreview) {
    avatarInput.addEventListener('change', function (e) {
      const file = e.target.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = () => {
          avatarPreview.src = reader.result;
        };
        reader.readAsDataURL(file);
      }
    });
  }

  const btnReset = document.getElementById('btnResetPassword');
  if (btnReset) {
    btnReset.addEventListener('click', function () {
      if (confirm('Bạn có chắc chắn muốn reset mật khẩu về số điện thoại?')) {
        fetch('/modules/staffs_edit/reset_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'id=<?= $staff['id'] ?>'
        })
        .then(res => res.json())
        .then(data => {
          alert(data.message);
        });
      }
    });
  }
});
</script>