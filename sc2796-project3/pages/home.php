<?php
include("includes/init.php");
$nav_home_class = "current_active_page";

$sql_select_query = "SELECT * FROM recipes LEFT OUTER JOIN recipe_tags ON recipes.id = recipe_tags.recipe_id LEFT OUTER JOIN tags ON recipe_tags.tag_id = tags.id";

$sql_select_params = array();

$tag = $_GET['tag'];

if ($tag != NULL) {
  $sql_select_query = $sql_select_query . " WHERE (label = :tag);";
  $sql_select_params[':tag'] = $tag;
}

$deleted_recipe = False;

if ($_GET['deleteMessage'] != NULL) {
  $deleted_recipe_name = trim($_GET['deleteMessage']);
  $successful_delete_message = "You have successfully deleted the recipe: " . $deleted_recipe_name;
  $deleted_recipe = True;
}


$sql_select_query = $sql_select_query . " GROUP BY dish;";

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>HomeFoods Homepage</title>

  <link rel="stylesheet" type="text/css" href="public/styles/site.css" media="all" />


</head>

<body>
  <!-- Need to include the header using php include -->
  <?php include("includes/header.php"); ?>

  <div class="content">
    <main>
      <!-- Will contain all of the div boxes that contain the contents of the web page -->
      <section>
        <?php
        $records= exec_sql_query(
          $db,
          $sql_select_query,
          $sql_select_params
        )->fetchALL();

        if (count($records) > 0) { ?>
          <ul>
            <?php
            foreach ($records as $record) { ?>
              <li>
                <cite>
                    <a href="<?php echo htmlspecialchars($record['source']); ?>">Image Source</a>
                </cite>
                <a href="/recipe?<?php echo http_build_query(array('id' => $record['recipe_id'])); ?>">
                <div class="imageContent">
                  <img src="/public/uploads/recipes/<?php echo $record['recipe_id'] . '.' . $record['file_ext']; ?>" alt="<?php echo htmlspecialchars($record['dish']); ?>" />
                </div>
                <div class="words">
                  <p><?php echo ucfirst($record['filename']) ?></p>
                </div>
                </a>
              </li>
            <?php
            } ?>
          </ul>
        <?php } ?>
      </section>
    </main>

    <?php
      $records = exec_sql_query(
        $db,
        "SELECT * FROM recipe_tags INNER JOIN tags ON recipe_tags.tag_id=tags.id GROUP BY label;"
      )->fetchAll();
    ?>
    <aside>
      <section>
        <h1>Tags:</h1>
        <?php
          foreach ($records as $record) {
            echo nl2br('<a href="/?tag=' . htmlspecialchars($record['label']) . '">' . '#' . htmlspecialchars($record['label']) . '</a>' . "\r\n");
          }
        ?>
      </section>
    </aside>
  </div>
  <?php include("includes/footer.php"); ?>
</body>

</html>
