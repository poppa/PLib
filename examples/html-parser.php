<?php

require_once '../src/PLib.php';

PLib\import ('htmlparser');
use PLib\HTML\Parser;

$html = '
<!doctype html>
<html>
  <head>
    <title>My HTML</title>
    <link rel="stylesheet" href="style.css">
    <script src="script.js"></script>
    <script>
      $(function () {
        $("#mydiv").html ("Some shit man");
      });
    </script>
  </head>
  <body>
    <div id="mydiv">
      <p><span id="myspan">Hello world &bull; Hello universe</span></p>
      <ul>
        <li>List item 1</li>
        <li>List item 2</li>
        <li>List item <i>3</i></li>
      </ul>
      <!-- A picture of me -->
      <!-- A picture of me indeed -->
      <img src="myimage.jpg" alt="A picture of me">
    </div>
    <div>
      <footer>Some cheesy footer</footer>
    </div>
  </body>
</html>';

$p = new Parser ();

$p->add_tags (array(
  '#comment' => function (DOMNode $node, $data) {
    // Remove comment nodes
    $node->parentNode->removeChild ($node);
  },

  '#text' => function (DOMNode $node, $value) {
    if (trim ($value) === "")
      $node->parentNode->removeChild ($node);
  },

  'i' => function (DOMNode $node, $tag, $attr, $data) {
    // Replace i tags with em tags
    $e = $node->ownerDocument->createElement ('em', $data);
    $node->parentNode->replaceChild ($e, $node);
  },

  'div' => function (DOMNode $node, $tag, $attr, $data) {
    // If the ID is "mydiv", change it...
    if (isset ($attr['id']) && $attr['id'] === 'mydiv')
      $node->setAttribute ('id', 'not-mydiv');
  },

  'span' => function (DOMNode $node, $tag, $attr, $data) {
    if (isset ($attr['id']) && $attr['id'] === 'myspan') {
      // Just for fun, insert an image after the other image
      $n = $node->ownerDocument->createElement ('img');
      $n->setAttribute ('src', 'my-other-image.png');
      $n->setAttribute ('alt', 'Another image of me');
      $node->parentNode->parentNode->appendChild ($n);
    }
  }
));

$s = $p->parse ($html)->render ();
echo $s;
?>