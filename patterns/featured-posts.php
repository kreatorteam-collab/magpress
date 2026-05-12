<?php
/**
 * Title: Featured Posts (2 articole mari)
 * Slug: magpress/featured-posts
 * Categories: featured, magpress
 * Description: Două articole mari side-by-side cu imagine full + overlay gradient + titlu peste. Înlocuiește slider-ul tradițional.
 * Keywords: featured, hero, top
 * Viewport Width: 1400
 */
?>
<!-- wp:group {"className":"magpress-featured","layout":{"type":"default"}} -->
<div class="wp-block-group magpress-featured">

  <!-- wp:query {"queryId":10,"query":{"perPage":2,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":"only"},"namespace":"magpress/featured"} -->
  <div class="wp-block-query">
    <!-- wp:post-template -->
      <!-- wp:group {"className":"magpress-featured-card","layout":{"type":"default"}} -->
      <div class="wp-block-group magpress-featured-card">
        <!-- wp:post-featured-image {"isLink":true} /-->
        <!-- wp:group {"className":"featured-content","layout":{"type":"default"}} -->
        <div class="wp-block-group featured-content">
          <!-- wp:post-terms {"term":"category"} /-->
          <!-- wp:post-title {"isLink":true,"level":2} /-->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->

    <!-- wp:query-no-results -->
      <!-- wp:query {"queryId":11,"query":{"perPage":2,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"magpress/featured-fallback"} -->
      <div class="wp-block-query">
        <!-- wp:post-template -->
          <!-- wp:group {"className":"magpress-featured-card","layout":{"type":"default"}} -->
          <div class="wp-block-group magpress-featured-card">
            <!-- wp:post-featured-image {"isLink":true} /-->
            <!-- wp:group {"className":"featured-content"} -->
            <div class="wp-block-group featured-content">
              <!-- wp:post-terms {"term":"category"} /-->
              <!-- wp:post-title {"isLink":true,"level":2} /-->
            </div>
            <!-- /wp:group -->
          </div>
          <!-- /wp:group -->
        <!-- /wp:post-template -->
      </div>
      <!-- /wp:query -->
    <!-- /wp:query-no-results -->
  </div>
  <!-- /wp:query -->

</div>
<!-- /wp:group -->
