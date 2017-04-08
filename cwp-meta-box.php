<?php 
/**
 * WordPress Custom Meta Box Class for AboutCG
 * @version  1.1 基于 AT_Meta_Box http://bbs.coolwp.org/index.php?/topic/108-wordpress-metabox/
 */

if ( ! class_exists( 'CWP_Meta_Box') ) :

class CWP_Meta_Box {
  
  /**
   * Holds meta box object
   */
  protected $_meta_box;
  
  /**
   * Holds meta box fields.
   */
  protected $_prefix;

  /**
   * does it work with WooCommerce?
   */
  protected $_with_wc;  
  /**
   * Holds Prefix for meta box fields.
   */
  protected $_fields;
  
  /**
   * _selfPath to allow themes as well as plugins.
   */
  protected $_selfPath;
  
  /**
   * $field_types  holds used field types
   */
  public $field_types = array();

  /**
   * $isInGroup  holds groupping boolean
   */
  public $isInGroup = false;


  public function __construct ( $meta_box ) {

    if ( ! is_admin() ){
      return;
    }
    

    add_filter('init', array($this,'load_textdomain'));

    $this->_meta_box = $meta_box;
    $this->_prefix = (isset($meta_box['prefix'])) ? $meta_box['prefix'] : 'mb_'; 

    $this->_with_wc = (isset($meta_box['with_wc'])) ? $meta_box['with_wc'] : false;

    $this->_fields = $this->_meta_box['fields'];

    $this->add_missed_values();

    if (isset($meta_box['with_theme'])&&$meta_box['with_theme']){

        $this->_selfPath = apply_filters('cwp_mb_base_uri', get_stylesheet_directory_uri() . '/cwp-meta-box', $meta_box['with_theme']);
    }else{

        $this->_selfPath = plugin_dir_url(  __FILE__  );
    }
    

    add_action( 'add_meta_boxes', array( $this, 'add' ) );
    add_action( 'save_post', array( $this, 'save' ) );

    add_action( 'admin_enqueue_scripts', array( $this, 'wp_admin_scripts' ),11 );

    add_filter('wp_handle_upload_prefilter', array($this,'Validate_upload_file_type'),10,1);

  }
  

  public function wp_admin_scripts( $hook ) {

      global $typenow;
      /*
      检查文章类型和是否为发布/编辑页面
       */
      if(!in_array($typenow,$this->_meta_box['pages'])||!$this->is_edit_page()){
        return ;
      }
      $plugin_path = $this->_selfPath;

     /* wp_enqueue_style( 'cwp-mb-css', $plugin_path . '/css/cwp-mb.css?t='.time() );*/
      wp_enqueue_style( 'cwp-mb-css', $plugin_path . '/css/cwp-mb.min.css' );
     /* wp_enqueue_script( 'cwp-mb-js', $plugin_path . '/js/cwp-mb.js', array( 'jquery' ), null, true );*/
      wp_enqueue_script( 'cwp-mb-js', $plugin_path . '/js/cwp-mb.min.js', array( 'jquery' ), null, true );
      
      $l10n = array(
        'Upload_File' => __('Upload File','cpmb'),
        'Remove_File' => __('Remove File','cpmb'),
        'Upload_Image' => __('Upload Image','cpmb'),
        'Remove_Image' => __('Remove Image','cpmb'),
        );
      wp_localize_script( 'cwp-mb-js', 'cwp_mbox', $l10n );

      if ($this->has_field('image') || $this->has_field('file')){

        wp_enqueue_script( 'media-upload' );
        add_thickbox();
        wp_enqueue_script( 'jquery-ui-core' );
        wp_enqueue_script( 'jquery-ui-sortable' );

      }


      // Check for special fields and add needed actions for them.
      foreach (array('upload','color','select') as $type) {
        call_user_func ( array( $this, 'check_field_' . $type ));
      }
   
    
  }
  
  /**
   * Check the Field select, Add needed Actions
   * @access public
   */
  public function check_field_select() {
    
      if ( ! $this->has_field( 'select' )&&!$this->has_field('taxonomy')){
        return;
      }

      if(!$this->_with_wc){

        $plugin_path = $this->_selfPath;
        wp_enqueue_style('cwp-mb-select2-css', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css');
        wp_enqueue_style('cwp-mb-select2-custom-css',  $plugin_path.'css/select2-custom.css');
        wp_enqueue_script('select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/i18n/zh-CN.js', array('jquery'), false, true);

      }else{
      /*	 wp_enqueue_script('select2', '//cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/i18n/zh-CN.js', array('jquery'), false, false);*/
        /* wp_enqueue_script('select2');*/
      }

  }

  /**
   * Check the Field Upload, Add needed Actions
   *
   * @access public
   */
  public function check_field_upload() {
    
    // Check if the field is an image or file. If not, return.
    if ( ! $this->has_field( 'image' ) && ! $this->has_field( 'file' ) ){
      return;
    }
    
    // Add data encoding type for file uploading.  
    add_action( 'post_edit_form_tag', array( $this, 'add_enctype' ) );
    
  }
  
  /**
   * Add data encoding type for file uploading
   *
   * @access public
   */
  public function add_enctype () {
    printf(' enctype="multipart/form-data" encoding="multipart/form-data" ');
  }
  
  /**
   * Check Field Color
   *
   * @access public
   */
  public function check_field_color() {
    
    if ( $this->has_field( 'color' ) && $this->is_edit_page() ) {
      wp_enqueue_style( 'wp-color-picker' );
      wp_enqueue_script( 'wp-color-picker' );
    }
  }
  

  

  
  /**
   * Add Meta Box for multiple post types.
   *
   * @access public
   */
  public function add($postType) {

    if(in_array($postType, $this->_meta_box['pages'])){

      add_meta_box( $this->_meta_box['id'], $this->_meta_box['title'], array( $this, 'show' ),$postType, $this->_meta_box['context'], $this->_meta_box['priority'] );

    }
  }
  
  /**
   * Callback function to show fields in meta box.
   *
   * @access public 
   */
  public function show() {

    $this->isInGroup = false;
    global $post;

    wp_nonce_field( basename(__FILE__), 'cwp_mb_nonce' );
    echo '<table class="form-table cwp-mb-wrapper">';
    foreach ( $this->_fields as $field ) {
      $field['multiple'] = isset($field['multiple']) ? $field['multiple'] : false;
      $meta = get_post_meta( $post->ID, $field['id'], !$field['multiple'] );
      $meta = ( $meta !== '' ) ? $meta : @$field['std'];

      if (!in_array($field['type'], array('image', 'repeater','file')))
        $meta = is_array( $meta ) ? array_map( 'esc_attr', $meta ) : esc_attr( $meta );
      
      if ($this->isInGroup !== true){
        echo '<tr>';
      }

      if (isset($field['group']) && $field['group'] == 'start'){
        $this->isInGroup = true;
        echo '<td><table class="form-table"><tr>';
      }
      
      call_user_func ( array( $this, 'show_field_' . $field['type'] ), $field, $meta );

      if ($this->isInGroup === true){
        if(isset($field['group']) && $field['group'] == 'end'){
          echo '</tr></table></td></tr>';
          $this->isInGroup = false;
        }
      }else{
        echo '</tr>';
      }
    }
    echo '</table>';
  }
  
  /**
   * Show Repeater Fields.
   * @param string $field 
   * @param string $meta 
   * @access public
   */
  public function show_field_repeater( $field, $meta ) {
    global $post;  
    // Get Plugin Path
    $plugin_path = $this->_selfPath;
    $this->show_field_begin( $field, $meta );
    $class = '';
      if ($field['sortable'])  
        $class = " repeater-sortable";
    echo "<div class='cwp-mb-repeat".$class."' id='{$field['id']}'>";
    
    $c = 0;
    $meta = get_post_meta($post->ID,$field['id'],true);
    
      if (count($meta) > 0 && is_array($meta) ){
         foreach ($meta as $me){
           //for labling toggles
           $mmm =  isset($me[$field['fields'][0]['id']])? $me[$field['fields'][0]['id']]: "";
           if ( in_array( $field['fields'][0]['type'], array('image','file') ) )
            $mmm = $c +1 ;
          /*
            '.$mmm.'<br/>
            <span class="cwp-repeater-item-title">'.$mmm.'</span>
            <div class="cwp-repeater-item-title">'.$mmm.'</div>
          */
           echo '<div class="cwp-mb-repater-block" title="'.$mmm.'"><span class="cwp-repeater-item-title">'.$mmm.'</span><table class="repeater-table" style="display: none;">';
         if ($field['inline']){
           echo '<tr class="cwp-mb-inline" valign="top">';
         }
        foreach ($field['fields'] as $f){
          //reset var $id for repeater
          $id = '';
          $id = $field['id'].'['.$c.']['.$f['id'].']';
          $m = isset($me[$f['id']]) ? $me[$f['id']]: '';
          $m = ( $m !== '' ) ? $m : $f['std'];
          if ('image' != $f['type'] && $f['type'] != 'repeater')
            $m = is_array( $m) ? array_map( 'esc_attr', $m ) : esc_attr( $m);

          $f['id'] = $id;
          if (!$field['inline']){
            echo '<tr>';
          } 
          call_user_func ( array( $this, 'show_field_' . $f['type'] ), $f, $m);
          if (!$field['inline']){
            echo '</tr>';
          } 
        }
        if ($field['inline']){  
          echo '</tr>';
        }
        echo '</table>';

        if ($field['sortable']){
          echo '<span class="re-control cwp-mb-sort-btn-container"><span class="dashicons dashicons-move cwp_mb_re_sort_handle"  title="'.__('Sort','cpmb').'" ></span></span>';
        }

        echo '<span class="re-control cwp-mb-edit-btn-container cwp-mb-toggle"><span class="dashicons dashicons-edit" title="'.__('Edit').'"></span></span><span class="re-control cwp-mb-remove-btn-container"><span class="dashicons dashicons-no"  title="'.__('Remove','cpmb').'" id="remove-'.$field['id'].'"></span></span><span class="re-control-clear"></span></div>';
        $c = $c + 1;
        }
      }

    echo '<div class="cwp-md-repeater-add-btn-container" id="add-'.$field['id'].'" title="'.__('Add','cpmb').'" ><span class="dashicons dashicons-plus"></span></div></div>';
    
    //create all fields once more for js function and catch with object buffer
    ob_start();
    echo '<div class="cwp-mb-repater-block"><table class="repeater-table">';
    if ($field['inline']){
      echo '<tr class="cwp-mb-inline" valign="top">';
    } 
    foreach ($field['fields'] as $f){
      //reset var $id for repeater
      $id = '';
      $id = $field['id'].'[CurrentCounter]['.$f['id'].']';
      $f['id'] = $id; 
      if (!$field['inline']){
        echo '<tr>';
      }
      if ($f['type'] != 'wysiwyg'){
        call_user_func ( array( $this, 'show_field_' . $f['type'] ), $f, '');
      } else{
        call_user_func ( array( $this, 'show_field_' . $f['type'] ), $f, '',true);
      }
      if (!$field['inline']){
        echo '</tr>';
      }  
    }
    if ($field['inline']){
      echo '</tr>';
    } 


    echo '</table> <div class="cwp-mb-remove-btn-container"><span class="dashicons dashicons-no"  title="'.__('Remove','cpmb').'" id="remove-'.$field['id'].'"></span></div> </div>';


    $counter = 'countadd_'.$field['id'];
    $js_code = ob_get_clean ();
    $js_code = str_replace("\n","",$js_code);
    $js_code = str_replace("\r","",$js_code);
    $js_code = str_replace("'","\"",$js_code);
    $js_code = str_replace("CurrentCounter","' + ".$counter." + '",$js_code);
    echo '<script>
        jQuery(document).ready(function() {
          var '.$counter.' = '.$c.';
          jQuery("#add-'.$field['id'].'").live(\'click\', function() {
            '.$counter.' = '.$counter.' + 1;
            jQuery(this).before(\''.$js_code.'\');            
            cwp_update_repeat_fields();
          });
              jQuery("#remove-'.$field['id'].'").live(\'click\', function() {
                  jQuery(this).parent().parent().remove();
              });
          });
        </script>';
  
    $this->show_field_end($field, $meta);
  }
  
  /**
   * Begin Field.
   *
   * @param string $field 
   * @param string $meta 
   * @access public
   */
  public function show_field_begin( $field, $meta) {
    echo "<td class='cwp-mb-field'".(($this->isInGroup === true)? " valign='top'": "").">";
    if ( !empty($field['name']) ) {
      echo "<div class='cwp-mb-label'>";
        echo "<label for='{$field['id']}'>{$field['name']}</label>";
      echo "</div>";
    }
  }
  
  /**
   * End Field.
   *
   * @param string $field 
   * @param string $meta 
   * @access public 
   */
  public function show_field_end( $field, $meta=NULL ,$group = false) {


    //print description
    if ( isset($field['desc']) && $field['desc'] != '' )
      echo "<div class='desc-field'>{$field['desc']}</div>";
    echo "</td>";
  }
  
  /**
   * Show Field Text.
   *
   * @param string $field 
   * @param string $meta 
   * @access public
   */
  public function show_field_text( $field, $meta) {  
    $this->show_field_begin( $field, $meta );
    echo "<input type='text' class='cwp-text ".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' value='{$meta}' ".( isset($field['style'])? "style='{$field['style']}'" : '' )."/>";
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Field number.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_number( $field, $meta) {  
    $this->show_field_begin( $field, $meta );
    $step = (isset($field['step']) || $field['step'] != '1')? "step='".$field['step']."' ": '';
    $min = isset($field['min'])? "min='".$field['min']."' ": '';
    $max = isset($field['max'])? "max='".$field['max']."' ": '';
    echo "<input type='number' class='cwp-mb-number".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' value='{$meta}' size='30' ".$step.$min.$max.( isset($field['style'])? "style='{$field['style']}'" : '' )."/>";
    $this->show_field_end( $field, $meta );
  }

  /**
   * Show Field code editor.
   *
   * @param string $field 
   * @author Ohad Raz
   * @param string $meta 
   * @since 2.1
   * @access public
   */
  public function show_field_code( $field, $meta) {
    $this->show_field_begin( $field, $meta );
    echo "<textarea class='code_text".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' data-lang='{$field['syntax']}' ".( isset($field['style'])? "style='{$field['style']}'" : '' )." data-theme='{$field['theme']}'>{$meta}</textarea>";
    $this->show_field_end( $field, $meta );
  }
  
  
  /**
   * Show Field hidden.
   *
   * @param string $field 
   * @param string|mixed $meta 
   * @since 0.1.3
   * @access public
   */
  public function show_field_hidden( $field, $meta) {  
    //$this->show_field_begin( $field, $meta );
    echo "<input type='hidden' ".( isset($field['style'])? "style='{$field['style']}' " : '' )."class='cwp-text".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' value='{$meta}'/>";
    //$this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Field Paragraph.
   *
   * @param string $field 
   * @since 0.1.3
   * @access public
   */
  public function show_field_paragraph( $field) {  
    //$this->show_field_begin( $field, $meta );
    echo '<p>'.$field['value'].'</p>';
    //$this->show_field_end( $field, $meta );
  }
    
  /**
   * Show Field Textarea.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_textarea( $field, $meta ) {
    $this->show_field_begin( $field, $meta );
      echo "<textarea class='cwp-textarea large-text".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." cols='60' rows='10'>{$meta}</textarea>";
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Field Select.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_select( $field, $meta ) {
    
    if ( ! is_array( $meta ) ) 
      $meta = (array) $meta;
      
    $this->show_field_begin( $field, $meta );
      echo "<select ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='cwp-mb-select".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}" . ( $field['multiple'] ? "[]' id='{$field['id']}' multiple='multiple'" : "'" ) . ">";
      foreach ( $field['options'] as $key => $value ) {
        echo "<option value='{$key}'" . selected( in_array( $key, $meta ), true, false ) . ">{$value}</option>";
      }
      echo "</select>";
    $this->show_field_end( $field, $meta );
    
  }
  
  /**
   * Show Radio Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public 
   */
  public function show_field_radio( $field, $meta ) {
    
    if ( ! is_array( $meta ) )
      $meta = (array) $meta;
      
    $this->show_field_begin( $field, $meta );

      echo '<div class="weui-cells weui-cells_radio">';
      foreach ( $field['options'] as $key => $value ) {


        echo "<label class='weui-cell weui-check__label'><div class='weui-cell__bd'><p>{$value}</p></div><div class='weui-cell__ft'><input type='radio' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='weui-check' name='{$field['id']}' value='{$key}'" . checked( in_array( $key, $meta ), true, false ) . " /><span class='weui-icon-checked'></span></div></label>";

/*        echo "<label class='weui-cell weui-check__label'><div class='weui-cell__hd'><input type='radio' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='weui-check' name='{$field['id']}' value='{$key}'" . checked( in_array( $key, $meta ), true, false ) . " /><span class='weui-icon-checked'></span></div><div class='weui-cell__hd'><p>{$value}</p></div></label>";*/




      }
      echo '</div>';
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Checkbox Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_checkbox( $field, $meta ) {
    $this->show_field_begin($field, $meta);

/*
    echo "<input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='rw-checkbox".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}'" . checked(!empty($meta), true, false) . " />";*/

    /*
    WeUI : on || ''
     */
    
/*    var_dump($field);*/


    echo "<label class='weui-switch-cp'><input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='weui-switch-cp__input' name='{$field['id']}' id='{$field['id']}'" . checked(!empty($meta), true, false) . " /><div class='weui-switch-cp__box'></div></label>";
    $this->show_field_end( $field, $meta );

      
  }
  
  /**
   * Show Wysiwig Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_wysiwyg( $field, $meta,$in_repeater = false ) {
    $this->show_field_begin( $field, $meta );
    
    if ( $in_repeater )
      echo "<textarea class='cwp-mb-wysiwyg theEditor large-text".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' cols='60' rows='10'>{$meta}</textarea>";
    else{
      // Use new wp_editor() since WP 3.3
      $settings = ( isset($field['settings']) && is_array($field['settings'])? $field['settings']: array() );
      $settings['editor_class'] = 'cwp-mb-wysiwyg'.( isset($field['class'])? ' ' . $field['class'] : '' );
      $id = str_replace( "_","",$this->stripNumeric( strtolower( $field['id']) ) );
      wp_editor( html_entity_decode($meta), $id, $settings);
    }
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show File Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_file( $field, $meta ) {
    wp_enqueue_media();
    $this->show_field_begin( $field, $meta );

    $std      = isset($field['std'])? $field['std'] : array('id' => '', 'url' => '');
    $multiple = isset($field['multiple'])? $field['multiple'] : false;
    $multiple = ($multiple)? "multiFile '" : "";
    $name     = esc_attr( $field['id'] );
    $value    = isset($meta['id']) ? $meta : $std;
    $has_file = (empty($value['url']))? false : true;
    $type     = isset($field['mime_type'])? $field['mime_type'] : '';
    $ext      = isset($field['ext'])? $field['ext'] : '';
    $type     = (is_array($type)? implode("|",$type) : $type);
    $ext      = (is_array($ext)? implode("|",$ext) : $ext);
    $id       = $field['id'];
    $li       = ($has_file)? "<li><a href='{$value['url']}' target='_blank'>{$value['url']}</a></li>": "";

    echo "<span class='cwp-mb-uploaded-file-url'><ul>{$li}</ul></span>";
    echo "<input type='hidden' name='{$name}[id]' value='{$value['id']}'/>";
    echo "<input type='hidden' name='{$name}[url]' value='{$value['url']}'/>";
    if ($has_file)
      echo "<input type='button' class='{$multiple} button cwp-mb-uploaded-file-clear' id='{$id}' value='".__('Remove File','cpmb')."' data-mime_type='{$type}' data-ext='{$ext}'/><br><br>";
    else
      echo "<input type='button' class='{$multiple} button cwp-mb-file-upload' id='{$id}' value='".__('Upload File','cpmb')."' data-mime_type='{$type}' data-ext='{$ext}'/><br><br>";

    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Image Field.
   *
   * @param array $field 
   * @param array $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_image( $field, $meta ) {
    wp_enqueue_media();
    $this->show_field_begin( $field, $meta );
        
    $std          = isset($field['std'])? $field['std'] : array('id' => '', 'url' => '');
    $name         = esc_attr( $field['id'] );
    $value        = isset($meta['id']) ? $meta : $std;
    
    $value['url'] = isset($meta['src'])? $meta['src'] : $value['url']; //backwords capability
    $has_image    = empty($value['url'])? false : true;
    $w            = isset($field['width'])? $field['width'] : 'auto';
    $h            = isset($field['height'])? $field['height'] : 'auto';
    $PreviewStyle = "style='width: $w; height: $h;". ( (!$has_image)? "display: none;'": "'");
    $id           = $field['id'];
    $multiple     = isset($field['multiple'])? $field['multiple'] : false;
    $multiple     = ($multiple)? "multiFile " : "";
/*
DEV
simplePanelImagePreview
 */
    echo "<span class='cwp-mb-uploaded-img-preview'><img {$PreviewStyle} src='{$value['url']}'><br/></span>";
    echo "<input type='hidden' name='{$name}[id]' value='{$value['id']}'/>";
    echo "<input type='hidden' name='{$name}[url]' value='{$value['url']}'/>";

    if ($has_image)
      echo "<input class='{$multiple} button  cwp-mb-uploaded-img-clear' id='{$id}' value='".__('Remove Image','cpmb')."' type='button'/><br><br>";
    else
      echo "<input class='{$multiple} button cwp-mb-img-upload' id='{$id}' value='".__('Upload Image','cpmb')."' type='button'/><br><br>";
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show Color Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_color( $field, $meta ) {
    
    if ( empty( $meta ) ) 
      $meta = '#';
      
    $this->show_field_begin( $field, $meta );
    if( wp_style_is( 'wp-color-picker', 'registered' ) ) { //iris color picker since 3.5
      echo "<input class='cwp-mb-color-iris".(isset($field['class'])? " {$field['class']}": "")."' type='text' name='{$field['id']}' id='{$field['id']}' value='{$meta}' size='8' />";  
    }else{
      echo "<input class='cwp-mb-color".(isset($field['class'])? " {$field['class']}": "")."' type='text' name='{$field['id']}' id='{$field['id']}' value='{$meta}' size='8' />";
      echo "<input type='button' class='cwp-mb-color-select button' rel='{$field['id']}' value='" . __( 'Select a color' ,'apc') . "'/>";
      echo "<div style='display:none' class='cwp-mb-color-picker' rel='{$field['id']}'></div>";
    }
    $this->show_field_end($field, $meta);
    
  }

  /**
   * Show Checkbox List Field
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public
   */
  public function show_field_checkbox_list( $field, $meta ) {

 /*       
        */
    if ( ! is_array( $meta ) ) 
      $meta = (array) $meta;
      
    $this->show_field_begin($field, $meta);
    
      $html = array();
      echo '<div class="weui-cells weui-cells_checkbox">';
      foreach ($field['options'] as $key => $value) {

        $html[] = "<label class='weui-cell weui-check__label'>
          <div class='weui-cell__hd'><input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )."  class='weui-check' name='{$field['id']}[]' value='{$key}'" . checked( in_array( $key, $meta ), true, false ) . " /><i class='weui-icon-checked'></i></div><div class='weui-cell__hd'><p>{$value}</p></div></label>";

      }
    
      echo implode( '<br />' , $html );
      echo '</div>';
    $this->show_field_end($field, $meta);
    
  }
  
  /**
   * Show Date Field.
   *
   * @param string $field 
   * @param string $meta 
   * @access public
   */
  public function show_field_date( $field, $meta ) {
    $this->show_field_begin( $field, $meta );

     echo "<input type='date'  ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='cwp-mb-time".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' rel='{$field['format']}' value='{$meta}' size='30' />";
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Show time field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 1.0
   * @access public 
   */
  public function show_field_time( $field, $meta ) {
    $this->show_field_begin( $field, $meta );
      $ampm = ($field['ampm'])? 'true' : 'false';

      echo "<input type='time'  ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='cwp-mb-time".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}' id='{$field['id']}' data-ampm='{$ampm}' rel='{$field['format']}' value='{$meta}' size='30' />";


    $this->show_field_end( $field, $meta );
  }
  
   /**
   * Show Posts field.
   * used creating a posts/pages/custom types checkboxlist or a select dropdown
   * @param string $field 
   * @param string $meta 
   * @access public 
   */
  public function show_field_posts($field, $meta) {
    global $post;
    
    if (!is_array($meta)) $meta = (array) $meta;
    $this->show_field_begin($field, $meta);
    $options = $field['options'];
    $posts = get_posts($options['args']);
    // checkbox_list
    if ('checkbox_list' == $options['type']) {

    	 echo '<div class="weui-cells weui-cells_checkbox">';
      foreach ($posts as $p) {

        echo "<label class='weui-cell weui-check__label'><div class='weui-cell__hd'><input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='weui-check' name='{$field['id']}[]' value='$p->ID'" .  checked(in_array($p->ID, $meta), true, false) . " /><i class='weui-icon-checked'></i></div><div class='weui-cell__hd'><p>$p->post_title</p></div></label>";

      }

       echo '</div>';

    }
    // select
    else {
      echo "<select ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='cwp-mb-posts-select".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}" . ($field['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'") . ">";
      if (isset($field['emptylabel']))
        echo '<option value="-1">'.(isset($field['emptylabel'])? $field['emptylabel']: __('Select ...','cpmb')).'</option>';
      foreach ($posts as $p) {
        echo "<option value='$p->ID'" . selected(in_array($p->ID, $meta), true, false) . ">$p->post_title</option>";
      }
      echo "</select>";
    }
    
    $this->show_field_end($field, $meta);
  }
  
  /**
   * Show Taxonomy field.
   * used creating a category/tags/custom taxonomy checkboxlist or a select dropdown
   * @param string $field 
   * @param string $meta 
   * @access public 
   * 
   * @uses get_terms()
   */
  public function show_field_taxonomy($field, $meta) {
    global $post;
    
    if (!is_array($meta)) $meta = (array) $meta;
    $this->show_field_begin($field, $meta);
    $options = $field['options'];

    $terms = get_terms($options['taxonomy'], $options['args']);
    

    if ('checkbox_list' == $options['type']) {

      echo '<div class="weui-cells weui-cells_checkbox">';
      foreach ($terms as $term) {
        echo "<label class='weui-cell weui-check__label'><div class='weui-cell__hd'><input type='checkbox' ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='weui-check' name='{$field['id']}[]' value='$term->slug'" . checked(in_array($term->slug, $meta), true, false) . " /><i class='weui-icon-checked'></i></div><div class='weui-cell__hd'><p>$term->name</p></div></label>";
      }
      echo '</div>';
    }
    // select
    else {
/*



$field['multiple']
$field['options']['multiple']
 */

   /*   var_dump( $field  );*/

      echo "<select ".( isset($field['style'])? "style='{$field['style']}' " : '' )." class='cwp-mb-tax-select".( isset($field['class'])? ' ' . $field['class'] : '' )."' name='{$field['id']}" . ($field['options']['multiple'] ? "[]' multiple='multiple' style='height:auto'" : "'") . " >";
      foreach ($terms as $term) {
        echo "<option value='$term->slug'" . selected(in_array($term->slug, $meta), true, false) . ">$term->name</option>";
      }
      echo "</select>";
    }
    
    $this->show_field_end($field, $meta);
  }

  /**
   * Show conditinal Checkbox Field.
   *
   * @param string $field 
   * @param string $meta 
   * @since 2.9.9
   * @access public
   */
  public function show_field_cond( $field, $meta ) {
  
    $this->show_field_begin($field, $meta);
    $checked = false;
    if (is_array($meta) && isset($meta['enabled']) && $meta['enabled'] == 'on'){
      $checked = true;
    }
    echo "<input type='checkbox' class='cwp-mb-conditinal-control' name='{$field['id']}[enabled]' id='{$field['id']}'" . checked($checked, true, false) . " />";
    //start showing the fields
    $display = ($checked)? '' :  ' style="display: none;"';
    
    echo '<div class="cwp-mb-conditinal-container" '.$display.'><table>';
    foreach ((array)$field['fields'] as $f){
      //reset var $id for cond
      $id = '';
      $id = $field['id'].'['.$f['id'].']';
      $m = '';
      $m = (isset($meta[$f['id']])) ? $meta[$f['id']]: '';
      $m = ( $m !== '' ) ? $m : (isset($f['std'])? $f['std'] : '');
      if ('image' != $f['type'] && $f['type'] != 'repeater')
        $m = is_array( $m) ? array_map( 'esc_attr', $m ) : esc_attr( $m);
        //set new id for field in array format
        $f['id'] = $id;
        echo '<tr>';
        call_user_func ( array( $this, 'show_field_' . $f['type'] ), $f, $m);
        echo '</tr>';
    }
    echo '</table></div>';
    $this->show_field_end( $field, $meta );
  }
  
  /**
   * Save Data from Metabox
   *
   * @param string $post_id 
   * @since 1.0
   * @access public 
   */
  public function save( $post_id ) {

    global $post_type;
    
    $post_type_object = get_post_type_object( $post_type );

    if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )                      // Check Autosave
    || ( ! isset( $_POST['post_ID'] ) || $post_id != $_POST['post_ID'] )        // Check Revision
    || ( ! in_array( $post_type, $this->_meta_box['pages'] ) )                  // Check if current post type is supported.
    || ( ! check_admin_referer( basename( __FILE__ ), 'cwp_mb_nonce') )    // Check nonce - Security
    || ( ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) )  // Check permission
    {
      return $post_id;
    }
    
    foreach ( $this->_fields as $field ) {
      
      $name = $field['id'];
      $type = $field['type'];
      $old = get_post_meta( $post_id, $name, ! $field['multiple'] );
      $new = ( isset( $_POST[$name] ) ) ? $_POST[$name] : ( ( $field['multiple'] ) ? array() : '' );
            

      // Validate meta value
/*      if ( class_exists( 'CWP_Meta_Box_Validate' ) && method_exists( 'CWP_Meta_Box_Validate', $field['validate_func'] ) ) {
        $new = call_user_func( array( 'CWP_Meta_Box_Validate', $field['validate_func'] ), $new );
      }*/
      
      //skip on Paragraph field
      if ($type != "paragraph"){

        // Call defined method to save meta value, if there's no methods, call common one.
        $save_func = 'save_field_' . $type;
        if ( method_exists( $this, $save_func ) ) {
          call_user_func( array( $this, 'save_field_' . $type ), $post_id, $field, $old, $new );
        } else {
          $this->save_field( $post_id, $field, $old, $new );
        }
      }
      
    } // End foreach
  }
  
  /**
   * Common function for saving fields.
   *
   * @param string $post_id 
   * @param string $field 
   * @param string $old 
   * @param string|mixed $new 
   * @since 1.0
   * @access public
   */
  public function save_field( $post_id, $field, $old, $new ) {
    $name = $field['id'];
    delete_post_meta( $post_id, $name );
    if ( $new === '' || $new === array() ) 
      return;
    if ( $field['multiple'] ) {
      foreach ( $new as $add_new ) {
        add_post_meta( $post_id, $name, $add_new, false );
      }
    } else {
      update_post_meta( $post_id, $name, $new );
    }
  }  
  
  /**
   * function for saving image field.
   *
   * @param string $post_id 
   * @param string $field 
   * @param string $old 
   * @param string|mixed $new 
   * @since 1.7
   * @access public
   */
  public function save_field_image( $post_id, $field, $old, $new ) {
    $name = $field['id'];
    delete_post_meta( $post_id, $name );
    if ( $new === '' || $new === array() || $new['id'] == '' || $new['url'] == '')
      return;
    
    update_post_meta( $post_id, $name, $new );
  }
  
  /*
   * Save Wysiwyg Field.
   *
   * @param string $post_id 
   * @param string $field 
   * @param string $old 
   * @param string $new 
   * @since 1.0
   * @access public 
   */
  public function save_field_wysiwyg( $post_id, $field, $old, $new ) {
    $id = str_replace( "_","",$this->stripNumeric( strtolower( $field['id']) ) );
    $new = ( isset( $_POST[$id] ) ) ? $_POST[$id] : ( ( $field['multiple'] ) ? array() : '' );
    $this->save_field( $post_id, $field, $old, $new );
  }
  
  public function save_field_repeater( $post_id, $field, $old, $new ) {
    if (is_array($new) && count($new) > 0){
      foreach ($new as $n){
        foreach ( $field['fields'] as $f ) {
          $type = $f['type'];
          switch($type) {
            case 'wysiwyg':
                $n[$f['id']] = wpautop( $n[$f['id']] ); 
                break;
              default:
                break;
          }
        }
        if(!$this->is_array_empty($n))
          $temp[] = $n;
      }
      if (isset($temp) && count($temp) > 0 && !$this->is_array_empty($temp)){
        update_post_meta($post_id,$field['id'],$temp);
      }else{

        delete_post_meta($post_id,$field['id']);
      }
    }else{

      delete_post_meta($post_id,$field['id']);
    }
  }

  public function save_field_file( $post_id, $field, $old, $new ) {
    
    $name = $field['id'];
    delete_post_meta( $post_id, $name );
    if ( $new === '' || $new === array() || $new['id'] == '' || $new['url'] == '')
      return;
    
    update_post_meta( $post_id, $name, $new );
  }
  

  public function save_field_file_repeater( $post_id, $field, $old, $new ) {}
  

  public function add_missed_values() {
    
    $this->_meta_box = array_merge( array( 'context' => 'normal', 'priority' => 'high', 'pages' => array( 'post' ) ), (array)$this->_meta_box );


    foreach ( $this->_fields as &$field ) {
      
      $multiple = in_array( $field['type'], array( 'checkbox_list', 'file', 'image' ) );
      $std = $multiple ? array() : '';
      $format = 'date' == $field['type'] ? 'yy-mm-dd' : ( 'time' == $field['type'] ? 'hh:mm' : '' );

      $field = array_merge( array( 'multiple' => $multiple, 'std' => $std, 'desc' => '', 'format' => $format, 'validate_func' => '' ), $field );
    
    }
    
  }


   public function has_field( $type ) {

    if (count($this->field_types) > 0){
      return in_array($type, $this->field_types);
    }

    $temp = array();
    foreach ($this->_fields as $field) {
      $temp[] = $field['type'];
      if ('repeater' == $field['type']  || 'cond' == $field['type']){
        foreach((array)$field["fields"] as $repeater_field) {
          $temp[] = $repeater_field["type"];  
        }
      }
    }

    $this->field_types = array_unique($temp);
    return $this->has_field($type);
  }


  public function is_edit_page() {
    global $pagenow;
    return in_array( $pagenow, array( 'post.php', 'post-new.php' ) );
  }

  public function fix_file_array( &$files ) {
    
    $output = array();
    
    foreach ( $files as $key => $list ) {
      foreach ( $list as $index => $value ) {
        $output[$index][$key] = $value;
      }
    }
    
    return $output;
  
  }

  /*
  弃用
   */
  public function get_jqueryui_ver() {
    
    global $wp_version;
    
    if ( version_compare( $wp_version, '3.1', '>=') ) {
      return '1.8.10';
    }
    
    return '1.7.3';
  
  }
  
  /**
   *  Add Field to meta box (generic function)
   */
  public function addField($id,$args){
    $new_field = array('id'=> $id,'std' => '','desc' => '','style' =>'');
    $new_field = array_merge($new_field, $args);
    $this->_fields[] = $new_field;
  }
  
  /**
   *  Add Text Field to meta box
   *  @since 1.0
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional
   *    'validate_func' => // validate function, string optional
   *   @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addText($id,$args,$repeater=false){
    $new_field = array('type' => 'text','id'=> $id,'std' => '','desc' => '','style' =>'','name' => 'Text Field');
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Number Field to meta box
   *  @since 1.0
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional
   *    'validate_func' => // validate function, string optional
   *   @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addNumber($id,$args,$repeater=false){

    $defaults = array('type' => 'number','id'=> $id,'std' => '0','desc' => '','style' =>'','name' => __('Number Field','cpmb'),'step' => '1','min' => '0');

   /* $new_field = array_merge($new_field, $args);*/
     $new_field = wp_parse_args( $args, $defaults );
    if(false === $repeater){

      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  
  /**
   *  Add Hidden Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional
   *    'validate_func' => // validate function, string optional
   *   @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addHidden($id,$args,$repeater=false){
    $new_field = array('type' => 'hidden','id'=> $id,'std' => '','desc' => '','style' =>'','name' => 'Text Field');
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Paragraph to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $value  paragraph html
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addParagraph($id,$args,$repeater=false){
    $new_field = array('type' => 'paragraph','id'=> $id,'value' => '');
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
    
  /**
   *  Add Checkbox Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addCheckbox($id,$args,$repeater=false){

/*var_dump($args);*/

    $defaults = array('type' => 'checkbox','id'=> $id,'std' => '','desc' => '','style' =>'','name' => __('Checkbox Field','cpmb'));
    $new_field = wp_parse_args( $args, $defaults );

    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  /**
   *  Add CheckboxList Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $options (array)  array of key => value pairs for select options
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   *  
   *   @return : remember to call: $checkbox_list = get_post_meta(get_the_ID(), 'meta_name', false); 
   *   which means the last param as false to get the values in an array
   */
  public function addCheckboxList($id,$options,$args,$repeater=false){

    $defaults = array('type' => 'checkbox_list','id'=> $id,'std' => '','desc' => '','style' =>'','name' => __('Checkbox List Field','cpmb'),'options' =>$options,'multiple' => true);

    $new_field =  wp_parse_args( $args, $defaults );

    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Textarea Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addTextarea($id,$args,$repeater=false){
    $new_field = array('type' => 'textarea','id'=> $id,'std' => '','desc' => '','style' =>'','name' => __('Textarea Field','cpmb'));
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Select Field to meta box
   *  @access public
   *  @param $id string field id, i.e. the meta key
   *  @param $options (array)  array of key => value pairs for select options  
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, (array) optional
   *    'multiple' => // select multiple values, optional. Default is false.
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addSelect($id,$options,$args,$repeater=false){
    $new_field = array('type' => 'select','id'=> $id,'std' => array(),'desc' => '','style' =>'','name' => __('Select Field','cpmb'),'multiple' => false,'options' => $options);
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  
  /**
   *  Add Radio Field to meta box
   *  @access public
   *  @param $id string field id, i.e. the meta key
   *  @param $options (array)  array of key => value pairs for radio options
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional 
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   */
  public function addRadio($id,$options,$args,$repeater=false){
    $new_field = array('type' => 'radio','id'=> $id,'std' => array(),'desc' => '','style' =>'','name' => __('Radio Field','cpmb'),'options' => $options);
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  /**
   *  Add Date Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *    'format' => // date format, default yy-mm-dd. Optional. Default "'d MM, yy'"  See more formats here: http://goo.gl/Wcwxn
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addDate($id,$args,$repeater=false){

    /*
    d MM, yy
    yy-mm-dd
     */
    $new_field = array('type' => 'date','id'=> $id,'std' => '','desc' => '','format'=>'yy-mm-dd','name' => __('Date Field','cpmb'));
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  /**
   *  Add Time Field to meta box
   *  @access public
   *  @param $id string- field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *    'format' => // time format, default HH:mm. Optional. See more formats here: http://trentrichardson.com/examples/timepicker/
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addTime($id,$args,$repeater=false){
    $new_field = array('type' => 'time', 'id'=> $id, 'std' => '','desc' => '','format'=>'HH:mm','name' => __('Time Field','cpmb'), 'ampm' => false);
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Color Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addColor($id,$args,$repeater=false){
    $new_field = array('type' => 'color','id'=> $id,'std' => '','desc' => '','name' => __('ColorPicker Field','cpmb'));
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add Image Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'validate_func' => // validate function, string optional
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addImage($id,$args,$repeater=false){
    $new_field = array('type' => 'image','id'=> $id,'desc' => '','name' => __('Image Field','cpmb'),'std' => array('id' => '', 'url' => ''),'multiple' => false);
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add File Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'validate_func' => // validate function, string optional 
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   */
  public function addFile($id,$args,$repeater=false){
    $new_field = array('type' => 'file','id'=> $id,'desc' => '','name' => __('File Field','cpmb'),'multiple' => false,'std' => array('id' => '', 'url' => ''));
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  /**
   *  Add WYSIWYG Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional Default 'width: 300px; height: 400px'
   *    'validate_func' => // validate function, string optional 
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   */
  public function addWysiwyg($id,$args,$repeater=false){
    $new_field = array('type' => 'wysiwyg','id'=> $id,'std' => '','desc' => '','style' =>'width: 300px; height: 400px','name' => __('WYSIWYG Editor Field','cpmb'));
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }

  public function addEditor($id,$args,$repeater=false){

  		$this->addWysiwyg($id,$args,$repeater);
  }
  
  /**
   *  Add Taxonomy Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $options mixed|array options of taxonomy field
   *    'taxonomy' =>    // taxonomy name can be category,post_tag or any custom taxonomy default is category
   *    'type' =>  // how to show taxonomy? 'select' (default) or 'checkbox_list'
   *    'args' =>  // arguments to query taxonomy, see https://developer.wordpress.org/reference/functions/get_terms/ default ('hide_empty' => false)  
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional 
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   */
  public function addTaxonomy($id,$options,$args,$repeater=false){

      $temp = array(
      'args' => array('hide_empty' => 0),
      'tax' => 'category',
      'type' => 'select'
      );

    $options = array_merge($temp,$options);



    $new_field = array('type' => 'taxonomy','id'=> $id,'desc' => '','name' => __('Taxonomy Field','cpmb'),'options'=> $options);

    $new_field = array_merge($new_field, $args);



    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }



  /**
   *  Add posts Field to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $options mixed|array options of taxonomy field
   *    'post_type' =>    // post type name, 'post' (default) 'page' or any custom post type
   *    'type' =>  // how to show posts? 'select' (default) or 'checkbox_list'
   *    'args' =>  // arguments to query posts, see https://codex.wordpress.org/Class_Reference/WP_Query default ('posts_per_page' => -1)  
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional 
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default)
   */
  public function addPosts($id,$options,$args,$repeater=false){
    $post_type = isset($options['post_type'])? $options['post_type']: (isset($args['post_type']) ? $args['post_type']: 'post');
    $type = isset($options['type'])? $options['type']: 'select';
    $q = array('posts_per_page' => -1, 'post_type' => $post_type);
    if (isset($options['args']) )
      $q = array_merge($q,(array)$options['args']);
    $options = array('post_type' =>$post_type,'type'=>$type,'args'=>$q);
    $new_field = array('type' => 'posts','id'=> $id,'desc' => '','name' => __('Posts Field','cpmb'),'options'=> $options,'multiple' => false);
    $new_field = array_merge($new_field, $args);
    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  /**
   *  Add repeater Field Block to meta box
   *  @access public
   *  @param $id string  field id, i.e. the meta key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'style' =>   // custom style for field, string optional
   *    'validate_func' => // validate function, string optional
   *    'fields' => //fields to repeater  
   */
  public function addRepeaterBlock($id,$args){
    $new_field = array(
      'type'     => 'repeater',
      'id'       => $id,
      'name'     => __('Reapeater Field','cpmb'),
      'fields'   => array(),
      'inline'   => false,
      'sortable' => false
    );
    $new_field = array_merge($new_field, $args);
    $this->_fields[] = $new_field;
  }

  /**
   *  Add Checkbox conditional Field to Page
   *  @access public
   *  @param $id string  field id, i.e. the key
   *  @param $args mixed|array
   *    'name' => // field name/label string optional
   *    'desc' => // field description, string optional
   *    'std' => // default value, string optional
   *    'validate_func' => // validate function, string optional
   *    'fields' => list of fields to show conditionally.
   *  @param $repeater bool  is this a field inside a repeatr? true|false(default) 
   */
  public function addCondition($id,$args,$repeater=false){
    $new_field = array(
      'type'   => 'cond',
      'id'     => $id,
      'std'    => '',
      'desc'   => '',
      'style'  =>'',
      'name'   => __('Conditional Field','cpmb'),
      'fields' => array()
    );
    $new_field = array_merge($new_field, $args);

    if(false === $repeater){
      $this->_fields[] = $new_field;
    }else{
      return $new_field;
    }
  }
  
  
  /**
   * Finish Declaration of Meta Box
   * @access public
   */
  public function Finish() {
    $this->add_missed_values();
  }
  
  /**
   * Helper function to check for empty arrays
   * @access public
   * @param $args mixed|array
   */
  public function is_array_empty($array){
    if (!is_array($array))
      return true;
    
    foreach ($array as $a){
      if (is_array($a)){
        foreach ($a as $sub_a){
          if (!empty($sub_a) && $sub_a != '')
            return false;
        }
      }else{
        if (!empty($a) && $a != '')
          return false;
      }
    }
    return true;
  }

  /**
   * Validate_upload_file_type 
   * Checks if the uploaded file is of the expected format
   * @access public
   * @uses get_allowed_mime_types() to check allowed types
   * @param array $file uploaded file
   * @return array file with error on mismatch
   */
  function Validate_upload_file_type($file) {
    if (isset($_POST['uploadeType']) && !empty($_POST['uploadeType']) && isset($_POST['uploadeType']) && $_POST['uploadeType'] == 'my_meta_box'){
      $allowed = explode("|", $_POST['uploadeType']);
      $ext =  substr(strrchr($file['name'],'.'),1);

      if (!in_array($ext, (array)$allowed)){
        $file['error'] = __('Sorry, you cannot upload this file type for this field.', 'cpmb' );
        return $file;
      }

      foreach (get_allowed_mime_types() as $key => $value) {
        if (strpos($key, $ext) || $key == $ext)
          return $file;
      }
      $file['error'] = __('Sorry, you cannot upload this file type for this field.', 'cpmb');
    }
    return $file;
  }

  /**
   * function to sanitize field id
   * 
   * @access public
   * @param  string $str string to sanitize
   * @return string      sanitized string
   */
  public function idfy($str){
    return str_replace(" ", "_", $str);
    
  }

  /**
   * stripNumeric Strip number form string
   * @access public
   * @param  string $str
   * @return string number less string
   */
  public function stripNumeric($str){
    return trim(str_replace(range(0,9), '', $str) );
  }


  /*
   * load_textdomain : may be load it in your plugin or theme.
   */
  public function load_textdomain(){
    load_plugin_textdomain( 'cpmb', false, dirname( plugin_basename( __FILE__ ) ) . '/lang');
  }
} // End Class
endif; // End Check Class Exists
