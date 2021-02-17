<?php

namespace CsrDelft\entity;

use CsrDelft\service\security\LoginService;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * @author C.S.R. Delft <pubcie@csrdelft.nl>
 * @author P.W.G. Brussee <brussee@live.nl>
 *
 * Content Management System Paginas zijn statische pagina's die via de front-end kunnen worden gewijzigd.
 *
 * @ORM\Table("cms_paginas")
 * @ORM\Entity(repositoryClass="CsrDelft\repository\CmsPaginaRepository")
 */
class CmsPagina {

	/**
	 * Primary key
	 * @ORM\Id()
	 * @ORM\Column(type="stringkey")
	 * @var string
	 */
	public $naam;
	/**
	 * Titel
	 * @ORM\Column(type="string")
	 * @var string
	 */
	public $titel;
	/**
	 * Inhoud
	 * @ORM\Column(type="text", length=16777216)
	 * @var string
	 */
	public $inhoud;
	/**
	 * DateTime
	 * @ORM\Column(type="datetime", name="laatst_gewijzigd")
	 * @var DateTimeImmutable
	 */
	public $laatstGewijzigd;
	/**
	 * Permissie voor tonen
	 * @ORM\Column(type="string", name="rechten_bekijken")
	 * @var string
	 */
	public $rechtenBekijken;
	/**
	 * Link
	 * @ORM\Column(type="string", name="rechten_bewerken")
	 * @var string
	 */
	public $rechtenBewerken;

	/**
	 * @return bool
	 */
	public function magBekijken() {
		return LoginService::mag($this->rechtenBekijken);
	}

	/**
	 * @return bool
	 */
	public function magBewerken() {
		return LoginService::mag($this->rechtenBewerken);
	}

	/**
	 * @return bool
	 */
	public function magRechtenWijzigen() {
		return LoginService::mag(P_ADMIN);
	}

	/**
	 * @return bool
	 */
	public function magVerwijderen() {
		return LoginService::mag(P_ADMIN);
	}

}
