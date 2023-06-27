<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
<title>法律查詢器</title>
<script src="//ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsdiff/5.1.0/diff.min.js" integrity="sha512-vco9RAxEuv4PQ+iTQyuKElwoUOcsVdp+WgU6Lgo82ASpDfF7vI66LlWz+CZc2lMdn52tjjLOuHvy8BQJFp8a1A==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.6/css/bootstrap.css">
</head>
<body>
<nav class="navbar navbar-default">
  <div class="container-fluid">
    <!-- Brand and toggle get grouped for better mobile display -->
    <div class="navbar-header">
      <a class="navbar-brand" href="/">法律查詢器</a>
  </div>
  <ul class="nav navbar-nav">
      <li><a href="https://github.com/ronnywang/law-history" target="_blank">程式碼</a></li>
      <li><a href="https://g0v.hackmd.io/@SA7CD7VRSp6Fcqw9CaElcQ/By0CawuI2" target="_blank">介紹共筆</a></li>
      <li><a href="https://ronny.tw/#donate" target="_blank">抖內贊助</a></li>
  </ul>

    <!-- Collect the nav links, forms, and other content for toggling -->
    <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
      <form class="navbar-form navbar-left" role="search" action="/">
        <div class="form-group">
          <input type="text" class="form-control" placeholder="Search" name="q">
        </div>
        <button type="submit" class="btn btn-default">搜尋法律</button>
      </form>
      <form class="navbar-form navbar-left" role="search" action="/bill">
        <div class="form-group">
          <input type="text" class="form-control" placeholder="Search" name="q">
        </div>
        <button type="submit" class="btn btn-default">搜尋議案</button>
      </form>
    </div><!-- /.navbar-collapse -->
  </div><!-- /.container-fluid -->
</nav>

<div class="container">
