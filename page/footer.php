</div>
<div class="panel panel-default">
    <div class="panel-heading">本頁使用 API</div>
    <div class="panel-body">
        <ul>
            <?php foreach (Param::getAPIs() as $url_reason) { ?>
            <li>
            <?= htmlspecialchars($url_reason[1]) ?>: <code><?= htmlspecialchars($url_reason[0]) ?></code>
            </li>
            <?php } ?>
        </ul>
    </div>
</div>
</body>
</html>
