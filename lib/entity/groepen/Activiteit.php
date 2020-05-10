<?php

namespace CsrDelft\entity\groepen;

use CsrDelft\entity\agenda\Agendeerbaar;
use CsrDelft\model\entity\interfaces\HeeftAanmeldLimiet;
use CsrDelft\model\entity\interfaces\HeeftSoort;
use CsrDelft\model\entity\security\AccessAction;
use CsrDelft\model\security\LoginModel;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;


/**
 * Activiteit.class.php
 *
 * @author P.W.G. Brussee <brussee@live.nl>
 *
 * @ORM\Entity(repositoryClass="CsrDelft\repository\groepen\ActiviteitenRepository")
 * @ORM\Table("activiteiten")
 */
class Activiteit extends AbstractGroep implements Agendeerbaar, HeeftAanmeldLimiet, HeeftSoort {
	public function getUUID() {
		return $this->id . '@activiteit.csrdelft.nl';
	}

	/**
	 * Maximaal aantal groepsleden
	 * @var string
	 * @ORM\Column(type="integer", nullable=true)
	 * @Serializer\Groups("datatable")
	 */
	public $aanmeld_limiet;
	/**
	 * Datum en tijd aanmeldperiode begin
	 * @var DateTimeImmutable
	 * @ORM\Column(type="datetime")
	 * @Serializer\Groups("datatable")
	 */
	public $aanmelden_vanaf;
	/**
	 * Datum en tijd aanmeldperiode einde
	 * @var DateTimeImmutable
	 * @ORM\Column(type="datetime")
	 * @Serializer\Groups("datatable")
	 */
	public $aanmelden_tot;
	/**
	 * Datum en tijd aanmelding bewerken toegestaan
	 * @var DateTimeImmutable|null
	 * @ORM\Column(type="datetime", nullable=true)
	 * @Serializer\Groups("datatable")
	 */
	public $bewerken_tot;
	/**
	 * Datum en tijd afmelden toegestaan
	 * @var DateTimeImmutable|null
	 * @ORM\Column(type="datetime", nullable=true)
	 * @Serializer\Groups("datatable")
	 */
	public $afmelden_tot;

	/**
	 * Intern / Extern / SjaarsActie / etc.
	 * @var ActiviteitSoort
	 * @ORM\Column(type="string")
	 * @Serializer\Groups("datatable")
	 */
	public $soort;
	/**
	 * Rechten benodigd voor aanmelden
	 * @var string
	 * @ORM\Column(type="string")
	 * @Serializer\Groups("datatable")
	 */
	public $rechten_aanmelden;
	/**
	 * Locatie
	 * @var string
	 */
	public $locatie;
	/**
	 * Tonen in agenda
	 * @var boolean
	 * @ORM\Column(type="boolean")
	 * @Serializer\Groups("datatable")
	 */
	public $in_agenda;

	/**
	 * @var ActiviteitDeelnemer[]
	 * @ORM\OneToMany(targetEntity="ActiviteitDeelnemer", mappedBy="groep")
	 */
	public $leden;

	public function getLeden() {
		return $this->leden;
	}

	public function getLidType() {
		return ActiviteitDeelnemer::class;
	}

	public function getUrl() {
		return '/groepen/activiteiten/' . $this->id;
	}

	/**
	 * Has permission for action?
	 *
	 * @param string $action
	 * @param array|null $allowedAuthenticationMethods
	 * @return boolean
	 */
	public function mag($action, $allowedAuthenticationMethods = null) {
		switch ($action) {

			case AccessAction::Bekijken:
			case AccessAction::Aanmelden:
				if (!empty($this->rechten_aanmelden) AND !LoginModel::mag($this->rechten_aanmelden, $allowedAuthenticationMethods)) {
					return false;
				}
				break;
		}
		$nu = date_create_immutable();
		switch ($action) {
			case AccessAction::Aanmelden:
				// Controleer maximum leden
				if (isset($this->aanmeld_limiet) and $this->aantalLeden() >= $this->aanmeld_limiet) {
					return false;
				}
				// Controleer aanmeldperiode
				if ($nu > $this->aanmelden_tot || $nu < $this->aanmelden_vanaf) {
					return false;
				}
				break;

			case AccessAction::Bewerken:
				// Controleer bewerkperiode
				if ( $nu > $this->bewerken_tot) {
					return false;
				}
				break;

			case AccessAction::Afmelden:
				// Controleer afmeldperiode
				if ($nu > $this->afmelden_tot) {
					return false;
				}
				break;
		}
		return parent::mag($action, $allowedAuthenticationMethods);
	}

	/**
	 * Rechten voor de gehele klasse of soort groep?
	 *
	 * @param AccessAction $action
	 * @param array|null $allowedAuthenticationMethods
	 * @param string $soort
	 * @return boolean
	 */
	public static function magAlgemeen($action, $allowedAuthenticationMethods=null, $soort = null) {
		if ($soort) {
			switch (ActiviteitSoort::from($soort)) {

				case ActiviteitSoort::OWee():
					if (LoginModel::mag('commissie:OWeeCie', $allowedAuthenticationMethods)) {
						return true;
					}
					break;

				case ActiviteitSoort::Dies():
					if (LoginModel::mag('commissie:DiesCie', $allowedAuthenticationMethods)) {
						return true;
					}
					break;

				case ActiviteitSoort::Lustrum():
					if (LoginModel::mag('commissie:LustrumCie', $allowedAuthenticationMethods)) {
						return true;
					}
					break;
			}
		}
		switch ($action) {

			case AccessAction::Aanmaken:
			case AccessAction::Aanmelden:
			case AccessAction::Bewerken:
			case AccessAction::Afmelden:
				return true;
		}
		return parent::magAlgemeen($action, $allowedAuthenticationMethods, $soort);
	}

	// Agendeerbaar:

	public function getBeginMoment() {
		return $this->begin_moment->getTimestamp();
	}

	public function getEindMoment() {
		if ($this->eind_moment AND $this->eind_moment !== $this->begin_moment) {
			return $this->eind_moment->getTimestamp();
		}
		return $this->getBeginMoment() + 1800;
	}

	public function getTitel() {
		return $this->naam;
	}

	public function getBeschrijving() {
		return $this->samenvatting;
	}

	public function getLocatie() {
		return $this->locatie;
	}

	public function isHeledag() {
		$begin = date('H:i', $this->getBeginMoment());
		$eind = date('H:i', $this->getEindMoment());
		return $begin == '00:00' AND ($eind == '23:59' OR $eind == '00:00');
	}

	public function isTransparant() {
		// Toon als transparant (vrij) als lid dat wil, activiteit hele dag(en) duurt of lid niet ingeketzt is
		return lid_instelling('agenda', 'transparantICal') === 'ja' ||
			$this->isHeledag() ||
			$this->getLid(LoginModel::getUid()) === false;
	}

	public function getAanmeldLimiet() {
		return $this->aanmeld_limiet;
	}

	public function getSoort() {
		return $this->soort;
	}

	public function setSoort($soort) {
		$this->soort = $soort;
	}
}
