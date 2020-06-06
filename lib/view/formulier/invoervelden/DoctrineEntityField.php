<?php

namespace CsrDelft\view\formulier\invoervelden;

use CsrDelft\common\ContainerFacade;
use CsrDelft\common\CsrException;
use CsrDelft\view\formulier\DisplayEntity;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @author G.J.W. Oolbekkink <g.j.w.oolbekkink@gmail.com>
 * @since 30/03/2017
 *
 * Select an entity based on primary key values in hidden input fields, supplied by remote data source.
 *
 * NOTE: support alleen entities met een enkele primary key.
 */
class DoctrineEntityField extends InputField {
	/**
	 * @var string
	 */
	private $show_value;
	/**
	 * @var  DisplayEntity
	 */
	private $entity;
	/**
	 * @var string
	 */
	private $idField;
	/**
	 * @var EntityManagerInterface
	 */
	private $em;
	/**
	 * @var string
	 */
	private $entityType;

	/**
	 * EntityField constructor.
	 * @param $name string Prefix van de input
	 * @param DisplayEntity|null $value
	 * @param $description string Beschrijvijng van de input
	 * @param $type
	 * @param $url string Url waar aanvullingen te vinden zijn
	 */
	public function __construct($name, $value, $description, $type, $url) {
		if (!is_a($type, DisplayEntity::class, true)) {
			throw new CsrException($type . ' moet DisplayEntity implementeren voor DoctrineEntityField');
		}
		$this->em = ContainerFacade::getContainer()->get('doctrine.orm.entity_manager');

		$meta = $this->em->getClassMetadata($type);

		if (count($meta->getIdentifier()) !== 1) {
			throw new CsrException('DoctrineEntityField ondersteund geen entities met een composite primary key');
		}

		$this->idField = $meta->getIdentifier()[0];
		$this->entityType = $type;
		$this->entity = $value ?? new $type();
		$this->suggestions[] = $url;
		$this->show_value = $this->entity->getWeergave();
		$this->origvalue = (string) $this->entity->getId();

		parent::__construct($name, $value ? (string) $value->getId() : null, $description);

		$this->autoselect = true;
	}

	public function getFormattedValue() {
		$value = $this->getValue();
		if ($value == null) {
			return null;
		}
		return $this->em->getReference($this->entityType, $value);
	}

	public function getName() {
		return $this->name;
	}

	public function validate() {
		if (!parent::validate()) {
			return false;
		}
		// parent checks not null
		if ($this->value == '') {
			return true;
		}

		return $this->error === '';
	}

	public function getHtml() {
		$html = '<input name="' . $this->name . '_show" value="' . $this->entity->getWeergave() . '" origvalue="' . $this->entity->getWeergave() . '"' . $this->getInputAttribute(array('type', 'id', 'class', 'disabled', 'readonly', 'maxlength', 'placeholder', 'autocomplete')) . ' />';

		$id = $this->getId() . '_' . $this->idField;
		$this->typeahead_selected .= '$("#' . $id . '").val(suggestion["' . $this->idField . '"]);';
		$html .= '<input type="hidden" name="' . $this->name . '" id="' . $id . '" value="' . $this->entity->getId() . '" />';

		return $html;
	}

	/**
	 * Dit veld is gepost als show en de pk is gepost.
	 *
	 * @return bool Of alles gepost is
	 */
	public function isPosted() {
		if (!filter_input(INPUT_POST, $this->name . '_show', FILTER_DEFAULT)) {
			return false;
		}

		if (!filter_input(INPUT_POST, $this->name, FILTER_DEFAULT)) {
			return false;
		}

		return true;
	}

}
