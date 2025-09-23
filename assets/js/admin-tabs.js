jQuery(document).ready(function($) {
    // Fungsi untuk menginisialisasi editor pada tab baru
    function initializeEditor(tabId) {
        var editorId = 'custom_tab_' + tabId + '_content';
        
        // Tunggu sebentar untuk memastikan DOM sudah siap
        setTimeout(function() {
            // Inisialisasi editor jika belum ada
            if (typeof tinyMCE !== 'undefined' && !tinyMCE.get(editorId)) {
                // Hapus instance editor yang mungkin sudah ada
                wp.editor.remove(editorId);
                
                // Inisialisasi editor baru
                wp.editor.initialize(editorId, {
                    tinymce: true,
                    quicktags: true,
                    mediaButtons: true,
                    editor_height: 200
                });
                
                // Tunggu editor selesai diinisialisasi
                var checkEditorInterval = setInterval(function() {
                    if (tinyMCE.get(editorId)) {
                        clearInterval(checkEditorInterval);
                        
                        // Pastikan tombol Visual/Code muncul
                        var $container = $('#' + editorId).closest('.wp-editor-wrap');
                        $container.find('.wp-editor-tabs').show();
                        
                        // Switch ke mode visual sebagai default
                        if (tinyMCE.get(editorId)) {
                            switchEditors.go(editorId, 'tmce');
                        }
                    }
                }, 100);
            }
        }, 200);
    }
    
    // Handler untuk tombol Tambah Tab
    $('.add_custom_tab_button').on('click', function() {
        var tabId = 'tab_' + Date.now();
        var template = `
            <div class="custom_tab_fields" data-tab="${tabId}">
                <h3>
                    <span class="tab-title"><?php _e('Tab Baru', 'custom-product-tabs-importer'); ?></span>
                    <button type="button" class="remove_tab button"><?php _e('Hapus', 'custom-product-tabs-importer'); ?></button>
                </h3>
                <div class="tab-content">
                    <p class="form-field">
                        <label><?php _e('Judul Tab', 'custom-product-tabs-importer'); ?></label>
                        <input type="text" name="custom_product_tabs[${tabId}][title]" value="" />
                    </p>
                    <p class="form-field">
                        <label><?php _e('Konten Tab', 'custom-product-tabs-importer'); ?></label>
                        <textarea id="custom_tab_${tabId}_content" name="custom_product_tabs[${tabId}][content]" class="custom_tab_content"></textarea>
                    </p>
                    <p class="form-field">
                        <label><?php _e('Prioritas', 'custom-product-tabs-importer'); ?></label>
                        <input type="number" name="custom_product_tabs[${tabId}][priority]" value="50" />
                    </p>
                </div>
            </div>
        `;
        
        $('.custom_tabs_container').append(template);
        initializeEditor(tabId);
    });
    
    // Handler untuk tombol Hapus Tab
    $(document).on('click', '.remove_tab', function() {
        var $tabContainer = $(this).closest('.custom_tab_fields');
        var tabId = $tabContainer.data('tab');
        var editorId = 'custom_tab_' + tabId + '_content';
        
        // Hapus editor sebelum menghapus container
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
            wp.editor.remove(editorId);
        }
        
        $tabContainer.remove();
    });
    
    // Inisialisasi editor untuk tab yang sudah ada
    $('.custom_tab_fields').each(function() {
        var tabId = $(this).data('tab');
        initializeEditor(tabId);
    });
    
    // Update judul tab saat input berubah
    $(document).on('input', 'input[name$="[title]"]', function() {
        $(this).closest('.custom_tab_fields').find('.tab-title').text($(this).val() || customTabsData.labels.newTab);
    });
});