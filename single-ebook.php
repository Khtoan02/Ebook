<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $post;

$root_id       = ebook_get_root_post_id( $post->ID );
$tree          = ebook_get_children_tree( $root_id );
$root_post     = get_post( $root_id ) ?: $post;
$download_link = get_post_meta( $root_id, '_ebook_download', true );
$status        = get_post_meta( $root_id, '_ebook_status', true ) ?: 'draft';
$version       = get_post_meta( $root_id, '_ebook_version', true ) ?: '1.0';
$ancestors     = array_reverse( get_post_ancestors( get_the_ID() ) );

$breadcrumb_items = [
    [
        'label' => __( 'Thư viện', 'ebook-gitbook' ),
        'url'   => home_url( '/' ),
    ],
];

foreach ( $ancestors as $ancestor_id ) {
    $breadcrumb_items[] = [
        'label' => ebook_get_plain_title( $ancestor_id ),
        'url'   => get_permalink( $ancestor_id ),
    ];
}

$breadcrumb_items[] = [
    'label' => ebook_get_plain_title( get_the_ID() ),
    'url'   => '',
];

$prev_id = ebook_get_adjacent_page_id( get_the_ID(), 'prev' );
$next_id = ebook_get_adjacent_page_id( get_the_ID(), 'next' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> lang="vi">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo esc_html( ebook_get_plain_title( $root_post ) ); ?> - <?php echo esc_html( ebook_get_plain_title( get_the_ID() ) ); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php wp_head(); ?>
    <style>
        body { font-family: 'Inter', system-ui, sans-serif; color: #334155; scroll-behavior: smooth; }
        .no-scrollbar::-webkit-scrollbar { width: 6px; }
        .no-scrollbar::-webkit-scrollbar-thumb { background-color: #f1f5f9; border-radius: 3px; }
        .no-scrollbar::-webkit-scrollbar-track { background-color: transparent; }

        /* TYPOGRAPHY CHUNG CHO EBOOK */
        .ebook-content h1 { font-size: 2.25rem; font-weight: 800; margin-bottom: 1.5rem; letter-spacing: -0.025em; color: #0f172a; line-height: 1.2; }
        .ebook-content h2 { font-size: 1.5rem; font-weight: 700; margin-top: 3rem; margin-bottom: 1.25rem; padding-bottom: 0.5rem; border-bottom: 1px solid #e2e8f0; scroll-margin-top: 5rem; color: #1e293b; }
        .ebook-content h3 { font-size: 1.25rem; font-weight: 600; margin-top: 2rem; margin-bottom: 0.75rem; scroll-margin-top: 5rem; color: #334155; }
        .ebook-content p { margin-bottom: 1.25rem; line-height: 1.75; color: #475569; }
        .ebook-content ul.default-list,
        .ebook-content .wp-block-list { list-style-type: disc; padding-left: 1.5rem; margin-bottom: 1.25rem; }

        /* ẢNH / HÌNH MINH HỌA */
        .ebook-content figure,
        .ebook-content .wp-block-image { margin: 2rem 0; text-align: center; }
        .ebook-content figure img,
        .ebook-content .wp-block-image img { border-radius: 0.75rem; box-shadow: 0 20px 25px -20px rgba(15,23,42,0.35); max-width: 100%; height: auto; }
        .ebook-content figure figcaption,
        .ebook-content .wp-block-image figcaption { margin-top: 0.75rem; font-size: 0.875rem; color: #64748b; }

        /* CHECKLIST – chỉ cần thêm class .checklist vào <ul> */
        .ebook-content ul.checklist { list-style: none; padding-left: 0; margin: 1.5rem 0; }
        .ebook-content ul.checklist li { display: flex; align-items: flex-start; margin-bottom: 0.75rem; font-size: 0.95rem; color: #475569; }
        .ebook-content ul.checklist li .check-icon {
            width: 20px; height: 20px; border-radius: 999px; border: 1px solid #cbd5f5;
            margin-right: 0.75rem; margin-top: 0.15rem; display: flex; align-items: center; justify-content: center;
            background: #0ea5e9; color: #fff; font-size: 0.7rem;
        }
        .ebook-content ul.checklist li span { flex: 1; }

        /* BẢNG DỮ LIỆU – áp cho .custom-table hoặc Gutenberg table */
        .custom-table,
        .ebook-content .wp-block-table table { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; }
        .custom-table th,
        .ebook-content .wp-block-table th { background-color: #f8fafc; font-weight: 600; text-align: left; padding: 12px 16px; color: #475569; border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; letter-spacing: 0.06em; text-transform: uppercase; }
        .custom-table td,
        .ebook-content .wp-block-table td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; color: #475569; vertical-align: top; font-size: 0.95rem; }
        .custom-table tr:last-child td,
        .ebook-content .wp-block-table tr:last-child td { border-bottom: none; }
        .custom-table tr:hover,
        .ebook-content .wp-block-table tr:hover { background-color: #fcfcfc; }

        /* QUY TRÌNH / TIMELINE – container: .ebook-timeline, từng bước: .timeline-step */
        .ebook-timeline { position: relative; border-left: 2px solid #e2e8f0; margin-left: 1rem; padding-left: 1.5rem; margin-top: 2.5rem; margin-bottom: 2.5rem; }
        .ebook-timeline .timeline-step { position: relative; padding-left: 1.5rem; margin-bottom: 1.75rem; }
        .ebook-timeline .timeline-step::before {
            content: ''; position: absolute; left: -1.1rem; top: 0.35rem;
            width: 18px; height: 18px; border-radius: 999px;
            background: #fff; border: 3px solid #0ea5e9; box-shadow: 0 4px 10px rgba(15,23,42,0.15);
        }
        .ebook-timeline .timeline-step-title { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem; }
        .ebook-timeline .timeline-step-body { font-size: 0.9rem; color: #64748b; }

        /* TABS – nhóm tab: .ebook-tabs, header: .ebook-tabs-nav, nội dung: .ebook-tab-pane */
        .ebook-tabs { margin: 2.5rem 0; }
        .ebook-tabs-nav { display: inline-flex; padding: 0.25rem; border-radius: 0.75rem; background-color: rgba(148,163,184,0.1); margin-bottom: 1rem; }
        .ebook-tabs-nav button {
            position: relative; padding: 0.5rem 1rem; font-size: 0.875rem;
            border-radius: 0.6rem; border: none; background: transparent;
            color: #64748b; cursor: pointer; transition: all 0.18s ease-in-out;
        }
        .ebook-tabs-nav button.is-active {
            background: #ffffff; color: #0369a1; font-weight: 600;
            box-shadow: 0 5px 12px -5px rgba(15,23,42,0.25);
        }
        .ebook-tab-pane { border: 1px solid #e2e8f0; border-radius: 0.9rem; padding: 1.5rem; background: #ffffff; box-shadow: 0 18px 40px -26px rgba(15,23,42,0.45); }

        /* ACCORDION (details/summary) */
        details > summary { list-style: none; cursor: pointer; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] summary ~ * { animation: sweep .2s ease-in-out; }
        @keyframes sweep { 0% {opacity: 0; transform: translateY(-5px)} 100% {opacity: 1; transform: translateY(0)} }

        /* SIDEBAR CÂY EBOOK */
        .active-chapter { background-color: #ffffff; color: #0284c7; font-weight: 600; border: 1px solid #e2e8f0; border-right: none; border-left: 3px solid #0ea5e9; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
        .sidebar-item { border: 1px solid transparent; }
        .sidebar-item:hover { background-color: #f8fafc; color: #0f172a; }

        .ebook-tree a { display: block; padding: 6px 12px; border-radius: 6px; font-size: 0.875rem; color: #475569; text-decoration: none; }
        .ebook-tree a:hover { background-color: #f8fafc; color: #0f172a; }
        .ebook-tree__item.is-active > a { background-color: #f0f9ff; color: #0284c7; font-weight: 600; border-left: 3px solid #0ea5e9; margin-left: -3px; padding-left: 13px; }

        /* RESPONSIVE TWEAKS */
        @media (max-width: 1023px) {
            .ebook-content h1 { font-size: 1.9rem; }
            .ebook-content h2 { font-size: 1.3rem; }
            .ebook-content h3 { font-size: 1.1rem; }
        }
        @media (max-width: 767px) {
            .ebook-content { padding-bottom: 4.5rem; }
        }
    </style>
</head>
<body <?php body_class( 'bg-white h-screen flex flex-col overflow-hidden text-slate-600' ); ?> >
<?php while ( have_posts() ) : the_post(); ?>
    <!-- HEADER MOBILE -->
    <header class="md:hidden flex items-center justify-between p-4 border-b border-gray-200 bg-white z-40 sticky top-0">
        <div class="font-bold text-lg text-slate-800 truncate max-w-[200px]">
                    <?php echo esc_html( ebook_get_plain_title( $root_post ) ); ?>
        </div>
        <button id="mobile-menu-btn" class="text-slate-500 focus:outline-none p-2 rounded-md hover:bg-gray-50 transition-colors">
            <i class="fas fa-bars text-xl"></i>
        </button>
    </header>

    <div class="flex flex-1 overflow-hidden relative w-full max-w-[1920px] mx-auto">
        <!-- SIDEBAR TRÁI -->
        <aside id="sidebar-left" class="w-72 bg-slate-50/80 backdrop-blur-sm border-r border-gray-200 flex-shrink-0 flex flex-col hidden md:flex absolute md:static inset-y-0 left-0 z-30 transform transition-transform duration-300 h-full no-scrollbar">
            <div class="p-5 border-b border-gray-200 bg-white/50 sticky top-0 z-10 backdrop-blur">
                <a href="<?php echo esc_url( get_permalink( $root_post ) ); ?>" class="flex items-center space-x-3 text-slate-800 hover:text-sky-600 transition-colors group">
                    <div class="w-9 h-9 bg-white border border-gray-200 rounded-lg flex items-center justify-center text-sky-600 shadow-sm group-hover:border-sky-200 group-hover:shadow-md transition-all">
                        <i class="fas fa-book-open"></i>
                    </div>
                    <span class="font-bold text-sm uppercase tracking-wide leading-tight line-clamp-2"><?php echo esc_html( ebook_get_plain_title( $root_post ) ); ?></span>
                </a>
            </div>

            <div class="overflow-y-auto flex-1 px-3 py-4 no-scrollbar space-y-0.5 ebook-tree">
                <?php
                ebook_render_navigation_tree(
                    [
                        [
                            'post'     => $root_post,
                            'children' => $tree,
                        ],
                    ],
                    get_the_ID()
                );
                ?>
            </div>

            <div class="p-4 border-t border-gray-200 text-xs text-slate-400 text-center bg-white/50">
                &copy; <?php echo esc_html( date_i18n( 'Y' ) ); ?> Ebook v<?php echo esc_html( $version ); ?>
            </div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="flex-1 overflow-y-auto no-scrollbar scroll-smooth relative bg-white" id="main-content">
            <div class="w-full max-w-7xl mx-auto px-4 sm:px-8 py-10 lg:py-12 flex">
                <article class="ebook-content w-full xl:w-3/4 pr-0 xl:pr-16">
                    <!-- Breadcrumbs -->
                    <nav class="flex text-xs text-slate-500 mb-8 items-center font-medium" aria-label="Breadcrumb">
                        <ol class="inline-flex items-center space-x-1 md:space-x-2">
                            <?php foreach ( $breadcrumb_items as $index => $crumb ) : ?>
                                <?php if ( $index > 0 ) : ?>
                                    <li><i class="fas fa-chevron-right text-[9px] text-slate-300 mx-1"></i></li>
                                <?php endif; ?>
                                <li class="inline-flex items-center">
                                    <?php if ( $crumb['url'] ) : ?>
                                        <a href="<?php echo esc_url( $crumb['url'] ); ?>" class="text-slate-500 hover:text-sky-600 transition-colors"><?php echo esc_html( $crumb['label'] ); ?></a>
                                    <?php else : ?>
                                        <span class="text-sky-600 font-semibold bg-sky-50 px-2 py-0.5 rounded-full"><?php echo esc_html( $crumb['label'] ); ?></span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    </nav>

                    <!-- Meta -->
                    <div class="flex flex-wrap items-center gap-3 mb-6 text-xs text-slate-500">
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1">
                            <?php esc_html_e( 'Trạng thái', 'ebook-gitbook' ); ?>:
                            <span class="ml-1 font-medium text-slate-800"><?php echo esc_html( ucfirst( $status ) ); ?></span>
                        </span>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-1">
                            v<?php echo esc_html( $version ); ?>
                        </span>
                        <?php if ( $download_link ) : ?>
                            <a href="<?php echo esc_url( $download_link ); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sky-700 hover:text-sky-900 ml-auto">
                                <i class="fas fa-download text-[11px]"></i>
                                <span><?php esc_html_e( 'Tải ebook', 'ebook-gitbook' ); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>

                    <h1><?php the_title(); ?></h1>
                    <?php if ( has_excerpt() ) : ?>
                        <p class="text-lg text-slate-600 leading-relaxed mb-8"><?php echo esc_html( get_the_excerpt() ); ?></p>
                    <?php endif; ?>

                    <!-- Nội dung chính -->
                    <div class="content-body space-y-6">
                        <?php the_content(); ?>
                    </div>

                    <!-- Điều hướng trước / sau -->
                    <nav class="mt-10 pt-6 border-t border-slate-200 flex flex-col gap-4 md:flex-row md:items-center md:justify-between text-sm">
                        <div class="md:w-1/2">
                            <?php if ( $prev_id ) : ?>
                                <span class="block text-xs text-slate-400 uppercase tracking-wide mb-1"><?php esc_html_e( 'Trang trước', 'ebook-gitbook' ); ?></span>
                                <a class="block text-sky-700 hover:text-sky-900 font-medium" href="<?php echo esc_url( get_permalink( $prev_id ) ); ?>">
                                    &laquo; <?php echo esc_html( ebook_get_plain_title( $prev_id ) ); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="md:w-1/2 md:text-right">
                            <?php if ( $next_id ) : ?>
                                <span class="block text-xs text-slate-400 uppercase tracking-wide mb-1"><?php esc_html_e( 'Trang tiếp theo', 'ebook-gitbook' ); ?></span>
                                <a class="block text-sky-700 hover:text-sky-900 font-medium" href="<?php echo esc_url( get_permalink( $next_id ) ); ?>">
                                    <?php echo esc_html( ebook_get_plain_title( $next_id ) ); ?> &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </article>

                <!-- TOC phải -->
                <aside class="hidden xl:block w-64 sticky top-10 h-fit pl-8 border-l border-gray-200 ml-8 flex-shrink-0">
                    <h4 class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4"><?php esc_html_e( 'Mục lục trang này', 'ebook-gitbook' ); ?></h4>
                    <ul id="toc-list-side" class="text-[13px] space-y-3 text-slate-500 relative border-l border-slate-100 pl-0 ml-1"></ul>
                </aside>
            </div>
        </main>

        <!-- Overlay for Mobile -->
        <div id="sidebar-overlay" class="fixed inset-0 bg-slate-900/20 z-20 hidden md:hidden backdrop-blur-[2px] transition-opacity"></div>
    </div>
<?php endwhile; ?>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const article   = document.querySelector('.ebook-content');
    const tocInline = document.getElementById('toc-list');
    const tocSide   = document.getElementById('toc-list-side');

    if (!article) return;

    /* ========= 1. TỰ ĐỘNG TẠO MỤC LỤC (H2, H3) ========= */
    if (tocInline && tocSide) {
        const headings = article.querySelectorAll('h2, h3');
        if (headings.length) {
            headings.forEach((heading) => {
                const id = heading.id || heading.innerText
                    .toLowerCase()
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .replace(/[^a-z0-9]+/g, '-');
                heading.id = id;

                const makeLink = () => {
                    const li   = document.createElement('li');
                    const link = document.createElement('a');
                    link.href  = `#${id}`;
                    link.innerText = heading.innerText;
                    let baseClass = 'toc-link block hover:text-sky-600 transition-colors pl-4 border-l-2 border-transparent -ml-[1px]';
                    if (heading.tagName === 'H3') baseClass += ' ml-3 text-xs';
                    link.className = baseClass;
                    link.addEventListener('click', (e) => {
                        e.preventDefault();
                        document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                    li.appendChild(link);
                    return li;
                };

                tocInline.appendChild(makeLink());
                tocSide.appendChild(makeLink());
            });

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    const id = entry.target.id;
                    document.querySelectorAll('.toc-link').forEach((link) => {
                        link.classList.remove('active', 'text-sky-600', 'font-medium', 'border-sky-500');
                        link.classList.add('border-transparent');
                        if (link.getAttribute('href') === `#${id}`) {
                            link.classList.add('active', 'text-sky-600', 'font-medium', 'border-sky-500');
                            link.classList.remove('border-transparent');
                        }
                    });
                });
            }, { rootMargin: '-100px 0px -65% 0px' });

            headings.forEach((heading) => observer.observe(heading));
        }
    }

    /* ========= 2. CHECKLIST – tự thêm icon nếu thiếu ========= */
    article.querySelectorAll('ul.checklist li').forEach((li) => {
        const hasIcon = li.querySelector('.check-icon');
        if (!hasIcon) {
            const icon = document.createElement('span');
            icon.className = 'check-icon';
            icon.innerHTML = '<i class="fas fa-check"></i>';
            const firstChild = li.firstChild;
            if (firstChild) {
                li.insertBefore(icon, firstChild);
            } else {
                li.appendChild(icon);
            }
        }
    });

    /* ========= 3. TABS – tự kích hoạt nếu có markup .ebook-tabs ========= */
    document.querySelectorAll('.ebook-tabs').forEach((tabsBlock) => {
        const nav    = tabsBlock.querySelector('.ebook-tabs-nav');
        const panes  = tabsBlock.querySelectorAll('.ebook-tab-pane');
        if (!nav || !panes.length) return;

        const buttons = nav.querySelectorAll('button');
        if (!buttons.length) return;

        const activate = (targetId) => {
            panes.forEach((pane) => {
                if (pane.id === targetId) {
                    pane.classList.remove('hidden');
                } else {
                    pane.classList.add('hidden');
                }
            });
            buttons.forEach((btn) => {
                if (btn.dataset.tabTarget === targetId) {
                    btn.classList.add('is-active');
                } else {
                    btn.classList.remove('is-active');
                }
            });
        };

        // Gán sự kiện cho button
        buttons.forEach((btn) => {
            const targetId = btn.dataset.tabTarget;
            if (!targetId) return;
            btn.addEventListener('click', () => activate(targetId));
        });

        // Active tab đầu tiên theo thứ tự
        const firstBtn = buttons[0];
        if (firstBtn && firstBtn.dataset.tabTarget) {
            activate(firstBtn.dataset.tabTarget);
        }
    });

    /* ========= 4. MOBILE SIDEBAR TOGGLE ========= */
    const menuBtn = document.getElementById('mobile-menu-btn');
    const sidebar = document.getElementById('sidebar-left');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuBtn && sidebar && overlay) {
        const closeSidebar = () => {
            sidebar.classList.add('hidden');
            overlay.classList.add('hidden');
            document.body.classList.remove('overflow-hidden');
        };
        const openSidebar = () => {
            sidebar.classList.remove('hidden');
            overlay.classList.remove('hidden');
            document.body.classList.add('overflow-hidden');
        };

        menuBtn.addEventListener('click', () => {
            if (sidebar.classList.contains('hidden')) {
                openSidebar();
            } else {
                closeSidebar();
            }
        });
        overlay.addEventListener('click', closeSidebar);
    }
});
</script>

<?php wp_footer(); ?>
</body>
</html>
