<?php
$json_file = file_get_contents('http://portumnachamber.com/business-directory/feeds/json');
$jfo = json_decode($json_file);
$nodes = $jfo->nodes;
?>

<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Data from Drupal, parsed with PHP</title>
</head>

<body>
  <h1>We are parsing this JSON using PHP</h1>

    <ul>
        <?php
          foreach ($nodes as $node) {
        ?>
        <li>
            <a href="<?php echo $node->node->nid; ?>">
                <h2><?php echo $node->node->title; ?></h2>
            </a>
        </li>
        <?php
        } // end foreach
        ?>
    </ul>

</body>
</html>
