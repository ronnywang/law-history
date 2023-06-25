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

if (strpos($ver, 'bill-') === 0) {
    $ret = LawAPI::searchLawVer(['law_id' => $law_id, 'ver' => $ver]);
    Param::addAPI($ret->api_url, "取得 law_id={$law_id}, ver={$ver} 的版本記錄");
    $current_law_ver = $ret->lawver[0];
}


$ret = LawAPI::searchLawVer(['law_id' => $law_id, 'type' => '三讀']);
Param::addAPI($ret->api_url, "取得 law_id={$law_id} 的三讀版本記錄");
$law_vers = $ret->lawver;
foreach ($law_vers as $law_ver) {
    if ($law_ver->{'法律版本代碼'} == $ver) {
        $current_law_ver = $law_ver;
    }
}
$ret = LawAPI::searchLawLine(['law_id' => $law_id, 'ver' => $ver]);
Param::addAPI($ret->api_url, "取得 law_id={$law_id}, ver={$ver} 的條文記錄");
$law_lines = $ret->lawline;
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h1>
<?php if (property_exists($law_data, '立法院代碼')) { ?>
<div style="float: right">
    <a class="btn btn-info" href="https://www.ly.gov.tw/Pages/ashx/LawRedirect.ashx?CODE=<?= urlencode($law_data->{'立法院代碼'}) ?>" target="_blank">立法院法律系統 <span class="glyphicon glyphicon-link" aria-hidden="true"></span></a>
</div>
<?php } ?>
<h2><?= htmlspecialchars($current_law_ver->{'版本名稱'}) ?>版本全文</h2>
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <?php if ($current_law_ver->{'版本種類'} == '三讀') { ?>
            <div class="panel-heading">版本歷程</div>
            <div class="panel-body">
                <div class="list-group">
                    <?php foreach ($law_vers as $law_ver) { ?>
                    <a class ="list-group-item <?= ($law_ver->{'法律版本代碼'} == $ver) ? ' active': '' ?>" href="/law/<?= $law_data->{'法律代碼'} ?>/<?= urlencode($law_ver->{'法律版本代碼'}) ?>"><?= htmlspecialchars($law_ver->{'日期'} . ' ' . $law_ver->{'動作'}) ?></a>
                    <?php } ?>
                </div>
            </div>
            <?php } elseif ($current_law_ver->{'版本種類'} == '議案') { ?>
            <div class="panel-heading">三讀歷程</div>
            <div class="panel-body">
                <div class="list-group">
                    <?php if ($current_law_ver->{'前版本代碼'}) { ?>
                    <a class="list-group-item" href="/law/<?= $law_data->{'法律代碼'} ?>/<?= urlencode($current_law_ver->{'前版本代碼'}) ?>">[前版本] <?= htmlspecialchars($current_law_ver->{'前版本代碼'}) ?></a>
                    <?php } ?>

                    <?php foreach ($current_law_ver->{'議案資料'}->detail->{'關連議案'} as $rel_bill) { ?>
                    <a class ="list-group-item <?= $rel_bill->billNo == $current_law_ver->{'議案資料'}->detail->billNo ? ' active' : '' ?>" href="/law/<?= $law_data->{'法律代碼'} ?>/bill-<?= urlencode($rel_bill->billNo) ?>"><?= htmlspecialchars($rel_bill->title) ?></a>
                    <?php } ?>

                    <?php if ($current_law_ver->{'三讀日期'}) { ?>
                    <a class="list-group-item" href="/law/<?= $law_data->{'法律代碼'} ?>/<?= urlencode($current_law_ver->{'三讀日期'} . '-三讀') ?>">[後版本] <?= htmlspecialchars($current_law_ver->{'三讀日期'} . '-三讀') ?></a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="col-md-9">
        <label><input type="checkbox" class="toggle-enable-date">顯示法條修法時間</label>
        <label><input type="checkbox" class="toggle-change-law">只顯示變動法條</label>
        <label><input type="checkbox" class="toggle-show-reason">顯示全部說明</label>
        <?php if ($current_law_ver->{'版本種類'} == '三讀') { ?>
        <a href="/lawver/<?= $law_id ?>/<?= urlencode($current_law_ver->{'法律版本代碼'}) ?>" class="btn btn-info">三讀歷程</a>
        <?php } ?>
        <a href="/lawdiff/<?= $law_id ?>/<?= urlencode($current_law_ver->{'法律版本代碼'}) ?>" class="btn btn-info">法案對照表</a>
        <table class="table">
            <thead>
                <tr>
                    <th>條號</th>
                    <th>內容</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($law_lines as $idx => $law_line) { ?>
            <tr id="<?= htmlspecialchars($law_line->{'法條代碼'}) ?>"
                class="law-tr
                <?php if ($law_line->{'動作'} != '未變更') { ?> law-tr-change <?php } ?>
                ">
                <td style="white-space: nowrap">
                    <p>
                    <a href="#<?= htmlspecialchars($law_line->{'法條代碼'}) ?>"><span class="glyphicon glyphicon-link" aria-hidden="true"></span></a>
                    <?= htmlspecialchars($law_line->{'條號'}) ?>
                    </p>
                    <p class="enable-date" style="display:none"><?= htmlspecialchars($law_line->{'此法版本'}) ?></p>
                </td>
                <td>
                    <p><?= nl2br(htmlspecialchars($law_line->{'內容'})) ?></p>
                    <?php if ($law_line->{'說明'}) { ?>
                    <a href="#" class="btn btn-info btn-toggle" data-target="reason-<?= $idx ?>">說明</a>
                    <?php } ?>
                    <a href="/lawline/<?= urlencode($law_id) ?>/<?= urlencode($law_line->{'法條代碼'}) ?>" class="btn btn-info">法條歷程</a>

                    <?php if ($law_line->{'說明'}) { ?>
                    <div class="panel panel-default panel-reason" id="reason-<?= $idx ?>" style="display: none">
                        <div class="panel-heading">[說明]</div>
                        <div class="panel-body">
                            <?= nl2br(htmlspecialchars($law_line->{'說明'})) ?>
                        </div>
                    </div>
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>
$('.btn-toggle').on('click', function(e){
    e.preventDefault();
    $('#' + $(this).data('target')).toggle();
});

$('.toggle-show-reason').on('change', function(e){
    if ($(this).is(':checked')) {
        $('.panel-reason').show();
    } else {
        $('.panel-reason').hide();
    }
});

$('.toggle-enable-date').on('change', function(e){
    if ($(this).is(':checked')) {
        $('.enable-date').show();
    } else {
        $('.enable-date').hide();
    }
});

$('.toggle-change-law').on('change', function(e){
    if ($(this).is(':checked')) {
        $('.law-tr:not(.law-tr-change)').hide();
    } else {
        $('.law-tr').show();
    }
});
</script>
<?php include(__DIR__ . '/footer.php'); ?>
