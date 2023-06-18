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

$prev_ids = [];
foreach ($law_lines as $law_line) {
    if (property_exists($law_line, '_prev_id')) {
        $prev_ids[] = $law_line->_prev_id;
    }
}

$ret = LawAPI::searchLawLine(['id' => $prev_ids]);
Param::addAPI($ret->api_url, sprintf("取得 id=%s 等前一版本法條的內容", mb_strimwidth(implode(',', $prev_ids), 0, 30, '...')));
$prev_law_lines = new StdClass;
foreach ($ret->lawline as $law_line) {
    $prev_law_lines->{$law_line->_id} = $law_line;
}
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($law_data->{'最新名稱'}) ?></h1>
<h2><?= htmlspecialchars($current_law_ver->{'版本名稱'}) ?>版本法案對照表</h2>
<div class="row">
    <div class="col-md-3">
        <div class="panel panel-default">
            <?php if ($current_law_ver->{'版本種類'} == '三讀') { ?>
            <div class="panel-heading">版本歷程</div>
            <div class="panel-body">
                <div class="list-group">
                    <?php foreach ($law_vers as $law_ver) { ?>
                    <a class ="list-group-item <?= ($law_ver->{'法律版本代碼'} == $ver) ? ' active': '' ?>" href="/lawdiff/<?= $law_data->{'法律代碼'} ?>/<?= urlencode($law_ver->{'法律版本代碼'}) ?>"><?= htmlspecialchars($law_ver->{'日期'} . ' ' . $law_ver->{'動作'}) ?></a>
                    <?php } ?>
                </div>
            </div>
            <?php } elseif ($current_law_ver->{'版本種類'} == '議案') { ?>
            <div class="panel-heading">三讀歷程</div>
            <div class="panel-body">
                <div class="list-group">
                    <?php foreach ($current_law_ver->{'議案資料'}->detail->{'關連議案'} as $rel_bill) { ?>
                    <a class ="list-group-item" href="/lawdiff/<?= $law_data->{'法律代碼'} ?>/bill-<?= urlencode($rel_bill->billNo) ?>"><?= htmlspecialchars($rel_bill->title) ?></a>
                    <?php } ?>
                </div>
            </div>
            <?php } ?>
        </div>
    </div>
    <div class="col-md-9">
        <a href="/law/<?= $law_id ?>/<?= urlencode($current_law_ver->{'法律版本代碼'}) ?>" class="btn btn-info">瀏覽版本全文</a>
        <?php if ($current_law_ver->{'版本種類'} == '三讀') { ?>
        <a href="/lawver/<?= $law_id ?>/<?= urlencode($current_law_ver->{'法律版本代碼'}) ?>" class="btn btn-info">三讀歷程</a>
        <?php } ?>
        <table class="table">
            <thead>
                <tr>
                    <th style="width: 50%">現行內容</th>
                    <th style="width: 50%">前內容</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($law_lines as $idx => $law_line) { ?>
            <?php if ($law_line->{'動作'} == '未變更') { continue; } ?>
            <tr id="<?= htmlspecialchars($law_line->{'法條代碼'}) ?>">
                <td style="white-space: nowrap" colspan="2">
                    <p>
                    <a href="#<?= htmlspecialchars($law_line->{'法條代碼'}) ?>"><span class="glyphicon glyphicon-link" aria-hidden="true"></span></a>
                    <?= htmlspecialchars($law_line->{'條號'}) ?>
                    </p>
                    <p class="enable-date" style="display:none"><?= htmlspecialchars($law_line->{'此法版本'}) ?></p>
                </td>
            </tr>
            <tr class="diff-tr">
                <td data-content="<?= htmlspecialchars($law_line->{'內容'}) ?>"><?= nl2br(htmlspecialchars($law_line->{'內容'})) ?></td>
                <td data-content="<?= htmlspecialchars($prev_law_lines->{$law_line->_prev_id}->{'內容'}) ?>"><?= nl2br(htmlspecialchars($prev_law_lines->{$law_line->_prev_id}->{'內容'})) ?></td>
            </tr>
            <?php if ($law_line->{'說明'}) { ?>
            <tr>
                <td colspan="3">
                    <div class="panel panel-default panel-reason" id="reason-<?= $idx ?>">
                        <div class="panel-heading">[說明]</div>
                        <div class="panel-body">
                            <?= nl2br(htmlspecialchars($law_line->{'說明'})) ?>
                        </div>
                    </div>
                </td>
            </tr>
            <?php } ?>
            <?php } ?>
            </tbody>
        </table>
    </div>
</div>
<script>

var get_dom_from_diff = function(texta, textb, type) {
    diff = Diff.diffChars(texta, textb);
    fragment = document.createDocumentFragment();
	diff.forEach((part) => {
		// green for additions, red for deletions
		// grey for common parts
		const color = part.added ? '#bedeac' :
			part.removed ? '#ffbdbd' : '';
		if (type == 'new' && part.added) { return ; }
		if (type == 'old' && part.removed) { return ; }
		span = document.createElement('span');
		span.style['background-color'] = color;
		lines = part.value.split("\n");
		for (var i = 0; i < lines.length; i ++) {
			if (i) {
				span.appendChild(document.createElement('br'));
			}

			span.appendChild(document
				.createTextNode(lines[i]));
		}
		fragment.appendChild(span);
	});
	return fragment;
};


$('.diff-tr').each(function(){
    var tr_dom = $(this);
    var texta = $('td', tr_dom).eq(0).data('content');
    var textb = $('td', tr_dom).eq(1).data('content');
    $('td', tr_dom).eq(0).html(get_dom_from_diff(textb, texta, 'old'));
    $('td', tr_dom).eq(1).html(get_dom_from_diff(textb, texta, 'new'));
});

</script>
<?php include(__DIR__ . '/footer.php'); ?>
