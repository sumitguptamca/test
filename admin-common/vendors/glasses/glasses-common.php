<?php
/*
               Inroads Shopping Cart - Glasses Product Data and Functions

                          Written 2018 by Randall Severy
                           Copyright 2018 Inroads, LLC

*/

$product_fields['model'] = array('datatype' => CHAR_TYPE);
$product_fields['model_number'] = array('datatype' => CHAR_TYPE);
$product_fields['frame_style'] = array('datatype' => CHAR_TYPE);
$product_fields['color_code'] = array('datatype' => CHAR_TYPE);
$product_fields['size'] = array('datatype' => CHAR_TYPE);
$product_fields['lens_base'] = array('datatype' => CHAR_TYPE);
$product_fields['lens_material'] = array('datatype' => CHAR_TYPE);
$product_fields['lens_color'] = array('datatype' => CHAR_TYPE);
$product_fields['photochromic'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['polarized'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['standard'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['temple_length'] = array('datatype' => CHAR_TYPE);
$product_fields['bridge_size'] = array('datatype' => CHAR_TYPE);
$product_fields['lens_width'] = array('datatype' => CHAR_TYPE);
$product_fields['lens_height'] = array('datatype' => CHAR_TYPE);
$product_fields['thickness'] = array('datatype' => CHAR_TYPE);
$product_fields['folding'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['crystals'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['rx_service'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['prescription'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['flex'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['front_color'] = array('datatype' => CHAR_TYPE);
$product_fields['shape'] = array('datatype' => CHAR_TYPE);
$product_fields['bridge_shape'] = array('datatype' => CHAR_TYPE);
$product_fields['rim_type'] = array('datatype' => CHAR_TYPE);
$product_fields['eye_wire'] = array('datatype' => CHAR_TYPE);
$product_fields['temple_material'] = array('datatype' => CHAR_TYPE);
$product_fields['front_material'] = array('datatype' => CHAR_TYPE);
$product_fields['frame_material'] = array('datatype' => CHAR_TYPE);
$product_fields['best_seller'] = array('datatype' => INT_TYPE,'datafieldtype' => CHECKBOX_FIELD);
$product_fields['collection'] = array('datatype' => CHAR_TYPE);
$product_fields['backorder_status'] = array('datatype' => CHAR_TYPE);

$vendor_product_fields = array(
   array('name'=>'model','label'=>'Model','type'=>CHAR_TYPE,'size'=>80,'compare'=>1),
   array('name'=>'model_number','label'=>'Model Number','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'frame_style','label'=>'Frame Style','type'=>CHAR_TYPE,'size'=>80,'filter_type'=>16,'compare'=>1),
   array('name'=>'color_code','label'=>'Color Code','type'=>CHAR_TYPE,'size'=>10),
   array('name'=>'size','label'=>'Size (Units: mm)','type'=>CHAR_TYPE,'size'=>10,'subproduct_select'=>1),
   array('name'=>'lens_base','label'=>'Lens Base','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'lens_material','label'=>'Lens Material','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'lens_color','label'=>'Lens Color','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'photochromic','label'=>'Photochromic','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'polarized','label'=>'Polarized','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'standard','label'=>'Standard','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'temple_length','label'=>'Temple Length','type'=>CHAR_TYPE,'size'=>10,'compare'=>1),
   array('name'=>'bridge_size','label'=>'Bridge Size','type'=>CHAR_TYPE,'size'=>10,'compare'=>1),
   array('name'=>'lens_width','label'=>'Lens Width','type'=>CHAR_TYPE,'size'=>10,'compare'=>1),
   array('name'=>'lens_height','label'=>'Lens Height','type'=>CHAR_TYPE,'size'=>10,'compare'=>1),
   array('name'=>'thickness','label'=>'Thickness','type'=>CHAR_TYPE,'size'=>10),
   array('name'=>'folding','label'=>'Folding','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'crystals','label'=>'Crystals','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'rx_service','label'=>'RX Service','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'prescription','label='>'Prescription','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'flex','label'=>'Flex','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'front_color','label'=>'Front Color','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'shape','label'=>'Shape','type'=>CHAR_TYPE,'size'=>80,'filter_type'=>16,'compare'=>1),
   array('name'=>'bridge_shape','label'=>'Bridge Shape','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'rim_type','label'=>'Rim Type','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'eye_wire','label'=>'Eye Wire','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'temple_material','label'=>'Temple Material','type'=>CHAR_TYPE,'size'=>80,'compare'=>1),
   array('name'=>'front_material','label'=>'Front Material','type'=>CHAR_TYPE,'size'=>80,'compare'=>1),
   array('name'=>'frame_material','label'=>'Frame Material','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'best_seller','label'=>'Best Seller','type'=>INT_TYPE,'field_type'=>CHECKBOX_FIELD),
   array('name'=>'collection','label'=>'Collection','type'=>CHAR_TYPE,'size'=>80),
   array('name'=>'backorder_status','label'=>'BackOrder Status','type'=>CHAR_TYPE,'size'=>80)
);

global $shape_replace;
$shape_replace = array(
   'rectangular / square' => 'Rectangle',
   'round / oval' => 'Round',
   'aviator' => 'Aviator',
   'cat eye' => 'Cat Eye',
   'panthos' => 'Round',
   'fork' => 'Round',
   'mask' => 'Other',
   'navigator' => 'Aviator',
   'rectangular' => 'Rectangle',
   'special shape' => 'Geometric',
   'oval modified' => 'Round',
   'tea cup' => 'Round',
   'square' => 'Square',
   'cat eye/butterfly' => 'Cat Eye',
   'oval' => 'Round',
   'round' => 'Round',
   'browline' => 'Browline',
   'rectangular shallow' => 'Rectangle',
   'geometric' => 'Geometric',
   'shield' => 'Other',
   'butterfly' => 'Cat Eye',
   '__long rectangular__' => 'Rectangle',
   'irregular' => 'Other',
   'pillow' => 'Rectangle',
   'rectangle' => 'Rectangle',
   'phantos' => 'Round',
   'pilot' => 'Aviator'
);

global $frame_style_replace;
$frame_style_replace = array(
   'sun' => 'Sunglass',
   'ophthalmic' => 'Optical',
   'clip' => 'Clip-On'
);

function add_specs_title_row($dialog,$title)
{
    $dialog->write('<tr><td colspan="2"><strong>'.$title.'</td></tr>'."\n");
}

function display_glasses_specs_fields($dialog,$row)
{
    $dialog->start_row('Model:');
    $dialog->add_input_field('model',$row,15);
    $dialog->add_inner_prompt('Model #:');
    $dialog->add_input_field('model_number',$row,15);
    $dialog->add_inner_prompt('Frame Style:');
    $dialog->add_input_field('frame_style',$row,15);
    $dialog->add_inner_prompt('Collection:');
    $dialog->add_input_field('collection',$row,15);
    $dialog->end_row();
    $dialog->start_row('BackOrder Status:');
    $dialog->add_input_field('backorder_status',$row,60);
    $dialog->end_row();

    add_specs_title_row($dialog,'Size and Shape');
    $dialog->start_row('Size:');
    $dialog->add_input_field('size',$row,15);
    $dialog->add_inner_prompt('Shape:');
    $dialog->add_input_field('shape',$row,15);
    $dialog->add_inner_prompt('Bridge Shape:');
    $dialog->add_input_field('bridge_shape',$row,15);
    $dialog->add_inner_prompt('Rim Type:');
    $dialog->add_input_field('rim_type',$row,15);
    $dialog->end_row();
    $dialog->start_row('Eye Wire:');
    $dialog->add_input_field('eye_wire',$row,15);
    $dialog->add_inner_prompt('Lens Width:');
    $dialog->add_input_field('lens_width',$row,15);
    $dialog->add_inner_prompt('Bridge Size:');
    $dialog->add_input_field('bridge_size',$row,15);
    $dialog->add_inner_prompt('Temple Length:');
    $dialog->add_input_field('temple_length',$row,15);
    $dialog->end_row();
    $dialog->start_row('Lens Height:');
    $dialog->add_input_field('lens_height',$row,15);
    $dialog->add_inner_prompt('Thickness:');
    $dialog->add_input_field('thickness',$row,15);
    $dialog->end_row();

    add_specs_title_row($dialog,'Color and Material');
    $dialog->start_row('Color Code:');
    $dialog->add_input_field('color_code',$row,15);
    $dialog->add_inner_prompt('Lens Color:');
    $dialog->add_input_field('lens_color',$row,15);
    $dialog->add_inner_prompt('Front Color:');
    $dialog->add_input_field('front_color',$row,15);
    $dialog->add_inner_prompt('Lens Material:');
    $dialog->add_input_field('lens_material',$row,15);
    $dialog->end_row();
    $dialog->start_row('Temple Material:');
    $dialog->add_input_field('temple_material',$row,15);
    $dialog->add_inner_prompt('Front Material:');
    $dialog->add_input_field('front_material',$row,15);
    $dialog->add_inner_prompt('Frame Material:');
    $dialog->add_input_field('frame_material',$row,15);
    $dialog->add_inner_prompt('Lens Base:');
    $dialog->add_input_field('lens_base',$row,15);
    $dialog->end_row();

    add_specs_title_row($dialog,'Options');
    $options = array('photochromic' => 'Photochromic',
       'polarized' => 'Polarized','standard' => 'Standard',
       'folding' => 'Folding','crystals' => 'Crystals',
       'rx_service' => 'Rx Service','prescription' => 'Rx Able',
       'flex' => 'Flex','best_seller' => 'Best Seller');
    $first_option = true;
    $dialog->write('<tr><td colspan="2">');
    foreach ($options as $field_name => $field_label) {
       if ($first_option) $first_option = false;
       else $dialog->write("&nbsp;&nbsp;&nbsp;\n");
       $dialog->add_checkbox_field($field_name,$field_label,$row);
    }
}

function convert_shape($shape)
{
    global $shape_replace;

    $lookup_shape = strtolower(trim($shape));
    if (! $lookup_shape) $shape = 'Other';
    else if (isset($shape_replace[$lookup_shape]))
       $shape = $shape_replace[$lookup_shape];
    return $shape;
}

function convert_frame_style($frame_style)
{
    global $frame_style_replace;

    $lookup_style = strtolower(trim($frame_style));
    if (isset($frame_style_replace[$lookup_style]))
       $frame_style = $frame_style_replace[$lookup_style];
    return $frame_style;
}

function custom_amazon_product_data($amazon,$row)
{
    $amazon_type = get_row_value($row,'amazon_type');
    if ($amazon_type != 'Shoes') return '';

    $color = get_row_value($row,'shopping_color');
    $size = get_row_value($row,'size');
    $xml_data = '<ProductData><Shoes><ClothingType>Eyewear</ClothingType>';
    if ($color || $size) {
       $xml_data .= '<VariationData><Parentage>child</Parentage>';
       if ($size) $xml_data .= '<Size>'.$size.'</Size>';
       if ($color) $xml_data .= '<Color>'.$color.'</Color>';
       if ($color && $size)
          $xml_data .= '<VariationTheme>SizeColor</VariationTheme>';
       else if ($color)
          $xml_data .= '<VariationTheme>Color</VariationTheme>';
       else $xml_data .= '<VariationTheme>Size</VariationTheme>';
       $xml_data .= '</VariationData>';
    }
    $xml_data .= '<ClassificationData>';
    if ($row['bridge_size'])
       $xml_data .= '<BridgeWidth unitOfMeasure="MM">'.$row['bridge_size'] .
                    '</BridgeWidth>';
    $amazon->append_xml($xml_data,'Department',$row['shopping_age']);
    if (! empty($row['frame_material']))
       $amazon->append_xml($xml_data,'FrameMaterialType',$row['frame_material']);
    else if (! empty($row['front_material']))
       $amazon->append_xml($xml_data,'FrameMaterialType',$row['front_material']);
    else if (! empty($row['temple_material']))
       $amazon->append_xml($xml_data,'FrameMaterialType',$row['temple_material']);
    $amazon->append_xml($xml_data,'ItemShape',$row['shape']);
    $amazon->append_xml($xml_data,'LensColor',$row['lens_color']);
    if ($row['lens_height'])
       $xml_data .= '<LensHeight unitOfMeasure="MM">'.$row['lens_height'] .
                    '</LensHeight>';
    $amazon->append_xml($xml_data,'LensMaterialType',$row['lens_material']);
    if ($row['lens_width'])
       $xml_data .= '<LensWidth unitOfMeasure="MM">'.$row['lens_width'] .
                    '</LensWidth>';
    $amazon->append_xml($xml_data,'ModelNumber',$row['model_number']);
    $amazon->append_xml($xml_data,'ModelName',$row['model']);
    if ($row['polarized'])
       $amazon->append_xml($xml_data,'PolarizationType','Polarized');
    else $amazon->append_xml($xml_data,'PolarizationType','Non-Polarized');
    $amazon->append_xml($xml_data,'StyleName',$row['frame_style']);
    $xml_data .= '</ClassificationData>';
    $xml_data .= '</Shoes></ProductData>';
    return $xml_data;
}

?>
