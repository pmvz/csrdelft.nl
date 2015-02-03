<?php

require_once 'model/entity/groepen/GroepTab.enum.php';
require_once 'model/CmsPaginaModel.class.php';
require_once 'view/CmsPaginaView.class.php';
require_once 'view/GroepLedenView.class.php';

/**
 * GroepenView.class.php
 * 
 * @author P.W.G. Brussee <brussee@live.nl>
 * 
 */
class GroepenBeheerTable extends DataTable {

	private $url;
	private $naam;

	public function __construct(GroepenModel $model) {
		parent::__construct($model::orm, null, 'familie');

		$this->url = $model->getUrl();
		$this->dataUrl = $this->url . 'beheren';

		$this->naam = $model->getNaam();
		$this->titel = 'Beheer ' . $this->naam;

		$this->hideColumn('id', false);
		$this->hideColumn('samenvatting');
		$this->hideColumn('omschrijving');
		$this->hideColumn('website');
		$this->hideColumn('maker_uid');
		$this->hideColumn('keuzelijst');
		$this->hideColumn('status_historie');
		$this->hideColumn('rechten_aanmelden');
		$this->hideColumn('rechten_beheren');
		$this->searchColumn('naam');
		$this->searchColumn('jaargang');
		$this->searchColumn('status');
		$this->searchColumn('soort');

		$create = new DataTableKnop('== 0', $this->tableId, $this->url . 'aanmaken', 'post popup', 'Toevoegen', 'Nieuwe groep toevoegen', 'add');
		$this->addKnop($create);

		$update = new DataTableKnop('== 1', $this->tableId, $this->url . 'wijzigen', 'post popup', 'Wijzigen', 'Wijzig groep eigenschappen', 'edit');
		$this->addKnop($update);

		if (property_exists($model::orm, 'aanmelden_tot')) {
			$sluiten = new DataTableKnop('>= 1', $this->tableId, $this->url . 'sluiten', 'post confirm', 'Sluiten', 'Inschrijvingen nu sluiten', 'lock');
			$this->addKnop($sluiten);
		}

		$opvolg = new DataTableKnop('>= 1', $this->tableId, $this->url . 'opvolging', 'post popup', 'Opvolging', 'Familienaam en groepstatus instellen', 'timeline');
		$this->addKnop($opvolg);

		$convert = new DataTableKnop('>= 1', $this->tableId, $this->url . 'converteren', 'post popup', 'Converteren', 'Converteer groep', 'lightning');
		$this->addKnop($convert);

		$delete = new DataTableKnop('>= 1', $this->tableId, $this->url . 'verwijderen', 'post confirm', 'Verwijderen', 'Definitief verwijderen', 'delete');
		$this->addKnop($delete);
	}

	public function getBreadcrumbs() {
		return '<a href="/groepen" title="Groepen"><span class="fa fa-users module-icon"></span></a> » <a href="' . $this->url . '">' . $this->naam . '</a> » <span class="active">Beheren</span>';
	}

	public function view() {
		$view = new CmsPaginaView(CmsPaginaModel::get($this->naam));
		$view->view();
		parent::view();
	}

}

class GroepenBeheerData extends DataTableResponse {

	public function getJson($groep) {
		$array = $groep->jsonSerialize();

		$array['detailSource'] = $groep->getUrl() . 'leden';
		$array['id'] .= '<a href="/rechten/bekijken/' . get_class($groep) . '/' . $groep->id . '" class="float-right" title="Rechten beheren"><img width="16" height="16" class="icon" src="/plaetjes/famfamfam/key.png" alt="rechten"></a>';
		$array['naam'] = '<span title="' . $groep->naam . (empty($groep->samenvatting) ? '' : '&#13;&#13;') . mb_substr($groep->samenvatting, 0, 100) . (strlen($groep->samenvatting) > 100 ? '...' : '' ) . '">' . $groep->naam . '</span>';
		$array['status'] = GroepStatus::getChar($groep->status);
		$array['samenvatting'] = null;
		$array['omschrijving'] = null;
		$array['website'] = null;
		$array['maker_uid'] = null;

		return parent::getJson($array);
	}

}

class GroepForm extends DataTableForm {

	public function __construct(Groep $groep, $action, $nocancel = false) {
		parent::__construct($groep, $action, get_class($groep) . ' ' . ($groep->id ? 'wijzigen' : 'aanmaken'));
		$fields = $this->generateFields();

		$fields['familie']->suggestions[] = $groep->getFamilieSuggesties();
		$fields['omschrijving']->description = 'Meer lezen';

		$fields['eind_moment']->from_datetime = $fields['begin_moment'];
		$fields['begin_moment']->to_datetime = $fields['eind_moment'];

		if (!LoginModel::mag('P_ADMIN')) {
			unset($fields['maker_uid']);
		}

		if (property_exists($groep, 'in_agenda')) {
			$fields['in_agenda']->required = false;
			$fields['in_agenda']->readonly = !LoginModel::mag('P_AGENDA_MOD');
		}

		// TODO: Wizard
		$this->wizard = true;

		$fields[] = $etc[] = new FormDefaultKnoppen($nocancel ? false : null);
		$this->addFields($fields);
	}

	public function validate() {
		$groep = $this->getModel();
		if (property_exists($groep, 'soort')) {
			$soort = $groep->soort;
		} else {
			$soort = null;
		}
		if (!$groep::magAlgemeen(A::Aanmaken, $soort)) {
			if ($groep instanceof Activiteit) {
				$soort = ActiviteitSoort::getDescription($soort);
			} elseif ($groep instanceof Commissie) {
				$soort = CommissieSoort::getDescription($soort);
			} else {
				$soort = get_class($groep);
			}
			setMelding('U mag geen ' . $soort . ' aanmaken', -1);
			return false;
		}

		$fields = $this->getFields();
		if ($fields['eind_moment']->getValue() !== null AND strtotime($fields['eind_moment']->getValue()) < strtotime($fields['begin_moment']->getValue())) {
			$fields['eind_moment']->error = 'Eindmoment moet na beginmoment liggen';
		}

		return parent::validate();
	}

}

class GroepOpvolgingForm extends DataTableForm {

	public function __construct(Groep $groep, $action) {
		parent::__construct($groep, $action, 'Opvolging instellen');

		$fields['fam'] = new TextField('familie', $groep->familie, 'Familienaam');
		$fields['fam']->suggestions[] = $groep->getFamilieSuggesties();

		$options = array();
		foreach (GroepStatus::getTypeOptions() as $status) {
			$options[$status] = GroepStatus::getChar($status);
		}
		$fields[] = new KeuzeRondjeField('status', $groep->status, 'Groepstatus', $options);

		$fields[] = new FormDefaultKnoppen();

		$this->addFields($fields);
	}

}

class GroepConverteerForm extends DataTableForm {

	private $soort;

	public function __construct(Groep $groep, GroepenModel $model) {
		parent::__construct($groep, $model->getUrl() . 'converteren', $model::orm . ' converteren');
		$huidig = get_class($model);

		$soorten = array();
		foreach (ActiviteitSoort::getTypeOptions() as $soort) {
			$soorten[$soort] = ActiviteitSoort::getDescription($soort);
		}
		$this->soort = new SelectField('soort', property_exists($groep, 'soort') ? $groep->soort : null, 'Activiteitsoort', $soorten);

		$options = array(
			'ActiviteitenModel'		 => $this->soort,
			'KetzersModel'			 => 'Ketzer (diversen)',
			'WerkgroepenModel'		 => WerkgroepenModel::orm,
			'OnderverenigingenModel' => OnderverenigingenModel::orm,
			'WoonoordenModel'		 => WoonoordenModel::orm,
			'BesturenModel'			 => BesturenModel::orm,
			'CommissiesModel'		 => CommissiesModel::orm,
			'GroepenModel'			 => 'Overige groep'
		);
		$class = new KeuzeRondjeField('class', $huidig, 'Converteren naar', $options, true);
		$class->newlines = true;

		$this->soort->onclick = <<<JS

$('#{$class->getId()}Option_ActiviteitenModel').click();
JS;

		$fields['class'] = $class;
		$fields[] = new FormDefaultKnoppen();
		$this->addFields($fields);
	}

	public function getValues() {
		$values = parent::getValues();
		$values['soort'] = $this->soort->getValue();
		return $values;
	}

	public function validate() {
		$values = $this->getValues();
		$model = $values['class']::instance(); // require once
		$orm = $model::orm;
		if (property_exists($orm, 'soort')) {
			$soort = $values['soort'];
		} else {
			$soort = null;
		}
		if (!$orm::magAlgemeen(A::Aanmaken, $soort)) {
			if ($model instanceof ActiviteitenModel) {
				$soort = ActiviteitSoort::getDescription($soort);
			} elseif ($model instanceof CommissiesModel) {
				$soort = CommissieSoort::getDescription($soort);
			} else {
				$soort = $model->getNaam();
			}
			setMelding('U mag geen ' . $soort . ' aanmaken', -1);
			return false;
		}

		return parent::validate();
	}

}

class GroepenView implements View {

	private $url;
	private $tab;
	private $groepen;
	/**
	 * Toon CMS pagina
	 * @var string
	 */
	private $pagina;

	public function __construct(GroepenModel $model, $groepen) {
		$this->groepen = $groepen;
		$this->url = $model->getUrl();
		$this->pagina = CmsPaginaModel::get($model->getNaam());
		if ($model instanceof BesturenModel) {
			$this->tab = GroepTab::Lijst;
		} else {
			$this->tab = GroepTab::Pasfotos;
		}
	}

	public function view() {
		echo '<div class="float-right"><a class="btn" href="' . $this->url . 'beheren"><img class="icon" src="/plaetjes/famfamfam/table.png" width="16" height="16"> Beheren</a></div>';
		$view = new CmsPaginaView($this->pagina);
		$view->view();
		foreach ($this->groepen as $groep) {
			// Controleer rechten
			if (!$groep->mag(A::Bekijken)) {
				continue;
			}
			echo '<hr>';
			$view = new GroepView($groep, $this->tab);
			$view->view();
		}
	}

	public function getBreadcrumbs() {
		return '<a href="/groepen" title="Groepen"><span class="fa fa-users module-icon"></span></a> » <span class="active">' . $this->getTitel() . '</span>';
	}

	public function getModel() {
		return $this->groepen;
	}

	public function getTitel() {
		return $this->pagina->titel;
	}

}

class GroepView implements View {

	private $groep;
	private $leden;
	private $bb;

	public function __construct(Groep $groep, $tab = null, $bb = false) {
		$this->groep = $groep;
		$this->bb = $bb;
		switch ($tab) {

			case GroepTab::Pasfotos:
				$this->leden = new GroepPasfotosView($groep);
				break;

			case GroepTab::Lijst:
				$this->leden = new GroepLijstView($groep);
				break;

			case GroepTab::Statistiek:
				$this->leden = new GroepStatistiekView($groep);
				break;

			case GroepTab::Emails:
				$this->leden = new GroepEmailsView($groep);
				break;

			case GroepTab::Emails:
				$this->leden = new GroepEmailsView($groep);
				break;

			default:
				if ($groep->keuzelijst) {
					$this->leden = new GroepLijstView($groep);
				} else {
					$this->leden = new GroepPasfotosView($groep);
				}
		}
	}

	public function getModel() {
		return $this->groep;
	}

	public function getTitel() {
		return $this->groep->naam;
	}

	public function getBreadcrumbs() {
		return null;
	}

	public function getHtml() {
		$html = '<div id="groep-' . $this->groep->id . '" class="bb-groep';
		if ($this->bb) {
			$html .= ' bb-block';
		}
		if ($this->groep->maker_uid == 1025 AND $this->bb) {
			$html .= ' bb-dies2015';
		}
		$html .= '"><div id="groep-samenvatting-' . $this->groep->id . '" class="groep-samenvatting"><h3>' . $this->getTitel() . '</h3>';
		if ($this->groep->maker_uid == 1025) {
			$html .= '<img src="/plaetjes/nieuws/m.png" width="70" height="70" alt="M" class="float-left" style="margin-right: 10px;">';
		}
		$html .= CsrBB::parse($this->groep->samenvatting);
		if (!empty($this->groep->omschrijving)) {
			$html .= '<div class="clear">&nbsp;</div><a id="groep-omschrijving-' . $this->groep->id . '" class="post noanim" href="' . $this->groep->getUrl() . 'omschrijving">Meer lezen »</a>';
		}
		$html .= '</div>';
		$html .= $this->leden->getHtml();
		$html .= '<div class="clear">&nbsp</div></div>';
		return $html;
	}

	public function view() {
		echo $this->getHtml();
	}

}
