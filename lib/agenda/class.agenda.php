<?php
# C.S.R. Delft | pubcie@csrdelft.nl
# -------------------------------------------------------------------
# class.agenda.php
# -------------------------------------------------------------------
# Dataklassen voor de agenda.
# -------------------------------------------------------------------

require_once 'maaltijden/class.maaltrack.php';

/**
 * Dit is een interface dat geïmplementeerd kan worden in allerlei
 * klassen, die dan als item in de agenda kunnen verschijnen.
 */
interface Agendeerbaar {

	public function getBeginMoment();
	public function getEindMoment();
	public function getTitel();
	public function getBeschrijving();
}

/**
 * AgendaItems zijn dingen in de agenda die niet ergens anders uit de
 * webstek komen.
 */
class AgendaItem implements Agendeerbaar {

	private $itemid;
	private $beginMoment;
	private $eindMoment;
	private $titel;
	private $beschrijving;
	private $rechtenBekijken;

	public function __construct($itemid=0, $beginMoment=0, $eindMoment=0, $titel='', $beschrijving='', $rechtenBekijken='P_NOBODY') {
		$this->itemid = $itemid;
		$this->setBeginMoment($beginMoment);
		$this->setEindMoment($eindMoment);
		$this->setTitel($titel);
		$this->setBeschrijving($beschrijving);
		$this->setRechtenBekijken($rechtenBekijken);
	}

	public function getItemID() {
		return $this->itemid;
	}
	public function getBeginMoment() {
		return $this->beginMoment;
	}
	public function getEindMoment() {
		return $this->eindMoment;
	}
	public function getTitel() {
		return $this->titel;
	}
	public function getBeschrijving() {
		return $this->beschrijving;
	}
	public function getRechtenBekijken() {
		return $this->rechtenBekijken;
	}

	public function setBeginMoment($beginMoment) {
		$this->beginMoment = $beginMoment;
	}
	public function setEindMoment($eindMoment) {
		$this->eindMoment = $eindMoment;
	}
	public function setTitel($titel) {
		$this->titel = $titel;
	}
	public function setBeschrijving($beschrijving) {
		$this->beschrijving = $beschrijving;
	}
	public function setRechtenBekijken($rechtenBekijken) {
		$this->rechtenBekijken = $rechtenBekijken;
	}

	public function magBekijken() {
		return LoginLid::instance()->hasPermission($this->getRechtenBekijken());
	}
	
	public function opslaan() {
		$db = MySql::instance();
		if ($this->getItemID() == 0) {
			$query = "
				INSERT INTO agenda (
					titel, beschrijving, begin, eind, rechtenBekijken
				) VALUES (
					'".$db->escape($this->getTitel())."',
					'".$db->escape($this->getBeschrijving())."',
					FROM_UNIXTIME(".$this->getBeginMoment()."),
					FROM_UNIXTIME(".$this->getEindMoment()."),
					'".$this->getRechtenBekijken()."'
				);";
		} else {
			$query = "
				UPDATE agenda SET
					titel = '".$db->escape($this->getTitel())."',
					beschrijving = '".$db->escape($this->getBeschrijving())."',
					begin = FROM_UNIXTIME(".$this->getBeginMoment()."),
					eind = FROM_UNIXTIME(".$this->getEindMoment().")
				WHERE id=".$this->getItemID().";";
		}
		if ($db->query($query)) {
			if ($this->getItemID() == 0) {
				$this->itemid = $db->insert_id();
			}
			return true;
		}
		return false;
	}
	
	public function verwijder() {
		$db = MySQL::instance();
		$query = "DELETE FROM agenda WHERE id = ".$this->getItemID();
		if ($db->query($query)) {
			return true;
		} else {
			return false;
		}
	}
	
	public static function getItem($id) {
		$db = MySQL::instance();
		$query = "SELECT titel, beschrijving, begin, eind, rechtenBekijken 
					FROM agenda WHERE id = ".(int)$id;
		$item = $db->getRow($query);
		$item['begin'] = strtotime($item['begin']);
		$item['eind'] = strtotime($item['eind']);
		
		return new AgendaItem($id, $item['begin'], $item['eind'], $item['titel'], 
				$item['beschrijving'], $item['rechtenBekijken']);
	}
}

/**
 * De Agenda bevat alle Agendeerbare objecten die voorkomen in de webstek.
 */
class Agenda {

	private $items;

	public function __construct() {

	}
	
	public function magToevoegen() {
		return LoginLid::instance()->hasPermission('P_AGENDA_POST');
	}
	
	public function magBeheren() {
		return LoginLid::instance()->hasPermission('P_AGENDA_MOD');
	}

	public function getItems($van=null, $tot=null, $filter=false) {
		$result = array();

		// Regulie agenda-items
		$qItems = "SELECT id, titel, beschrijving, begin, eind, rechtenBekijken FROM agenda WHERE 1=1";
		if ($van != null) {
			$qItems .= " AND eind >= '".date('Y-m-d', $van)."'";
		}
		if ($tot != null) {
			$qItems .= " AND begin <= '".date('Y-m-d', $tot)."'";
		}
		$qItems .= " ORDER BY begin ASC, titel ASC";

		$rItems = MySql::instance()->query($qItems);
		while ($aItem = MySql::instance()->next($rItems)) {
			$item = new AgendaItem($aItem['id'], strtotime($aItem['begin']), strtotime($aItem['eind']), $aItem['titel'], $aItem['beschrijving'], $aItem['rechtenBekijken']);

			if ($filter == false || $item->magBekijken()) {
				$result[] = $item;
			}
		}
		
		// Maaltijden ophalen
		$maaltrack = new Maaltrack();		
		$result = array_merge($result, $maaltrack->getMaaltijden($van, $tot, $filter, $filter, null, false));
		
		// Sorteren
		usort($result, array('Agenda', 'vergelijkAgendeerbaars'));

		return $result;
	}

	public function getItemsByWeek($jaar=null, $week=null) {
		$van = null;
		$tot = null;

		return $this->getItems($van, $tot);
	}

	public function getItemsByMaand($jaar, $maand) {		
		// Zondag van de eerste week van de maand uitrekenen
		$startMoment = mktime(0, 0, 0, $maand, 1, $jaar);		
		if (date('w', $startMoment) != 0) {
			$startMoment = strtotime('last Sunday', $startMoment);
		}
		
		// Zaterdag van de laatste week van de maand uitrekenen
		$eindMoment = mktime(0, 0, 0, $maand, 1, $jaar);
		$eindMoment = strtotime('+1 month', $eindMoment) - 1;
		if (date('w', $eindMoment) == 6) {
			$eindMoment++;			
		} else {
			$eindMoment = strtotime('next Saturday', $eindMoment);
			$eindMoment = strtotime('+1 day', $eindMoment);
		}
		
		// Array met weken en dagen maken
		$cur = $startMoment;		
		$agenda = array();
		while ($cur != $eindMoment) {
			$week = Agenda::weekNumber($cur);
			$dag = date('d', $cur);			
			$agenda[$week][$dag]['datum'] = $cur;
			$agenda[$week][$dag]['items'] = array();
			
			$cur = strtotime('+1 day', $cur);			
		}
				
		// Items toevoegen aan het array
		$items = $this->getItems($startMoment, $eindMoment);
		foreach ($items as $item) {
			$week = Agenda::weekNumber($item->getBeginMoment());
			$dag = date('d', $item->getEindMoment());
			$agenda[$week][$dag]['items'][] = $item;
		}	
		
		return $agenda;
	}
	
	/**
	 * Geeft het weeknummer van de eerste dag van de week van $date terug.
	 */
	public static function weekNumber($date) {
		if (date('w', $date) == 0) {
			return strftime('%U', $date);
		} else {
			return strftime('%U', strtotime('last Sunday', $date));
		}
	}
	
	/**
	 * Vergelijkt twee Agendeerbaars op beginMoment t.b.v. sorteren.
	 */
	public static function vergelijkAgendeerbaars(Agendeerbaar $foo, Agendeerbaar $bar) {
		if ($foo->getBeginMoment() == $bar->getBeginMoment) {
			return 0;
		}
		return ($foo->getBeginMoment() > $bar->getBeginMoment()) ? 1 : -1;
	}
}
?>