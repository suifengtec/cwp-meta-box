<?php
/**
 * @Author: suifengtec
 * @Date:   2017-02-28 16:59:50
 * @Last Modified by:   suifengtec
 * @Last Modified time: 2017-02-28 18:37:23
 */
/*
Plugin Name: CWP_Meta_Box_Usage
 */
class CWP_Meta_Box_Usage{


	public function __construct(){



		if(is_admin()&&!class_exists('CWP_Meta_Box')){
			require_once('cwp-meta-box.php');

			$this->mb();
		}



	}

	public function mb(){

		if(!is_admin()){
			return ;
		}
		if(!class_exists('CWP_Meta_Box')){
			require_once __DIR__.'cwp-meta-box.php';
		}

		$prefix = 'cwp_';

		$config = array(
			'id'             => 'demo_meta_box1',
			'title'          => ' Repeater ',
			'pages'          => array('post','page','product'),
			'context'        => 'normal',
			'priority'       => 'high',
			'fields'         => array(),
			'with_theme' => false,
			'with_wc' => false,
		);

		$meta =  new CWP_Meta_Box($config);

		$meta->addRadio( $prefix.'_dp_enable', array('on'=>'Enable', 'off'=>'Disbale'), array('name'=> 'Enable Something ?', 'std'=> array('off') ) );


		$repeater[] = $meta->addText( $prefix.'title', array('name'=> 'Current Node Title '), true);
		$repeater[] = $meta->addText( $prefix.'percentage', array('name'=> 'Current Node percentage'), true);
		$repeater[] = $meta->addText( $prefix.'discount', array('name'=> 'Current Node discount'), true);

		$repeater[] = $meta->addDate( $prefix.'date', array('name'=> 'Current Node date'), true);
		$repeater[] = $meta->addNumber( $prefix.'number', array('name'=> 'Current Node number', 'min' => '1'), true);
 
		$meta->addRepeaterBlock($prefix.'re_',array(
		'inline'   => true,
		'name'     => 'Nodes',
		'desc'   => 'Some info for Nodes.',
		'fields'   => $repeater,
		'sortable' => true
		));


		$meta->Finish();


		$prefix = 'cwp_';
		$config = array(
			'id'             => 'demo_meta_box2',          // meta box id, unique per meta box
			'title'          => ' Meta Box Demo ',          // meta box title
			'pages'          => array('page'),      // post types, accept custom post types as well, default is array('post'); optional
			'context'        => 'normal',            // where the meta box appear: normal (default), advanced, side; optional
			'priority'       => 'high',            // order of meta box: high (default), low; optional
			'fields'         => array(),            // list of meta fields (can be added by field arrays)
			'with_theme' => false,       //change path if used with theme set to true, false for a plugin or anything else for a custom path(default false).
			'with_wc' => false, /* is only working with WooCommerce Product?*/
		);

		$meta2 =  new CWP_Meta_Box($config);


		$meta2->addText( $prefix.'text_field_id', array('name'=> 'Text ') );

		$meta2->addTextarea( $prefix.'textarea_field_id', array('name'=> 'Textarea ') );

		$meta2->addCheckbox( $prefix.'checkbox_field_id', array('name'=> 'Checkbox ', 'desc'=>'desc for this checkbox field'));

		$meta2->addSelect( $prefix.'select_field_id', array('selectkey1'=>'Select Value1','selectkey2'=>' Value2'), array('name'=> 'Select ', 'std'=> array('selectkey2'), 'desc'=>'desc for this select field' ) );

		$meta2->addImage($prefix.'image_field_id', array('name'=> ' Image ', 'desc'=>'desc for this image field' ) );

		$meta2->addFile($prefix.'file_field_id', array('name'=> ' File', 'desc'=>'desc for this file field' ) );


		$meta2->Finish();

		$prefix = '_groupped_';
		$config3 = array(
			'id'             => 'demo_meta_box3',  
			'title'          => 'Groupped Meta Box fields', 
			'pages'          =>  array('post', 'page','product'), 
			'context'        => 'normal',  
			'priority'       => 'low',  
			'fields'         => array(), 
			'with_theme' => false
		);
		  
		$meta3 =  new CWP_Meta_Box($config3);

		$meta3->addText( $prefix.'text_field_id', array( 'name'=> 'Text ', 'group' => 'start' ) );

		$meta3->addCheckbox( $prefix.'checkbox_field_id', array( 'name'=> 'Checkbox') );

		$meta3->addSelect( $prefix.'select_field_id', array('selectkey1'=>' Value1', 'selectkey2'=>' Value2' ), array( 'name'=> 'Select ', 'std'=> array('selectkey2'),'group' => 'end' ) );

		$meta3->Finish();
 

	}


}

new CWP_Meta_Box_Usage;
