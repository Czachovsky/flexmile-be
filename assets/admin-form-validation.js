/**
 * FlexMile - Admin Form Validation
 * Walidacja i podpowiedzi dla formularza dodawania ofert
 */
jQuery(document).ready(function($) {
    'use strict';

    // Funkcja do aktualizacji wskaźnika postępu
    window.updateProgressIndicator = function() {
        var sections = {
            gallery: $('#flexmile_gallery_ids').val() && $('#flexmile_gallery_ids').val().length > 0,
            details: $('#car_brand').val() && $('#car_model').val(),
            equipment: $('textarea[name="standard_equipment"]').val() && $('textarea[name="standard_equipment"]').val().trim().length > 0,
            pricing: $('#flexmile_rental_periods').val() && $('#flexmile_mileage_limits').val() && 
                     $('#flexmile_price_matrix input[type="number"]').filter(function() { return $(this).val() && parseFloat($(this).val()) > 0; }).length > 0,
            flags: true
        };

        var total = Object.keys(sections).length;
        var completed = Object.values(sections).filter(Boolean).length;
        var percentage = total > 0 ? Math.round((completed / total) * 100) : 0;

        // Aktualizuj wskaźnik postępu
        $('#flexmile-progress-indicator .flexmile-progress-section').each(function() {
            var sectionKey = $(this).data('section');
            var isCompleted = false;

            if (sectionKey === 'flexmile_samochod_gallery') {
                isCompleted = sections.gallery;
            } else if (sectionKey === 'flexmile_samochod_details') {
                isCompleted = sections.details;
            } else if (sectionKey === 'flexmile_samochod_wyposazenie') {
                isCompleted = sections.equipment;
            } else if (sectionKey === 'flexmile_samochod_pricing') {
                isCompleted = sections.pricing;
            } else if (sectionKey === 'flexmile_samochod_flags') {
                isCompleted = sections.flags;
            }

            var $section = $(this);
            var $statusText = $section.find('div').filter(function() {
                var style = $(this).attr('style') || '';
                return style.indexOf('font-size: 11px') !== -1 || $(this).css('font-size') === '11px';
            });
            var $checkmark = $section.find('.flexmile-section-checkmark');
            
            if (isCompleted) {
                $section.css({
                    'background': '#ecfdf3',
                    'border-color': '#22c55e'
                });
                
                // Aktualizuj tekst statusu
                if ($statusText.length) {
                    $statusText.text('✓ Uzupełnione').css('color', '#16a34a');
                }
                
                // Pokaż checkmark
                if ($checkmark.length) {
                    $checkmark.show();
                } else {
                    // Jeśli nie ma checkmarka, dodaj go
                    var $flexContainer = $section.find('div[style*="display: flex"]').first();
                    if ($flexContainer.length) {
                        $flexContainer.append('<span class="flexmile-section-checkmark" style="color: #22c55e; font-size: 18px;">✓</span>');
                    }
                }
            } else {
                $section.css({
                    'background': '#ffffff',
                    'border-color': '#e2e8f0'
                });
                
                // Aktualizuj tekst statusu
                if ($statusText.length) {
                    $statusText.text('Do wypełnienia').css('color', '#64748b');
                }
                
                // Ukryj checkmark
                $checkmark.hide();
            }
        });

        // Aktualizuj pasek postępu
        $('#flexmile-progress-indicator .progress-bar').css('width', percentage + '%');
        $('#flexmile-progress-indicator .progress-percentage').text(percentage + '%');
    };

    // Walidacja pól wymaganych
    function validateField($field, required) {
        var value = $field.val();
        var isValid = true;

        if (required && (!value || value.trim() === '')) {
            $field.addClass('flexmile-field-error');
            isValid = false;
        } else {
            $field.removeClass('flexmile-field-error');
            if (value && value.trim() !== '') {
                $field.addClass('flexmile-field-success');
                setTimeout(function() {
                    $field.removeClass('flexmile-field-success');
                }, 2000);
            }
        }

        return isValid;
    }

    // Walidacja marki i modelu
    $('#car_brand').on('change', function() {
        validateField($(this), true);
        if ($(this).val()) {
            validateField($('#car_model'), true);
        }
        updateProgressIndicator();
    });

    $('#car_model').on('change', function() {
        validateField($(this), true);
        updateProgressIndicator();
    });

    // Walidacja okresów i limitów
    $('#flexmile_rental_periods, #flexmile_mileage_limits').on('blur', function() {
        var value = $(this).val();
        var isValid = /^[\d,\s]+$/.test(value) && value.split(',').length > 0;

        if (isValid) {
            $(this).removeClass('flexmile-field-error').addClass('flexmile-field-success');
            setTimeout(function() {
                $(this).removeClass('flexmile-field-success');
            }.bind(this), 2000);
        } else {
            $(this).addClass('flexmile-field-error');
        }

        updateProgressIndicator();
    });

    // Walidacja cen w tabeli
    $('#flexmile_price_matrix').on('blur', 'input[type="number"]', function() {
        var value = parseFloat($(this).val());
        if (value && value > 0) {
            $(this).removeClass('flexmile-field-error').addClass('flexmile-field-success');
            setTimeout(function() {
                $(this).removeClass('flexmile-field-success');
            }.bind(this), 2000);
        } else if ($(this).val() && value <= 0) {
            $(this).addClass('flexmile-field-error');
        }
        updateProgressIndicator();
    });

    // Walidacja galerii
    $('#flexmile_gallery_ids').on('change', function() {
        updateProgressIndicator();
    });

    // Walidacja wyposażenia
    $('textarea[name="standard_equipment"]').on('blur', function() {
        updateProgressIndicator();
    });

    // Podpowiedzi dla pól
    function addFieldHints() {
        // Dodaj podpowiedzi do ważnych pól
        if ($('#car_brand').length && !$('#car_brand').next('.flexmile-field-hint').length) {
            $('#car_brand').after('<span class="flexmile-field-hint" title="Wybierz markę samochodu">ℹ️</span>');
        }

        if ($('#flexmile_rental_periods').length && !$('#flexmile_rental_periods').closest('.flexmile-pricing-input-group').find('.flexmile-field-hint').length) {
            $('#flexmile_rental_periods').closest('.flexmile-pricing-input-group').find('label').append('<span class="flexmile-field-hint" title="Okresy wynajmu w miesiącach, oddzielone przecinkami">ℹ️</span>');
        }
    }

    // Inicjalizacja
    addFieldHints();
    updateProgressIndicator();

    // Aktualizuj wskaźnik postępu przy zapisie
    $('#post').on('submit', function() {
        updateProgressIndicator();
    });

    // Prosty tooltip dla podpowiedzi (bez jQuery UI)
    $(document).on('mouseenter', '.flexmile-field-hint', function() {
        var $hint = $(this);
        var title = $hint.attr('title');
        
        if (title && !$hint.data('tooltip-added')) {
            // Usuń istniejący tooltip jeśli jest
            $('.flexmile-tooltip').remove();
            
            // Utwórz prosty tooltip
            var tooltip = $('<div class="flexmile-tooltip" style="position: absolute; background: #1e293b; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 12px; z-index: 10000; pointer-events: none; white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.2);"></div>');
            tooltip.text(title);
            $('body').append(tooltip);
            
            // Pozycjonuj tooltip
            var hintOffset = $hint.offset();
            var hintWidth = $hint.outerWidth();
            var tooltipWidth = tooltip.outerWidth();
            
            tooltip.css({
                top: hintOffset.top - tooltip.outerHeight() - 8 + 'px',
                left: hintOffset.left + (hintWidth / 2) - (tooltipWidth / 2) + 'px'
            });
            
            $hint.data('tooltip-added', true);
        }
    }).on('mouseleave', '.flexmile-field-hint', function() {
        $('.flexmile-tooltip').remove();
        $(this).data('tooltip-added', false);
    });
});

