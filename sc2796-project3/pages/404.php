<?php
include("includes/init.php");
?>
<!DOCTYPE html>
<html lang="en">

  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <title>Page Not Found</title>

    <link rel="stylesheet" type="text/css" href="public/styles/site.css" media="all" />
  </head>

  <body>
    <?php include("includes/header.php"); ?>

    <div class="notFoundPage">
      <h2>We are sorry, the page: <em>&quot;<?php echo htmlspecialchars($request_uri); ?>&quot;</em>, does not exist.</h2>
      <h2>Please use the navigation bar to navigate to the homepage or click on the following link: <a href="/">Homepage</a> </h2>
    </div>

    <?php include("includes/footer.php"); ?>
  </body>

</html>
