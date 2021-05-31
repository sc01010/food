<!-- The header of the website including the navigation bar -->
<header>
  <div class="main_name">
    <h1><a href="/">HomeFoods</a></h1>
  </div>

  <nav>
    <div class="links">
      <ul>
        <li class="<?php echo $nav_home_class; ?>"><a href="/">Homepage</a></li>
        <li class="<?php echo $nav_newRecipe_class; ?>"><a href="/new-recipe">Add New Recipe</a></li>
      </ul>
    </div>

    <div class="login_form">
      <?php if (!is_user_logged_in()) {
        echo_login_form($url, $session_messages);
      } ?>

      <?php if (is_user_logged_in()) { ?>
        <ul>
          <li id="nav_sign_out"><a href="<?php echo logout_url(); ?>">Sign Out</a></li>
        </ul>
      <?php } ?>
    </div>
  </nav>

</header>
