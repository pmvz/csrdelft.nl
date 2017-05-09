<?php

require_once 'model/fiscaat/CiviCategorieModel.class.php';
require_once 'view/fiscaat/CiviCategorieSuggestiesView.class.php';

/**
 * Class BeheerCiviBestellingController
 *
 * @author G.J.W. Oolbekkink <g.j.w.oolbekkink@gmail.com>
 * @property CiviBestellingModel $model
 */
class BeheerCiviCategorienController extends AclController {
	public function __construct($query) {
		parent::__construct($query, CiviCategorieModel::instance());

		if ($this->getMethod() == "POST") {
			$this->acl = [
			];
		} else {
			$this->acl = [
				'suggesties' => 'P_MAAL_MOD',
			];
		}
	}

	public function performAction(array $args = array()) {
		$this->action = 'overzicht';

		if ($this->hasParam(3)) {
			$this->action = $this->getParam(3);
		}
		return parent::performAction($args);
	}

	public function GET_suggesties() {
		$query = '%' . $this->getParam('q') . '%';
		$this->view = new CiviCategorieSuggestiesView($this->model->find('type LIKE ?', array($query)));
	}
}