<?php
/**
 * Main template file
 */

get_header(); ?>

<main class="container mx-auto px-4 py-8">
    <?php if (have_posts()) : ?>
        <div class="grid gap-8">
            <?php while (have_posts()) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('bg-white shadow-md rounded-lg p-6'); ?>>
                    <h2 class="text-3xl font-bold mb-4">
                        <a href="<?php the_permalink(); ?>" class="text-blue-600 hover:text-blue-800">
                            <?php the_title(); ?>
                        </a>
                    </h2>
                    <div class="text-gray-600 mb-4">
                        <?php the_date(); ?> par <?php the_author(); ?>
                    </div>
                    <div class="prose max-w-none">
                        <?php the_excerpt(); ?>
                    </div>
                    <a href="<?php the_permalink(); ?>" class="inline-block mt-4 text-blue-600 hover:text-blue-800">
                        Lire la suite &rarr;
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <div class="mt-8">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <div class="text-center py-12">
            <h2 class="text-2xl font-bold mb-4">Aucun contenu trouvé</h2>
            <p class="text-gray-600">Désolé, aucun contenu ne correspond à votre recherche.</p>
        </div>
    <?php endif; ?>
</main>

<?php get_footer(); ?>
