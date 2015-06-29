<?php include('includes/header.php'); ?>
<body>
  <!--  Home Page -->
  <div data-role="page" id="home" data-theme="b">

    <header data-role="header">
        <h1> <img src="img/logo.png" alt="Annertech's App"/> </h1>
    </header>

    <?php include('includes/nav.php'); ?>

    <div data-role="content">
      <p>Welcome to the Annertech App.</p>
      <p>We put in some welcome message or nice image or something like that.</p>
    </div>

    <footer data-role="footer" data-position="fixed">
        <h4>Annertech's App</h4>
    </footer>

  </div>

  <!--  News Page -->
  <div data-role="page" id="news" data-theme="b">

    <header data-role="header">
      <h1> <img src="img/logo.png" alt="Annertech's App"/> </h1>
    </header>

    <?php include('includes/nav.php'); ?>

    <div data-role="content">

      <?php
        $html = "";
        $url = "http://feeds.feedburner.com/annertech/mwjR";
        $xml = simplexml_load_file($url);
        for($i = 0; $i < 10; $i++){
        	$title = $xml->channel->item[$i]->title;
        	$link = $xml->channel->item[$i]->link;
        	$description = $xml->channel->item[$i]->description;
        	$pubDate = $xml->channel->item[$i]->pubDate;
          $html .= '<div class="feed-title">';
          $html .= "<a href='$link'><h3>$title</h3></a></div>";
        	$html .= '<div class="feed-description">';
          $html .= "$description";
        	$html .= "<br />$pubDate<hr /></div>";
        }
        echo $html;
        ?>
    </div>

    <footer data-role="footer" data-position="fixed">
        <h4>Annertech's App</h4>
    </footer>

  </div>

  <!--  Contact Page -->
  <div data-role="page" id="contact" data-theme="b">

    <header data-role="header">
        <h1> <img src="img/logo.png" alt="Annertech's App"/> </h1>
    </header>

    <?php include('includes/nav.php'); ?>

    <div data-role="content">
      <p>Want to contact us?</p>
      <p>McN Associates Office Suite,<br>
        51 Dawson St,<br>
        Dublin 2, Ireland Unit 204,<br>
        Business Innovation Centre,<br>
        NUI Galway, Ireland</p>
        <iframe src="https://www.google.com/maps/embed?pb=!1m14!1m8!1m3!1d4764.178235491188!2d-6.258384!3d53.3416609!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0xeea153bd4ecc9483!2sAnnertech!5e0!3m2!1sen!2sie!4v1435607764208" width="480" height="480" frameborder="0" style="border:0" allowfullscreen></iframe>

    </div>

    <footer data-role="footer" data-position="fixed">
        <h4> Annertech's APP </h4>
    </footer>

  </div>

</body>
</html>
