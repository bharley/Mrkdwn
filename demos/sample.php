<?php

include '../src/Mrkdwn.php';
$mrkdwn = new Mrkdwn;

$text = <<<MARKDOWN
This is a **very** complicated paragraph.
MARKDOWN;

echo $mrkdwn->parse($text);

