/**
 * FlexMile - Admin Gallery Management
 * Obsługa galerii zdjęć w edycji samochodu
 */
jQuery(document).ready(function($) {
    'use strict';

    // Zmienne globalne
    var frame;
    var galleryContainer = $('.flexmile-gallery-images');
    var galleryInput = $('#flexmile_gallery_ids');

    /**
     * Inicjalizacja sortowania (drag & drop)
     */
    function initSortable() {
        if (galleryContainer.length && typeof $.fn.sortable !== 'undefined') {
            // Jeśli sortable już istnieje, odśwież listę elementów
            if (galleryContainer.hasClass('ui-sortable')) {
                galleryContainer.sortable('refresh');
                return;
            }
            
            // Inicjalizuj sortable
            galleryContainer.sortable({
                items: '.gallery-item',
                cursor: 'move',
                tolerance: 'pointer',
                placeholder: 'gallery-item-placeholder',
                opacity: 0.8,
                revert: 100,
                forcePlaceholderSize: true,
                update: function() {
                    updateGalleryIds();
                }
            });
        }
    }

    // Inicjalizuj sortowanie przy załadowaniu
    initSortable();

    /**
     * Dodawanie zdjęć do galerii
     */
    $('#flexmile_add_gallery_images').on('click', function(e) {
        e.preventDefault();

        // Jeśli frame już istnieje, otwórz go
        if (frame) {
            frame.open();
            return;
        }

        // Stwórz nowy media frame
        frame = wp.media({
            title: 'Wybierz zdjęcia do galerii',
            button: {
                text: 'Dodaj do galerii'
            },
            multiple: true,
            library: {
                type: 'image'
            }
        });

        // Gdy zdjęcia zostaną wybrane
        frame.on('select', function() {
            var selection = frame.state().get('selection');
            var ids = getGalleryIds();

            selection.map(function(attachment) {
                attachment = attachment.toJSON();

                // Dodaj tylko jeśli jeszcze nie ma w galerii
                if (ids.indexOf(attachment.id) === -1) {
                    ids.push(attachment.id);
                    addImageToGallery(attachment);
                }
            });

            // Aktualizuj ukryte pole
            updateGalleryInput(ids);
            
            // Zamknij okno mediów po dodaniu zdjęć
            frame.close();
        });

        // Otwórz media frame
        frame.open();
    });

    /**
     * Usuwanie zdjęcia z galerii
     */
    $(document).on('click', '.remove-gallery-image', function(e) {
        e.preventDefault();

        var item = $(this).closest('.gallery-item');
        var id = item.data('id');

        // Usuń z DOM
        item.fadeOut(300, function() {
            $(this).remove();
            updateGalleryIds();
        });
    });

    /**
     * Dodaje zdjęcie do galerii (DOM)
     */
    function addImageToGallery(attachment) {
        var thumbnail = attachment.sizes && attachment.sizes.thumbnail
            ? attachment.sizes.thumbnail.url
            : attachment.url;

        var html = '<div class="gallery-item" data-id="' + attachment.id + '">' +
            '<img src="' + thumbnail + '" alt="">' +
            '<button type="button" class="remove-gallery-image" title="Usuń">&times;</button>' +
            '</div>';

        galleryContainer.append(html);
        
        // Reinicjalizuj sortowanie po dodaniu nowego elementu
        initSortable();
    }

    /**
     * Pobiera ID z galerii (z DOM)
     */
    function getGalleryIds() {
        var ids = [];
        galleryContainer.find('.gallery-item').each(function() {
            var id = $(this).data('id');
            if (id) {
                ids.push(parseInt(id));
            }
        });
        return ids;
    }

    /**
     * Aktualizuje ukryte pole z ID zdjęć
     */
    function updateGalleryIds() {
        var ids = getGalleryIds();
        updateGalleryInput(ids);
    }

    /**
     * Aktualizuje wartość w input (hidden field)
     */
    function updateGalleryInput(ids) {
        galleryInput.val(ids.join(','));
    }

    /**
     * Placeholder dla sortowania
     */
    $('<style>')
        .text('.gallery-item-placeholder { border: 2px dashed #2271b1; background: #f0f0f0; opacity: 0.5; }')
        .appendTo('head');

});
