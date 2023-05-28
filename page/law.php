<?php
$law_id = Param::get('law_id');
$ret = LawAPI::searchLaw(['law_id' => $law_id]);
if (!$ret->data) {
    include(__DIR__ . '/notfound.php');
    exit;
}
$law_data = $ret->data[0];
if (!$ver = Param::get('ver')) {
    $ver = $law_data->{'現行版本號'};
}
$law_vers = LawAPI::searchLawVer(['law_id' => $law_id])->lawver;
$law_lines = LawAPI::searchLawLine(['law_id' => $law_id, 'ver' => $ver])->lawline;
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h1>
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading">版本歷程</div>
            <div class="panel-body">
                <div class="list-group">
                    <?php foreach ($law_vers as $law_ver) { ?>
                    <a class ="list-group-item <?= ($law_ver->{'法律版本代碼'} == $ver) ? ' active': '' ?>" href="/law/<?= $law_data->{'法律代碼'} ?>/<?= urlencode($law_ver->{'法律版本代碼'}) ?>"><?= htmlspecialchars($law_ver->{'日期'} . ' ' . $law_ver->{'動作'}) ?></a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-9">
        <table class="table">
            <thead>
                <tr>
                    <th>條號</th>
                    <th>內容</th>
                    <th>理由</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($law_lines as $law_line) { ?>
            <tr>
                <td><?= htmlspecialchars($law_line->{'條號'}) ?></td>
                <td><?= nl2br(htmlspecialchars($law_line->{'內容'})) ?></td>
                <td><?= nl2br(htmlspecialchars($law_line->{'說明'})) ?></td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<?php include(__DIR__ . '/footer.php'); ?>
