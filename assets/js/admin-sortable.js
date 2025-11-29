(function ($) {
    $(function () {
        var $table = $('#the-list');
        if (!$table.length || typeof Sortable === 'undefined') {
            return;
        }

        Sortable.create($table[0], {
            animation: 150,
            handle: '.dashicons-move',
            ghostClass: 'sortable-ghost',
            onEnd: function () {
                var order = [];
                $table.children('tr').each(function () {
                    var id = $(this).attr('id');
                    if (!id) return;
                    order.push(id.replace('post-', ''));
                });

                $.post(ebookSort.ajaxurl, {
                    action: 'ebook_reorder',
                    nonce: ebookSort.nonce,
                    order: order,
                });
            },
        });
    });
})(jQuery);
