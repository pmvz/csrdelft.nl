<?php

require_once 'MVC/view/LidInstellingenView.class.php';

/**
 * LidLidInstellingenController.class.php
 * 
 * @author P.W.G. Brussee <brussee@live.nl>
 * 
 */
class LidInstellingenController extends AclController {

	/**
	 * Data access model
	 * @var LidInstellingen
	 */
	private $model;

	public function __construct($query) {
		$this->model = LidInstellingen::instance();
		parent::__construct($query);
		if (!$this->isPosted()) {
			$this->acl = array(
				'beheer' => 'P_LOGGED_IN',
				'reset' => 'P_ADMIN'
			);
		} else {
			$this->acl = array(
				'opslaan' => 'P_LOGGED_IN'
			);
		}
		$this->action = 'beheer';
		if ($this->hasParam(2)) {
			$this->action = $this->getParam(2);
		}
		$this->performAction($this->getParams(3));
	}

	public function beheer() {
		$body = new LidInstellingenView($this->model);
		$this->view = new CsrLayoutPage($body);
	}

	public function opslaan() {
		$this->model->save(); // fetches $_POST values itself
		invokeRefresh('/', 'Instellingen opgeslagen', 1);
	}

	public function reset($module, $key, $value) {
		$count = $this->model->setForAll($module, $key, $value);
		setMelding('Voor ' . $count . ' leden de instelling aangepast', 1);
		invokeRefresh('/instellingen/beheer', 'Vergeet niet in de code de default value aan te passen voor de overige leden!', 2);
	}

}
