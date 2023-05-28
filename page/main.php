<?php
$obj = LawAPI::searchLaw($_GET);
?>
<?php include(__DIR__ . '/header.php'); ?>
<form method="get">
    搜尋：<input type="text" name="q">
    <button type="submit">搜尋</button>
</form>
<hr>
<code><?= htmlspecialchars($obj->api_url) ?></code>
<?php foreach ($obj->data as $law_data) { ?>
<h3><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h3>
<?php } ?>
<?php include(__DIR__ . '/footer.php'); ?>
