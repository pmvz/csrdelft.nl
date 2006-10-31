<?php

# instellingen & rommeltjes
require_once('/srv/www/www.csrdelft.nl/lib/include.config.php');
if(!$lid->hasPermission('P_MAIL_POST')){ header('location: '.CSR_ROOT.''); exit; }

## zijkolom in elkaar jetzen
	$zijkolom=new kolom();

require_once('class.csrmail.php');
$csrmail = new Csrmail($lid, $db);
require_once('class.csrmailcontent.php');
$body = new CsrmailContent($csrmail);
if($_SERVER['REQUEST_METHOD']=='POST'){
	if($csrmail->valideerBerichtInvoer($sError)===true){
		$iBerichtID=(int)$_GET['ID'];
		if($iBerichtID==0){
			//nieuw bericht invoeren
			if($csrmail->addBericht($_POST['titel'], $_POST['categorie'], $_POST['bericht'] )){
				$body->addUserMessage('<h3>Dank u</h3>
					Uw bericht is opgenomen in ons databeest, en het zal in de komende pubcie-post verschijnen.');
			}else{
				$body->addUserMessage('<h1>Fout</h1>Er ging iets mis met het invoeren van uw bericht. 
					Probeer opnieuw, of stuur uw bericht in een mail naar pubcie@csrdelft.nl');
			}
		}else{
			//bericht bewerken.
			if($csrmail->bewerkBericht($iBerichtID, $_POST['titel'], $_POST['categorie'], $_POST['bericht'])){
				$body->addUserMessage('<h3>Dank u</h3>
					Uw bericht is opgenomen in ons databeest, en het zal in de komende pubcie-post verschijnen.');
			}else{
				$body->addUserMessage('<h1>Fout</h1>Er ging iets mis met het invoeren van uw bericht. 
					Probeer opnieuw, of stuur uw bericht in een mail naar pubcie@csrdelft.nl');
			}
		}
	}else{
		if(isset($_GET['ID']) AND $_GET['ID']==0){
			$body->addNewForm($sError);
		}else{
			$body->addEditForm((int)$_GET['ID'], $sError);
		}
	}
}else{
	if(isset($_GET['ID'])){
		$iBerichtID=(int)$_GET['ID'];
		if(isset($_GET['verwijder'])){
			if($csrmail->verwijderBerichtVoorGebruiker($iBerichtID)){
				$body->addUserMessage('<h3>Uw bericht is verwijderd.</h3>');
			}else{
				$body->addUserMessage('<h3>Er ging iets mis!</h3>
					Uw bericht is niet verwijderd. Probeer het a.u.b. nog eens.');
			}
		}
		if(isset($_GET['bewerken'])){
			//bericht bewerken.
			$body->addEditForm($iBerichtID);
		}
	}
}
$pagina=new csrdelft($body,  $lid, $db);
$pagina->setZijkolom($zijkolom);

$pagina->view();

?>
