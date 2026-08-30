<?php
/**
 * Single article — design inspirat din povesteacasei.ro.
 */
if (!defined('ABSPATH')) { exit; }
get_header();
the_post();
$cats = get_the_category();
?>

<div class="container site-content">

  <header class="article-hero">
    <?php magpress_breadcrumbs(); ?>

    <?php if ($cats): ?>
      <a class="badge badge--pill <?php echo esc_attr(magpress_category_color_class($cats[0])); ?>" href="<?php echo esc_url(get_category_link($cats[0])); ?>"><?php echo esc_html($cats[0]->name); ?></a>
    <?php endif; ?>

    <h1 class="article-title"><?php the_title(); ?></h1>

    <?php if (has_excerpt()): ?>
      <p class="article-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
    <?php endif; ?>

    <?php echo magpress_render_article_meta(); // plain default; rich news byline dacă toggle ON ?>
  </header>

  <?php if (has_post_thumbnail()): ?>
    <figure class="article-image">
      <?php the_post_thumbnail('magpress-hero', ['loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async']); ?>
    </figure>
  <?php endif; ?>

  <?php get_template_part('template-parts/google-discover'); ?>

  <article class="article-body">
    <div class="entry-content">
      <?php the_content(); ?>
      <?php wp_link_pages(['before' => '<nav class="page-links">', 'after' => '</nav>']); ?>
    </div>
  </article>

  <?php echo magpress_get_end_article_ad(); ?>

  <?php /* Author box — vizibil daca autorul are bio in profil */
  $author_id = get_the_author_meta('ID');
  $author_bio = get_the_author_meta('description', $author_id);
  if (!empty($author_bio)): ?>
    <aside class="author-box">
      <div class="author-box__avatar">
        <?php echo get_avatar($author_id, 80, '', get_the_author_meta('display_name', $author_id)); ?>
      </div>
      <div class="author-box__body">
        <span class="author-box__label">DESPRE AUTOR</span>
        <h3 class="author-box__name"><a href="<?php echo esc_url(get_author_posts_url($author_id)); ?>"><?php echo esc_html(get_the_author_meta('display_name', $author_id)); ?></a></h3>
        <p class="author-box__bio"><?php echo esc_html($author_bio); ?></p>
      </div>
    </aside>
  <?php endif; ?>

  <?php echo magpress_render_post_tags(); // News feature toggle ?>

  <?php
  // Related — 4 articole din aceeasi categorie, excluzand pe asta.
  if ($cats):
    $cat_ids = wp_list_pluck($cats, 'term_id');
    // v0.9.208: ORDER BY RAND() pe zeci de mii de articole era cel mai scump query
    // al temei si rula la fiecare afisare de articol. Luam ultimele 12 din categorie
    // (query ieftin, indexat, cache-abil) si amestecam in PHP -> aceeasi varietate.
    $related = new WP_Query([
        'posts_per_page'      => 12,
        'category__in'        => $cat_ids,
        'post__not_in'        => [get_the_ID()],
        'ignore_sticky_posts' => 1,
        'no_found_rows'       => true,
    ]);
    if ($related->have_posts()):
      shuffle($related->posts);
      $related->posts      = array_slice($related->posts, 0, 4);
      $related->post_count = count($related->posts);
      ?>
      <section class="related-row">
        <?php
          set_query_var('section_title', 'Articole similare');
          set_query_var('section_url', get_category_link($cats[0]));
          get_template_part('template-parts/section-header');
        ?>
        <div class="card-grid">
          <?php while ($related->have_posts()): $related->the_post();
            get_template_part('template-parts/card');
          endwhile; ?>
        </div>
      </section>
    <?php endif; wp_reset_postdata();
  endif;
  ?>

  <?php echo magpress_render_most_read(5); // News feature toggle ?>

  <?php if (comments_open() || get_comments_number()): ?>
    <section class="article-comments">
      <?php comments_template(); ?>
    </section>
  <?php endif; ?>

</div>

<?php get_footer();
