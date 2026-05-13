<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $title ?? 'Error' ?> - Accounting</title>
</head>
<body>
        <?= \Accounting\Interfaces\HTTP\HttpError::$code ?>
    </div>
    <h5><?= $title ?? 'Error' ?></h5>
    <p><?= htmlspecialchars(\Accounting\Interfaces\HTTP\HttpError::$message) ?></p>
    </div>
</div>
</body>
</html>