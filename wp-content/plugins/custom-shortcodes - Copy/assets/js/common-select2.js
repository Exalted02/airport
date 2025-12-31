jQuery(document).ready(function ($) {
    $('select.common-select2').each(function () {
        $(this).select2({
            placeholder: $(this).data('placeholder') || 'Select options',
            allowClear: true,
            width: '100%',
            closeOnSelect: false
        });
    });
});
