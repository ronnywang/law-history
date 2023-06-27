<?php
$billno = Param::get('bill_id');
$bill_data = BillAPI::getBillData($billno);
Param::addAPI('/api/billdata?billNo=' . urlencode($billno), "議案資料");
if ($bill_data->docData->{'對照表'}) {
    $laws = [];
    foreach ($bill_data->docData->{'對照表'} as $idx => $table) {
        try {
            $lawname = BillAPI::getLawNameFromTableName($table->{'對照表標題'});
            $laws[] = $lawname;
        } catch (Exception $e) {
        }
        $bill_data->docData->{'對照表'}[$idx]->{'法律'} = $lawname;
    }
    $ret = LawAPI::searchLaw(['names' => $laws]);
    $lawname_id = [];
    foreach ($ret->data as $law) {
        $lawname_id[$law->{'最新名稱'}] = $law->_id;
    }
}
?>
<?php include(__DIR__ . '/header.php'); ?>
<h1><?= htmlspecialchars($bill_data->detail->{'議案名稱'}) ?></h1>
<h2><?= htmlspecialchars($bill_data->detail->{'提案單位/提案委員'}) ?></h2>
<dl>
    <dd>議案狀態</dd>
    <dt><?= htmlspecialchars($bill_data->detail->{'議案狀態'}) ?></dt>
    <dd>提案人</dd>
    <dt>
    <?php foreach ($bill_data->detail->{'提案人'} as $p) { ?>
    <a href="#"><?= htmlspecialchars($p) ?></a>
    <?php } ?>
    </dt>
    <dd>連署人</dd>
    <dt>
    <?php foreach ($bill_data->detail->{'連署人'} as $p) { ?>
    <a href="#"><?= htmlspecialchars($p) ?></a>
    <?php } ?>
    </dt>
</dl>
<h3>相關附件</h3>
<div>
    <?php foreach ($bill_data->detail->{'相關附件'} as $attch) { ?>
    <a class="btn btn-primary" href="<?= htmlspecialchars($attch->{'網址'}) ?>"><?= htmlspecialchars($attch->{'名稱'}) ?></a>
    <?php } ?>
</div>
<h3>議案流程</h3>
<table class="table">
    <thead>
        <tr>
            <th>日期</th>
            <th>狀態</th>
            <th>會期</th>
            <th>院會/委員會</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($bill_data->detail->{'議案流程'} as $flow) { ?>
    <tr>
        <td>
            <?php foreach ($flow->{'日期'} as $d) { ?>
            <div><?= htmlspecialchars($d) ?></div>
            <?php } ?>
        </td>
        <td><?= htmlspecialchars($flow->{'狀態'}) ?></td>
        <td><?= htmlspecialchars($flow->{'會期'}) ?></td>
        <td><?= htmlspecialchars($flow->{'院會/委員會'}) ?></td>
    </tr>
    <?php } ?>
    </tbody>
</table>
<?php if ($bill_data->detail->{'關連議案'}) { ?>
<h3>關連議案</h3>
<ul>
    <?php foreach ($bill_data->detail->{'關連議案'} as $relbill) { ?>
    <li><a href="/bill/detail/<?= urlencode($relbill->billNo) ?>"><?= htmlspecialchars($relbill->{'議案名稱'}) ?></a></li>
    <?php } ?>
</ul>
<?php } ?>
<?php if ($bill_data->docData) { ?>
    <h3>關係文書</h3>
    <dl>
    <?php foreach (['字號', '案由', '提案人'] as $t) { ?>
    <dd><?= $t ?></dd>
    <dt><?= htmlspecialchars($bill_data->docData->{$t}) ?></dt>
    <?php } ?>
    </dl>

    <?php if ($bill_data->docData->{'對照表'}) { ?>
    <h3>對照表</h3>
    <ul>
        <?php foreach ($bill_data->docData->{'對照表'} as $table) { ?>
        <li>
        <?php if (property_exists($table, '法律') and array_key_exists($table->{'法律'}, $lawname_id)) { ?>
        <a href="/lawdiff/<?= urlencode($lawname_id[$table->{'法律'}]) ?>/bill-<?= $bill_data->detail->billNo ?>"><?= htmlspecialchars($table->{'對照表標題'}) ?></a>
        <?php } else { ?>
        <a href="#"><?= htmlspecialchars($table->{'對照表標題'}) ?></a>
        <?php } ?>
        </li>
        <?php } ?>
    </ul>
    <?php } ?>
<?php } ?>
<?php include(__DIR__ . '/footer.php'); ?>
