<?php
$ret = BillAPI::searchBill($_GET);
Param::addAPI($ret->api_url, "取得本頁議案列表資料");
?>
<?php include(__DIR__ . '/header.php'); ?>
<?php if ($_GET['q']) { ?>
<h2>搜尋「<?= htmlspecialchars($_GET['q']) ?>」結果</h2>
<?php } ?>
<table class="table">
    <thead>
        <tr>
            <th>屆期</th>
            <th>議案名稱</th>
            <th>提案</th>
            <th>時間</th>
            <th>進度</th>
        </tr>
    </thead>

    <tbody>
    <?php foreach ($ret->data as $data) { ?>
    <tr>
        <td><?= $data->{'屆期'} ?></td>
        <td>
            <p><a href="/bill/detail/<?= urlencode($data->billNo) ?>"><?= htmlspecialchars($data->billNo) ?></a></p>
            <?= htmlspecialchars($data->{'議案名稱'}) ?>
        </td>
        <td><?= htmlspecialchars($data->{'提案單位/提案委員'}) ?></td>
        <td>
            <?php if ($data->first_time) { ?>
            <div><?= date('Y-m-d', $data->first_time / 1000) ?></div>
            <?php } ?>
            <?php if ($data->last_time) { ?>
            <div><?= date('Y-m-d', $data->last_time / 1000) ?></div>
            <?php } ?>
        </td>
        <td>
            <?= htmlspecialchars($data->{'議案狀態'}) ?>
        </td>
    </tr>
    <?php } ?>
    </tbody>
</table>
<?php include(__DIR__ . '/footer.php'); ?>
