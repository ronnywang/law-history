<?php
$law_id = Param::get('law_id');
$lawline_id = Param::get('lawline_id');
$ret = LawAPI::searchLaw(['law_id' => $law_id]);
if (!$ret->data) {
    include(__DIR__ . '/notfound.php');
    exit;
}
Param::addAPI($ret->api_url, "取得 law_id={$law_id} 法律資料");
$law_data = $ret->data[0];

$ret = LawAPI::searchLawLine(['law_id' => $law_id, 'lawline_id' => $lawline_id]);
Param::addAPI($ret->api_url, "取得 law_id={$law_id}, lawline_id={$lawline_id} 法條資料");
$law_lines = $ret->lawline;
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h1>
<h2><?= htmlspecialchars($lawline_id) ?>修法歷程</h2>
<div class="row">
    <div class="list-group">
        <?php foreach ($law_lines as $idx => $law_line) { ?>
        <?php if ($law_line->{'動作'} == '未變更') { continue; } ?>
        <div class="list-group-item">
            <h4 class="list-group-item-heading">
                <?= htmlspecialchars($law_line->{'法律版本代碼'}) ?>
                <?= htmlspecialchars($law_line->{'動作'}) ?>
            </h4>
            <p class="list-group-item-text"><?= nl2br(htmlspecialchars($law_line->{'內容'})) ?></p>
            <?php if ($law_line->{'說明'}) { ?>
            <div class="panel panel-default">
                <div class="panel-heading">說明</div>
                <div class="panel-body"><?= nl2br(htmlspecialchars($law_line->{'說明'})) ?></div>
            </div>
            <?php } ?>
            <a href="/law/<?= $law_id ?>/<?= urlencode($law_line->{'法律版本代碼'}) ?>#<?= urlencode($law_line->{'法條代碼'}) ?>" class="btn btn-info">瀏覽版本全文</a>
        </div>
        <?php } ?>
    </div>
</div>
<script>
$('.btn-toggle').on('click', function(e){
    e.preventDefault();
    $('#' + $(this).data('target')).toggle();
});
$('.toggle-enable-date').on('change', function(e){
    if ($(this).is(':checked')) {
        $('.enable-date').show();
    } else {
        $('.enable-date').hide();
    }
});
</script>
<?php include(__DIR__ . '/footer.php'); ?>
