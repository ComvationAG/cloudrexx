/**
 * This file is loaded by the abstract SystemComponentBackendController
 * You may add own JS files using
 * \JS::registerJS(substr($this->getDirectory(false, true) . '/View/Script/FileName.css', 1));
 * or remove this file if you don't need it
 */

function updateOption(optionName,optionData, callback){
    jQuery.post( "index.php?cmd=JsonData&object=TemplateEditor&act=updateOption&tid="+cx.variables.get('themeid','TemplateEditor'), { optionName: optionName, optionData:optionData }, function (reponse) {
        if (reponse.status != 'error'){
            jQuery("#preview-template-editor").attr('src', jQuery("#preview-template-editor").get(0).contentWindow.location.href);
        }
        callback(reponse);
    }, "json");
}

var saveOptions = function (){
    jQuery(this).addClass('spinner');
    var that = this;
    jQuery.post( "index.php?cmd=JsonData&object=TemplateEditor&act=saveOptions&tid="+cx.variables.get('themeid','TemplateEditor'), {}, function (data) {
        jQuery(that).removeClass('spinner');
    }, "json");
};

jQuery(function(){

    jQuery('.option.view .buttons button').click(function(){
        jQuery("#preview-template-editor").css({'width': jQuery(this).data('size')});
        jQuery('.option.view .buttons button').removeClass('active');
        jQuery(this).addClass('active');
    });

    jQuery('#saveOptionsButton').click(saveOptions);
    jQuery('#layout').change(function(){
        location.href = location.href.replace("tid="+cx.variables.get('themeid','TemplateEditor'), "tid="+jQuery(this).val());
    })
});
