<?php

namespace CsrDelft\controller\groepen;

use CsrDelft\common\CsrToegangException;
use CsrDelft\model\groepen\LichtingenModel;
use CsrDelft\view\JsonResponse;

/**
 * LichtingenController.class.php
 *
 * @author P.W.G. Brussee <brussee@live.nl>
 *
 * Controller voor lichtingen.
 *
 * @property LichtingenModel $model
 */
class LichtingenController extends AbstractGroepenController {
	const NAAM = 'lichtingen';

	public function __construct() {
		parent::__construct(LichtingenModel::instance());
	}

	public function zoeken($zoekterm = null) {
		if (!$zoekterm && !$this->hasParam('q')) {
			throw new CsrToegangException();
		}
		if (!$zoekterm) {
			$zoekterm = $this->getParam('q');
		}
		$result = array();
		if (is_numeric($zoekterm)) {

			$data = range($this->model->getJongsteLidjaar(), $this->model->getOudsteLidjaar());
			$found = preg_grep('/' . (int)$zoekterm . '/', $data);

			foreach ($found as $lidjaar) {
				$result[] = array(
					'url' => '/groepen/lichtingen/' . $lidjaar . '#' . $lidjaar,
					'label' => 'Groepen',
					'value' => 'Lichting:' . $lidjaar
				);
			}
		}
		$this->view = new JsonResponse($result);
	}

}
