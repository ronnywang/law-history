<?php

class Parser
{
    public static function getListFromWeb()
    {
	    foreach (glob(__DIR__ . "/bill-html/*.gz") as $f) {
		    yield [basename($f), filemtime($f)];
	    }
return;
        $content = file_get_contents('https://lydata.s3.ronny.tw/bill-html/?C=M;O=A');
        $doc = new DOMDocument;
        @$doc->loadHTML($content);
        foreach ($doc->getElementsByTagName('tr') as $tr_dom) {
            $filename = $tr_dom->getElementsByTagName('a')->item(0)->nodeValue;
            if (!strpos($filename, '.gz')) {
                continue;
            }
            yield [$filename, strtotime($tr_dom->getElementsByTagName('td')->item(2)->nodeValue)];
        }
    }

    public static function parsePerson($person)
    {
        $persons = preg_split('#　　#u', $person);
        $persons = array_map(function($s) {
            return trim(str_replace('　', '', $s));
        }, $persons);
        $persons = array_values(array_filter($persons, 'strlen'));
        return $persons;
    }

    public static function parseOldBillDetail($billno, $doc)
    {
        $th_dom = $doc->getElementById('t1');
        $tbody_dom = $th_dom->parentNode;
        while ($tbody_dom->nodeName != 'tbody') {
            $tbody_dom = $tbody_dom->parentNode;
            if (!$tbody_dom) {
                throw new Exception($billno);
            }
        }
        $obj = new StdClass;
        $obj->billNo = $billno;

        foreach ($tbody_dom->childNodes as $tr_dom) {
            if ($tr_dom->nodeName != 'tr') {
                continue;
            }
            $th_dom = $tr_dom->getElementsByTagName('th')->item(0);
            $key = trim($th_dom->nodeValue);

            if (in_array($key, array('審查委員會', '議案名稱', '提案單位/提案委員', '議案狀態', '交付協商'))) {
                $td_dom = $tr_dom->getElementsByTagName('td')->item(0);
                $value = trim($td_dom->nodeValue);
                $obj->{$key} = $value;
            } else if ($key == '相關附件') {
                $obj->{'相關附件'} = array();
                preg_match_all('/<a class="[^"]*"[^>]*href="([^"]*)"\s+title="([^"]*)"/', $doc->saveHTML($tr_dom), $matches);
                foreach ($matches[0] as $idx => $m) {
                    $o = new StdClass;
                    $o->{'網址'} = trim($matches[1][$idx]);
                    $o->{'名稱'} = trim($matches[2][$idx]);
                    $obj->{'相關附件'}[] = $o;
                }
            } else if ($key == '關連議案') {
                $obj->{'關連議案'} = array();
                foreach ($tr_dom->getElementsByTagName('a') as $a_dom) {
                    $name = preg_split("/\s+/", trim($a_dom->nodeValue));
                    $billno = explode("'", $a_dom->getAttribute('onclick'))[1];
                    $obj->{'關連議案'}[] = array(
                        'billNo' => $billno,
                        '提案人' => $name[0],
                        '議案名稱' => $name[1],
                    );
                }
            } else if ('提案人' == $key or '連署人' == $key) {
                $obj->{$key} = '';
                if (preg_match("/getLawMakerName\('([^']*)', '([^']*)'\);/", $doc->saveHTML($tr_dom), $matches)) {
                    $obj->{$key} = Parser::parsePerson(trim($matches[2]));
                }
            } else if ('議案流程' == $key) {
                $obj->{'議案流程'} = array();
                foreach ($tr_dom->getElementsByTagName('tbody')->item(0)->getElementsByTagName('tr') as $sub_tr_dom) {
                    $record = array();
                    $sub_td_doms = $sub_tr_dom->getElementsByTagName('td');
                    $record['會期'] = trim($sub_td_doms->item(0)->nodeValue);
                    $record['日期'] = array();
                    foreach ($sub_td_doms->item(1)->getElementsByTagName('div')->item(0)->childNodes as $dom) {
                        if ($dom->nodeName == 'a') {
                            $record['日期'][] = trim($dom->nodeValue);
                        } else if ($dom->nodeName == '#text' and trim($dom->nodeValue)) {
                            $record['日期'][] = trim($dom->nodeValue);
                        }
                    }
                    $record['院會/委員會'] = trim($sub_td_doms->item(2)->nodeValue);
                    $record['狀態'] = '';
                    foreach ($sub_td_doms->item(3)->childNodes as $n) {
                        if ($n->nodeName == '#text') {
                            $record['狀態'] .= trim($n->nodeValue);
                        } elseif ($n->nodeName == 'a') {
                            $record['狀態'] .= trim($n->nodeValue);
                        }
                    }
                    $record['狀態'] = preg_replace('/\s+/', ' ', $record['狀態']);
                    $obj->{'議案流程'}[] = $record;
                }
            } else {
                $td_dom = $tr_dom->getElementsByTagName('td')->item(0);
                throw new Exception("{$key} 找不到");
            }
        }
        return $obj;
    }

    public static function parseBillDetail($billno, $content)
    {
        $doc = new DOMDocument;
        $content = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">', $content);
        @$doc->loadHTML($content);
        if ($th_dom = $doc->getElementById('t1')) {
            return self::parseOldBillDetail($billno, $doc);
        }
        if (!$h4_dom = $doc->getElementsByTagName('h4')->item(0)) {
            throw new Exception("unknown {$billno}: h4 not found");
        }
        $obj = new StdClass;
        $obj->billNo = $billno;
        $obj->{'相關附件'} = [];
        $obj->{'議案流程'} = [];
        $obj->{'關連議案'} = [];
        $obj->{'議案名稱'} = $h4_dom->nodeValue;
        $dom = $h4_dom;
        while ($dom = $dom->nextSibling) {
            if ($dom->nodeName == 'div' and $dom->getAttribute('class') == 'row' and $h6_dom = $dom->getElementsByTagName('h6')->item(0)) {
                $obj->{'提案單位/提案委員'} = $h6_dom->nodeValue;
                break;
            }
        }
        if (!$dom) {
            throw new Exception("unknown {$billno}: no 提案單位");
        }
        while ($dom = $dom->nextSibling) {
            if ($dom->nodeName == 'div' and $span_dom = $dom->getElementsByTagName('span')->item(0) and (strpos($span_dom->getAttribute('class'), 'fw-bolder') !== false)) {
                $obj->{'議案狀態'} = $span_dom->nodeValue;
                break;
            }
        }
        if (!$dom) {
            throw new Exception("unknown {$billno}: no 議案狀態");
        }
        foreach ($dom->parentNode->getElementsByTagName('a') as $a_dom) {
            if (strpos($a_dom->getAttribute('class'), 'Ur-BadgeLink') !== false) {
                $f = new StdClass;
                $f->{'名稱'} = trim($a_dom->nodeValue);
                $f->{'網址'} = $a_dom->getAttribute('href');
                $obj->{'相關附件'}[] = $f;
            }
        }
        foreach ($doc->getElementsByTagName('h3') as $h3_dom) {
            if ($h3_dom->nodeValue == '關聯議案') {
                $dom = $h3_dom->parentNode;
                foreach ($dom->getElementsByTagName('a') as $a_dom) {
                    if (!preg_match('#/ppg/bills/(.*)/details#', $a_dom->getAttribute('href'), $matches)) {
                        throw new Exception("unknown {$billno}: wrong link " . $a_dom->getAttribute('href'));
                    }
                    $b = new StdClass;
                    $b->billNo = $matches[1];
                    $b->{'議案名稱'} = $a_dom->nodeValue;
                    $obj->{'關連議案'}[] = $b;
                }
            } else if (in_array($h3_dom->nodeValue, ['提案人', '連署人'])) {
                $type = $h3_dom->nodeValue;
                $obj->{$type} = [];
                foreach ($h3_dom->parentNode->getElementsByTagName('a') as $a_dom) {
                    $obj->{$type}[] = $a_dom->nodeValue;
                }
            } else if ($h3_dom->nodeValue == '審議進度') {
                $dom = $h3_dom->parentNode;
                foreach ($dom->getElementsByTagName('dl') as $dl_dom) {
                    $p = new StdClass;
                    $p->{'日期'} = [];
                    $p->{'狀態'} = $dl_dom->getElementsByTagName('dt')->item(0)->getElementsByTagName('h5')->item(0)->nodeValue;
                    $text = trim($dl_dom->getElementsByTagName('dd')->item(0)->getElementsByTagName('h5')->item(0)->nodeValue);
                    if ($text == '') {
                        // TODO: 委員會發文？
                    } else if (preg_match('#^(.*) (\d*-.*)$#', $text, $matches)) {
                        $p->{'會期'} = $matches[2];
                        $p->{'院會/委員會'} = trim($matches[1]);
                    } elseif (in_array($text, ['議事處', '資訊處']) or preg_match('#^[^\s]+委員會$#u', trim($text))) {
                        $p->{'院會/委員會'} = trim($text);
                    } else {
                        throw new Exception("unknown {$billno}: wrong text {$text}");
                    }
                    foreach ($dl_dom->getElementsByTagName('p') as $p_dom) {
                        if (strpos($p_dom->getAttribute('class'), 'card-text') !== false) {
                            if (preg_match('#(\d+)年(\d+)月(\d+)日#', $p_dom->nodeValue, $matches)) {
                                $p->{'日期'}[] = sprintf("%03d/%02d/%02d", $matches[1], $matches[2], $matches[3]); 
                            } else {
                                throw new Exception("unknown {$billno}: wrong date {$p_dom->nodeValue}");
                            }
                        }
                    }

                    $obj->{'議案流程'}[] = $p;
                }
            }
        }
        return $obj;
    }

    public static function onlystr($str)
    {
        return preg_Replace('/\s+/', '', $str);
    }

    public static function parseBillDoc($billNo, $content)
    {
        $record = new StdClass;
        $record->billNo = $billNo;

        $doc = new DOMDocument;
        if (!$content) {
            throw new Exception("{$billNo} no content");
        }
        $content = preg_replace('#<img src="([^"]*)" name="DW\d+" alt="DW\d+" align="left" hspace="12" width="\d+"/>\n#', '', $content);
        @$doc->loadHTML($content);
        foreach ($doc->getElementsByTagName('meta') as $meta_dom) {
            if ($meta_dom->getAttribute('name') == 'created') {
                $record->created_at = $meta_dom->getAttribute('content');
            }
        }
        file_put_contents("tmp.html", $content);
        foreach ($doc->getElementsByTagName('p') as $p_dom) {
            if (strpos(trim($p_dom->nodeValue), '院總第') === 0) {
                $tr_dom = $p_dom->parentNode;
                while ('tr' != $tr_dom->nodeName) {
                    $tr_dom = $tr_dom->parentNode;
                }
                // TODO: 審查報告的字號可能會有多筆
                $record->{'字號'} = self::onlystr($tr_dom->nodeValue);
            } else if (strpos(trim($p_dom->nodeValue), '案由：') === 0) {
                $record->{'案由'} = preg_replace('/^案由：/u', '', trim($p_dom->nodeValue));
            } else if (strpos(trim($p_dom->nodeValue), '提案人：') === 0) {
                $record->{'提案人'} = preg_replace('/^提案人：/u', '', trim($p_dom->nodeValue));
            } else if (strpos(trim($p_dom->nodeValue), '連署人：') === 0) {
                $record->{'連署人'} = preg_replace('/^連署人：/u', '', trim($p_dom->nodeValue));
            } else if (in_array(self::onlystr($p_dom->nodeValue), array('修正條文', '增訂條文', '條文', '審查會通過條文', '審查會通過', '審查會條文'))) {
                $record2 = new StdClass;
                if (in_array(self::onlystr($p_dom->nodeValue), array('審查會通過', '審查會條文', '審查會通過條文'))) {
                    $record2->{'立法種類'} = '審查會版本';
                    // TODO: 審查會通過條文 (處理多筆字號)
                    unset($record->{'字號'});
                }
                //往上找 table 位置
                $table_dom = $p_dom->parentNode;
                while ('table' != $table_dom->nodeName) {
                    $table_dom = $table_dom->parentNode;
                    if (!$table_dom) {
                        continue 2;
                        throw new Exception("table not found");
                    }
                }

                // 如果是審查會通過版本，標題頭可能會在另一個 table ，因此需要往上抓
                if ($record2->{'立法種類'} == '審查會版本') {
                    $title_table_dom = $table_dom;
                    while ($title_table_dom = $title_table_dom->previousSibling) {
                        if ($title_table_dom->nodeName == 'table' and preg_match('#條文對照表$#', trim($title_table_dom->nodeValue))) {
                            $record2->{'對照表標題'} = preg_replace('#\s+#', '', $title_table_dom->nodeValue);
                            $record2->{'對照表標題'} = str_replace('」', '', $record2->{'對照表標題'});
                            break;
                        }
                    }
                }
                $record2->{'修正記錄'} = array();
                $tr_doms = array();
                foreach ($table_dom->childNodes as $tbody_dom) {
                    if ('tbody' == $tbody_dom->nodeName) {
                        foreach ($tbody_dom->childNodes as $tr_dom) {
                            if ('tr' != $tr_dom->nodeName) {
                                continue;
                            }
                            $tr_doms[] = $tr_dom;
                        }
                    } else if ('tr' == $tbody_dom->nodeName) {
                        $tr_doms[] = $tbody_dom;
                    }
                }
                $columns = array();
                while ($tr_dom = array_shift($tr_doms)) {
                    $td_doms = array();
                    $all_td_doms = [];
                    $only_first = true;
                    foreach ($tr_dom->childNodes as $td_dom) {
                        if ('td' != $td_dom->nodeName) {
                            continue;
                        }
                        $all_td_doms[] = $td_dom;
                        if (!count($td_doms) and trim($td_dom->nodeValue) == '') {
                            continue;
                        }
                        if (count($td_doms) and trim($td_dom->nodeValue) != '') {
                            $only_first = false;
                        }
                        if ($td_dom->getAttribute('rowspan')) {
                            for ($i = 0; $i < $td_dom->getAttribute('rowspan') - 1; $i ++) {
                                array_shift($tr_doms);
                            }
                            continue 2;
                        }
                        $td_doms[] = $td_dom;
                    }
                    if (!count($td_doms)) {
                        continue;
                    }
                    if ($only_first) {
                        $record2->{'對照表標題'} = self::onlystr($td_doms[0]->nodeValue);
                    } else if (in_array(self::onlystr($td_doms[0]->nodeValue), array('審查會通過條文', '審查會通過', '審查會條文'))) {
                        // TODO: 審查會通過條文 (處理多筆字號)
                        unset($record->{'字號'});
                        foreach ($td_doms as $idx => $td_dom) {
                            if (in_array(self::onlystr($td_dom->nodeValue), array('審查會通過條文', '審查會通過', '審查會條文'))) {
                                $columns['審查會通過條文'] = $idx;
                            } else if (in_array(self::onlystr($td_dom->nodeValue), array('現行條文', '現行法條文', '現行法'))) {
                                $columns['現行條文'] = $idx;
                            } else if (self::onlystr($td_dom->nodeValue) == '說明') {
                                $columns['說明'] = $idx;
                            }
                        }
                        $record2->{'立法種類'} = '審查會版本';
                        if (!array_key_exists('審查會通過條文', $columns) or !array_key_exists('說明', $columns)) {
                            throw new Exception("找不到審查會通過條文和說明欄位");
                            //echo $doc->saveHTML($tr_dom);
                            //echo json_encode($columns, JSON_UNESCAPED_UNICODE) . "\n";
                            //exit;
                        }
                    } else if (count($td_doms) >= 2 and trim($td_doms[0]->nodeValue) == '修正條文') {
                        $record2->{'立法種類'} = '修正條文';
                    } else if (count($td_doms) == 2 and self::onlystr($td_doms[0]->nodeValue) == '增訂條文') {
                        $record2->{'立法種類'} = '增訂條文';
                    } else if (count($td_doms) == 3 and self::onlystr($td_doms[0]->nodeValue) == '條文' and trim($td_doms[1]->nodeValue) == '現行條文') {
                        $record2->{'立法種類'} = '修正條文';
                    } else if (count($td_doms) == 3 and self::onlystr($td_doms[0]->nodeValue) == '條文' and self::onlystr($td_doms[1]->nodeValue) == '參考條文' and self::onlystr($td_doms[2]->nodeValue) == '說明') {
                        $record2->{'立法種類'} = '制定條文';
                        $columns['條文'] = 0;
                        $columns['說明'] = 2;
                    } else if (count($td_doms) == 2 and self::onlystr($td_doms[0]->nodeValue) == '條文') {
                        $record2->{'立法種類'} = '制定條文';
                        $columns['條文'] = 0;
                        $columns['說明'] = 1;
                    } else if (count($td_doms) == 3 and trim($td_doms[0]->nodeValue) == '修正名稱') {
                        $tr_dom = array_shift($tr_doms);
                        $td_doms = $tr_dom->getElementsByTagName('td');
                        $record2->{'名稱修正'} = array(
                            '修正名稱' => trim($td_doms->item(0)->nodeValue),
                            '現行名稱' => trim($td_doms->item(1)->nodeValue),
                            '說明' => str_replace("\t", "", trim($td_doms->item(2)->nodeValue)),
                        );

                    } else if (count($td_doms) == 2 and in_array(trim($td_doms[0]->nodeValue), array('名稱', '法案名稱'))) {
                        $tr_dom = array_shift($tr_doms);
                        $td_doms = $tr_dom->getElementsByTagName('td');
                        $record2->{'名稱說明'} = str_replace("\t", "", trim($td_doms->item(1)->nodeValue));
                    } else if ('審查會版本' == $record2->{'立法種類'}) {
                        if (!array_key_exists('現行條文', $columns)) {
                            $record2->{'修正記錄'}[] = array(
                                '增訂條文' => str_replace("\t", "", trim($td_doms[$columns['審查會通過條文']]->nodeValue)),
                                '說明' => str_replace("\t", "", trim($td_doms[$columns['說明']]->nodeValue)),
                            );
                        } else {
                            $record2->{'修正記錄'}[] = array(
                                '修正條文' => str_replace("\t", "", trim($td_doms[$columns['審查會通過條文']]->nodeValue)),
                                '現行條文' => str_replace("\t", "", trim($td_doms[$columns['現行條文']]->nodeValue)),
                                '說明' => str_replace("\t", "", trim($td_doms[$columns['說明']]->nodeValue)),
                            );
                        }
                    } else if ('修正條文' == $record2->{'立法種類'}) { // and $td_doms->length == 3) {
                        $record2->{'修正記錄'}[] = array(
                            '修正條文' => str_replace("\t", "", trim($all_td_doms[0]->nodeValue)),
                            '現行條文' => str_replace("\t", "", trim($all_td_doms[1]->nodeValue)),
                            '說明' => str_replace("\t", "", trim($all_td_doms[2]->nodeValue)),
                        );
                    } else if ('增訂條文' == $record2->{'立法種類'} and count($td_doms) == 2) {
                        $record2->{'修正記錄'}[] = array(
                            '增訂條文' => str_replace("\t", "", trim($td_doms[0]->nodeValue)),
                            '說明' => str_replace("\t", "", trim($td_doms[1]->nodeValue)),
                        );
                    } else if ('制定條文' == $record2->{'立法種類'}) {
                        $record2->{'修正記錄'}[] = array(
                            '增訂條文' => str_replace("\t", "", trim($td_doms[$columns['條文']]->nodeValue)),
                            '說明' => str_replace("\t", "", trim($td_doms[$columns['說明']]->nodeValue)),
                        );
                    } else {
                        if ($record2->{'立法種類'} == '審查會版本') {
                           // == '1070321070300100') {
                            continue;
                        }
                        continue;
                        echo $doc->saveHTML($tr_dom);
                        echo 'trim($td_doms[0]->nodeValue) => ' .trim($td_doms[0]->nodeValue) . "\n";
                        throw new Exception("error");
                        exit;
                    }
                }
                if ($record2->{'立法種類'} == '審查會版本') {
                    if (array_key_exists('現行條文', $columns)) {
                        $record2->{'立法種類'} = '修正條文';
                    } else {
                        $record2->{'立法種類'} = '增訂條文';
                    }

                }
                $record->{'對照表'}[] = $record2;
            }
        }

        $record->{'總說明'} = '';
        if (property_exists($record, '對照表標題')) {
            foreach ($doc->getElementsByTagName('span') as $span_dom) {
                if ($span_dom->nodeValue == $record->{'對照表標題'} . '總說明') {
                    $p_dom = $span_dom;
                    while ($p_dom = $p_dom->parentNode) {
                        if ($p_dom->nodeName == 'p') {
                            break;
                        }
                    }
                    if ($p_dom) {
                        while ($p_dom = $p_dom->nextSibling) {
                            if ($p_dom->nodeName == '#text') {
                                continue;
                            }
                            if ($p_dom->nodeName != 'p') {
                                break;
                            }
                            $record->{'總說明'} .= trim($p_dom->nodeValue) . "\n";
                        }
                    }
                }
            }
            $record->{'總說明'} = trim($record->{'總說明'});
        }

        return $record;
    }
}
