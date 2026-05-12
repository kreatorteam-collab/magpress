<?php
/**
 * Title: Rând categorie (6 articole)
 * Slug: magpress/category-row
 * Categories: featured, magpress
 * Description: Rând cu titlu categorie + 6 articole în grid 3 coloane. Chenare fine, culori din theme.json (override per site).
 * Keywords: categorie, grid, articole
 * Viewport Width: 1280
 *
 * IMPORTANT: După inserare pe homepage, editează în Site Editor:
 *  - Categoria afișată (Query → Filters → Taxonomies)
 *  - Titlul secțiunii (heading-ul de sus)
 *  - Numărul de articole (default 6)
 */
?>
<!-- wp:group {"className":"magpress-section","layout":{"type":"default"}} -->
<section class="wp-block-group magpress-section">

  <!-- wp:group {"className":"magpress-section-title","layout":{"type":"flex","flexWrap":"nowrap"}} -->
  <div class="wp-block-group magpress-section-title">
    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Titlu Categorie</h2>
    <!-- /wp:heading -->
    <!-- wp:html -->
    <span class="title-accent" aria-hidden="true"></span>
    <!-- /wp:html -->
  </div>
  <!-- /wp:group -->

  <!-- wp:query {"queryId":20,"query":{"perPage":6,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"magpress/category-row"} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"className":"magpress-grid","layout":{"type":"grid","columnCount":3}} -->
      <!-- wp:group {"className":"magpress-card magpress-card-bordered","layout":{"type":"constrained"}} -->
      <div class="wp-block-group magpress-card magpress-card-bordered">
        <!-- wp:group {"className":"card-image"} -->
        <div class="wp-block-group card-image">
          <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->
          <!-- wp:post-terms {"term":"category","className":"card-category"} /-->
        </div>
        <!-- /wp:group -->
        <!-- wp:group {"className":"card-body"} -->
        <div class="wp-block-group card-body">
          <!-- wp:post-title {"isLink":true,"level":3,"className":"card-title"} /-->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->

    <!-- wp:query-no-results -->
      <!-- wp:paragraph -->
      <p>Nu există articole în această categorie încă.</p>
      <!-- /wp:paragraph -->
    <!-- /wp:query-no-results -->
  </div>
  <!-- /wp:query -->

</section>
<!-- /wp:group -->
