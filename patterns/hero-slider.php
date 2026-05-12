<?php
/**
 * Title: Hero Slider
 * Slug: magpress/hero-slider
 * Categories: featured, magpress
 * Description: Slider hero CSS-only cu ultimele 5 articole — zero JavaScript, scroll-snap nativ.
 * Keywords: hero, slider, featured
 * Block Types: core/template-part/header
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"className":"magpress-hero","layout":{"type":"default"}} -->
<div class="wp-block-group magpress-hero">

  <!-- wp:query {"queryId":10,"query":{"perPage":5,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":"only"},"namespace":"magpress/hero"} -->
  <div class="wp-block-query">
    <!-- wp:post-template {"className":"magpress-hero-track"} -->
      <!-- wp:group {"className":"magpress-hero-slide","layout":{"type":"default"}} -->
      <div class="wp-block-group magpress-hero-slide">
        <!-- wp:group {"className":"hero-image","layout":{"type":"default"}} -->
        <div class="wp-block-group hero-image">
          <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
        </div>
        <!-- /wp:group -->

        <!-- wp:group {"className":"hero-content","layout":{"type":"constrained"}} -->
        <div class="wp-block-group hero-content">
          <!-- wp:post-terms {"term":"category","className":"hero-category"} /-->
          <!-- wp:post-title {"isLink":true,"level":2,"className":"hero-title"} /-->
        </div>
        <!-- /wp:group -->
      </div>
      <!-- /wp:group -->
    <!-- /wp:post-template -->

    <!-- wp:query-no-results -->
      <!-- wp:query {"queryId":11,"query":{"perPage":5,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false},"namespace":"magpress/hero-fallback"} -->
      <div class="wp-block-query">
        <!-- wp:post-template {"className":"magpress-hero-track"} -->
          <!-- wp:group {"className":"magpress-hero-slide","layout":{"type":"default"}} -->
          <div class="wp-block-group magpress-hero-slide">
            <!-- wp:group {"className":"hero-image"} -->
            <div class="wp-block-group hero-image">
              <!-- wp:post-featured-image {"isLink":true,"aspectRatio":"16/9"} /-->
            </div>
            <!-- /wp:group -->
            <!-- wp:group {"className":"hero-content","layout":{"type":"constrained"}} -->
            <div class="wp-block-group hero-content">
              <!-- wp:post-terms {"term":"category","className":"hero-category"} /-->
              <!-- wp:post-title {"isLink":true,"level":2,"className":"hero-title"} /-->
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
