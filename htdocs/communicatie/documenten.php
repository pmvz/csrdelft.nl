<?php

require_once 'configuratie.include.php';
require_once 'documenten/documentcontroller.class.php';

/**
 * index.php	| 	Jan Pieter Waagmeester (jieter@jpwaag.com)
 *
 * Documentenketzerding.
 */
if (isset($_GET['querystring'])) {
	$docControl = new DocumentController($_GET['querystring']);
	$docControl->performAction();
} else {
	die('epic fail');
}

$pagina = new CsrLayoutPage($docControl->getView());

$pagina->addStylesheet($pagina->getCompressedStyleUrl('layout', 'documenten'), true);
$pagina->addScript($pagina->getCompressedScriptUrl('layout', 'documenten'), true);

$pagina->view();
