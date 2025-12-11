/**
 * FlexMile - Admin Brand/Model Dropdowns
 * Dynamiczne dropdowny z zależnością marka -> model
 */
jQuery(document).ready(function($) {
    'use strict';

    var brandSelect = $('#car_brand');
    var modelSelect = $('#car_model');
    
    if (!brandSelect.length || !modelSelect.length) {
        console.log('FlexMile: Nie znaleziono pól marki lub modelu');
        return;
    }
    
    var initialBrand = brandSelect.val();
    var initialModel = modelSelect.data('initial-model');
    
    console.log('FlexMile: Inicjalizacja - marka:', initialBrand, ', model:', initialModel);

    /**
     * Ładuje modele dla wybranej marki
     */
    function loadModels(brandSlug, selectModel) {
        if (!brandSlug) {
            modelSelect.html('<option value="">-- Najpierw wybierz markę --</option>').prop('disabled', true);
            return;
        }

        modelSelect.html('<option value="">Ładowanie...</option>').prop('disabled', true);

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'flexmile_get_models',
                brand_slug: brandSlug,
                nonce: flexmileDropdown.nonce
            },
            success: function(response) {
                if (response.success && response.data.models) {
                    var options = '<option value="">-- Wybierz model --</option>';

                    $.each(response.data.models, function(index, model) {
                        var selected = (selectModel && selectModel === model) ? ' selected' : '';
                        options += '<option value="' + model + '"' + selected + '>' + model + '</option>';
                    });

                    modelSelect.html(options).prop('disabled', false);
                    
                    // Jeśli model był już wybrany, zaktualizuj tytuł
                    if (selectModel) {
                        setTimeout(function() {
                            updatePostTitle();
                        }, 200);
                    } else {
                        // Nawet jeśli model nie był wybrany, zaktualizuj tytuł z samą marką
                        setTimeout(function() {
                            updatePostTitle();
                        }, 100);
                    }
                } else {
                    modelSelect.html('<option value="">Nie znaleziono modeli</option>').prop('disabled', true);
                }
            },
            error: function() {
                modelSelect.html('<option value="">Błąd ładowania modeli</option>').prop('disabled', true);
            }
        });
    }

    /**
     * Aktualizuje tytuł wpisu na podstawie marki i modelu
     * (Działa tylko z klasycznym edytorem - Gutenberg jest wyłączony)
     */
    function updatePostTitle() {
        var brandText = brandSelect.find('option:selected').text().trim();
        var modelText = modelSelect.find('option:selected').text().trim();
        
        // Pomiń opcje placeholder
        if (brandText.indexOf('--') === 0 || brandText === 'Ładowanie...' || !brandText) {
            brandText = '';
        }
        if (modelText.indexOf('--') === 0 || modelText === 'Nie znaleziono modeli' || modelText === 'Błąd ładowania modeli' || modelText === 'Ładowanie...' || !modelText) {
            modelText = '';
        }

        var newTitle = '';
        if (brandText && modelText) {
            newTitle = brandText + ' ' + modelText;
        } else if (brandText) {
            newTitle = brandText;
        }

        if (!newTitle) {
            console.log('FlexMile: Brak danych do aktualizacji tytułu (marka:', brandText, ', model:', modelText, ')');
            return;
        }

        // Klasyczny edytor WordPress
        var titleInput = $('#title, input[name="post_title"]').first();
        if (titleInput.length) {
            titleInput.val(newTitle);
            titleInput.trigger('input').trigger('change');
            
            // Usuń placeholder jeśli istnieje
            var titlePrompt = $('#title-prompt-text');
            if (titlePrompt.length) {
                titlePrompt.hide();
            }
            
            console.log('FlexMile: Tytuł zaktualizowany na:', newTitle);
            return;
        }

        console.log('FlexMile: Nie znaleziono pola tytułu');
    }

    /**
     * Obsługa zmiany marki
     */
    brandSelect.on('change', function() {
        var brandSlug = $(this).val();
        var brandName = $(this).find('option:selected').text().trim();
        
        console.log('FlexMile: Zmieniono markę na:', brandName);
        
        loadModels(brandSlug);
        
        // Aktualizuj tytuł od razu z marką (przed załadowaniem modeli)
        if (brandName && brandName.indexOf('--') !== 0) {
            setTimeout(function() {
                updatePostTitle();
            }, 50);
        }
    });

    /**
     * Obsługa zmiany modelu
     */
    modelSelect.on('change', function() {
        var modelName = $(this).find('option:selected').text().trim();
        console.log('FlexMile: Zmieniono model na:', modelName);
        
        // Opóźnij trochę, aby upewnić się, że wartość jest już ustawiona
        setTimeout(function() {
            updatePostTitle();
        }, 100);
    });

    /**
     * Inicjalizacja przy edycji (jeśli marka już wybrana)
     */
    if (initialBrand) {
        loadModels(initialBrand, initialModel);
        // Aktualizuj tytuł po załadowaniu modeli
        setTimeout(function() {
            updatePostTitle();
        }, 800);
    } else {
        // Nawet jeśli marka nie jest wybrana, sprawdź czy tytuł jest pusty i ustaw placeholder
        var titleInput = $('#title');
        if (titleInput.length && !titleInput.val().trim()) {
            var titlePrompt = $('#title-prompt-text');
            if (titlePrompt.length) {
                titlePrompt.show();
            }
        }
    }

    // Aktualizuj tytuł również przy ręcznej zmianie (na wypadek gdyby użytkownik chciał go zmienić)
    $('#title').on('focus', function() {
        // Pozwól użytkownikowi edytować tytuł ręcznie
    });
    
    // Fallback - aktualizacja po 1 sekundzie (na wypadek problemów z timingiem)
    setTimeout(function() {
        if (initialBrand && initialModel) {
            console.log('FlexMile: Fallback - aktualizacja tytułu');
            updatePostTitle();
        }
    }, 1000);
});
