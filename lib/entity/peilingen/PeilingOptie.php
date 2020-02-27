<?php

namespace CsrDelft\entity\peilingen;

use CsrDelft\common\datatable\DataTableEntry;
use CsrDelft\view\bbcode\CsrBB;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation as Serializer;

/**
 * @author G.J.W. Oolbekkink <g.j.w.oolbekkink@gmail.com>
 * @ORM\Entity(repositoryClass="CsrDelft\repository\peilingen\PeilingOptiesRepository")
 * @ORM\Table("peiling_optie")
 */
class PeilingOptie implements DataTableEntry {
	/**
	 * Primary key
	 * @var int
	 * @ORM\Column(type="integer")
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public $id;
	/**
	 * Foreign key
	 * @var int
	 * @ORM\Column(type="integer")
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public $peiling_id;
	/**
	 * Titel
	 * @var string
	 * @ORM\Column(type="string")
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public $titel;
	/**
	 * @var string
	 * @ORM\Column(type="text", nullable=true)
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public $beschrijving;
	/**
	 * Aantal stemmen
	 * @var int
	 * @ORM\Column(type="integer")
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public $stemmen = 0;
	/**
	 * @var string
	 * @ORM\Column(type="string", length=4, nullable=true)
	 * @Serializer\Groups({"datatable"})
	 */
	public $ingebracht_door;

	/**
	 * @var Peiling
	 * @ORM\ManyToOne(targetEntity="Peiling", inversedBy="opties")
	 */
	public $peiling;

	protected static $computed_attributes = [
		'beschrijving_formatted' => []
	];

	/**
	 * @return string
	 * @Serializer\Groups({"datatable", "vue"})
	 */
	public function getBeschrijvingFormatted() {
		return CsrBB::parse($this->beschrijving);
	}
}
