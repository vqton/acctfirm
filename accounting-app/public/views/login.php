<?php $title = 'Đăng nhập'; ob_start(); ?>
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Đăng nhập — Accounting</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f0f2f5;display:flex;align-items:center;min-height:100vh}.login-card{max-width:400px;width:100%;margin:0 auto}.card{border:0;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,.08)}.brand{text-align:center;margin-bottom:24px}.brand h4{font-weight:600;color:#1a1a2e}</style>
</head>
<body>
<div class="login-card">
    <div class="brand"><h4><i class="bi bi-calculator"></i> ACCOUNTING</h4></div>
    <div class="card p-4">
        <h5 class="mb-3 text-center">Đăng nhập</h5>
        <div id="errorMsg" class="alert alert-danger d-none"></div>
        <form id="loginForm">
            <div class="mb-3"><label class="form-label">Tên đăng nhập</label><input type="text" class="form-control" id="username" required autofocus></div>
            <div class="mb-3"><label class="form-label">Mật khẩu</label><input type="password" class="form-control" id="password" required></div>
            <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
        </form>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$('#loginForm').submit(function(e){
    e.preventDefault();
    $.ajax({url:'/api/auth/login',method:'POST',contentType:'application/json',
        data:JSON.stringify({username:$('#username').val(),password:$('#password').val()}),
        success:function(){window.location.href='/';},
        error:function(x){var m='Sai tên đăng nhập hoặc mật khẩu';try{m=JSON.parse(x.responseText).error;}catch(e){};$('#errorMsg').text(m).removeClass('d-none');}
    });
});
</script>
</body></html>
<?php $content = ob_get_clean(); echo $content; ?>
