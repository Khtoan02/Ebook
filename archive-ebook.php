<?php
get_header();
?>
<section class="max-w-6xl mx-auto px-6 py-12">
    <header class="mb-10">
        <p class="text-sm uppercase tracking-wide text-slate-500">Library</p>
        <h1 class="text-4xl font-semibold mt-2"><?php post_type_archive_title(); ?></h1>
        <?php if ( term_description() ) : ?>
            <p class="mt-4 text-slate-600"><?php echo wp_kses_post( term_description() ); ?></p>
        <?php endif; ?>
    </header>

    <?php if ( have_posts() ) : ?>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php while ( have_posts() ) : the_post(); ?>
                <article class="border border-slate-200 bg-white rounded-xl p-6 flex flex-col gap-3 shadow-sm">
                    <div class="text-xs uppercase tracking-wide text-slate-500">
                        <?php echo esc_html( get_post_meta( get_the_ID(), '_ebook_status', true ) ?: 'Draft' ); ?> · v<?php echo esc_html( get_post_meta( get_the_ID(), '_ebook_version', true ) ?: '1.0' ); ?>
                    </div>
                    <h2 class="text-2xl font-semibold">
                        <a href="<?php the_permalink(); ?>" class="hover:text-indigo-600">
                            <?php the_title(); ?>
                        </a>
                    </h2>
                    <p class="text-sm text-slate-600 line-clamp-3"><?php the_excerpt(); ?></p>
                    <div class="mt-auto flex items-center justify-between text-sm font-medium">
                        <span><?php echo count( get_children( [ 'post_parent' => get_the_ID(), 'post_type' => 'ebook' ] ) ); ?> chương</span>
                        <a class="text-indigo-600" href="<?php the_permalink(); ?>">Đọc ngay →</a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <div class="mt-10">
            <?php the_posts_pagination(); ?>
        </div>
    <?php else : ?>
        <p>Chưa có ebook nào.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
