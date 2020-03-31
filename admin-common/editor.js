/*
         Inroads Control Panel/Shopping Cart - WYSIWYG Editor Configuration File

                       Written 2007-2011 by Randall Severy
                        Copyright 2007-2011 Inroads, LLC
*/

FCKConfig.FontSizes = '8px;9px;10px;11px;12px;13px;14px;15px;16px;18px;20px;24px;28px;36px;48px;';

FCKConfig.Plugins.Add('TableToggleBorders');
FCKConfig.Plugins.Add('dragresizetable');

FCKConfig.ToolbarSets["Inroads"] = [
	['Source','Preview','Print','SpellCheck'],
	['Cut','Copy','Paste','PasteText','PasteWord'],
	['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
	['Smiley','SpecialChar','PageBreak'],
	['TextColor','BGColor'],
  ['FitWindow','ShowBlocks'],
	'/',
	['Bold','Italic','Underline','StrikeThrough','-','Subscript','Superscript'],
	['OrderedList','UnorderedList','-','Outdent','Indent','Blockquote'],
	['JustifyLeft','JustifyCenter','JustifyRight','JustifyFull'],
	['Link','Unlink','Anchor'],
	['Image','Flash','Table','Rule','-','TableToggleBorders'],
	'/',
	['FontFormat','FontName','FontSize']
] ;

