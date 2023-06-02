<?php
$ret = LawAPI::searchLaw($_GET);
Param::addAPI($ret->api_url, "取得本頁法律列表資料");
?>
<?php include(__DIR__ . '/header.php'); ?>
<form method="get">
    搜尋：<input type="text" name="q">
    <button type="submit">搜尋</button>
</form>
<hr>
<?php foreach ($ret->data as $law_data) { ?>
<h3><a href="/law/<?= $law_data->{'法律代碼'} ?>"><?= htmlspecialchars($law_data->{'最新名稱'}) ?></a></h3>
<?php } ?>
<?php include(__DIR__ . '/footer.php'); ?>
