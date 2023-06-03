<?php
$law_id = Param::get('law_id');
$ret = LawAPI::searchLaw(['law_id' => $law_id]);
Param::addAPI($ret->api_url, "取得 law_id={$law_id} 資料");

if (!$ret->data) {
    include(__DIR__ . '/notfound.php');
    exit;
}
$law_data = $ret->data[0];
if (!$ver = Param::get('ver')) {
    $ver = $law_data->{'現行版本號'};
}

$ret = LawAPI::searchLawVer(['law_id' => $law_id, 'ver' => $ver]);
Param::addAPI($ret->api_url, "取得 law_id={$law_id} 的版本記錄");
$law_ver = $ret->lawver[0];

$idmap = new StdClass;
if ($billids = $ret->bill_id) {
    $ret = BillAPI::searchBillIDMap(['id' => $billids]);
    Param::addAPI($ret->api_url, sprintf("取得 %s 等 ID 的 billNo", mb_strimwidth(implode(',', $billids), 0, 30, '...')));
    $idmap = $ret->map;
}
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h1>
<h2><?= htmlspecialchars($ver) ?> 三讀歷程記錄</h2>
<a href="/law/<?= $law_id ?>/<?= urlencode($ver) ?>" class="btn btn-info">瀏覽版本全文</a>
<table class="table">
    <thead>
        <tr>
            <th>進度</th>
            <th>會議日期</th>
            <th>立法紀錄</th>
            <th>主提案</th>
            <th>關係文書</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($law_ver->{'修訂歷程'} as $data) { ?>
    <tr>
        <td><?= htmlspecialchars($data->{'進度'}) ?></td>
        <td><?= htmlspecialchars($data->{'會議日期'}) ?></td>
        <td><?= htmlspecialchars($data->{'立法紀錄'}) ?></td>
        <td><?= htmlspecialchars($data->{'主提案'}) ?></td>
        <td>
            <?php foreach ($data->{'關係文書'} as $rel) { ?>
            <a href="<?= htmlspecialchars($rel[1]) ?>"><?= htmlspecialchars($rel[0]) ?></a>
                <?php if (array_key_exists(2, $rel) and property_exists($idmap, $rel[2])) { ?>
                <a href="https://ppg.ly.gov.tw/ppg/bills/<?= urlencode($idmap->{$rel[2]}) ?>/details">議案資料</a>
                <?php } ?>
            <?php } ?>
        </td>
    </tr>
    <?php } ?>
    </tbody>
</table>
<script>
</script>
<?php include(__DIR__ . '/footer.php'); ?>
