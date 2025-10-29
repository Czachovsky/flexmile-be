/**
 * FlexMile - Admin Brand/Model Dropdowns
 * Dynamiczne dropdowny z zależnością marka -> model
 */
jQuery(document).ready(function($) {
    'use strict';

    var brandSelect = $('#car_brand');
    var modelSelect = $('#car_model');
    var initialBrand = brandSelect.val();
    var initialModel = modelSelect.data('initial-model');

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
     * Obsługa zmiany marki
     */
    brandSelect.on('change', function() {
        var brandSlug = $(this).val();
        loadModels(brandSlug);
    });

    /**
     * Inicjalizacja przy edycji (jeśli marka już wybrana)
     */
    if (initialBrand) {
        loadModels(initialBrand, initialModel);
    }
});
