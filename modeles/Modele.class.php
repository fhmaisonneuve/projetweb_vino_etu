<?php
/**
 * Class Modele
 * Template de classe modèle.
 */
class Modele {
	
    protected $_db;
	function __construct ()
	{
		$this->_db = MonSQL::getInstance();
	}
	
	function __destruct ()
	{
		
	}
}




?>