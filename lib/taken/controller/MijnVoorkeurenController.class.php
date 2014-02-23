<?php


require_once 'taken/model/VoorkeurenModel.class.php';
require_once 'taken/view/MijnVoorkeurenView.class.php';

/**
 * MijnVoorkeurenController.class.php	| 	P.W.G. Brussee (brussee@live.nl)
 * 
 */
class MijnVoorkeurenController extends AclController {

	public function __construct($query) {
		parent::__construct($query);
		if (!$this->isPosted()) {
			$this->acl = array(
				'mijn' => 'P_CORVEE_IK'
			);
		}
		else {
			$this->acl = array(
				'inschakelen' => 'P_CORVEE_IK',
				'uitschakelen' => 'P_CORVEE_IK',
				'eetwens' => 'P_CORVEE_IK'
			);
		}
		$this->action = 'mijn';
		if ($this->hasParam(2)) {
			$this->action = $this->getParam(2);
		}
		$crid = null;
		if ($this->hasParam(3)) {
			$crid = intval($this->getParam(3));
		}
		$this->performAction(array($crid));
	}
	
	public function mijn() {
		$voorkeuren = VoorkeurenModel::getVoorkeurenVoorLid(\LoginLid::instance()->getUid());
		$eetwens = VoorkeurenModel::getEetwens(\LoginLid::instance()->getLid());
		$this->view = new MijnVoorkeurenView($voorkeuren, $eetwens);
		$this->view = new CsrLayoutPage($this->getContent());
		$this->view->addStylesheet('taken.css');
		$this->view->addScript('taken.js');
	}
	
	public function inschakelen($crid) {
		$abonnement = VoorkeurenModel::inschakelenVoorkeur($crid, \LoginLid::instance()->getUid());
		$this->view = new MijnVoorkeurenView($abonnement);
	}
	
	public function uitschakelen($crid) {
		VoorkeurenModel::uitschakelenVoorkeur($crid, \LoginLid::instance()->getUid());
		$this->view = new MijnVoorkeurenView($crid);
	}
	
	public function eetwens() {
		$eetwens = filter_input(INPUT_POST, 'eetwens', FILTER_SANITIZE_STRING);
		VoorkeurenModel::setEetwens(\LoginLid::instance()->getLid(), $eetwens);
		$this->view = new MijnVoorkeurenView(null, $eetwens);
	}
}

?>