<?php
require_once '../src/PLib.php';

PLib\import ('xml/builder');

$doc = new PLib\XML\HTMLDocument ();

$root = $doc->add_node ('html');

$head = $root->add_node ('head');
$head->add_node ('meta', null, array('charset' => 'utf-8'));
$head->add_node ('title', 'My web site & stuff');
$head->add_node ('link', null, array('rel' => 'stylesheet', 
                                     'href' => 'style.css'));
$head->add_node ('script', 
  'if (document.body.length > 0)
    document.body.write ("This is my life");');

$body = $root->add_node ('body');
$body->add_node ('h1', 'Welcome to my site');
$body->add_node ('p', 'This is just some silly stuff ')
     ->add_node ('img', null, array('src' => 'mypic.jpg', 
                                    'alt' => 'A picture of me'));
$body->add_node_tree ('
  <div class="my-class">
    <div class="left">
      <p>Some content to the left</p>
    </div>
    <div class="right last">
      <p>Some content to the right</p>
    </div>
  </div>
');

unset ($head, $body, $root);

echo $doc->render (true);
?>