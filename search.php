<?php
get_header();
?>
<section class="max-w-5xl mx-auto px-6 py-10 space-y-6">
    <header>
        <p class="text-sm uppercase tracking-wide text-slate-500">Kết quả tìm kiếm</p>
        <h1 class="text-3xl font-semibold mt-2"><?php echo esc_html( get_search_query() ); ?></h1>
    </header>
    <?php if ( have_posts() ) : ?>
        <div class="space-y-4">
            <?php while ( have_posts() ) : the_post(); ?>
                <article class="border border-slate-200 bg-white rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wide text-slate-500 mb-2">Ebook</p>
                    <h2 class="text-2xl font-semibold"><a href="<?php the_permalink(); ?>" class="hover:text-indigo-600"><?php the_title(); ?></a></h2>
                    <p class="text-slate-600 mt-2"><?php echo wp_trim_words( get_the_excerpt(), 30 ); ?></p>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="mt-6">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p>Không tìm thấy nội dung phù hợp.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
