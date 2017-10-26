<?php

namespace CsrDelft\controller;

use CsrDelft\controller\fiscaat\BeheerCiviBestellingController;
use CsrDelft\controller\fiscaat\BeheerCiviCategorienController;
use CsrDelft\controller\fiscaat\BeheerCiviProductenController;
use CsrDelft\controller\fiscaat\BeheerCiviSaldoController;
use CsrDelft\controller\fiscaat\PinTransactieController;
use CsrDelft\controller\framework\AclController;
use CsrDelft\view\CsrLayoutPage;
use CsrDelft\view\fiscaat\FiscaatOverzichtView;

class FiscaatRouterController extends AclController {
	public function __construct($query) {
		parent::__construct($query, $query);

		$this->acl = array(
			'overzicht' => 'P_MAAL_MOD',
			'producten' => 'P_MAAL_MOD',
			'saldo' => 'P_MAAL_MOD',
			'bestellingen' => 'P_MAAL_IK',
			'categorien' => 'P_MAAL_MOD',
			'pin' => 'P_MAAL_MOD',
		);
	}

	public function performAction(array $args = array()) {
		$this->action = 'overzicht';
		if ($this->hasParam(2)) {
			$this->action = $this->getParam(2);
		}
		$controller = parent::performAction($args);
		if ($controller !== null) {
			$controller->performAction();
			$this->view = $controller->getView();
		}
	}

	public function overzicht() {
		$this->view = new CsrLayoutPage(new FiscaatOverzichtView(null));
		$this->view->addCompressedResources('fiscaat');
	}

	public function producten() {
		return new BeheerCiviProductenController($this->model);
	}

	public function saldo() {
		return new BeheerCiviSaldoController($this->model);
	}

	public function bestellingen() {
		return new BeheerCiviBestellingController($this->model);
	}

	public function categorien() {
		return new BeheerCiviCategorienController($this->model);
	}

	public function pin() {
		return new PinTransactieController($this->model);
	}
}
