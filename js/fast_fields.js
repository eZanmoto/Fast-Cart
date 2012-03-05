$(function () {
    var tabContainers = $('div.fast_fields_tabs > div');
    
    $('div.fast_fields_tabs ul.fast_fields_tab_navigation a').click(function () {
        tabContainers.hide().filter(this.hash).show();
        
        $('div.fast_fields_tabs ul.fast_fields_tab_navigation a').removeClass('selected');
        $(this).addClass('selected');
        
        return false;
    }).filter(':first').click();
});