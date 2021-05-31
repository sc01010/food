<?php
include("includes/init.php");
$nav_home_class = "current_active_page";

// The recipe that was selected by the user
$recipe_id= (int)trim($_GET['id']);
$url= "/recipe" . http_build_query(array('id' => $recipe_id));

// feedback message CSS classes
$name_feedback_class = 'hidden';
$ingredient_feedback_class = 'hidden';
$instruction_feedback_class = 'hidden';
$add_tag_feedback_class = 'hidden';
$delte_tag_feedback_class = 'hidden';

// is the current user allowed to edit?
$edit_permission= False;

// showing edit form?
$reveal_edit_form= False;

$image_centered = '';


// was the edit recipe button clicked?
if (isset($_GET['changeRecipe'])) {
  $reveal_edit_form= True;

  $recipe_id= (int)trim($_GET['changeRecipe']);

  $image_centered = "centered";
}


// Getting the document record
if ($recipe_id) {
  $records = exec_sql_query(
    $db,
    "SELECT * FROM recipes INNER JOIN recipe_tags ON recipes.id=recipe_tags.recipe_id INNER JOIN tags ON tags.id=recipe_tags.tag_id WHERE recipe_id = :id;",
    array(':id' => $recipe_id)
    )->fetchALL();

  if (count($records) > 0) {
    $recipe= $records[0];
  } else {
    $recipe= NULL;
  }
}

// adding a new tag for the recipe
$db->beginTransaction();
if (isset($_POST["add"])) {

  $add_new_tag = trim($_POST['newTag']);
  $repetition_tag_feedback_message = False;
  $form_valid = True;
  // $recipe_id= (int)trim($_GET['changeRecipe']);

  $results = exec_sql_query(
    $db,
    "SELECT id, label FROM tags WHERE label = :tagName;",
    array(':tagName' => $add_new_tag)
    )->fetchALL();

  $tagCount = count($results);
  $new_insert_tag_id = $results[0]['id'];

  $rows = exec_sql_query(
    $db,
    "SELECT * FROM recipes INNER JOIN recipe_tags ON recipes.id=recipe_tags.recipe_id INNER JOIN tags ON tags.id=recipe_tags.tag_id WHERE recipe_id = :id AND label = :labelName;",
    array(
      ':id' => $recipe_id,
      ':labelName' => $add_new_tag
    )
  )->fetchAll();

  $rowCounting = count($rows);

  if ($rowCounting > 0 || $add_new_tag == '') {
    $form_valid = False;
    $repetition_tag_feedback_message = True;
  }

  if ($tagCount < 1) {
    if (!empty($add_new_tag) && $form_valid == True) {
      $result = exec_sql_query(
        $db,
        "INSERT INTO tags (label) VALUES (:tag);",
        array(
          ':tag' => $add_new_tag //tainted
        )
      );
      $inserted_tag_id = $db->lastInsertId("id");
    }
  } else {
    $inserted_tag_id = $new_insert_tag_id;
  }

  // $inserted_tag_id = 1;
    // $inserted_tag_id = $db->lastInsertId("id");
  if (!empty($add_new_tag) && $form_valid == True) {
    $result = exec_sql_query(
      $db,
      "INSERT INTO recipe_tags (tag_id, recipe_id) VALUES (:tagId, :recipeId);",
      array(
        ':tagId' => $inserted_tag_id, //tainted
        ':recipeId' => $recipe_id
      )
    );
  }
}

$db->commit();

$delete_error_message = False;

// keeping track of the name of the recipe when deleting and redirecting back to the homepage
$recipeName = trim($recipe['dish']);

$db->beginTransaction();

$rows = exec_sql_query(
  $db,
  "SELECT * FROM recipe_tags WHERE recipe_id = :id;",
  array(
    ':id' => $recipe_id
  )
)->fetchAll();
$rowCount = count($rows);
if ($_GET['action'] == 'delete_tag' && $rowCount > 1) {
  $tagID = trim($_GET['tag_id']);

  if (is_user_logged_in() && $current_user['id'] == $recipe['user_id']) {
    $result = exec_sql_query(
    $db,
    "DELETE FROM recipe_tags WHERE tag_id = :tagId AND recipe_id = :recipeId;",
    array(
      ':tagId' => $tagID, //tainted
      ':recipeId' => $recipe_id
    )
  );
  }
} else if ($_GET['action'] == 'delete_tag' && count($record['label']) < 1) {
  $delete_error_message = True;
}

$db->commit();

$delete_recipe_error_message = False;
$record_deleted = False;

$db->beginTransaction();
if (isset($_POST['delete_this_recipe']) && is_user_logged_in() && $current_user['id'] == $recipe['user_id']) {
  $tagID = trim($_GET['tag_id']);

  if (is_user_logged_in() && $current_user['id'] == $recipe['user_id']) {
    $result = exec_sql_query(
      $db,
      "DELETE FROM recipe_tags WHERE recipe_id = :recipeId;",
      array(
        ':recipeId' => $recipe_id
      )
    );

    $result = exec_sql_query(
      $db,
      "DELETE FROM recipes WHERE dish = :dishName",
      array(
        ':dishName' => $recipe['dish'], //tainted
      )
    );

    $recipeFilePath = 'public/uploads/recipes/' . $recipe['recipe_id'] . '.' . $recipe['file_ext'];

    // removing the file from the uploads folder
    unlink($recipeFilePath);

    $record_deleted = True;
  } else {
    $delete_recipe_error_message = True;
  }
}

$db->commit();

// Getting the document record
if ($recipe_id) {
  $records = exec_sql_query(
    $db,
    "SELECT * FROM recipes INNER JOIN recipe_tags ON recipes.id=recipe_tags.recipe_id INNER JOIN tags ON tags.id=recipe_tags.tag_id WHERE recipe_id = :id;",
    array(':id' => $recipe_id)
    )->fetchALL();

  if (count($records) > 0) {
    $recipe= $records[0];
  } else {
    $recipe= NULL;
  }
}

// edit form feedback messages CSS class
$recipe_name_feedback = 'hidden';
$recipe_ingredient_feedback = 'hidden';
$recipe_instruction_feedback = 'hidden';
$change_default_edit_form_answers = False;

// When there is a valid recipe
if ($recipe) {

  // Checking that the owner of the recipe is the user that is logged in
  if (is_user_logged_in() && $current_user['id'] == $recipe['user_id']) {
  $edit_permission= True;
  }

  // Making sure that the user need to have logged in and valid credentials
  if (!is_user_logged_in() || $current_user['id'] != $recipe['user_id']) {
  $edit_permission= False;
  $reveal_edit_form= False;
  }

  $db->beginTransaction();

  // initializing the sticky variables
  $sticky_recipe_name = '';
  $sticky_recipe_ingredient = '';
  $sticky_recipe_instruction = '';

  // When the owner of the recipes saves changes
  if (isset($_POST['saveChanges'])) {
    $dishName= trim($_POST['dish']); // un-trusted
    $ingredient= trim($_POST['ingredient']); // un-trusted
    $instruction= trim($_POST['instruction']); //un-trusted

    // set sticky values to values just received
    $sticky_recipe_name = $dishName;
    $sticky_recipe_ingredient = $ingredient;
    $sticky_recipe_instruction = $instruction;

    // making sure that the recipe name is unique
    $rows = exec_sql_query (
      $db,
      "SELECT * FROM recipes WHERE (dish=:recipeName);",
      array(
        ':recipeName' => $dishName
      )
    )->fetchAll();
    $numOccurancesRecipeName = count($rows);
    $currentValidName = $recipe['dish'];

    // if the dish name input is not empty, update it
    if (!empty($dishName) && !empty($ingredient) && !empty($instruction) && ($dishName == $currentValidName || $numOccurancesRecipeName == 0)) {
      exec_sql_query(
      $db,
      "UPDATE recipes SET dish = :dishName, ingredient = :recipeIngredient, instruction = :recipeInstruction WHERE (id = :recipeID);",
      array(
        'dishName' => $dishName,
        'recipeIngredient' => $ingredient,
        'recipeInstruction' => $instruction,
        'recipeID' => $recipe_id
      )
    );

    // retrieving the updated recipe
    $records = exec_sql_query(
      $db,
      "SELECT * FROM recipes INNER JOIN recipe_tags ON recipes.id=recipe_tags.recipe_id INNER JOIN tags ON tags.id=recipe_tags.tag_id WHERE recipe_id = :id;",
      array(':id' => $recipe_id)
      )->fetchALL();
      $recipe= $records[0];
    } else {
      $recipe_name_feedback = '';
      $recipe_ingredient_feedback = '';
      $recipe_instruction_feedback = '';
      $change_default_edit_form_answers = True;
    }
  }
  $db->commit();

  // Information about the recipe
  $dishName = htmlspecialchars($recipe['filename']);
  $url = "/recipe?" . http_build_query(array('id' => $recipe['recipe_id']));
  $changeRecipe_url = "/recipe?" . http_build_query(array('changeRecipe' => $recipe['recipe_id']));

  if ($change_default_edit_form_answers == False) {
    $sticky_recipe_name = $recipe['dish'];
    $sticky_recipe_ingredient = $recipe['ingredient'];
    $sticky_recipe_instruction = $recipe['instruction'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <title>Lunch Recipes</title>

  <link rel="stylesheet" type="text/css" href="public/styles/site.css" media="all" />


</head>

<body>
  <?php include("includes/header.php"); ?>

  <?php if ($record_deleted) { ?>
    <div class='successful_delete'>
      <h2>This recipe was successfully deleted!</h2>
      <h2>Use the navigation bar to navigate back to the homepage or click on the following link: <a href="/">Homepage</a> </h2>
    </div>
  <?php } ?>

  <div class="content">
    <main class="specificRecipe">
      <section>
        <?php if ($recipe) { ?>
          <div class="top_half">
            <div class="image_and_citation <?php if ($reveal_edit_form) echo $image_centered; ?>">
              <div class="only_img_and_cite">
                <div class="specificImage">
                  <img src="/public/uploads/recipes/<?php echo $recipe['recipe_id'] . '.' . $recipe['file_ext']; ?>" alt="<?php echo htmlspecialchars($recipe['dish']); ?>" />
                </div>
                <div class="citation">
                  <cite>
                    <a href="<?php echo htmlspecialchars($recipe['source']); ?>">Image Source</a>
                  </cite>
                </div>
              </div>
              <div class="changing_recipe_a_element">
                <?php if ($edit_permission && $reveal_edit_form == False) {?>
                  <h2>
                    Click to edit recipe:
                      (<a href="<?php echo $changeRecipe_url; ?>">Edit Recipe</a>)
                  </h2>
                <?php } ?>
              </div>
              <div class="recipe_delete">
                <?php if ($_GET['changeRecipe'] == NULL) { ?>
                  <?php if ($edit_permission) { ?>
                    <h2>
                    Click to delete recipe:
                    (<a href="<?php echo $changeRecipe_url; ?>">Delete Recipe</a>)
                    </h2>
                  <?php } ?>
                <?php } ?>
              </div>
            </div>
            <?php if ($reveal_edit_form == False) { ?>
              <div class="recipe_information">
              <?php if ($repetition_tag_feedback_message) { ?>
                <div class="error_recipe_edit_feedback">
                  <h4>Sorry, an error occurred in the process of adding the new tag. Please make sure that the tag field is not empty and that the tag does not already exist for this recipe. Please try again and thank you for your patience.</h4>
                </div>
              <?php } ?>
              <?php if ($change_default_edit_form_answers) { ?>
                <div class="error_recipe_edit_feedback">
                  <h4>Sorry, there was an error upon the submission of your edited recipe. Please make sure that the recipe name is unique, and the ingredients and instructions for this recipe is not left blank. Please try to edit the recipe again, thank you for your patience.</h4>
                </div>
              <?php } ?>
                <div class="recipe_title">
                  <div class="recipe_name">
                    <h2>
                      <?php echo htmlspecialchars(ucfirst($recipe['dish'])); ?>
                    </h2>
                  </div>
                </div>
                <div class="recipe_label_pair">
                  <div class="recipe_label">
                    <h3>
                      <?php echo "Ingredients for recipe: "; ?>
                    </h3>
                  </div>
                  <div class="recipe_content">
                    <h3>
                      <?php echo htmlspecialchars($recipe['ingredient']); ?>
                    </h3>
                  </div>
                </div>
                <div class="recipe_label_pair">
                  <div class="recipe_label">
                    <h3>
                      <?php echo "Instructions for recipe: "; ?>
                    </h3>
                  </div>
                  <div class="recipe_content dense_text">
                    <h3>
                      <?php echo htmlspecialchars($recipe['instruction']); ?>
                    </h3>
                  </div>
                </div>
                <div class="recipe_label_pair">
                  <div class="recipe_label">
                    <h3>
                      <?php echo "Tags:"; ?>
                    </h3>
                  </div>
                  <div class="recipe_content">
                    <h3>
                    <?php foreach ($records as $record) {
                        echo '#' . nl2br(htmlspecialchars($record['label']) . "\r\n");
                    } ?>
                    </h3>
                  </div>
                </div>
                <div class="recipe_label_pair">
                  <div class="recipe_label">
                    <h3>
                      <?php echo "Source of the recipe: "; ?>
                    </h3>
                  </div>
                  <div class="recipe_content">
                    <h3>
                      <?php if ($recipe['source'] != NULL && $recipe['source'] != '') { ?>
                        <cite>
                          <a href="<?php echo htmlspecialchars($recipe['source']); ?>"><?php echo htmlspecialchars($recipe['source']); ?></a>
                        </cite>
                      <?php } ?>

                      <?php if ($recipe['source'] == NULL || $recipe['source'] == '') {
                        echo "No Source";
                      } ?>
                    </h3>
                  </div>
                </div>
              </div>
          <?php } ?>
          </div>

          <?php if ($delete_recipe_error_message) {
            echo
            "<div class='error general_error'>Sorry, there was an error with deleting this recipe!</div>";
          } ?>

          <?php if ($reveal_edit_form) { ?>
            <div class="lower_half">
              <div class="delete_confirm">
                <div class="delete_confirm_part_one">
                  <h2>Delete Recipe - This action is permanent and cannot be undone:
                  </h2>
                </div>
                <?php if ($reveal_edit_form && is_user_logged_in() && $current_user['id'] == $recipe['user_id']) { ?>
                  <div class="delete_confirm_part_two">
                    <h2>
                      <form action='<?php echo $url; ?>' method='post' novalidate>
                        <button id="perm_delete_button" type='submit' name='delete_this_recipe'>Delete Recipe</button>
                      </form>
                    </h2>
                  </div>
                <?php }?>
              </div>

            <div class=edit_form>
              <form action="<?php echo $url; ?>" method="post" novalidate>
                <div class="label_input_pair">
                  <label for="dish"> Recipe Name:</label>
                      <input id="dish" type="text" name="dish" value="<?php echo htmlspecialchars($sticky_recipe_name); ?>" required />
                </div>

                <div class="label_input_pair">
                  <label for="ingredient"> Recipe ingredient:</label>
                      <textarea id="ingredient" rows = "4" cols = "110" name="ingredient" required><?php echo htmlspecialchars($sticky_recipe_ingredient); ?></textarea>
                </div>

                <div class="label_input_pair">
                  <label for="instruction"> Recipe instruction:</label>
                      <textarea id="instruction" rows = "15" cols = "110" name="instruction" required><?php echo htmlspecialchars($sticky_recipe_instruction); ?> </textarea>
                </div>
                <div class="send">
                  <button type="submit" name="saveChanges">Save Changes</button>
                </div>
              </form>
            </div>

            <!-- Deleting the tags -->
            <?php if ($delete_error_message) {
              echo
              "<div class='error general_error'>Each recipe must have at least one tag. Please make sure that you have at least 2 tags before deleting a tag.</div>";
            } ?>
            <div class="tag_content_box">
              <div class="tag_align">
                <p>Existing Tags:</p>
              </div>
              <div class="tag_content">
                <?php foreach ($records as $record) { ?>
                  <?php
                    echo '#' . htmlspecialchars($record['label']);
                    echo "<a href=" . $changeRecipe_url . '&action=delete_tag&' . 'recipe_id=' . htmlspecialchars($record['recipe_id']) . '&tag_id=' . htmlspecialchars($record['tag_id']) . ">(Delete Tag)</a>";
                    echo nl2br("\r\n");
                  ?>
                <?php } ?>
                </div>
              </div>

            <!-- adding new tag -->
            <!-- <div class=add_new_form> -->
              <form action="<?php echo $url; ?>" method="post" novalidate>
                <div class=add_new_form>
                  <div class="label_input_pair">
                    <label for="newTag"> Add a New Tag (don't include "#"): </label>
                    <input id="newTag" type="text" name="newTag" />
                  </div>
                  <div class="same_line">
                    <button type="submit" name="add">Add New Tag</button>
                  </div>
                </div>
              </form>
            <!-- </div> -->
            <div class="citations">
              <div class="cite_label">
                <h4> Source: </h4>
              </div>
              <div class="cite_source">
                <h4>
                  <?php if ($recipe['source'] != NULL && $recipe['source'] != '') { ?>
                    <cite>
                      <a href="<?php echo htmlspecialchars($recipe['source']); ?>"><?php echo htmlspecialchars($recipe['source']); ?></a>
                    </cite>
                  <?php } ?>

                  <?php if ($recipe['source'] == NULL || $recipe['source'] == '') {
                    echo "No Source";
                  } ?>
                </h4>
              </div>
            </div>
          </div>
          <?php } ?>
        <?php } ?>
      </section>
    </main>
  </div>
  <?php include("includes/footer.php"); ?>
</body>

</html>
