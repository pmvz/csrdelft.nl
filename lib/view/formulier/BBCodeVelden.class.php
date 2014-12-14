<?php

/**
 * BBCodeVelden.class.php
 * 
 * @author Jan Pieter Waagmeester <jieter@jpwaag.com>
 * @author P.W.G. Brussee <brussee@live.nl>
 * 
 * 
 * Bevat de uitbreidingen van TextareaField:
 * 
 * 	- CsrBBPreviewField		Textarea met bbcode voorbeeld
 * 
 */
class CsrBBPreviewField extends TextareaField {

	public $previewOnEnter = false;

	public function __construct($name, $value, $description, $rows = 5, $max_len = null, $min_len = null) {
		parent::__construct($name, $value, $description, $rows, $max_len, $min_len);
	}

	public function getPreviewDiv() {
		return '<div id="bbcodePreview_' . $this->getId() . '" class="previewDiv bbcodePreview"></div>';
	}

	public function getHtml() {
		return parent::getHtml() . <<<HTML

<div class="float-right">
	<a href="http://csrdelft.nl/wiki/cie:diensten:forum" target="_blank" title="Ga naar het overzicht van alle opmaak codes">Opmaakhulp</a>
	<a class="btn" onclick="preview{$this->getId()}();" title="Toon voorbeeld met opmaak">Voorbeeld</a>
</div>
HTML;
	}

	public function getJavascript() {
		$js = parent::getJavascript();
		if (!$this->previewOnEnter) {
			$js .= <<<JS

var preview{$this->getId()} = function () {
	CsrBBPreview('#{$this->getId()}', '#bbcodePreview_{$this->getId()}');
};
$('#{$this->getId()}').keyup(function(event) {
	if(event.keyCode === 13) { // enter
		preview{$this->getId()}();
	}
});
JS;
		}
		return $js;
	}

}

class RequiredCsrBBPreviewField extends CsrBBPreviewField {

	public $required = true;

}
