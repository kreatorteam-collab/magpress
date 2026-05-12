<?php
/**
 * Title: Rând categorie (8 articole)
 * Slug: magpress/category-row
 * Categories: featured, magpress
 * Description: Rând cu icon colorat + titlu categorie + "Vezi toate" la dreapta + 8 articole în grid 4 coloane.
 * Keywords: categorie, grid, articole, baie, dormitor
 * Viewport Width: 1400
 *
 * IMPORTANT: După inserare pe homepage, în Site Editor:
 *  - Schimbă heading-ul cu numele categoriei
 *  - Modifică Query → Filters → Taxonomies → alege categoria
 *  - Actualizează href-ul pentru "Vezi toate" cu URL-ul categoriei
 */
?>
<!-- wp:group {"className":"magpress-section","layout":{"type":"default"}} -->
<section class="wp-block-group magpress-section">

  <!-- wp:group {"className":"magpress-section-title","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
  <div class="wp-block-group magpress-section-title">
    <!-- wp:html -->
    <span class="title-icon" aria-hidden="true"></span>
    <!-- /wp:html -->

    <!-- wp:heading {"level":2} -->
    <h2 class="wp-block-heading">Titlu Categorie</h2>
    <!-- /wp:heading -->

    <!-- wp:html -->
    <a class="section-link" href="#">Vezi toate</a>
    <!-- /wp:html -->
  </div>
  <!-- /wp:group -->

  <!-- wp:query {"queryId":20,"query":{"perPage":8,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"magpress/category-row"} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"className":"magpress-grid","layout":{"type":"grid","columnCount":4}} -->
      <!-- wp:group {"className":"magpress-card","layout":{"type":"constrained"}} -->
      <div class="wp-block-group magpress-card">
        <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"3/2"} /-->
        <!-- wp:group {"className":"card-body","layout":{"type":"default"}} -->
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
