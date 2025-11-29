<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Lấy danh sách các Ebook chính (không có parent)
$ebooks = get_posts( [
    'post_type'      => 'ebook',
    'post_parent'    => 0,
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'post_status'    => 'any', // Lấy tất cả status (draft, publish, private, etc.)
] );

// Debug: Log số lượng ebook tìm được
if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
    error_log( 'Ebook Manager: Tìm thấy ' . count( $ebooks ) . ' ebook(s)' );
}

// Tính toán thống kê tổng quan
$total_ebooks = count( $ebooks );
$total_views = 0;
$total_conversions = 0;
$published_count = 0;

foreach ( $ebooks as $ebook ) {
    $total_views += (int) ( get_post_meta( $ebook->ID, '_ebook_views', true ) ?: 0 );
    $total_conversions += (int) ( get_post_meta( $ebook->ID, '_ebook_conversions', true ) ?: 0 );
    if ( $ebook->post_status === 'publish' ) {
        $published_count++;
    }
}

// Xử lý thông báo
$message = '';
if ( isset( $_GET['deleted'] ) && $_GET['deleted'] == '1' ) {
    $message = '<div class="ebook-notice ebook-notice--success"><i class="fas fa-check-circle"></i> Ebook đã được xóa thành công!</div>';
}
?>
<div class="ebook-manager-wrap">
    <div class="ebook-manager-header">
        <div class="ebook-manager-header__content">
            <h1 class="ebook-manager-title">
                <i class="fas fa-book-open"></i>
                Quản lý Ebook
            </h1>
            <p class="ebook-manager-subtitle">Quản lý các Ebook chính của website</p>
        </div>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=ebook-editor' ) ); ?>" class="ebook-btn ebook-btn--primary">
            <i class="fas fa-plus"></i>
            <span>Thêm Ebook mới</span>
        </a>
    </div>

    <?php echo $message; ?>

    <?php if ( ! empty( $ebooks ) ) : ?>
    <!-- Thống kê tổng quan -->
    <div class="ebook-stats-grid">
        <div class="ebook-stat-card">
            <div class="ebook-stat-card__icon ebook-stat-card__icon--blue">
                <i class="fas fa-book"></i>
            </div>
            <div class="ebook-stat-card__content">
                <div class="ebook-stat-card__value"><?php echo number_format( $total_ebooks ); ?></div>
                <div class="ebook-stat-card__label">Tổng số Ebook</div>
            </div>
        </div>
        <div class="ebook-stat-card">
            <div class="ebook-stat-card__icon ebook-stat-card__icon--green">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="ebook-stat-card__content">
                <div class="ebook-stat-card__value"><?php echo number_format( $published_count ); ?></div>
                <div class="ebook-stat-card__label">Đã xuất bản</div>
            </div>
        </div>
        <div class="ebook-stat-card">
            <div class="ebook-stat-card__icon ebook-stat-card__icon--purple">
                <i class="fas fa-eye"></i>
            </div>
            <div class="ebook-stat-card__content">
                <div class="ebook-stat-card__value"><?php echo number_format( $total_views ); ?></div>
                <div class="ebook-stat-card__label">Tổng lượt truy cập</div>
            </div>
        </div>
        <div class="ebook-stat-card">
            <div class="ebook-stat-card__icon ebook-stat-card__icon--orange">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="ebook-stat-card__content">
                <div class="ebook-stat-card__value"><?php echo number_format( $total_conversions ); ?></div>
                <div class="ebook-stat-card__label">Tổng lượt chuyển đổi</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="ebook-manager-content">
        <?php if ( empty( $ebooks ) ) : ?>
            <div class="ebook-empty-state">
                <div class="ebook-empty-state__icon">
                    <i class="fas fa-book-open"></i>
                </div>
                <h3>Chưa có Ebook nào</h3>
                <p>Bắt đầu bằng cách thêm Ebook đầu tiên của bạn</p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=ebook-editor' ) ); ?>" class="ebook-btn ebook-btn--primary">
                    <i class="fas fa-plus"></i>
                    <span>Thêm Ebook mới</span>
                </a>
            </div>
        <?php else : ?>
            <!-- Toolbar -->
            <div class="ebook-toolbar">
                <div class="ebook-toolbar__left">
                    <div class="ebook-search">
                        <i class="fas fa-search ebook-search__icon"></i>
                        <input type="text" id="ebook-search-input" class="ebook-search__input" placeholder="Tìm kiếm Ebook...">
                    </div>
                    <div class="ebook-filter">
                        <select id="ebook-status-filter" class="ebook-filter__select">
                            <option value="">Tất cả trạng thái</option>
                            <option value="publish">Đã xuất bản</option>
                            <option value="draft">Bản nháp</option>
                            <option value="pending">Chờ duyệt</option>
                            <option value="private">Riêng tư</option>
                        </select>
                    </div>
                </div>
                <div class="ebook-toolbar__right">
                    <div class="ebook-view-toggle">
                        <button class="ebook-view-btn active" data-view="table" title="Xem dạng bảng">
                            <i class="fas fa-table"></i>
                        </button>
                        <button class="ebook-view-btn" data-view="card" title="Xem dạng thẻ">
                            <i class="fas fa-th-large"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table View -->
            <div class="ebook-table-view" id="ebook-table-view">
                <div class="ebook-table-wrapper">
                    <table class="ebook-table">
                        <thead>
                            <tr>
                                <th class="ebook-table__col-id">ID</th>
                                <th class="ebook-table__col-title">Tiêu đề</th>
                                <th class="ebook-table__col-status">Trạng thái</th>
                                <th class="ebook-table__col-stats">Lượt truy cập</th>
                                <th class="ebook-table__col-stats">Lượt ở lại</th>
                                <th class="ebook-table__col-stats">Lượt chuyển đổi</th>
                                <th class="ebook-table__col-date">Ngày tạo</th>
                                <th class="ebook-table__col-actions">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody id="ebook-table-body">
                            <?php foreach ( $ebooks as $ebook ) : 
                                $views = get_post_meta( $ebook->ID, '_ebook_views', true ) ?: 0;
                                $time_on_page = get_post_meta( $ebook->ID, '_ebook_time_on_page', true ) ?: 0;
                                $conversions = get_post_meta( $ebook->ID, '_ebook_conversions', true ) ?: 0;
                                $edit_url = admin_url( 'admin.php?page=ebook-editor&ebook_id=' . $ebook->ID );
                                $view_url = get_permalink( $ebook->ID );
                                $delete_url = wp_nonce_url( 
                                    admin_url( 'admin-post.php?action=ebook_delete&ebook_id=' . $ebook->ID ),
                                    'ebook_delete_' . $ebook->ID
                                );
                                $thumbnail = get_the_post_thumbnail_url( $ebook->ID, 'thumbnail' ) ?: 'https://via.placeholder.com/150';
                            ?>
                                <tr class="ebook-row" data-id="<?php echo esc_attr( $ebook->ID ); ?>" data-status="<?php echo esc_attr( $ebook->post_status ); ?>" data-title="<?php echo esc_attr( strtolower( get_the_title( $ebook->ID ) ) ); ?>">
                                    <td class="ebook-table__col-id"><?php echo esc_html( $ebook->ID ); ?></td>
                                    <td class="ebook-table__col-title">
                                        <div class="ebook-title-cell">
                                            <?php if ( $thumbnail ) : ?>
                                                <img src="<?php echo esc_url( $thumbnail ); ?>" alt="" class="ebook-thumbnail">
                                            <?php endif; ?>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="ebook-table__title-link">
                                                <?php echo esc_html( get_the_title( $ebook->ID ) ); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="ebook-table__col-status">
                                        <span class="ebook-status ebook-status--<?php echo esc_attr( $ebook->post_status ); ?>">
                                            <?php
                                            $status_labels = [
                                                'publish' => 'Đã xuất bản',
                                                'draft'   => 'Bản nháp',
                                                'pending' => 'Chờ duyệt',
                                                'private' => 'Riêng tư',
                                            ];
                                            echo esc_html( $status_labels[ $ebook->post_status ] ?? $ebook->post_status );
                                            ?>
                                        </span>
                                    </td>
                                    <td class="ebook-table__col-stats">
                                        <div class="ebook-stat">
                                            <i class="fas fa-eye"></i>
                                            <span><?php echo number_format( $views ); ?></span>
                                        </div>
                                    </td>
                                    <td class="ebook-table__col-stats">
                                        <div class="ebook-stat">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo esc_html( ebook_format_time( $time_on_page ) ); ?></span>
                                        </div>
                                    </td>
                                    <td class="ebook-table__col-stats">
                                        <div class="ebook-stat">
                                            <i class="fas fa-exchange-alt"></i>
                                            <span><?php echo number_format( $conversions ); ?></span>
                                        </div>
                                    </td>
                                    <td class="ebook-table__col-date">
                                        <div class="ebook-date">
                                            <i class="far fa-calendar"></i>
                                            <span><?php echo esc_html( get_the_date( 'd/m/Y', $ebook->ID ) ); ?></span>
                                        </div>
                                    </td>
                                    <td class="ebook-table__col-actions">
                                        <div class="ebook-actions">
                                            <a href="<?php echo esc_url( $view_url ); ?>" target="_blank" class="ebook-btn ebook-btn--sm ebook-btn--view" title="Xem">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <a href="<?php echo esc_url( $edit_url ); ?>" class="ebook-btn ebook-btn--sm ebook-btn--edit" title="Sửa">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="<?php echo esc_url( $delete_url ); ?>" 
                                               class="ebook-btn ebook-btn--sm ebook-btn--delete" 
                                               title="Xóa"
                                               onclick="return confirm('Bạn có chắc chắn muốn xóa Ebook này? Hành động này sẽ xóa tất cả các trang con và không thể hoàn tác.');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card View -->
            <div class="ebook-card-view" id="ebook-card-view" style="display: none;">
                <div class="ebook-cards-grid" id="ebook-cards-grid">
                    <?php foreach ( $ebooks as $ebook ) : 
                        $views = get_post_meta( $ebook->ID, '_ebook_views', true ) ?: 0;
                        $time_on_page = get_post_meta( $ebook->ID, '_ebook_time_on_page', true ) ?: 0;
                        $conversions = get_post_meta( $ebook->ID, '_ebook_conversions', true ) ?: 0;
                        $edit_url = admin_url( 'admin.php?page=ebook-editor&ebook_id=' . $ebook->ID );
                        $view_url = get_permalink( $ebook->ID );
                        $delete_url = wp_nonce_url( 
                            admin_url( 'admin-post.php?action=ebook_delete&ebook_id=' . $ebook->ID ),
                            'ebook_delete_' . $ebook->ID
                        );
                        $thumbnail = get_the_post_thumbnail_url( $ebook->ID, 'medium' ) ?: 'https://via.placeholder.com/400x250';
                    ?>
                        <div class="ebook-card ebook-card-item" data-id="<?php echo esc_attr( $ebook->ID ); ?>" data-status="<?php echo esc_attr( $ebook->post_status ); ?>" data-title="<?php echo esc_attr( strtolower( get_the_title( $ebook->ID ) ) ); ?>">
                            <div class="ebook-card__header">
                                <?php if ( $thumbnail ) : ?>
                                    <div class="ebook-card__thumbnail" style="background-image: url('<?php echo esc_url( $thumbnail ); ?>');"></div>
                                <?php endif; ?>
                                <span class="ebook-status ebook-status--<?php echo esc_attr( $ebook->post_status ); ?> ebook-card__status">
                                    <?php
                                    $status_labels = [
                                        'publish' => 'Đã xuất bản',
                                        'draft'   => 'Bản nháp',
                                        'pending' => 'Chờ duyệt',
                                        'private' => 'Riêng tư',
                                    ];
                                    echo esc_html( $status_labels[ $ebook->post_status ] ?? $ebook->post_status );
                                    ?>
                                </span>
                            </div>
                            <div class="ebook-card__body">
                                <h3 class="ebook-card__title">
                                    <a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( get_the_title( $ebook->ID ) ); ?></a>
                                </h3>
                                <div class="ebook-card__meta">
                                    <span class="ebook-card__id">ID: <?php echo esc_html( $ebook->ID ); ?></span>
                                    <span class="ebook-card__date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo esc_html( get_the_date( 'd/m/Y', $ebook->ID ) ); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="ebook-card__stats">
                                <div class="ebook-card__stat">
                                    <i class="fas fa-eye"></i>
                                    <span class="ebook-card__stat-value"><?php echo number_format( $views ); ?></span>
                                    <span class="ebook-card__stat-label">Lượt xem</span>
                                </div>
                                <div class="ebook-card__stat">
                                    <i class="fas fa-clock"></i>
                                    <span class="ebook-card__stat-value"><?php echo esc_html( ebook_format_time( $time_on_page ) ); ?></span>
                                    <span class="ebook-card__stat-label">Ở lại</span>
                                </div>
                                <div class="ebook-card__stat">
                                    <i class="fas fa-exchange-alt"></i>
                                    <span class="ebook-card__stat-value"><?php echo number_format( $conversions ); ?></span>
                                    <span class="ebook-card__stat-label">Chuyển đổi</span>
                                </div>
                            </div>
                            <div class="ebook-card__actions">
                                <a href="<?php echo esc_url( $view_url ); ?>" target="_blank" class="ebook-btn ebook-btn--sm ebook-btn--view">
                                    <i class="fas fa-external-link-alt"></i>
                                    <span>Xem</span>
                                </a>
                                <a href="<?php echo esc_url( $edit_url ); ?>" class="ebook-btn ebook-btn--sm ebook-btn--edit">
                                    <i class="fas fa-edit"></i>
                                    <span>Sửa</span>
                                </a>
                                <a href="<?php echo esc_url( $delete_url ); ?>" 
                                   class="ebook-btn ebook-btn--sm ebook-btn--delete"
                                   onclick="return confirm('Bạn có chắc chắn muốn xóa Ebook này? Hành động này sẽ xóa tất cả các trang con và không thể hoàn tác.');">
                                    <i class="fas fa-trash"></i>
                                    <span>Xóa</span>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- No Results -->
            <div class="ebook-no-results" id="ebook-no-results" style="display: none;">
                <i class="fas fa-search"></i>
                <h3>Không tìm thấy Ebook nào</h3>
                <p>Thử thay đổi từ khóa tìm kiếm hoặc bộ lọc</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    // Search functionality
    const searchInput = document.getElementById('ebook-search-input');
    const statusFilter = document.getElementById('ebook-status-filter');
    const tableBody = document.getElementById('ebook-table-body');
    const cardsGrid = document.getElementById('ebook-cards-grid');
    const noResults = document.getElementById('ebook-no-results');
    const tableView = document.getElementById('ebook-table-view');
    const cardView = document.getElementById('ebook-card-view');
    
    function filterEbooks() {
        const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';
        const statusValue = statusFilter ? statusFilter.value : '';
        let visibleCount = 0;
        
        // Filter table rows
        if (tableBody) {
            const rows = tableBody.querySelectorAll('.ebook-row');
            rows.forEach(row => {
                const title = row.getAttribute('data-title') || '';
                const status = row.getAttribute('data-status') || '';
                const matchesSearch = !searchTerm || title.includes(searchTerm);
                const matchesStatus = !statusValue || status === statusValue;
                
                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Filter cards
        if (cardsGrid) {
            const cards = cardsGrid.querySelectorAll('.ebook-card-item');
            cards.forEach(card => {
                const title = card.getAttribute('data-title') || '';
                const status = card.getAttribute('data-status') || '';
                const matchesSearch = !searchTerm || title.includes(searchTerm);
                const matchesStatus = !statusValue || status === statusValue;
                
                if (matchesSearch && matchesStatus) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Show/hide no results
        if (noResults) {
            if (visibleCount === 0) {
                noResults.style.display = 'block';
                if (tableView) tableView.style.display = 'none';
                if (cardView) cardView.style.display = 'none';
            } else {
                noResults.style.display = 'none';
                const activeView = document.querySelector('.ebook-view-btn.active');
                if (activeView) {
                    const view = activeView.getAttribute('data-view');
                    if (view === 'table' && tableView) tableView.style.display = 'block';
                    if (view === 'card' && cardView) cardView.style.display = 'block';
                }
            }
        }
    }
    
    if (searchInput) {
        searchInput.addEventListener('input', filterEbooks);
    }
    
    if (statusFilter) {
        statusFilter.addEventListener('change', filterEbooks);
    }
    
    // View toggle
    const viewButtons = document.querySelectorAll('.ebook-view-btn');
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const view = this.getAttribute('data-view');
            viewButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            if (view === 'table') {
                if (tableView) tableView.style.display = 'block';
                if (cardView) cardView.style.display = 'none';
            } else {
                if (tableView) tableView.style.display = 'none';
                if (cardView) cardView.style.display = 'block';
            }
        });
    });
})();
</script>

