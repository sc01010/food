<?php
include("includes/init.php");
$nav_newRecipe_class = "current_active_page";

// defining the max file size
define("MAX_FILE_SIZE", 1000000);

$form_valid = False;
$show_confirmation_form = False;

// user must be logged in to upload files
if (is_user_logged_in()) {

  // feedback class
  $file_feedback_class = 'hidden';
  $name_feedback_class = 'hidden';
  $ingredient_feedback_class = 'hidden';
  $instruction_feedback_class = 'hidden';
  $tag_feedback_class = 'hidden';

  // upload fields - set to null as default
  $upload_dishName = NULL;
  $upload_dishIngredient = NULL;
  $upload_dishInstruction = NULL;
  $upload_dishSource = NULL;
  $upload_filename = NULL;
  $upload_ext = NULL;

  // sticky values
  $sticky_dish = '';
  $sticky_ingredient = '';
  $sticky_instruction = '';
  $sticky_source = '';
  $sticky_tag = '';

  // checking whether the recipe was successfully inserted - additional constraints
  $recipe_name_not_unique = False;
  $recipe_inserted = False;
  $recipe_insert_failed = False;

  if (isset($_POST['add_recipe'])) {
    $upload_dishName = trim($_POST['dish']);
    $upload_dishIngredient = trim($_POST['ingredient']);
    $upload_dishInstruction = trim($_POST['instruction']);
    $upload_tag = trim($_POST['newTag']);
    $upload_dishSource = trim($_POST['source']);

    // making the assumption that the form is valid
    $form_valid = True;

    $upload = $_FILES['image-file'];

    if ($upload['error'] == UPLOAD_ERR_OK) {

      // Get the name of the file
      $upload_filename = basename($upload['name']);

      $upload_ext = strtolower(pathinfo($upload_filename, PATHINFO_EXTENSION));

      // using server-side to validate file type
      if (!in_array($upload_ext, array('png', 'gif', 'jpeg', 'jpg',))) {
        $form_valid = False;
      }

    } else {
      // error from the file upload
      $form_valid = False;
    }

    // required fields

    // name of the recipe
    if (empty($upload_dishName)) {
      $form_valid = False;
      $name_feedback_class = '';
    } else {
      // Recipe name should be unique
      $records = exec_sql_query(
        $db,
        "SELECT * FROM recipes WHERE (dish = :recipeName);",
        array(
          'recipeName' => $upload_dishName
        )
      )->fetchAll();
      if (count($records) > 0) {
        $form_valid = False;
        $recipe_name_not_unique = True;
        $name_feedback_class = '';
      }
    }

    if (empty($upload_dishIngredient)) {
      $form_valid = False;
      $ingredient_feedback_class = '';
    }

    if (empty($upload_dishInstruction)) {
      $form_valid = False;
      $instruction_feedback_class = '';
    }

    if (empty($upload_tag)) {
      $form_valid = False;
      $tag_feedback_class = '';
    }

    if (empty($upload_dishSource)) {
      // the source is optional, thus set to NULL if empty instead of empty string
      $upload_dishSource = NULL;
    }

    if ($form_valid) {
      $db->beginTransaction();
      $result = exec_sql_query(
        $db,
        "INSERT INTO recipes (user_id, dish, ingredient, instruction, filename, file_ext, source) VALUES (:user_id, :dish, :ingredient, :instruction, :filename, :file_ext, :source);",
        array(
          ':user_id' => $current_user['id'],
          ':dish' => $upload_dishName,
          ':ingredient' => $upload_dishIngredient,
          ':instruction' => $upload_dishInstruction,
          ':filename' => $upload_filename,
          ':file_ext' => $upload_ext,
          ':source' => $upload_dishSource
        )
      );

      if ($result) {
        $uploaded_recipe_id = $db->lastInsertId("id");
      }

      $results = exec_sql_query(
        $db,
        "SELECT id, label FROM tags WHERE label = :tagName;",
        array(':tagName' => $upload_tag)
        )->fetchALL();

      $tagCount = count($results);
      $new_insert_tag_id = $results[0]['id'];

      if ($tagCount < 1) {
        $result = exec_sql_query(
          $db,
          "INSERT INTO tags (label) VALUES (:newLabel);",
          array(
            ':newLabel' => $upload_tag
          )
        );

        if ($result) {
          $uploaded_tag_id = $db->lastInsertId("id");
        }
      } else {
        $uploaded_tag_id = $new_insert_tag_id;
      }

      $result = exec_sql_query(
        $db,
        "INSERT INTO recipe_tags (tag_id, recipe_id) VALUES (:newTag, :newRecipe);",
        array(
          ':newTag' => $uploaded_tag_id,
          ':newRecipe' => $uploaded_recipe_id
        )
      );

      if ($result) {

        $new_path = 'public/uploads/recipes/' . $uploaded_recipe_id . '.' . $upload_ext;

        move_uploaded_file($upload["tmp_name"], $new_path);
      }

      $show_confirmation_form = True;

      $db->commit();

    } else {
      // Setting the stick values for the form
      // The uploaded file is not sticky
      $file_feedback_class = '';

      $sticky_dish = $upload_dishName;
      $sticky_ingredient = $upload_dishIngredient;
      $sticky_instruction = $upload_dishInstruction;
      $sticky_source = $upload_dishSource;
      $sticky_tag = $upload_tag;
    }
  }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Adding a new recipe</title>

  <link rel="stylesheet" type="text/css" href="public/styles/site.css" media="all" />
</head>

<body>
  <!-- Need to include the header using php include -->
  <?php include("includes/header.php"); ?>

  <?php if ($show_confirmation_form == False) { ?>
    <div class="content_newRecipe">
      <div class="form_centering">
        <?php if (is_user_logged_in()) { ?>
        <h2>Upload a new recipe:</h2>
        <form action="/new-recipe" method="post" enctype="multipart/form-data" novalidate>
          <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo MAX_FILE_SIZE; ?>" />

          <div class="form_feedback <?php echo $file_feedback_class; ?>">
            <p>Please select an image for the recipe</p>
          </div>
          <div class="label_input_pair">
            <label for="upload_file">Image of Dish:</label>
            <input id="upload_file" type="file" name="image-file" accept="image/*" required />
          </div>

          <div class="form_feedback <?php echo $name_feedback_class; ?>">
            <p>Please provide a unique recipe name</p>
          </div>
          <div class="label_input_pair">
            <label for="add_recipe_name">Name of the recipe:</label>
            <input id="add_recipe_name" type="text" name="dish" value="<?php echo htmlspecialchars($sticky_dish); ?>" required />
          </div>

          <div class="form_feedback <?php echo $ingredient_feedback_class; ?>">
            <p>Please provide a list of ingredients for the recipe</p>
          </div>
          <div class="label_input_pair">
            <label for="add_recipe_ingredient">Ingredients for the recipe:</label>
            <textarea rows = "4" cols = "70" id="add_recipe_ingredient" name="ingredient" required><?php echo htmlspecialchars($sticky_ingredient); ?></textarea>
          </div>

          <div class="form_feedback <?php echo $instruction_feedback_class; ?>">
            <p>Please provide an instruction for the recipe</p>
          </div>
          <div class="label_input_pair">
            <label for="add_recipe_instruction">Instructions for the recipe:</label>
            <textarea rows = "15" cols = "70" id="add_recipe_instruction" name="instruction" required><?php echo htmlspecialchars($sticky_instruction); ?></textarea>
          </div>

          <div class="form_feedback <?php echo $tag_feedback_class; ?>">
            <p>Please provide a tag for this recipe</p>
          </div>
          <div class="label_input_pair">
            <label for="add_recipe_tag"> Add a New Tag:</label>
              <input id="add_recipe_tag" type="text" name="newTag" value="<?php echo htmlspecialchars($sticky_tag); ?>" required/>
          </div>

          <div class="label_input_pair">
            <label for="add_recipe_source">Source of this recipe(URL if applicable):</label>
            <input id="add_recipe_source" type="text" name="source" placeholder="URL of the information source. (optional)" value="<?php echo htmlspecialchars($sticky_source); ?>" required />
          </div>
          <div class="new_add">
            <button type="submit" name="add_recipe">Add Recipe</button>
          </div>
        </form>
        <?php } else {
          echo "<h1>";
          echo "Please login to authenticate your homecook status before creating a new recipe";
          echo "</h1>";
        }
        ?>
      </div>
    </div>
  <?php } ?>
  <?php if ($show_confirmation_form) { ?>
    <div class="confirmation_new_recipe">
      <h2>The new recipe was successfully added!</h2>
      <h2>Use the navigation bar to navigate back to the homepage or click on the following link: <a href="/">Homepage</a> </h2>
    </div>
  <?php } ?>

  <?php include("includes/footer.php"); ?>
</body>

</html>
