<?php
get_header();

$ebooks = new WP_Query( [
    'post_type'      => 'ebook',
    'posts_per_page' => 12,
    'post_parent'    => 0,
    'orderby'        => 'menu_order',
    'order'          => 'ASC',
] );
?>
<section class="max-w-6xl mx-auto px-6 py-12 space-y-10">
    <header class="text-center space-y-4">
        <p class="text-sm uppercase tracking-wide text-indigo-600">Thư viện Ebook</p>
        <h1 class="text-4xl font-semibold">Khám phá các bộ ebook chất lượng</h1>
        <p class="text-slate-600 max-w-3xl mx-auto">Mỗi ebook được tổ chức dạng GitBook với phân chương rõ ràng, cập nhật liên tục và có thể đọc liền mạch bằng HTMX.</p>
    </header>

    <?php if ( $ebooks->have_posts() ) : ?>
        <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            <?php
            while ( $ebooks->have_posts() ) :
                $ebooks->the_post();
                $series = wp_get_post_terms( get_the_ID(), 'ebook-series', [ 'fields' => 'names' ] );
                $status = get_post_meta( get_the_ID(), '_ebook_status', true ) ?: 'Draft';
                $version = get_post_meta( get_the_ID(), '_ebook_version', true ) ?: '1.0';
                ?>
                <article class="border border-slate-200 bg-white rounded-2xl p-6 shadow-sm flex flex-col gap-4">
                    <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-slate-500">
                        <span><?php echo esc_html( ucfirst( $status ) ); ?></span>
                        <span class="w-1 h-1 rounded-full bg-slate-300"></span>
                        <span>v<?php echo esc_html( $version ); ?></span>
                    </div>
                    <h2 class="text-2xl font-semibold">
                        <a class="hover:text-indigo-600" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                    </h2>
                    <p class="text-slate-600 text-sm line-clamp-3"><?php echo wp_trim_words( get_the_excerpt(), 32 ); ?></p>
                    <?php if ( ! empty( $series ) ) : ?>
                        <p class="text-xs font-medium text-indigo-600">Danh mục: <?php echo esc_html( implode( ', ', $series ) ); ?></p>
                    <?php endif; ?>
                    <div class="mt-auto flex items-center justify-between text-sm font-medium">
                        <span class="text-slate-500"><?php echo count( get_children( [ 'post_parent' => get_the_ID(), 'post_type' => 'ebook' ] ) ); ?> chương</span>
                        <a class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 hover:border-indigo-200 hover:text-indigo-600" href="<?php the_permalink(); ?>">Đọc ngay <span aria-hidden="true">→</span></a>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
        <?php if ( $ebooks->max_num_pages > 1 ) : ?>
            <div class="text-center">
                <a href="<?php echo esc_url( get_post_type_archive_link( 'ebook' ) ); ?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold hover:border-indigo-200 hover:text-indigo-600">Xem thêm ebook</a>
            </div>
        <?php endif; ?>
    <?php else : ?>
        <p class="text-center text-slate-500">Chưa có ebook nào. Hãy thêm ebook mới trong khu vực quản trị.</p>
    <?php endif; ?>
</section>
<?php
wp_reset_postdata();
get_footer();
