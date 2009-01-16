<?php
/*
 * pasfotos.php	| 	Jan Pieter Waagmeester (jieter@jpwaag.com)
 *
 * Zet een stel uid's om in pasfoto's
 */
require_once('include.config.php');

if($lid->hasPermission('P_LEDEN_READ') AND isset($_GET['string'])){
	$string=trim(urldecode($_GET['string']));
	$uids=explode(',', $string);
	$link=isset($_GET['link']);

	echo '<div class="pasfotomatrix">';
	foreach($uids as $uid){
		if($lid->isValidUid($uid)){
			if($link){
				echo '<a href="/communicatie/profiel/'.$uid.'" title="'.$lid->getNaamLink($uid, 'full', false).'">';
			}
			echo $lid->getPasfoto($uid, true);
			if($link){ echo '</a>'; }

		}
	}
	echo '</div>';
}else{
	return 'b0rkb0rkb0rk';
}
?>

