<?php
/**
 * Ace Source Editor Plugin
 *
 * Events: OnManagerPageBeforeRender, OnRichTextEditorRegister, OnSnipFormPrerender,
 * OnTempFormPrerender, OnChunkFormPrerender, OnPluginFormPrerender,
 * OnFileCreateFormPrerender, OnFileEditFormPrerender, OnDocFormPrerender
 *
 * @author Danil Kostin <danya.postfactum(at)gmail.com>
 *
 * @package ace
 *
 * @var array $scriptProperties
 * @var Ace $ace
 */
if ($modx->event->name == 'OnRichTextEditorRegister') {
    $modx->event->output('Ace');
    return;
}

if ($modx->getOption('which_element_editor', null, 'Ace') !== 'Ace') {
    return;
}

$ace = $modx->getService('ace', 'Ace', $modx->getOption('ace.core_path', null, $modx->getOption('core_path').'components/ace/').'model/ace/');
$ace->initialize();

$extensionMap = array(
    'tpl'   => 'text/x-smarty',
    'htm'   => 'text/html',
    'html'  => 'text/html',
    'css'   => 'text/css',
    'scss'  => 'text/x-scss',
    'less'  => 'text/x-less',
    'svg'   => 'image/svg+xml',
    'xml'   => 'application/xml',
    'xsl'   => 'application/xml',
    'js'    => 'application/javascript',
    'json'  => 'application/json',
    'php'   => 'application/x-php',
    'sql'   => 'text/x-sql',
    'md'    => 'text/x-markdown',
    'txt'   => 'text/plain',
    'twig'  => 'text/x-twig'
);

// Defines wether we should highlight modx tags
$modxTags = false;
switch ($modx->event->name) {
    case 'OnSnipFormPrerender':
        $field = 'modx-snippet-snippet';
        $mimeType = 'application/x-php';
        break;
    case 'OnTempFormPrerender':
        $field = 'modx-template-content';
        $modxTags = true;

        switch (true) {
            case $modx->getOption('twiggy_class'):
                $mimeType = 'text/x-twig';
                break;
            case $modx->getOption('pdotools_fenom_parser'):
                $mimeType = 'text/x-smarty';
                break;
            default:
                $mimeType = 'text/html';
                break;
        }

        break;
    case 'OnChunkFormPrerender':
        $field = 'modx-chunk-snippet';
        if ($modx->controller->chunk && $modx->controller->chunk->isStatic()) {
            $extension = pathinfo($modx->controller->chunk->getSourceFile(), PATHINFO_EXTENSION);
            $mimeType = isset($extensionMap[$extension]) ? $extensionMap[$extension] : 'text/plain';
        } else {
            switch (true) {
		    	case $modx->getOption('twiggy_class'):
					$mimeType = 'text/x-twig';
					break;
				case $modx->getOption('pdotools_fenom_default'):
					$mimeType = 'text/x-smarty';
					break;
				default:
					$mimeType = 'text/html';
					break;
			}
        }
        $modxTags = true;
        break;
    case 'OnPluginFormPrerender':
        $field = 'modx-plugin-plugincode';
        $mimeType = 'application/x-php';
        break;
    case 'OnFileCreateFormPrerender':
        $field = 'modx-file-content';
        $mimeType = 'text/plain';
        break;
    case 'OnFileEditFormPrerender':
        $field = 'modx-file-content';
        $extension = pathinfo($scriptProperties['file'], PATHINFO_EXTENSION);
        $mimeType = isset($extensionMap[$extension])
            ? $extensionMap[$extension]
            : 'text/plain';
        $modxTags = $extension == 'tpl';
        break;
    case 'OnDocFormPrerender':
        if (!$modx->controller->resourceArray) {
            return;
        }
        $field = 'ta';
        $mimeType = $modx->getObject('modContentType', $modx->controller->resourceArray['content_type'])->get('mime_type');

        switch (true) {
            case $mimeType == 'text/html' && $modx->getOption('twiggy_class'):
                $mimeType = 'text/x-twig';
                break;
            case $mimeType == 'text/html' && $modx->getOption('pdotools_fenom_parser'):
                $mimeType = 'text/x-smarty';
                break;
        }

        if ($modx->getOption('use_editor')){
            $richText = $modx->controller->resourceArray['richtext'];
            $classKey = $modx->controller->resourceArray['class_key'];
            if ($richText || in_array($classKey, array('modStaticResource','modSymLink','modWebLink','modXMLRPCResource'))) {
                $field = false;
            }
        }
        $modxTags = true;
        break;
    default:
        return;
}

$modxTags = (int) $modxTags;
$script = '';
$parserHightlits=[];
if($modxTags){
	if(method_exists($modx->parser,'getEditorMime'))$mimeType=$modx->parser->getEditorMime('ace',$mimeType);
	if(method_exists($modx->parser,'getEditorHighlights'))$parserHightlits=$modx->parser->getEditorHighlights('ace');
	$script .= "Ext.apply(MODx.ux.Ace.additHighlightRules,".json_encode($parserHightlits).");";
}
if ($field) {
    $script .= "MODx.ux.Ace.replaceComponent('$field', '$mimeType', $modxTags);";
}

if ($modx->event->name == 'OnDocFormPrerender' && !$modx->getOption('use_editor')) {
    $script .= "MODx.ux.Ace.replaceTextAreas(Ext.query('.modx-richtext'));";
}

if ($script) {
    $modx->controller->addHtml('<script>Ext.onReady(function() {' . $script . '});</script>');
}
