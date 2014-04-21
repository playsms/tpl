<?php

include "../src/Playsms/Tpl.php";

$the_data = 'This is the data';

$tpl = new Playsms\Tpl;

$tpl->name = 'dir2/test2';

$tpl->vars = array(
	'title' => 'This is test 2',
	'content' => 'This is sample content',
);

$tpl->ifs = array(
	'valid' => TRUE,
	'something' => FALSE,
);

$tpl->loops = array(
	'data' => array(
		array('color' => 'Red',   'hex' => '#FF0000'),
		array('color' => 'Green', 'hex' => '#00FF00'),
		array('color' => 'Blue',  'hex' => '#0000FF'),
	),
);

$tpl->injects = array('the_data');

$tpl->compile();

echo "<p>Original content:</p>\n";
echo $tpl->getContent();

echo "<br /><br />\n\n";

echo "<p>Manipulated content:</p>\n";
echo $tpl->getResult();

echo "<br /><br />\n\n";

echo "<p>Compiled content:</p>\n";
echo $tpl->getCompiled();
