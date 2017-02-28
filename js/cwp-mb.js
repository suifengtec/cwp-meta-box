var $ = jQuery.noConflict(), e_d_count = 0, Ed_array = Array;
jQuery(document).ready(function($) {
  $(window).resize(function() {
    $.each(Ed_array, function() {
      var tt = this;
      $(tt.getScrollerElement()).width(100); 
      width = $(tt.getScrollerElement()).parent().width();
      $(tt.getScrollerElement()).width(width); 
      tt.refresh();
    });
  });
});
function cwp_update_repeat_fields(){
    cwp_metabox_fields.init();
}
var cwp_metabox_fields = {
  oncefancySelect: false,
  init: function(){
    if (!this.oncefancySelect){
      this.fancySelect();
      this.oncefancySelect = true;
    }
    this.load_conditinal();
    this.load_color_picker();
    $(".cwp-mb-toggle").live('click', function() {
      $(this).parent().find('.repeater-table').toggle('slow');
    });
    $('.repeater-sortable').sortable({
      opacity: 0.7,
      revert: true,
      cursor: 'move',
      handle: '.cwp_mb_re_sort_handle',
      placeholder: 'cwp_mb_re_sort_highlight'
    });
  },
  fancySelect: function(){
    if ($().select2){
      $('.cwp-mb-select, .cwp-mb-posts-select, .cwp-mb-tax-select').each(function (){
        if(! $(this).hasClass('no-fancy'))
          $(this).select2();
      });
    }  
  },
  get_query_var: function(name){
    var match = RegExp('[?&]' + name + '=([^&#]*)').exec(location.href);
    return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
  },
 load_conditinal: function(){
    $(".cwp-mb-conditinal-control").click(function(){
      if($(this).is(':checked')){
        $(this).next().show('fast');    
      }else{
        $(this).next().hide('fast');    
      }
    });
  },
  load_color_picker: function(){
    if ($('.cwp-mb-color-iris').length>0)
      $('.cwp-mb-color-iris').wpColorPicker(); 
  },
};
window.setTimeout('cwp_metabox_fields.init();',2000);

jQuery(document).ready(function($){

  var cwpMbUpload =(function(){
    var inited;
    var file_id;
    var file_url;
    var file_type;
    function init (){
      return {
        image_frame: new Array(),
        file_frame: new Array(),
        hooks:function(){
          $(document).on('click','.cwp-mb-img-upload,.cwp-mb-file-upload', function( event ){
            event.preventDefault();
            if ($(this).hasClass('cwp-mb-file-upload'))
              inited.upload($(this),'file');
            else
              inited.upload($(this),'image');
          });

          $('.cwp-mb-uploaded-img-clear,.cwp-mb-uploaded-file-clear').live('click', function( event ){
            event.preventDefault();
            inited.set_fields($(this));
            $(inited.file_url).val("");
            $(inited.file_id).val("");
            if ($(this).hasClass('cwp-mb-uploaded-img-clear')){
              inited.set_preview('image',false);
              inited.replaceImageUploadClass($(this));
            }else{
              inited.set_preview('file',false);
              inited.replaceFileUploadClass($(this));
            }
          });     
        },
        set_fields: function (el){
          inited.file_url = $(el).prev();
          inited.file_id = $(inited.file_url).prev();
        },
        upload:function(el,utype){
          inited.set_fields(el)
          if (utype == 'image')
            inited.upload_Image($(el));
          else
            inited.upload_File($(el));
        },
        upload_File: function(el){
          var mime = $(el).attr('data-mime_type') || '';
          var ext = $(el).attr("data-ext") || false;
          var name = $(el).attr('id');
          var multi = ($(el).hasClass('multiFile')? true: false);
          
          if ( typeof inited.file_frame[name] !== "undefined")  {
            if (ext){
              inited.file_frame[name].uploader.uploader.param( 'uploadeType', ext);
              inited.file_frame[name].uploader.uploader.param( 'uploadeTypecaller', 'my_meta_box' );
            }
            inited.file_frame[name].open();
            return;
          }
  
          inited.file_frame[name] = wp.media({
            library: { type: mime },
            title: jQuery( this ).data( 'uploader_title' ),
            button: {
            text: jQuery( this ).data( 'uploader_button_text' ),
            },
            multiple: multi
          });

          inited.file_frame[name].on( 'select', function() {
            attachment = inited.file_frame[name].state().get('selection').first().toJSON();
            $(inited.file_id).val(attachment.id);
            $(inited.file_url).val(attachment.url);
            inited.replaceFileUploadClass(el);
            inited.set_preview('file',true);
          });

          inited.file_frame[name].open();
          if (ext){
            inited.file_frame[name].uploader.uploader.param( 'uploadeType', ext);
            inited.file_frame[name].uploader.uploader.param( 'uploadeTypecaller', 'my_meta_box' );
          }
        },
        upload_Image:function(el){
          var name = $(el).attr('id');
          var multi = ($(el).hasClass('multiFile')? true: false);
          if ( typeof inited.image_frame[name] !== "undefined")  {
                  inited.image_frame[name].open();
                  return;
          }
          inited.image_frame[name] =  wp.media({
            library: {
              type: 'image'
            },
            title: jQuery( this ).data( 'uploader_title' ),
            button: {
            text: jQuery( this ).data( 'uploader_button_text' ),
            },
            multiple: multi 
          });
         
          inited.image_frame[name].on( 'select', function() {
            attachment = inited.image_frame[name].state().get('selection').first().toJSON();
            $(inited.file_id).val(attachment.id);
            $(inited.file_url).val(attachment.url);
            inited.replaceImageUploadClass(el);
            inited.set_preview('image',true);
          });
          inited.image_frame[name].open();
        },
        replaceImageUploadClass: function(el){
          if ($(el).hasClass('cwp-mb-img-upload')){
            $(el).removeClass('cwp-mb-img-upload').addClass('cwp-mb-uploaded-img-clear').val(cwp_mbox.Remove_Image);
          }else{
            $(el).removeClass('cwp-mb-uploaded-img-clear').addClass('cwp-mb-img-upload').val(cwp_mbox.Upload_Image);
          }
        },
        replaceFileUploadClass: function(el){
          if ($(el).hasClass('cwp-mb-file-upload')){
            $(el).removeClass('cwp-mb-file-upload').addClass('cwp-mb-uploaded-file-clear').val(cwp_mbox.Remove_File);
          }else{
            $(el).removeClass('cwp-mb-uploaded-file-clear').addClass('cwp-mb-file-upload').val(cwp_mbox.Upload_File);
          }
        },
        set_preview: function(stype,ShowFlag){
          ShowFlag = ShowFlag || false;
          var fileuri = $(inited.file_url).val();
          if (stype == 'image'){
            if (ShowFlag)
              $(inited.file_id).prev().find('img').attr('src',fileuri).show();
            else
              $(inited.file_id).prev().find('img').attr('src','').hide();
          }else{
            if (ShowFlag)
              $(inited.file_id).prev().find('ul').append('<li><a href="' + fileuri + '" target="_blank">'+fileuri+'</a></li>');
            else
              $(inited.file_id).prev().find('ul').children().remove();
          }
        }
      }
    }
    return {
      getInstance :function(){
        if (!inited){
          inited = init();
        }
        return inited; 
      }
    }
  })()
  var cwpMbMedia = cwpMbUpload.getInstance();
  cwpMbMedia.hooks();
});