<?php
require_once __DIR__ . "/../../config/config.php";
require_once __DIR__ . "/../../includes/auth.php";
session_start();

if (!isLoggedIn()) { redirect(pageUrl("login.php")); }

$user = getCurrentUser();
$pdo = getDbConnection();
$errors = []; $success = false; $employee = null;
$id = $_GET["id"] ?? 0;

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$id]);
    $employee = $stmt->fetch();
}

if (!$employee) { header("Location: " . pageUrl("modules/employees/list.php")); exit; }

$formData = [
    "full_name" => $employee["full_name"],
    "position" => $employee["position"],
    "department" => $employee["department"],
    "email" => $employee["email"] ?? "",
    "phone" => $employee["phone"] ?? "",
    "hire_date" => $employee["hire_date"],
    "salary" => $employee["salary"] ?? "",
    "status" => $employee["status"] ?? "active",
    "notes" => $employee["notes"] ?? ""
];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $formData = [
        "full_name" => trim($_POST["full_name"] ?? ""),
        "position" => trim($_POST["position"] ?? ""),
        "department" => $_POST["department"] ?? "",
        "email" => trim($_POST["email"] ?? ""),
        "phone" => trim($_POST["phone"] ?? ""),
        "hire_date" => $_POST["hire_date"] ?? "",
        "salary" => trim($_POST["salary"] ?? ""),
        "status" => $_POST["status"] ?? "active",
        "notes" => trim($_POST["notes"] ?? "")
    ];
    if (empty($formData["full_name"])) $errors[] = "Введите ФИО";
    if (empty($formData["position"])) $errors[] = "Введите должность";
    if (empty($formData["department"])) $errors[] = "Выберите отдел";
    if (!empty($formData["email"]) && !filter_var($formData["email"], FILTER_VALIDATE_EMAIL)) $errors[] = "Некорректный email";
    if (empty($formData["hire_date"])) $errors[] = "Выберите дату приема";
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE employees SET full_name=:fn,position=:pos,department=:dep,email=:em,phone=:ph,hire_date=:hd,salary=:sal,status=:st,notes=:nt,updated_at=NOW() WHERE id=:id");
            $stmt->execute([":fn"=>$formData["full_name"],":pos"=>$formData["position"],":dep"=>$formData["department"],":em"=>$formData["email"]?:null,":ph"=>$formData["phone"]?:null,":hd"=>$formData["hire_date"],":sal"=>$formData["salary"]?:null,":st"=>$formData["status"],":nt"=>$formData["notes"]?:null,":id"=>$id]);
            $success = true;
            header("Refresh: 2; URL=" . pageUrl("modules/employees/list.php"));
        } catch (PDOException $e) { $errors[] = "Ошибка: " . $e->getMessage(); }
    }
}
$pageTitle = "Редактирование сотрудника";
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title><?=e($pageTitle)?> - <?=e(APP_NAME)?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?=asset("assets/css/style.css")?>">
<style>.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding:1.25rem 1.5rem;background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1)}.page-title{font-size:1.5rem;font-weight:600;color:#1a1a1a;margin:0}.breadcrumb{display:flex;list-style:none;padding:0;margin:0.5rem 0 0 0;font-size:0.875rem}.breadcrumb-item+.breadcrumb-item::before{content:"/";padding:0 0.5rem;color:#6c757d}.breadcrumb-item a{text-decoration:none;color:#007bff}.card{background:#fff;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,0.1);margin-bottom:1.5rem;border:none}.card-header{padding:1.25rem 1.5rem;border-bottom:1px solid #e9ecef;background:#fafbfc;border-radius:8px 8px 0 0}.card-title{font-size:1.1rem;font-weight:600;color:#1a1a1a;margin:0}.card-body{padding:1.5rem}.card-footer{padding:1.25rem 1.5rem;border-top:1px solid #e9ecef;background:#fafbfc;border-radius:0 0 8px 8px;display:flex;gap:0.75rem;justify-content:flex-end}.form-group{margin-bottom:1.25rem}.form-label{display:block;margin-bottom:0.5rem;font-weight:500;color:#333}.form-label .required{color:#dc3545;margin-left:0.25rem}.form-control{width:100%;padding:0.625rem 0.875rem;border:1px solid #ced4da;border-radius:6px;font-size:0.95rem}.form-control:focus{outline:none;border-color:#007bff;box-shadow:0 0 0 3px rgba(0,123,255,0.15)}.form-select{width:100%;padding:0.625rem 0.875rem;border:1px solid #ced4da;border-radius:6px;background:#fff}.btn{display:inline-flex;align-items:center;gap:0.5rem;padding:0.625rem 1.25rem;border:none;border-radius:6px;font-size:0.95rem;font-weight:500;cursor:pointer;text-decoration:none}.btn-primary{background:#007bff;color:#fff}.btn-primary:hover{background:#0056b3}.btn-secondary{background:#6c757d;color:#fff}.alert{padding:1rem 1.25rem;border-radius:6px;margin-bottom:1.25rem}.alert-success{background:#d4edda;color:#155724}.alert-danger{background:#f8d7da;color:#721c24}.row{display:flex;flex-wrap:wrap;margin:0 -0.75rem}.col-md-6{flex:0 0 50%;max-width:50%;padding:0 0.75rem}.col-md-12{flex:0 0 100%;max-width:100%;padding:0 0.75rem}@media(max-width:768px){.col-md-6{flex:0 0 100%}.page-header{flex-direction:column;align-items:flex-start}.card-footer{flex-direction:column}}</style></head>
<body><div class="app-container"><?php include BASE_PATH."/includes/sidebar.php";?><div class="main-content"><?php include BASE_PATH."/includes/topbar.php";?><div class="content-area"><div class="content-wrapper"><div class="page-header"><div><h1 class="page-title">Редактирование сотрудника</h1><ol class="breadcrumb"><li class="breadcrumb-item"><a href="<?=pageUrl("index.php")?>">Главная</a></li><li class="breadcrumb-item"><a href="<?=pageUrl("modules/employees/list.php")?>">Сотрудники</a></li><li class="breadcrumb-item active">Редактирование</li></ol></div></div><?php if($success):?><div class="alert alert-success"><strong>Успешно!</strong> Данные обновлены.</div><?php endif; ?><?php if(!empty($errors)):?><div class="alert alert-danger"><strong>Ошибки:</strong><ul><?php foreach($errors as $error):?><li><?=htmlspecialchars($error)?></li><?php endforeach;?></ul></div><?php endif; ?><div class="card"><div class="card-header"><h3 class="card-title">Информация о сотруднике</h3></div><form method="POST"><div class="card-body"><div class="row"><div class="col-md-12"><div class="form-group"><label class="form-label">ФИО <span class="required">*</span></label><input type="text" class="form-control" name="full_name" value="<?=htmlspecialchars($formData["full_name"])?>" required></div></div></div><div class="row"><div class="col-md-6"><div class="form-group"><label class="form-label">Должность <span class="required">*</span></label><input type="text" class="form-control" name="position" value="<?=htmlspecialchars($formData["position"])?>" required></div></div><div class="col-md-6"><div class="form-group"><label class="form-label">Отдел <span class="required">*</span></label><select class="form-select" name="department" required><option value="">Выберите отдел</option><option value="administration"<?=$formData["department")==="administration"?" selected":""?>>Администрация</option><option value="sales"<?=$formData["department")==="sales"?" selected":""?>>Отдел продаж</option><option value="production"<?=$formData["department")==="production"?" selected":""?>>Производство</option><option value="warehouse"<?=$formData["department")==="warehouse"?" selected":""?>>Склад</option><option value="accounting"<?=$formData["department")==="accounting"?" selected":""?>>Бухгалтерия</option><option value="hr"<?=$formData["department")==="hr"?" selected":""?>>HR-отдел</option></select></div></div></div><div class="row"><div class="col-md-6"><div class="form-group"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?=htmlspecialchars($formData["email"])?>"></div></div><div class="col-md-6"><div class="form-group"><label class="form-label">Телефон</label><input type="tel" class="form-control" name="phone" value="<?=htmlspecialchars($formData["phone"])?>"></div></div></div><div class="row"><div class="col-md-6"><div class="form-group"><label class="form-label">Дата приема <span class="required">*</span></label><input type="date" class="form-control" name="hire_date" value="<?=htmlspecialchars($formData["hire_date"])?>" required></div></div><div class="col-md-6"><div class="form-group"><label class="form-label">Зарплата (BYN)</label><input type="number" class="form-control" name="salary" value="<?=htmlspecialchars($formData["salary"])?>" min="0" step="0.01"></div></div></div><div class="row"><div class="col-md-12"><div class="form-group"><label class="form-label">Статус</label><select class="form-select" name="status"><option value="active"<?=$formData["status")==="active"?" selected":""?>>Работает</option><option value="vacation"<?=$formData["status")==="vacation"?" selected":""?>>В отпуске</option><option value="sick"<?=$formData["status")==="sick"?" selected":""?>>На больничном</option><option value="terminated"<?=$formData["status")==="terminated"?" selected":""?>>Уволен</option></select></div></div></div><div class="row"><div class="col-md-12"><div class="form-group"><label class="form-label">Заметки</label><textarea class="form-control" name="notes" rows="4"><?=htmlspecialchars($formData["notes"])?></textarea></div></div></div></div><div class="card-footer"><a href="<?=pageUrl("modules/employees/list.php")?>" class="btn btn-secondary">Отмена</a><button type="submit" class="btn btn-primary">Сохранить изменения</button></div></form></div></div></div></div></div><script src="<?=asset("assets/js/main.js")?>"></script></body></html>
