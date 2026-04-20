<?php
$lines = file('recovery_panel.php');
$out = array_merge(array_slice($lines, 0, 389), array_slice($lines, 588));
file_put_contents('recovery_panel.php', implode('', $out));
echo "Done.";
?>