jQuery(document).ready(function ($) {
    var autosaveTimer = null;
    var autosaveInProgress = false;
    var autosaveQueued = false;
    var pendingStatusElements = [];

    function setAutosaveStatus($status, message, state) {
        if (!$status || !$status.length) {
            return;
        }

        var oldTimer = $status.data('clearTimer');
        if (oldTimer) {
            clearTimeout(oldTimer);
        }

        $status.text(message);
        $status.removeClass('is-saving is-saved is-error');
        if (state) $status.addClass('is-' + state);

        if ('saved' === state) {
            $status.data('clearTimer', setTimeout(function () {
                $status.text('').removeClass('is-saving is-saved is-error');
            }, 2500));
        }
    }

    function syncCustomTabEditors() {
        if (typeof tinyMCE !== 'undefined' && typeof tinyMCE.triggerSave === 'function') {
            tinyMCE.triggerSave();
        }
    }

    function collectCustomTabs() {
        var tabs = [];

        $('.custom_tabs_container .custom_tab_fields').each(function () {
            var $tab = $(this);
            var tabId = String($tab.data('tab'));
            var editorId = 'custom_tab_' + tabId + '_content';
            var editor = typeof tinyMCE !== 'undefined' ? tinyMCE.get(editorId) : null;
            var content = editor && editor.isHidden() === false
                ? editor.getContent()
                : $tab.find('.custom_tab_content').val();

            tabs.push({
                id: tabId,
                title: $tab.find('input[name$="[title]"]').val() || '',
                content: content || ''
            });
        });

        return tabs;
    }

    function addPendingStatus($status) {
        if (!$status || !$status.length) {
            $status = $('.custom-tabs-autosave-status');
        }

        if ($status.length && $.inArray($status[0], pendingStatusElements) === -1) {
            pendingStatusElements.push($status[0]);
        }
        setAutosaveStatus($status, customTabsData.labels.saving, 'saving');
    }

    function runCustomTabsAutosave() {
        if (autosaveInProgress) {
            autosaveQueued = true;
            return;
        }

        autosaveInProgress = true;
        autosaveQueued = false;
        var statuses = pendingStatusElements.slice();
        pendingStatusElements = [];
        syncCustomTabEditors();

        $.ajax({
            url: customTabsData.ajaxUrl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'custom_tabs_autosave',
                nonce: customTabsData.autosaveNonce,
                post_id: customTabsData.postId,
                tabs: JSON.stringify(collectCustomTabs())
            }
        }).done(function (response) {
            var success = response && response.success;
            $.each(statuses, function (index, element) {
                var $status = $(element);
                var changedAgain = $.inArray(element, pendingStatusElements) !== -1;
                if (!changedAgain) {
                    setAutosaveStatus(
                        $status,
                        success ? customTabsData.labels.saved : customTabsData.labels.saveFailed,
                        success ? 'saved' : 'error'
                    );
                }
            });
        }).fail(function () {
            $.each(statuses, function (index, element) {
                if ($.inArray(element, pendingStatusElements) === -1) {
                    setAutosaveStatus($(element), customTabsData.labels.saveFailed, 'error');
                }
            });
        }).always(function () {
            autosaveInProgress = false;
            if (autosaveQueued || pendingStatusElements.length) {
                runCustomTabsAutosave();
            }
        });
    }

    function queueCustomTabsAutosave($status) {
        addPendingStatus($status);
        clearTimeout(autosaveTimer);
        autosaveTimer = setTimeout(runCustomTabsAutosave, 350);
    }

    function bindEditorAutosave(editor) {
        if (!editor || editor._customTabsAutosaveBound) {
            return;
        }

        editor._customTabsAutosaveBound = true;
        editor.on('blur', function () {
            var $textarea = $('#' + editor.id);
            if ($textarea.length && $textarea.hasClass('custom_tab_content')) {
                queueCustomTabsAutosave($textarea.closest('.form-field-group').find('.custom-tab-field-status'));
            }
        });
    }

    if (typeof tinymce !== 'undefined') {
        tinymce.on('AddEditor', function (event) {
            bindEditorAutosave(event.editor);
        });
    }

    function bindExistingEditors() {
        if (typeof tinyMCE === 'undefined') {
            return;
        }
        $('.custom_tab_content').each(function () {
            bindEditorAutosave(tinyMCE.get(this.id));
        });
    }
    bindExistingEditors();
    setTimeout(bindExistingEditors, 300);
    setTimeout(bindExistingEditors, 1000);

    $(document).on('blur', '#custom_product_tabs_data input[name$="[title]"], #custom_product_tabs_data textarea.custom_tab_content', function () {
        var $field = $(this);
        queueCustomTabsAutosave($field.closest('.form-field-group').find('.custom-tab-field-status'));
    });

    // Fungsi untuk menginisialisasi editor
    function initializeEditor(tabId, forceInit = false) {
        var editorId = 'custom_tab_' + tabId + '_content';
        var $textarea = $('#' + editorId);

        if (!$textarea.length) return;

        // Tunggu sebentar untuk memastikan DOM sudah siap
        setTimeout(function () {
            if (typeof wp === 'undefined' || !wp.editor || !wp.editor.initialize) return;

            var isPHPGenerated = $textarea.closest('.wp-editor-wrap').length > 0;

            if (isPHPGenerated && !forceInit) {
                // Jika dibuat via PHP, jangan diapa-apakan saat load awal
                return;
            }

            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                wp.editor.remove(editorId);
            }

            // Jika dipaksa re-init (setelah move) pada editor PHP
            // Kita harus membersihkan UI yang dibuat oleh wp.editor.initialize jika ada
            if (isPHPGenerated) {
                // Untuk editor PHP, sebaiknya gunakan mceAddEditor agar tidak double UI
                if (typeof tinyMCE !== 'undefined') {
                    tinyMCE.execCommand('mceAddEditor', false, editorId);
                }
                return;
            }

            // Untuk editor baru (via JS), inisialisasi penuh
            wp.editor.initialize(editorId, {
                tinymce: {
                    wpautop: true,
                    plugins: 'charmap colorpicker hr lists paste tabfocus textcolor wordpress wpemoji wpgallery wplink wpview',
                    toolbar1: 'formatselect bold italic | bullist numlist | blockquote | alignleft aligncenter alignright | link unlink | wp_more spellchecker fullscreen',
                    setup: function (ed) {
                        ed.on('change', function () {
                            ed.save();
                            $(ed.getElement()).trigger('change');
                        });
                        ed.on('blur', function () {
                            var $textarea = $(ed.getElement());
                            if ($textarea.hasClass('custom_tab_content')) {
                                queueCustomTabsAutosave($textarea.closest('.form-field-group').find('.custom-tab-field-status'));
                            }
                        });
                    }
                },
                quicktags: true,
                mediaButtons: true
            });

            // Pastikan toolbar muncul
            var checkEditorInterval = setInterval(function () {
                if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                    clearInterval(checkEditorInterval);
                    var $container = $('#' + editorId).closest('.wp-editor-wrap');
                    $container.find('.wp-editor-tabs').show();
                }
            }, 100);
        }, 200);
    }

    // Inisialisasi Sortable untuk fitur Drag & Drop
    function initSortable() {
        if ($.fn.sortable) {
            $('.custom_tabs_container').sortable({
                handle: '.tab-drag-handle',
                placeholder: 'tab-sortable-placeholder',
                axis: 'y',
                start: function (e, ui) {
                    ui.placeholder.height(ui.item.height());

                    // Hapus instance TinyMCE sementara saat dipindahkan
                    ui.item.find('.custom_tab_content').each(function () {
                        var id = $(this).attr('id');
                        var editor = typeof tinyMCE !== 'undefined' ? tinyMCE.get(id) : null;
                        if (editor) {
                            editor.save();
                        }
                        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(id)) {
                            tinyMCE.execCommand('mceRemoveEditor', false, id);
                        }
                    });
                },
                stop: function (e, ui) {
                    // Re-inisialisasi TinyMCE setelah posisi baru menetap
                    ui.item.find('.custom_tab_content').each(function () {
                        var id = $(this).attr('id');
                        var tabId = ui.item.closest('.custom_tab_fields').data('tab');
                        initializeEditor(tabId, true);
                    });
                    queueCustomTabsAutosave();
                }
            });
        }
    }

    initSortable();

    // Fungsi pembantu untuk memindahkan posisi tab
    function moveTab($tab, direction) {
        var editorId = 'custom_tab_' + $tab.data('tab') + '_content';

        // Hapus editor sebelum memindahkan
        var editor = typeof tinyMCE !== 'undefined' ? tinyMCE.get(editorId) : null;
        if (editor) {
            editor.save();
            tinyMCE.execCommand('mceRemoveEditor', false, editorId);
        }

        if (direction === 'up') {
            var $prev = $tab.prev('.custom_tab_fields');
            if ($prev.length) {
                $tab.insertBefore($prev);
            }
        } else {
            var $next = $tab.next('.custom_tab_fields');
            if ($next.length) {
                $tab.insertAfter($next);
            }
        }

        // Re-inisialisasi editor
        initializeEditor($tab.data('tab'), true);
        queueCustomTabsAutosave();
    }

    // Handler tombol Move Up
    $(document).on('click', '.move-tab-up', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $tab = $(this).closest('.custom_tab_fields');
        moveTab($tab, 'up');
    });

    // Handler tombol Move Down
    $(document).on('click', '.move-tab-down', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var $tab = $(this).closest('.custom_tab_fields');
        moveTab($tab, 'down');
    });

    // Handler untuk tombol Tambah Tab
    $(document).on('click', '.add_custom_tab_button', function (e) {
        e.preventDefault();

        var tabId = 'tab_' + Date.now();
        var newTabLabel = (typeof customTabsData !== 'undefined') ? customTabsData.labels.newTab : 'Tab Baru';
        var tabTitleLabel = (typeof customTabsData !== 'undefined') ? customTabsData.labels.tabTitle : 'Nama / Judul Tab';
        var tabContentLabel = (typeof customTabsData !== 'undefined') ? customTabsData.labels.tabContent : 'Konten Lengkap Tab';

        var template = `
            <div class="custom_tab_fields cardx active" data-tab="${tabId}">
                <div class="tab-header">
                    <div class="tab-reorder-actions">
                        <button type="button" class="move-tab-up button-link" title="Pindahkan ke atas">
                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                        </button>
                        <button type="button" class="move-tab-down button-link" title="Pindahkan ke bawah">
                            <span class="dashicons dashicons-arrow-down-alt2"></span>
                        </button>
                    </div>
                    <div class="tab-drag-handle">
                        <span class="dashicons dashicons-menu"></span>
                    </div>
                    <div class="tab-title-wrapper">
                        <span class="tab-title">${newTabLabel}</span>
                    </div>
                    <div class="tab-actions">
                        <button type="button" class="toggle-tab-content button-link">
                            <span class="dashicons dashicons-arrow-up-alt2"></span>
                        </button>
                    </div>
                </div>
                
                <div class="tab-content-wrapper">
                    <div class="form-grid">
                        <div class="form-field-group full-width">
                            <label>${tabTitleLabel} <span class="custom-tab-field-status" aria-live="polite"></span></label>
                            <input type="text" name="custom_product_tabs[${tabId}][title]" value="" placeholder="Contoh: Spesifikasi Lengkap" />
                        </div>
                    </div>

                    <div class="form-field-group full-width">
                        <label>${tabContentLabel} <span class="custom-tab-field-status" aria-live="polite"></span></label>
                        <div class="editor-container">
                            <textarea id="custom_tab_${tabId}_content" name="custom_product_tabs[${tabId}][content]" class="custom_tab_content"></textarea>
                        </div>
                    </div>

                    <div class="tab-footer">
                        <button type="button" class="remove_tab button button-link delete">
                            <span class="dashicons dashicons-trash"></span> ${customTabsData.labels.delete}
                        </button>
                    </div>
                </div>
            </div>
        `;

        var $newTab = $(template);
        $('.custom_tabs_container').append($newTab);

        initializeEditor(tabId, true);
        queueCustomTabsAutosave($newTab.find('.custom-tab-field-status').first());

        $('html, body').animate({
            scrollTop: $newTab.offset().top - 100
        }, 500);
    });

    // Handler untuk tombol Hapus Tab
    $(document).on('click', '.remove_tab', function (e) {
        e.preventDefault();
        e.stopPropagation();

        if (!confirm('Apakah Anda yakin ingin menghapus tab ini?')) {
            return;
        }

        var $tabContainer = $(this).closest('.custom_tab_fields');
        var tabId = $tabContainer.data('tab');
        var editorId = 'custom_tab_' + tabId + '_content';

        $tabContainer.fadeOut(300, function () {
            if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
                wp.editor.remove(editorId);
            }
            $tabContainer.remove();
            queueCustomTabsAutosave();
        });
    });

    // Expand/Collapse tab (Hanya melalui tombol toggle)
    $(document).on('click', '.toggle-tab-content', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $card = $(this).closest('.custom_tab_fields');
        var $content = $card.find('.tab-content-wrapper');
        var $icon = $(this).find('.dashicons');

        if ($content.is(':animated')) {
            return false;
        }

        if ($card.hasClass('active')) {
            // Jika sedang aktif, maka kita tutup
            $card.removeClass('active');
            $content.stop(true, true).slideUp(250);
            $icon.removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
        } else {
            // Jika sedang tutup, maka kita buka
            $card.addClass('active');
            $content.stop(true, true).slideDown(250);
            $icon.removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
        }
    });

    // Inisialisasi editor untuk tab yang sudah ada
    $('.custom_tab_fields').each(function () {
        var tabId = $(this).data('tab');
        $(this).removeClass('active');
        $(this).find('.tab-content-wrapper').hide();
        initializeEditor(tabId);
    });

    $(document).on('input', 'input[name$="[title]"]', function () {
        var val = $(this).val();
        var defaultLabel = (typeof customTabsData !== 'undefined') ? customTabsData.labels.newTab : 'Tab Baru';
        $(this).closest('.custom_tab_fields').find('.tab-title').text(val || defaultLabel);
    });
});