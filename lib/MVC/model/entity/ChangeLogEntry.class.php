<?php

/**
 * ChangeLogEntry.class.php
 * 
 * @author P.W.G. Brussee <brussee@live.nl>
 * 
 */
class ChangeLogEntry extends PersistentEntity {

	/**
	 * Primary key
	 * @var int
	 */
	public $id;
	/**
	 * The moment it changed
	 * @var DateTime
	 */
	public $moment;
	/**
	 * Time elapsed since last change
	 * @var int
	 */
	public $elapsed;
	/**
	 * The thing that changed
	 * @var string
	 */
	public $subject;
	/**
	 * The property that changed
	 * @var string
	 */
	public $property;
	/**
	 * The value before
	 * @var string
	 */
	public $old_value;
	/**
	 * The value after
	 * @var string
	 */
	public $new_value;
	/**
	 * Who did it
	 * @var UID
	 */
	public $uid;
	/**
	 * Database table columns
	 * @var array
	 */
	protected static $persistent_attributes = array(
		'id'		 => array(T::Integer, false, 'auto_increment'),
		'moment'	 => array(T::DateTime),
		'elapsed'	 => array(T::Integer, true),
		'subject'	 => array(T::String),
		'property'	 => array(T::String),
		'old_value'	 => array(T::String, true),
		'new_value'	 => array(T::String, true),
		'uid'		 => array(T::UID)
	);
	/**
	 * Database primary key
	 * @var array
	 */
	protected static $primary_key = array('id');
	/**
	 * Database table name
	 * @var string
	 */
	protected static $table_name = 'changelog';

}
