<?php
# C.S.R. Delft | pubcie@csrdelft.nl
# -------------------------------------------------------------------
# maaltijden/class.maaltijd.php
# -------------------------------------------------------------------
# De logica van Maaltijd zit als volgt in elkaar:
#
# Er zijn maaltijden met een aantal eigenschappen, leden kunnen abonnementen
# hebben, of zich los inschrijven voor maaltijden.
#
# Qua inschrijving van een lid voor een bepaalde maaltijd zijn er 3 mogelijkheden:
# AAN - expliciet aanmelden
# AF - expliciet afmelden
# geen expliciete aan/afmelding - inschrijving klappert mee met een abo
#
# de handmatige opties overrulen een abonnement.
# zodra het maximum van een maaltijd is bereikt, kan er niet meer voor ingeschreven
# worden. als er een abo wordt aangezet op een volle maaltijd zal dit resulteren in
# een expliciete uitschrijving voor de reeds volle maaltijd die het abo overschrijft
# deze expliciete uitschrijving kan niet veranderd worden in een aanmelding als de
# maaltijd vol is, en het abo nog aanstaat
#
# N.B. Het controleren op permissies en het controleren van correctheid van
# opgestuurde data in het formulier gebeurt *niet* hier, maar een stap eerder,
# bij het inladen van de FORM-data.
# -------------------------------------------------------------------

require_once 'agenda/agenda.class.php';

class Maaltijd implements Agendeerbaar {
	# MySQL connectie
	private $_db;


	# id van de maaltijd waar we bewerkingen op uitvoeren
	private $_maalid;
	# Evt. foutmelding
	private $_error = '';
	private $_proxyerror = '';

	# maaltijd-record
	private $_maaltijd = false;

	# we gaan bewerkingen uitvoeren op een maaltijd, onder verantwoordelijkheid van een bepaald lid
	# NB!! Gebruik MaalTrack::isMaaltijd voor controle of de maaltijd wel bestaat
	function __construct($maalid) {
		$this->_maalid = (int)$maalid;
		$this->_db=MySql::instance();

		# gegevens van de maaltijd inladen
		$result = $this->_db->select("SELECT * FROM maaltijd WHERE id='{$this->_maalid}'");
		if (($result !== false) and $this->_db->numRows($result) > 0){
			$this->_maaltijd = $this->_db->next($result);
		}
	}
	
	# haalt de taken op
	function getTaken(){
		$taken = array();
		$sMaaltijdTakenQuery="
			SELECT
				uid, kok, afwas, theedoek, schoonmaken_frituur, schoonmaken_afzuigkap, schoonmaken_keuken, klussen_licht, klussen_zwaar, punten_toegekend
			FROM
				maaltijdcorvee
			WHERE
				maalid=".$this->_maalid."";
		$rMaaltijdTaken=$this->_db->query($sMaaltijdTakenQuery);
		if (($rMaaltijdTaken !== false) and $this->_db->numRows($rMaaltijdTaken) > 0) {
			while ($record = $this->_db->next($rMaaltijdTaken)) {
				if ($record['kok']) $taken['koks'][] = $record['uid'];
				if ($record['afwas']) $taken['afwassers'][] = $record['uid'];
				if ($record['theedoek']) $taken['theedoeken'][] = $record['uid'];
				if ($record['schoonmaken_frituur']) $taken['schoonmaken_frituur'][] = $record['uid'];
				if ($record['schoonmaken_afzuigkap']) $taken['schoonmaken_afzuigkap'][] = $record['uid'];
				if ($record['schoonmaken_keuken']) $taken['schoonmaken_keuken'][] = $record['uid'];
				if ($record['klussen_licht']) $taken['klussen_licht'][] = $record['uid'];
				if ($record['klussen_zwaar']) $taken['klussen_zwaar'][] = $record['uid'];
				if ($record['punten_toegekend']) $taken['toegekend'][$record['uid']] = $record['punten_toegekend'];				
			}
		}

		return $taken;
	}

	function getError() {
		$error = $this->_error;
		$this->_error = "";
		return $error;
	}

	# De 'proxy' is het aan/afmelden van anderen via je eigen login
	function getProxyError() {
		$error = $this->_proxyerror;
		$this->_proxyerror = "";
		return $error;
	}

	# wat gegevens voor de maaltijdprintlijst
	function getDatum() { return $this->_maaltijd['datum']; }
	function getTP() { return $this->_maaltijd['tp']; }
	public function isTp($uid=null){
		if($uid==null){ $uid=LoginLid::instance()->getUid(); }
		return $uid==$this->getTP();
	}
	public function isKok($uid=null){
		if($uid==null){ $uid=LoginLid::instance()->getUid(); }
		$kok=0;
		$sKok="
			SELECT
				kok
			FROM
				maaltijdcorvee
			WHERE
				uid = '".$uid."' AND maalid = ".$this->getMaalId().";";
		$rKok = $this->_db->query($sKok);
		if (($rKok !== false) and $this->_db->numRows($rKok) > 0) {
			$record = $this->_db->next($rKok);
			$kok = $record['kok'];
		}
		return $kok == 1;
	}

	public function getID(){ return $this->getMaalId(); }
	function getMaalId() { return $this->_maalid; }

	public function getMoment(){ return date('Y-m-d H:i', $this->_maaltijd['datum']); }
	public function getTekst(){ return $this->_maaltijd['tekst']; }
	public function getKoks(){ return $this->_maaltijd['koks']; }
	public function getAfwassers(){ return $this->_maaltijd['afwassers']; }
	public function getTheedoeken(){ return $this->_maaltijd['theedoeken']; }
	# alle info...
	public function getInfo() { return $this->_maaltijd; }
	public function getAantalAanmeldingen(){ return $this->_maaltijd['aantal']; }
	public function getMaxAanmeldingen(){ return $this->_maaltijd['max']; }
	
	# Aanmelden van een gebruiker voor deze maaltijd.
	function aanmelden($uid = '') {
		$loginlid=LoginLid::instance();
		if ($uid == '') $uid = $loginlid->getUid();
		$proxy = ($uid != $loginlid->getUid()) ? true : false;

		# kijken of er wel een geldige uid is opgegeven
		if ($proxy and (!Lid::exists($uid) or !preg_match('/S_((GAST)?LID|NOVIET)/', $loginlid->getLid()->getStatus($uid))) ) {
			$this->_proxyerror = "Opgegeven lid bestaat niet of is geen gewoon Lid.";
			return false;
		}

		# kijken of iemand anders aangemeld wordt voor een maaltijd	die meer dan
		# MAALTIJD_PROXY_MAX_TOT vooruit is
		# P_MAAL_MOD mag dat wel overigens...
		if ($proxy and ($this->_maaltijd['datum'] - time()) > MAALTIJD_PROXY_MAX_TOT and !$loginlid->hasPermission('P_MAAL_MOD')) {
			$this->_proxyerror = "U kunt een ander persoon nu niet voor deze maaltijd opgeven.";
			return false;
		}

		if ($proxy){ $fullname = $loginlid->getLid()->getNaam(); }

		# kan er ueberhaupt nog veranderd worden aan deze maaltijd?
		if ($this->_maaltijd['gesloten'] == '1') {
			if (!$proxy){
				$this->_error = "De inschrijving voor deze maaltijd is inmiddels gesloten.";
			}else{
				$this->_proxyerror = "De inschrijving voor deze maaltijd is inmiddels gesloten.";
			}
			return false;
		}

		# kijk of deze gebruiker al was aan- of afgemeld
		$status = $this->getStatus($uid);
		# $status is nu 'AAN', 'AF' of 'AUTO'

		# combineer de gegevens en kijk of de gewenste actie in een
		# netto extra inschrijving resulteert, en of dat kan.
		# extra inschrijving als:
		# - status AF
		# - status AUTO en geen abo
		if (($status == 'AF' or ($status == 'AUTO' and !$this->heeftAbo($uid))) and $this->isVol()) {
			if (!$proxy) $this->_error = "De aanmelding is mislukt omdat het maximaal aantal inschrijvingen inmiddels is bereikt.";
			else $this->_proxyerror = "De aanmelding is mislukt omdat het maximaal aantal inschrijvingen inmiddels is bereikt.";
			return false;
		}

		# aanmelding wegschrijven
		$time = time();
		$door = $loginlid->getUid();
		if(isset($_SERVER['REMOTE_ADDR'])){ $ip = $_SERVER['REMOTE_ADDR'];
		}else{ $ip = '0.0.0.0'; }

		# als er een AF stond, maken we er een AAN van
		if ($status == 'AF') {
			$this->_db->query("
				UPDATE maaltijdaanmelding
				SET
					status = 'AAN',
					tijdstip = {$time},
					door = '{$door}',
					ip = '{$ip}'
				WHERE maalid='{$this->_maalid}' AND uid='{$uid}'
			");
			$this->recount();
			if ($proxy) $this->_proxyerror = "{$fullname} is nu aangemeld voor de maaltijd.";
			return true;
		# als er nog niets stond zetten we een AAN in de tabel
		} elseif ($status == 'AUTO') {
			$this->_db->query("
				INSERT INTO maaltijdaanmelding (uid, maalid, status, tijdstip, door, ip)
				VALUES('{$uid}', '{$this->_maalid}', 'AAN', '{$time}', '{$door}', '{$ip}');
			");
			$this->recount();
			return true;
		# als gebruiker al is aangemeld zeggen dat dat al zo is
		} elseif ($status == 'AAN') {
			if (!$proxy) $this->_error = "U bent al aangemeld voor deze maaltijd.";
			else $this->_proxyerror = "De persoon die u wilt aanmelden is inmiddels al aangemeld voor deze maaltijd.";
			return false;
		}
		return false;
	}

	# Afmelden van een gebruiker voor deze maaltijd.
	function afmelden($uid = '') {
		$loginlid=LoginLid::instance();
		if ($uid == '') $uid = $loginlid->getUid();
		$proxy = (!$loginlid->isSelf($uid)) ? true : false;

		if ($proxy) {
			# afmelden anderen mag als we MAAL_MOD rechten hebben
			if ($loginlid->hasPermission('P_MAAL_MOD')) {
			# of als we MAAL_WIJ hebben en op confide zijn
			} elseif (opConfide() or $this->aangemeldDoor($uid, $loginlid->getUid())) {
			} else {
				$this->_proxyerror = "U heeft geen rechten om personen af te melden die u niet zelf aangemeld heeft.";
				return false;
			}
		}

		if ($proxy and !Lid::exists($uid)) {
			$this->_proxyerror = "Opgegeven lid bestaat niet.";
			return false;
		}
		if ($proxy) $fullname = (string)LidCache::getLid($uid);

		# kan er ueberhaupt nog veranderd worden aan deze maaltijd?
		if ($this->_maaltijd['gesloten'] == '1') {
			if (!$proxy) $this->_error = "De inschrijving voor deze maaltijd is inmiddels gesloten.";
			else $this->_proxyerror = "De inschrijving voor deze maaltijd is inmiddels gesloten.";
			return false;
		}

		# kijk of deze gebruiker al was aan- of afgemeld
		$status = $this->getStatus($uid);
		# $status is nu 'AAN', 'AF' of 'AUTO'

		# afmelden zal geen extra inschrijving opleveren, dus we letten niet op isvol();

		# afmelding wegschrijven
		$time = time();
		$door = $loginlid->getUid();
		if (isset($_SERVER['REMOTE_ADDR'])) $ip = $_SERVER['REMOTE_ADDR'];
		else $ip = '0.0.0.0';

		switch($status){
			case 'AAN':
				# als er een AAN stond, maken we er een AF van
				$afmelden="
					UPDATE maaltijdaanmelding
					SET
						status = 'AF',
						tijdstip = {$time},
						door = '{$door}',
						ip = '{$ip}',
						gasten = 0,
						gasten_opmerking = ''
					WHERE maalid='{$this->_maalid}' AND uid = '{$uid}'
				";
				$this->_db->query($afmelden);
				$this->recount();
				return true;
			break;
			case 'AUTO':
				# als er niets stond een AF neerzetten
				$afmelden="
					INSERT INTO maaltijdaanmelding (
						uid, maalid, status, tijdstip, door, ip
					)VALUES(
						'{$uid}', '{$this->_maalid}', 'AF', '{$time}', '{$door}', '{$ip}'
					);";
				$this->_db->query($afmelden);
				$this->recount();
				return true;
			break;
			case 'AF':
				# als er al was afgemeld niets doen
				if (!$proxy){
					$this->_error = "U bent al afgemeld voor deze maaltijd.";
				}else{
					$this->_proxyerror = "{$fullname} al afgemeld voor deze maaltijd.";
				}

				return false;
			break;
		}
		return false;
	}

	public function heeftAbo($uid=null) {
		if($uid==null){
			$uid =LoginLid::instance()->getUid();
		}elseif(!Lid::exists($uid)){
			$this->_error = "Opgegeven lid bestaat niet.";
			return false;
		}
		# kijk of deze gebruiker een abo voor deze maaltijd heeft
		$heeftAbo="
			SELECT uid FROM maaltijdabo
			WHERE uid='{$uid}' AND abosoort='{$this->_maaltijd['abosoort']}'
			LIMIT 1;";
		$result =$this->_db->select($heeftAbo);
		return (($result !== false) and $this->_db->numRows($result) > 0);
	}

	function getStatus($uid=null) {
		if($uid===null){ $uid=LoginLid::instance()->getUid(); }
		if($this->isGesloten()){
			$result = $this->_db->select("SELECT uid FROM maaltijdgesloten WHERE maalid={$this->_maalid} AND uid='{$uid}'");
			if (($result !== false) and $this->_db->numRows($result) > 0) {
				return 'AAN';
			}
			return 'AF';
		}else{
			# kijk of deze gebruiker al was aan- of afgemeld
			$result = $this->_db->select("SELECT status FROM maaltijdaanmelding WHERE maalid={$this->_maalid} AND uid='{$uid}'");
			if (($result !== false) and $this->_db->numRows($result) > 0) {
				$record = $this->_db->next($result);
				if ($record['status'] == 'AAN' or $record['status'] == 'AF' or $record['status'] == 'ONBEKEND'){
					return $record['status'];
				}
			}
			return 'AUTO';
		}
	}

	function getGasten($uid = '') {
		# vraag het huidige aantal gasten op voor de maaltijd
		$result = $this->_db->select("SELECT gasten FROM maaltijdaanmelding WHERE maalid='{$this->_maalid}' AND uid = '{$uid}'");
		if (($result !== false) and $this->_db->numRows($result) > 0) {
			$record = $this->_db->next($result);
			return $record['gasten'];
		}
		return 0;
	}

	# is $uid aangemeld door $door?
	function aangemeldDoor($uid, $door) {
		$result = $this->_db->select("
			SELECT maalid
			FROM maaltijdaanmelding
			WHERE maalid='{$this->_maalid}'
			AND uid = '{$uid}'
			AND door = '{$door}'
			AND status = 'AAN';");
		return (($result !== false) AND $this->_db->numRows($result) > 0);
	}

	/*
	* Gasten aanmelden gaat per lid. Een lid kan een gast meenemen. Als een gast niet
	* expliciet bij iemand hoort kan een van de bestuursleden de gasten onder de eigen
	* naam zetten.
	*
	* $gasten = aantal gasten voor het huidige lid.
	* $opmerking = eventuele eetwens.
	*/
	function gastAanmelden($gasten, $opmerking) {

		$gasten = abs((int)$gasten);
		$opmerking = $this->_db->escape(mb_substr(trim($opmerking), 0, 255));
		$uid=$loginlid=LoginLid::instance()->getUid();

		## Als gasten 0 is, gooi dan opmerkingen maar leeg
		if ($gasten < 1) $opmerking = '';

		//alvorens gasten aangemeld kunnen worden moet er een regeltje zijn in de
		//maaltijdaanmelding-tabel. Als die nog niet bestaat moet het dus NU even
		//aangemaakt worden. Als men niet is aangemeld, kan men ook geen gasten
		//meenemen.
		$status = $this->getStatus($uid);
		if($status=='AF'){
			$this->_error="U bent zelf niet aangemeld";
			return false;
		}elseif($status=='AUTO'){
			//status is auto, dus nog even een expliciete aanmelding maken
			$this->aanmelden();
		}

		# Het maximum aantal deelnemers aan de maaltijd mag niet overschreden worden
		if ($this->isVol($gasten-$this->getGasten($uid))) {
			$this->_error = "De gastenaanmelding is mislukt omdat het maximaal aantal inschrijvingen is bereikt, of wordt bereikt als de ".$gasten." gasten toegevoegd worden.";
			return false;
		}

		$aanmelden="
			UPDATE
				maaltijdaanmelding
			SET
				gasten=".$gasten.",
				gasten_opmerking='".$opmerking."'
			WHERE
				uid='".$uid."'
			AND
				maalid=".$this->_maalid."
			LIMIT 1;";
		if(!$this->_db->query($aanmelden) AND $this->_db->affectedRows()!=1){
			$this->_error="U bent zelf niet aangemeld";
			return false;
		}
		$this->recount();
		return true;
	}

	# tel het aantal aanmeldingen voor een maaltijd opnieuw en zet het in
	# de tabel bij de maaltijd
	function recount() {
		# tel alle abo's:
		# iedereen die de abosoort als abo heeft, en niet voorkomt in de aanmeldingentabel
		$abo = 0;
		$abonnementen="
			SELECT count(*) AS aantal
			FROM maaltijdabo
			WHERE
				abosoort = '{$this->_maaltijd['abosoort']}'
			AND
				uid NOT IN (
					SELECT uid
					FROM maaltijdaanmelding
					WHERE maalid = '{$this->_maalid}')
			LIMIT 1;";
		$result = $this->_db->select($abonnementen);
		if (($result !== false) and $this->_db->numRows($result) > 0) {
			$record = $this->_db->next($result);
			$abo = $record['aantal'];
		}

		# tel alle aanmeldingen
		# aantal AAN in de maaltijdaanmeldingtabel
		$aan = 0;
		$aanmeldingen="
			SELECT count(*) AS aantal
			FROM maaltijdaanmelding
			WHERE maalid='{$this->_maalid}' AND status = 'AAN'";
		$result = $this->_db->select($aanmeldingen);
		if (($result !== false) and $this->_db->numRows($result) > 0) {
			$record = $this->_db->next($result);
			$aan = $record['aantal'];
		}

		# tel alle gasten
		$gasten=0;
		$sGasten="
			SELECT
				SUM(gasten) as aantal
			FROM
				maaltijdaanmelding
			WHERE
				maalid=".$this->_maalid."
			AND
				status='AAN'
			LIMIT 1;";
		$rGasten=$this->_db->query($sGasten);
		if($rGasten!==false AND $this->_db->numRows($rGasten)){
			$aGasten=$this->_db->next($rGasten);
			$gasten=$aGasten['aantal'];
		}

		# totaal berekenen en opslaan
		$totaal = $abo + $aan + $gasten;
		$this->_maaltijd['aantal'] = $totaal;
		$this->_db->query("UPDATE maaltijd SET aantal='".$totaal."' WHERE id='".$this->_maalid."';");

		return $totaal;
	}

	## isVol met variabele voor hoeveel erbij geteld moet worden, nodig voor gasten aanmelden
	function isVol($plus = 1) {
		return $this->getAantalAanmeldingen()+$plus-1 >= $this->_maaltijd['max'];
	}

	function isGesloten() { return $this->_maaltijd['gesloten'] == '1'; }

	function sluit() {
		# inschrijving gesloten?
		if ($this->isGesloten()){ return false; }

		# haal de aanmeldingen op en prop ze in de maaltijdgesloten tabel:
		# uid, naam, eetwens, maalid, door, gasten, gasten_opmerking, tijdstip, ip
		$aanmeldingen=$this->getAanmeldingen();

		foreach ($aanmeldingen as $aan) {
			if(isset($aan['door_uid'])){ $door=$aan['door_uid']; }else{ $door=''; }
			if(isset($aan['tijdstip'])){ $tijdstip=$aan['tijdstip']; }else{ $tijdstip=0; }
			if(isset($aan['ip'])){ $ip=$aan['ip']; }else{ $ip=''; }
			if(isset($aan['gasten'])){ $gasten=$aan['gasten']; }else{ $gasten=''; }
			if(isset($aan['gasten_opmerking'])){ $gasten_opmerking=$aan['gasten_opmerking']; }else{ $gasten_opmerking=''; }
			
			$aanQuery=
				"INSERT INTO
					maaltijdgesloten
				(
					uid, eetwens, maalid, door,
					gasten, gasten_opmerking, tijdstip, ip
				)VALUES(
					'".$aan['uid']."', '".$aan['eetwens']."', ".$this->_maalid.", 
					'".$door."',
					'".$gasten."', '".$gasten_opmerking."', ".$tijdstip.", '".$ip."'
				);";
			$this->_db->query($aanQuery);
		}

		# verwijder de losse aanmeldingen uit de maaltijdaanmeldingtabel
		$leegmaken="DELETE FROM maaltijdaanmelding WHERE maalid=".$this->_maalid.";";
		$this->_db->query($leegmaken);

		# sluit de maaltijd door het vlaggetje gesloten te zetten...
		$this->_maaltijd['gesloten'] = '1';
		$this->_db->query("UPDATE maaltijd SET gesloten = '1' WHERE id = {$this->_maalid}");

		return true;
	}

	function getAanmeldingen() {

		# Eerst opvragen van de losse aanmeldingen AAN
		# merk op dat ook de achternaam wordt opgehaald, nodig voor sorteren!
		$aan = array();
		$sAan="
			SELECT
				maaltijdaanmelding.uid AS uid,
				maaltijdaanmelding.status AS status,
				maaltijdaanmelding.door AS door_uid,
				maaltijdaanmelding.gasten AS gasten,
				maaltijdaanmelding.gasten_opmerking AS gasten_opmerking,
				maaltijdaanmelding.tijdstip AS tijdstip,
				maaltijdaanmelding.ip AS ip,
				lid.eetwens AS eetwens,
				lid.achternaam AS achternaam,
				lid.maalcieSaldo AS saldo
			FROM
				maaltijdaanmelding
			INNER JOIN
				lid ON (lid.uid=maaltijdaanmelding.uid)
			WHERE
				maaltijdaanmelding.maalid='".$this->_maalid."' AND
				maaltijdaanmelding.status='AAN';";
		$rAan=$this->_db->select($sAan);

		if($rAan!==false and $this->_db->numRows($rAan) > 0){
			while($aAan=$this->_db->next($rAan)){
				//hier array met uid als key maken, om zometeen alles te kunnen wegstrepen
				$aan[$aAan['uid']]=array(
					'uid' => $aAan['uid'],
					'naam' => (string)LidCache::getLid($aAan['uid']),
					'eetwens' => $aAan['eetwens'],
					'achternaam' => $aAan['achternaam'],
					'saldo' => $aAan['saldo'],
					'door_uid' => $aAan['door_uid'],
					'gasten' => $aAan['gasten'],
					'gasten_opmerking' => $aAan['gasten_opmerking'],
					'tijdstip' => $aAan['tijdstip'],
					'ip' => $aAan['ip']
					);

			}
		}

		# Dan opvragen van de abo's
		$abo = array();
		$rAbo = $this->_db->select("
			SELECT
				maaltijdabo.uid AS uid,
				lid.voornaam AS voornaam,
				lid.tussenvoegsel AS tussenvoegsel,
				lid.achternaam AS achternaam,
				lid.eetwens AS eetwens,
				lid.maalcieSaldo AS saldo
			FROM
			 	maaltijdabo
			INNER JOIN
				lid ON(maaltijdabo.uid=lid.uid)
			WHERE
				abosoort='".$this->_maaltijd['abosoort']."'");
		if (($rAbo !== false) and $this->_db->numRows($rAbo) > 0){
			while ($aAbo = $this->_db->next($rAbo)){
				//hier array met uid als key maken, om zometeen alles te kunnen wegstrepen
				$abo[$aAbo['uid']]=array(
					'uid' => $aAbo['uid'],
					'naam' => (string)LidCache::getLid($aAbo['uid']),
					'eetwens' => $aAbo['eetwens'],
					'achternaam' => $aAbo['achternaam'],
					'saldo' => $aAbo['saldo'],
					'gasten' => 0);
			}
		}

		# Dan opvragen van de losse aanmeldingen AF
		$af = array();
		$result = $this->_db->select("SELECT * FROM maaltijdaanmelding WHERE maalid='{$this->_maalid}' AND status = 'AF'");
		if (($result !== false) and $this->_db->numRows($result) > 0)
			while ($record = $this->_db->next($result)) $af[$record['uid']] = $record;

		# En die AF en AAN meldingen wegstrepen uit de abolijst.
		$abo = array_diff_key($abo, $af, $aan);
		# vervolgens de overgebleven abo's bij de AAN lijst zetten
		$aan = $aan + $abo;

		# nog ff sorteren, hier worden de keys anders, geen uids meer dus.
		usort($aan, 'sort_achternaam_uid');

		return $aan;
	}


	# geeft een array terug met de aanmeldingen van leden, (los en abo) door elkaar
	# naast de informatie uit de inschrijvingen tabel staat ook de naam en de opmerking
	# uit het profiel erbij

	# N.B. als de maaltijd gesloten is, dan kijken we in de tabel maaltijdgesloten, en nemen daar
	# alles uit wat bij deze maaltijd hoort. Als de maaltijd nog niet is gesloten, is het wat
	# gecompliceerder, en zullen we de gegevens van aan/afmeldingen en abo's moeten combineren.

	# De functie die een maaltijdinschrijving sluit maakt ook gebruik van deze functie om de
	# aanmeldingen over te zetten naar de maaltijdgesloten-tabel.
	function getAanmeldingen_Oud() {
		# inschrijving gesloten?
		if($this->isGesloten()){
			$aan = array();
			$aanQuery="
				SELECT
					maaltijdgesloten.uid AS uid,
					maaltijdgesloten.eetwens AS eetwens,
					lid.maalcieSaldo AS saldo,
					gasten,
					gasten_opmerking,
					tijdstip
				FROM maaltijdgesloten
				INNER JOIN lid ON(maaltijdgesloten.uid=lid.uid)
				WHERE maalid='".$this->_maalid."'
				ORDER BY lid.achternaam;";
			$result = $this->_db->query($aanQuery);
			if(($result !== false) AND $this->_db->numRows($result) > 0){
				while($record = $this->_db->next($result)){
					$aan[$record['uid']]=$record;
					$aan[$record['uid']]['naam']=(string)LidCache::getLid($record['uid']);
				}
			}
		}else{
			# als inschrijving nog niet gesloten is de normale getAanmeldingen() gebruiken.
			$aan = $this->getAanmeldingen();
		}

		# Verwerken van gasten tot aparte regels in de lijst
		$aanOud = $aan;
		$aan = array();
		foreach ($aanOud as $id => $item) {
			$aan[$id] = $item;
			if ($item['gasten'] > 0) {
				for ($i = 0; $i < $item['gasten']; $i++) {
					$aan[$id.'_gast'.$i]['naam'] = 'Gast van '.$item['naam'];
					$aan[$id.'_gast'.$i]['uid'] = $item['uid'];
				}
			}
		}

		return $aan;
	}
	
	/* implement interface Agendeerbaar
	 */
	public function getBeginMoment() {
		return $this->_maaltijd['datum'];
	}
	public function getEindMoment() {
		return strtotime('+1 hour', $this->_maaltijd['datum']);
	}
	public function getTitel() {
		return $this->getTekst();
	}
	public function getBeschrijving() {
		return 'Maaltijd met '.$this->getAantalAanmeldingen().' eters.';
	}
	public function isHeledag(){ return false; }
	
	/*
	 * Haal de $aantal meest recente maaltijden op voor een gegeven lid.
	 */
	public static function getRecenteMaaltijden($uid, $aantal=10){
		$db=Mysql::instance();
		$maalQuery="
			SELECT
				maaltijd.datum AS datum,
				maaltijd.tekst AS tekst
			FROM maaltijdgesloten
			INNER JOIN maaltijd ON(maaltijdgesloten.maalid=maaltijd.id)
			WHERE maaltijdgesloten.uid='".$uid."'
			ORDER BY datum DESC
			LIMIT ".$aantal.";";
		return $db->query2array($maalQuery);
	}
}

?>
