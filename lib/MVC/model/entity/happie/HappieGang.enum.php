<?php

/**
 * Gang.enum.php
 * 
 * @author P.W.G. Brussee <brussee@live.nl>
 * 
 * Gang van gerecht.
 * Drank apart.
 * 
 */
abstract class HappieGang implements PersistentEnum {

	const Drank = 'drank';
	const Voorgerecht = 'voor';
	const Hoofdgerecht = 'hoofd';
	const Bijgerecht = 'bij';
	const Nagerecht = 'na';

	public static function getTypeOptions() {
		return array(self::Drank, self::Voorgerecht, self::Hoofdgerecht, self::Bijgerecht, self::Nagerecht);
	}

	public static function getSelectOptions() {
		$options = array();
		foreach (HappieGang::getTypeOptions() as $option) {
			$options[$option] = self::format($option);
		}
		return $options;
	}

	public static function format($gang) {
		return ucfirst($gang) . ($gang == self::Drank ? '' : 'gerecht');
	}

}
