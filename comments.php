<?php
/**
 * Comments — listare + form.
 */
if (!defined('ABSPATH')) { exit; }
if (post_password_required()) { return; }
?>
<div class="comments-area">

  <?php if (have_comments()): ?>
    <h2 class="comments-title">
      <?php
      $count = get_comments_number();
      printf(_n('%s comentariu', '%s comentarii', $count, 'magpress'), number_format_i18n($count));
      ?>
    </h2>

    <ol class="comments-list">
      <?php wp_list_comments(['style' => 'ol', 'short_ping' => true, 'avatar_size' => 48]); ?>
    </ol>

    <?php the_comments_pagination(['mid_size' => 2, 'class' => 'magpress-pagination']); ?>
  <?php endif; ?>

  <?php if (comments_open()):
    comment_form([
      'title_reply' => 'Lasă un comentariu',
      'label_submit' => 'Trimite',
      'class_submit' => 'btn',
    ]);
  endif; ?>

</div>
