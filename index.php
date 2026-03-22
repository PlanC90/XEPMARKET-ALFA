<?php
get_header();
?>

<main id="primary" class="site-main container">

    <?php if (have_posts()): ?>

        <?php if (is_home() && !is_front_page()): ?>
            <header>
                <h1 class="page-title screen-reader-text">
                    <?php single_post_title(); ?>
                </h1>
            </header>
        <?php endif; ?>

        <div class="post-grid">
            <?php
            while (have_posts()):
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('glass-card'); ?>>
                    <?php if (has_post_thumbnail()): ?>
                        <div class="post-thumbnail">
                            <a href="<?php the_permalink(); ?>">
                                <?php the_post_thumbnail('medium'); ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <div class="post-content">
                        <header class="entry-header">
                            <?php the_title('<h2 class="entry-title"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>'); ?>
                        </header>

                        <div class="entry-summary">
                            <?php the_excerpt(); ?>
                        </div>
                    </div>
                </article>
                <?php
            endwhile;
            ?>
        </div>

        <?php
        the_posts_navigation();

    else:
        ?>
        <section class="no-results not-found">
            <header class="page-header">
                <h1 class="page-title">
                    <?php esc_html_e('Nothing Found', 'xepmarket2'); ?>
                </h1>
            </header>
            <div class="page-content">
                <p>
                    <?php esc_html_e('It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'xepmarket2'); ?>
                </p>
                <?php get_search_form(); ?>
            </div>
        </section>
        <?php
    endif;
    ?>

</main>

<?php
get_footer();
