<?php

require_once '../src/PLib.php';

PLib\import ('htmlparser');

$html = '
<!doctype html>
<html>
  <head>
    <title>My HTML</title>
  </head>
  <body>
    <div id="mydiv">
      <p><span>Hello world</span></p>
      <!-- A picture of me -->
      <img src="myimage.jpg" alt="A picture of me">
    </div>
  </body>
</html>';

$p = new PLib\HTML\Parser ($html);

$p->add_containers (array(
  'title' => function ($p) {
    echo "Got title callback\n";
  },
  'p' => function ($p) {
    echo "Got p callback\n";
    return false;
  },
  'span' => function ($p) {
    echo "Got span callback\n";
  }
));

$p->add_tags (array(
  'img' => function ($p) {
    echo "Got img callback\n";
  }
));

$p->set_data_callback (function ($p) {
  echo "Got data\n";
});

$p->set_tag_callback (function ($p, $name, $attr) {
  echo "  + Got tag: $name\n";
  print_r($attr);
});

$p->parse ();
?>