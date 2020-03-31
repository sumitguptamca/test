/*
       Inroads Shopping Cart - Cardknox API Module iFields JavaScript Functions

                           Written 2019 by Randall Severy
                            Copyright 2019 Inroads, LLC
*/

function getStyles(className)
{
    var styleSheets = window.document.styleSheets;
    var styleSheetsLength = styleSheets.length;
    for (var i = 0; i < styleSheetsLength; i++) {
        try {
           var classes = styleSheets[i].rules || styleSheets[i].cssRules;
        } catch(e) { var classes = null; }
        if (! classes) continue;
        var classesLength = classes.length;
        for (var x = 0; x < classesLength; x++) {
           if (typeof(classes[x].selectorText) == 'undefined') continue;
           var selectors = classes[x].selectorText.split(',');
           var num_selectors = selectors.length;
           for (var y = 0;  y < num_selectors;  y++) {
              if (selectors[y].trim() == className) {
                 var ret;
                 if (classes[x].cssText) ret = classes[x].cssText;
                 else ret = classes[x].style.cssText;
                 return ret;
              }
           }
        }
    }
    return null;
}

function build_styles(styles_array)
{
    var styles_obj = {};
    for (var index in styles_array) {
       var styles = getStyles(styles_array[index]);
       if (! styles) continue;
       var cut_pos = styles.indexOf('{');
       if (cut_pos != -1) styles = styles.substring(cut_pos + 1).trim();
       cut_pos = styles.indexOf('}');
       if (cut_pos != -1) styles = styles.substring(0,cut_pos).trim();
       var parts = styles.split(';');
       var num_parts = parts.length;
       for (var index = 0;  index < num_parts;  index++) {
          var style_parts = parts[index].split(':');
          if (style_parts.length != 2) continue;
          styles_obj[style_parts[0].trim()] = style_parts[1].trim();
       }
    }
    return styles_obj;
}

function get_mobile_cart_cookie()
{
    var value = '; ' + document.cookie;
    var parts = value.split('; mobile_cart=');
    if (parts.length > 1) return parts.pop().split(';').shift();
    return null;
}

function cardknox_ifields_onload()
{
    if (document.AddOrder || document.EditOrder) var order_screen = true;
    else var order_screen = false;
    var mobile_cart = get_mobile_cart_cookie();
    setAccount(ifields_key,'AxiumPro','3.0');
    if (order_screen) {
       var fname = document.getElementById('fname');
       var field_height = fname.offsetHeight;
       var field_width = fname.offsetWidth;
       var payment_amount = document.getElementById('payment_amount');
       var payment_width = payment_amount.offsetWidth;
       var styles = window.getComputedStyle(fname,null);
       var height_offset = parseInt(styles.borderTop) +
          parseInt(styles.paddingTop) + parseInt(styles.paddingBottom) +
          parseInt(styles.borderBottom);
    }
    else {
       var card_name = document.getElementById('card_name');
       var field_height = card_name.offsetHeight;
       if (mobile_cart) var field_width = 200;
       else var field_width = card_name.offsetWidth;
       var styles = window.getComputedStyle(card_name,null);
    }
    var offset = parseInt(styles.borderLeft) + parseInt(styles.paddingLeft) +
                 parseInt(styles.paddingRight) + parseInt(styles.borderRight);
    var iframe = document.getElementById('ifields_card_number_iframe');
    iframe.height = field_height;
    if (order_screen) iframe.height++;
    iframe.width = field_width;
    var card_number_width = field_width - offset;
    var iframe = document.getElementById('ifields_cvv_iframe');
    iframe.height = field_height;
    if (order_screen) {
       iframe.height++;
       iframe.width = payment_width;
       iframe.style.width = payment_width + 'px';
       var cvv_width = payment_width - offset;
    }
    else var cvv_width = iframe.offsetWidth - offset;

    if (order_screen) {
       var cardnum_styles = build_styles(['input.text']);
       var cvv_styles = build_styles(['input.text']);
       var td_styles = build_styles(['.fieldtable td']);
    }
    else {
       var cardnum_styles = build_styles(['.cart_field',
          '.cart_form input[type="text"]',
          '.cart_form #payment_table input[name="card_number"]']);
       var cvv_styles = build_styles(['.cart_field',
          '.cart_form input[type="text"]',
          '.cart_form #payment_table input[name="card_cvv"]']);
    }
    if (cardnum_styles) {
       cardnum_styles.width = card_number_width + 'px';
       if (order_screen) {
          cardnum_styles.height = (field_height - height_offset) + 'px';
          if ((! cardnum_styles['font-family']) && td_styles['font-family'])
             cardnum_styles['font-family'] = td_styles['font-family'];
          if ((! cardnum_styles['font-size']) && td_styles['font-size'])
             cardnum_styles['font-size'] = td_styles['font-size'];
       }
       cardnum_styles['min-width'] = null;
       setIfieldStyle('card-number',cardnum_styles);
    }
    if (cvv_styles) {
       cvv_styles.width = cvv_width + 'px';
       if (order_screen) {
          cvv_styles.height = (field_height - height_offset) + 'px';
          if ((! cvv_styles['font-family']) && td_styles['font-family'])
             cvv_styles['font-family'] = td_styles['font-family'];
          if ((! cvv_styles['font-size']) && td_styles['font-size'])
             cvv_styles['font-size'] = td_styles['font-size'];
       }
       cvv_styles['min-width'] = null;
       setIfieldStyle('cvv',cvv_styles);
    }
}

var getting_tokens = false;

function ifield_token_success()
{
    processing_payment = false;
    var button = document.getElementById('ContinuePayment');
    button.click();
}

function ifield_token_error()
{
    processing_payment = false;
    getting_tokens = false;
}

function module_process_payment()
{
    if (getting_tokens) return true;
    getting_tokens = true;
    getTokens(ifield_token_success,ifield_token_error,60000);
    return false;
}

var token_print_function;
var token_template;
var token_label;

function ifield_token_dialog_success()
{
    if (document.AddOrder) {
       adding_order = false;   process_add_order();
    }
    else update_order(token_print_function,token_template,token_label);
}

function ifield_token_dialog_error()
{
    if (document.AddOrder) adding_order = false;
    getting_tokens = false;
}

function module_process_dialog_payment(print_function,template,label)
{
    if (getting_tokens) return false;
    getting_tokens = true;   token_print_function = print_function;
    token_template = template;   token_label = label;
    getTokens(ifield_token_dialog_success,ifield_token_dialog_error,60000);
    return true;
}

