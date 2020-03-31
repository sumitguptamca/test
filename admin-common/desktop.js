/*
        Inroads Control Panel/Shopping Cart - Desktop Tab JavaScript Functions

                          Written 2016 by Randall Severy
                            Copyright 2016 Inroads, LLC
*/

function click_tab(tab_name)
{
    var tab = top.document.getElementById(tab_name);
    if (! tab) return;
    var onclick = tab.firstChild.nextSibling.getAttribute('onclick');
    if (! onclick) return;
    var this_var = 'document.getElementById("' + tab_name +
                   '").firstChild.nextSibling';
    onclick = onclick.replace(/this/g,this_var);
    onclick = onclick.replace(/ return false;/g,'');
    top.eval(onclick);
}

function colorStrToIntArray(color) {
    // strip '#'
    if (color.length == 4 || color.length == 7) {
        color = color.substr(1);
    }

    // for colors like '#fff'
  	if (color.length == 3) {
    		var r = parseInt(color.substr(0, 1) + color.substr(0, 1), 16),
       			g = parseInt(color.substr(1, 1) + color.substr(1, 1), 16),
      			b = parseInt(color.substr(2, 1) + color.substr(2, 1), 16);

    		return [r, g, b];
  	} 

    // for colors like '#ffffff'
    else if (color.length == 6) {
        return [
        		parseInt(color.substr(0, 2), 16), 
        		parseInt(color.substr(2, 2), 16), 
        		parseInt(color.substr(4, 2), 16)
        ];
    }

    return false;
}

function calculateSteps(color1, color2, steps) {
  	var output = [],
     		start = colorStrToIntArray(color1),
     		end = colorStrToIntArray(color2);

    var calculate = function(start, end, step) {
        return start + Math.round((end - start) * (step / (steps / 2)));
    };

    for ( var i = 0; i < steps; i++ ) {
      	var color = [0, 0, 0];

      	color[0] = calculate(start[0], end[0], i);
      	color[1] = calculate(start[1], end[1], i);
      	color[2] = calculate(start[2], end[2], i);

      	output.push(color);
  	}
    
    return output;
}


$( document ).ready(function() {
	
	var steps = $( "ul" ).children().length;

	var level = $("ul").attr("class");

	firstcolor = 'ffc000';
	lastcolor = 'ff5e00';
	
	if (level == 'buttons cart_tab level_1') {
		firstcolor = 'd682fa';
		lastcolor = '913eb5';
	}	

	if (level == 'buttons catalog_tab level_1') {
		firstcolor = '19a5ff';
		lastcolor = '1b6696';
	}	
	
	if (level == 'buttons management_tab level_1') {
		firstcolor = 'f2463c';
		lastcolor = '9a3732';
	}	
	
	if (level == 'buttons marketing_tab level_1') {
		firstcolor = 'f7cf7b';
		lastcolor = 'eda613';
	}	
	
	if (level == 'buttons content_tab level_1') {
		firstcolor = '10f1ad';
		lastcolor = '157558';
	}				
	


	var colors = calculateSteps(firstcolor, lastcolor, steps);
	console.log(colors);
	
	$( "ul.buttons > li" ).not('ul.toplevel > li').each(function() {
		$(this).attr("style", "background: rgb(" + colors[$( this ).index()][0] + ", " + colors[$( this ).index()][1] + ", " + colors[$( this ).index()][2] + ")");
	});	
});

