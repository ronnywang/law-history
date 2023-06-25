<?php
$ret = LawAPI::searchLaw($_GET);
Param::addAPI($ret->api_url, "取得本頁法律列表資料");
?>
<?php include(__DIR__ . '/header.php'); ?>
<?php if ($_GET['q']) { ?>
<h2>搜尋「<?= htmlspecialchars($_GET['q']) ?>」結果</h2>
<?php } ?>
<?php foreach ($ret->data as $law_data) { ?>
<ul>
    <li>
    <h3><a href="/law/<?= $law_data->{'法律代碼'} ?>"><?= htmlspecialchars($law_data->{'最新名稱'}) ?></a></h3>
    更新日期: <?= htmlspecialchars($law_data->{'更新日期'}) ?>
    </li>
</ul>
<?php } ?>
<?php include(__DIR__ . '/footer.php'); ?>
